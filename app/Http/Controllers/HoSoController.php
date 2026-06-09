<?php

namespace App\Http\Controllers;

use App\Models\HoSo;
use App\Models\LoaiChinhSach;
use App\Models\MinhChungFile;
use App\Models\TrangThai;
use App\Services\CloudinaryService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * HoSoController
 *
 * Quản lý hồ sơ (BM.01: MGHP, BM.02: TCXH)
 * Day 2: Số hóa biểu mẫu JSON + Upload minh chứng Cloudinary
 */
class HoSoController extends Controller
{
    private CloudinaryService $cloudinaryService;
    private GeminiService $geminiService;

    public function __construct(CloudinaryService $cloudinaryService, GeminiService $geminiService)
    {
        $this->cloudinaryService = $cloudinaryService;
        $this->geminiService = $geminiService;
    }

    /**
     * POST /api/ho-so/store
     *
     * Nộp hồ sơ mới (BM.01 hoặc BM.02) kèm minh chứng
     *
     * Request (multipart/form-data):
     * {
     *   "ma_loai_cs": 1,  // 1=MGHP(BM.01), 2=TCXH(BM.02)
     *   "ma_dot": 1,
     *   "form_data": "{\"ho_ten\":\"Nguyễn A\",\"ma_so_sv\":\"20210001\",\"dien_thoai\":\"0901234567\",\"trang_thai_ho_gia_dinh\":\"hộ_nghèo\",\"ghi_chu\":\"...\"}" // JSON string
     *   "minh_chungs": [file1, file2, ...] // Array file ảnh
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "ma_ho_so": 1,
     *     "ma_loai_cs": 1,
     *     "ma_trang_thai": 2,
     *     "du_lieu_form": {...},
     *     "minh_chungs": [...]
     *   },
     *   "message": "Nộp hồ sơ thành công. Vui lòng chờ xét duyệt."
     * }
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // 1. VALIDATION
            $validator = Validator::make($request->all(), [
                'ma_loai_cs' => 'required|integer|in:1,2',
                'ma_dot' => 'required|integer|exists:dot_thu_ho_so,MaDot',
                'form_data' => 'required|string',
                'minh_chungs' => 'array|min:1',
                'minh_chungs.*' => 'file|image|mimes:jpeg,png,jpg|max:5120',  // 5MB per file
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu đầu vào không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 2. PARSE & VALIDATE FORM DATA (JSON)
            $formDataJson = $request->input('form_data');
            $formData = json_decode($formDataJson, true);

            if ($formData === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form data không phải JSON hợp lệ'
                ], 422);
            }

            // 3. VALIDATE FORM DATA SCHEMA
            $validationResult = $this->validateFormDataSchema($formData, (int) $request->input('ma_loai_cs'));
            if (!$validationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu biểu mẫu không hợp lệ: ' . $validationResult['error']
                ], 422);
            }

            // 4. BẮT ĐẦU TRANSACTION
            DB::beginTransaction();

            try {
                // 5. TẠO HỒ SƠ MỚI
                $hoSo = HoSo::create([
                    'MaNguoiDung' => $user->MaNguoiDung,
                    'MaDot' => (int) $request->input('ma_dot'),
                    'MaLoaiCS' => (int) $request->input('ma_loai_cs'),
                    'MaTrangThai' => 2,  // Chờ thẩm định
                    'du_lieu_form' => $formData,  // Lưu dạng JSON
                    'GhiChu' => null,
                ]);

                Log::info('HoSoController::store - HoSo created', [
                    'ma_ho_so' => $hoSo->MaHoSo,
                    'ma_nguoi_dung' => $user->MaNguoiDung
                ]);

                // 6. UPLOAD MINH CHỨNG CLOUDINARY
                $minhChungFolder = 'quanlics/minh_chung/' . date('Y/m/d') . '/' . $hoSo->MaHoSo;
                $uploadedMinhChungs = [];
                $failedUploads = [];

                if ($request->hasFile('minh_chungs')) {
                    foreach ($request->file('minh_chungs') as $index => $file) {
                        $publicId = 'mc_' . $hoSo->MaHoSo . '_' . $index . '_' . time();
                        $uploadResult = $this->cloudinaryService->uploadMinhChung(
                            $file,
                            $minhChungFolder,
                            $publicId
                        );

                        if (!$uploadResult['success']) {
                            $failedUploads[] = [
                                'file' => $file->getClientOriginalName(),
                                'error' => $uploadResult['error']
                            ];
                            continue;
                        }

                        // Lưu vào database
                        $minhChung = MinhChungFile::create([
                            'MaHoSo' => $hoSo->MaHoSo,
                            'TenFile' => $file->getClientOriginalName(),
                            'DuongDanFile' => $uploadResult['url'],
                            'PublicIdCloudinary' => $uploadResult['public_id'],
                            'KichThuoc' => $uploadResult['size'] ?? 0,
                            'KieuFile' => $file->getMimeType(),
                            'ThoiGianUpload' => now(),
                        ]);

                        $uploadedMinhChungs[] = [
                            'ma_minh_chung' => $minhChung->MaMinhChung,
                            'ten_file' => $minhChung->TenFile,
                            'url' => $minhChung->DuongDanFile,
                            'kich_thuoc' => $minhChung->KichThuoc,
                        ];

                        Log::info('HoSoController::store - MinhChung uploaded', [
                            'ma_minh_chung' => $minhChung->MaMinhChung,
                            'url' => $uploadResult['url']
                        ]);

                        // 7. TRIGGER OCR HOOK (DAY 3 PREPARATION)
                        // Gửi URL ảnh sang GeminiService@ocrDocument để chuẩn bị
                        // Lưu vào bảng PHAN_TICH_AI_HO_SO (pending, chờ Day 3 chạy hàng loạt)
                        $this->scheduleMinhChungOCR($hoSo->MaHoSo, $minhChung->MaMinhChung, $uploadResult['url']);
                    }
                }

                // Nếu tất cả upload thất bại
                if (count($failedUploads) > 0 && count($uploadedMinhChungs) === 0) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Không thể upload bất kỳ minh chứng nào',
                        'failed_uploads' => $failedUploads
                    ], 500);
                }

                // 8. COMMIT TRANSACTION
                DB::commit();

                // Cảnh báo nếu có upload thất bại nhưng vẫn lưu được hồ sơ
                $warning = null;
                if (count($failedUploads) > 0) {
                    $warning = 'Một số minh chứng không tải lên được. Bạn có thể bổ sung sau.';
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'ma_ho_so' => $hoSo->MaHoSo,
                        'ma_loai_cs' => $hoSo->MaLoaiCS,
                        'ma_trang_thai' => $hoSo->MaTrangThai,
                        'du_lieu_form' => $hoSo->du_lieu_form,
                        'minh_chungs' => $uploadedMinhChungs,
                    ],
                    'message' => 'Nộp hồ sơ thành công. Vui lòng chờ xét duyệt.',
                    'warning' => $warning,
                    'failed_uploads' => $failedUploads,
                ], 201);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('HoSoController::store - Transaction Error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi lưu hồ sơ: ' . $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('HoSoController::store - General Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu'
            ], 500);
        }
    }

    /**
     * GET /api/ho-so/{maSV}
     * Lấy danh sách hồ sơ của sinh viên (phân trang)
     */
    public function index()
    {
        try {
            $user = Auth::user();

            $hoSos = HoSo::where('MaNguoiDung', $user->MaNguoiDung)
                ->with(['loaiChinhSach', 'trangThai', 'minhChungFiles'])
                ->orderBy('MaHoSo', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $hoSos
            ], 200);
        } catch (Exception $e) {
            Log::error('HoSoController::index - Error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy danh sách hồ sơ'
            ], 500);
        }
    }

    /**
     * GET /api/ho-so/{maHoSo}
     * Lấy chi tiết hồ sơ
     */
    public function show($maHoSo)
    {
        try {
            $user = Auth::user();

            $hoSo = HoSo::where('MaHoSo', $maHoSo)
                ->where('MaNguoiDung', $user->MaNguoiDung)
                ->with(['loaiChinhSach', 'trangThai', 'minhChungFiles', 'phanTichAI'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $hoSo
            ], 200);
        } catch (Exception $e) {
            Log::error('HoSoController::show - Error', [
                'ma_ho_so' => $maHoSo,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hồ sơ không tìm thấy'
            ], 404);
        }
    }

    /**
     * POST /api/ho-so/{maHoSo}/minh-chung-them
     * Thêm minh chứng bổ sung cho hồ sơ
     */
    public function addMinhChung(Request $request, $maHoSo)
    {
        try {
            $user = Auth::user();

            // Kiểm tra quyền sở hữu hồ sơ
            $hoSo = HoSo::where('MaHoSo', $maHoSo)
                ->where('MaNguoiDung', $user->MaNguoiDung)
                ->firstOrFail();

            // Validate files
            $validator = Validator::make($request->all(), [
                'minh_chungs' => 'required|array|min:1',
                'minh_chungs.*' => 'file|image|mimes:jpeg,png,jpg|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu đầu vào không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $minhChungFolder = 'quanlics/minh_chung/' . date('Y/m/d') . '/' . $maHoSo;
            $uploadedMinhChungs = [];

            DB::beginTransaction();

            foreach ($request->file('minh_chungs') as $index => $file) {
                $publicId = 'mc_' . $maHoSo . '_' . uniqid();
                $uploadResult = $this->cloudinaryService->uploadMinhChung(
                    $file,
                    $minhChungFolder,
                    $publicId
                );

                if (!$uploadResult['success']) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Lỗi upload file: ' . $uploadResult['error']
                    ], 500);
                }

                $minhChung = MinhChungFile::create([
                    'MaHoSo' => $maHoSo,
                    'TenFile' => $file->getClientOriginalName(),
                    'DuongDanFile' => $uploadResult['url'],
                    'PublicIdCloudinary' => $uploadResult['public_id'],
                    'KichThuoc' => $uploadResult['size'] ?? 0,
                    'KieuFile' => $file->getMimeType(),
                    'ThoiGianUpload' => now(),
                ]);

                $uploadedMinhChungs[] = [
                    'ma_minh_chung' => $minhChung->MaMinhChung,
                    'ten_file' => $minhChung->TenFile,
                    'url' => $minhChung->DuongDanFile,
                ];

                // Trigger OCR
                $this->scheduleMinhChungOCR($maHoSo, $minhChung->MaMinhChung, $uploadResult['url']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $uploadedMinhChungs,
                'message' => 'Thêm minh chứng thành công'
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('HoSoController::addMinhChung - Error', [
                'ma_ho_so' => $maHoSo,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi thêm minh chứng'
            ], 500);
        }
    }

    /**
     * DELETE /api/ho-so/{maHoSo}/minh-chung/{maMinhChung}
     * Xóa minh chứng
     */
    public function deleteMinhChung($maHoSo, $maMinhChung)
    {
        try {
            $user = Auth::user();

            // Kiểm tra quyền
            $hoSo = HoSo::where('MaHoSo', $maHoSo)
                ->where('MaNguoiDung', $user->MaNguoiDung)
                ->firstOrFail();

            $minhChung = MinhChungFile::where('MaMinhChung', $maMinhChung)
                ->where('MaHoSo', $maHoSo)
                ->firstOrFail();

            // Xóa từ Cloudinary
            $this->cloudinaryService->deleteFile($minhChung->PublicIdCloudinary);

            // Xóa record
            $minhChung->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa minh chứng thành công'
            ], 200);
        } catch (Exception $e) {
            Log::error('HoSoController::deleteMinhChung - Error', [
                'ma_ho_so' => $maHoSo,
                'ma_minh_chung' => $maMinhChung,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xóa minh chứng'
            ], 500);
        }
    }

    /** PRIVATE HELPER METHODS */

    /**
     * Validate form data schema theo loại biểu mẫu
     */
    private function validateFormDataSchema(array $formData, int $maLoaiCS): array
    {
        // BM.01: Miễn giảm học phí
        if ($maLoaiCS === 1) {
            $required = ['ho_ten', 'ma_so_sv', 'dien_thoai', 'trang_thai_ho_gia_dinh'];
            foreach ($required as $field) {
                if (!isset($formData[$field]) || empty($formData[$field])) {
                    return [
                        'valid' => false,
                        'error' => "Trường bắt buộc '$field' không được bỏ trống"
                    ];
                }
            }

            // Validate trạng thái
            $validStatuses = ['hộ_nghèo', 'cận_nghèo', 'hộ_chính_sách'];
            if (!in_array($formData['trang_thai_ho_gia_dinh'], $validStatuses)) {
                return [
                    'valid' => false,
                    'error' => 'Trạng thái hộ gia đình không hợp lệ'
                ];
            }
        }
        // BM.02: Trợ cấp xã hội
        else if ($maLoaiCS === 2) {
            $required = ['ho_ten', 'ma_so_sv', 'dien_thoai', 'dien_thoai_phu', 'so_tai_khoan_ngan_hang'];
            foreach ($required as $field) {
                if (!isset($formData[$field]) || empty($formData[$field])) {
                    return [
                        'valid' => false,
                        'error' => "Trường bắt buộc '$field' không được bỏ trống"
                    ];
                }
            }

            // Validate số điện thoại
            if (!preg_match('/^0[0-9]{9}$/', $formData['dien_thoai'])) {
                return [
                    'valid' => false,
                    'error' => 'Số điện thoại không hợp lệ'
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Schedule OCR processing (DAY 3 PREPARATION)
     * Lưu vào bảng PHAN_TICH_AI_HO_SO với trạng thái pending
     */
    private function scheduleMinhChungOCR(int $maHoSo, int $maMinhChung, string $urlAnh): void
    {
        try {
            // Đặt marker để Day 3 biết cần xử lý hình ảnh này
            // Tạm thời chỉ log, Day 3 sẽ implement hàng loạt OCR
            Log::info('HoSoController::scheduleMinhChungOCR - Scheduled', [
                'ma_ho_so' => $maHoSo,
                'ma_minh_chung' => $maMinhChung,
                'url_anh' => $urlAnh
            ]);
        } catch (Exception $e) {
            Log::error('HoSoController::scheduleMinhChungOCR - Error', [
                'message' => $e->getMessage()
            ]);
        }
    }
}
