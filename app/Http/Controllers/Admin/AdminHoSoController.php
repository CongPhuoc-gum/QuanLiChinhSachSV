<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HoSo;
use App\Models\NhatKyXetDuyet;
use App\Models\PhanTichAIHoSo;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * AdminHoSoController - DAY 4
 *
 * Quản lý hồ sơ cho cán bộ CTSV/Admin
 * - Xem danh sách hồ sơ với filter
 * - Xem chi tiết hồ sơ + kết quả AI
 * - Thẩm định & duyệt hồ sơ
 */
class AdminHoSoController extends Controller
{
    /**
     * GET /api/admin/ho-so
     *
     * Lấy danh sách toàn bộ hồ sơ với hỗ trợ filter
     *
     * Query Parameters:
     * - status: Trạng thái (2=Chờ thẩm định, 3=Đang bổ sung, 4=Chờ TP duyệt, etc.)
     * - loai_cs: Loại chính sách (1=MGHP, 2=TCXH)
     * - search: Tìm kiếm theo ma_so_sv hoặc ho_ten
     * - sort: Sắp xếp (created_asc, created_desc, name_asc, name_desc)
     * - page: Trang (default: 1)
     * - per_page: Số bản ghi/trang (default: 20)
     *
     * Response:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "ma_ho_so": 1,
     *       "ma_so_sv": "20210001",
     *       "ho_ten": "Nguyễn Văn A",
     *       "trang_thai": 2,
     *       "trang_thai_name": "Chờ thẩm định",
     *       "loai_cs": 1,
     *       "loai_cs_name": "MGHP",
     *       "ngay_nop": "2026-06-01T10:30:00",
     *       "ai_status": "APPROVED|WARNING|NEED_REVIEW",
     *       "ai_match_rate": 0.98
     *     }
     *   ],
     *   "total": 150,
     *   "per_page": 20,
     *   "current_page": 1,
     *   "message": "Danh sách hồ sơ"
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = HoSo::with([
                'nguoiDung.sinhVien',
                'loaiChinhSach',
                'trangThai',
                'phanTichAI'
            ]);

            // Filter by status
            if ($request->has('status') && !empty($request->input('status'))) {
                $status = (int) $request->input('status');
                $query->where('MaTrangThai', $status);
            }

            // Filter by loai_cs (policy type)
            if ($request->has('loai_cs') && !empty($request->input('loai_cs'))) {
                $loaiCs = (int) $request->input('loai_cs');
                $query->where('MaLoaiCS', $loaiCs);
            }

            // Search by ma_so_sv or ho_ten
            if ($request->has('search') && !empty($request->input('search'))) {
                $searchTerm = '%' . $request->input('search') . '%';
                $query->whereHas('nguoiDung.sinhVien', function ($q) use ($searchTerm) {
                    $q
                        ->where('MaSoSV', 'like', $searchTerm)
                        ->orWhere('HoDem', 'like', $searchTerm)
                        ->orWhere('Ten', 'like', $searchTerm);
                });
            }

            // Sorting
            $sort = $request->input('sort', 'created_desc');
            switch ($sort) {
                case 'created_asc':
                    $query->orderBy('NgayNop', 'asc');
                    break;
                case 'created_desc':
                    $query->orderBy('NgayNop', 'desc');
                    break;
                case 'name_asc':
                    $query->orderBy('MaHoSo', 'asc');
                    break;
                case 'name_desc':
                default:
                    $query->orderBy('MaHoSo', 'desc');
                    break;
            }

            // Paginate
            $perPage = min((int) $request->input('per_page', 20), 100);
            $hoSos = $query->paginate($perPage);

            // Format response
            $data = $hoSos->map(function ($hoSo) {
                return [
                    'ma_ho_so' => $hoSo->MaHoSo,
                    'ma_so_sv' => $hoSo->nguoiDung?->sinhVien?->MaSoSV ?? 'N/A',
                    'ho_ten' => ($hoSo->nguoiDung?->sinhVien?->HoDem ?? '') . ' ' . ($hoSo->nguoiDung?->sinhVien?->Ten ?? ''),
                    'trang_thai' => $hoSo->MaTrangThai,
                    'trang_thai_name' => $hoSo->trangThai?->TenTrangThai ?? 'Unknown',
                    'loai_cs' => $hoSo->MaLoaiCS,
                    'loai_cs_name' => $hoSo->loaiChinhSach?->TenLoaiCS ?? 'Unknown',
                    'ngay_nop' => $hoSo->NgayNop,
                    'ai_status' => $hoSo->phanTichAI?->TrangThaiXuLy ?? 'PENDING',
                    'ai_match_rate' => $hoSo->phanTichAI?->TyLeKhop ?? null,
                ];
            });

            Log::info('AdminHoSoController::index - Listed hồ sơ', [
                'total' => $hoSos->total(),
                'page' => $hoSos->currentPage()
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $hoSos->total(),
                    'per_page' => $hoSos->perPage(),
                    'current_page' => $hoSos->currentPage(),
                    'last_page' => $hoSos->lastPage(),
                    'from' => $hoSos->firstItem(),
                    'to' => $hoSos->lastItem(),
                ],
                'message' => 'Danh sách hồ sơ'
            ], 200);
        } catch (Exception $e) {
            Log::error('AdminHoSoController::index - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy danh sách hồ sơ'
            ], 500);
        }
    }

    /**
     * GET /api/admin/ho-so/{id}
     *
     * Xem chi tiết một hồ sơ, kèm theo AI analysis + minh chứng
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "ho_so": {...},
     *     "ai_analysis": {
     *       "ma_phan_tich": 1,
     *       "overall_match_rate": 0.98,
     *       "status": "APPROVED",
     *       "field_comparisons": [...],
     *       "discrepancies": [],
     *       "recommendation": "✅ Tự động duyệt..."
     *     },
     *     "minh_chungs": [...]
     *   },
     *   "message": "Chi tiết hồ sơ"
     * }
     */
    public function show($maHoSo)
    {
        try {
            $hoSo = HoSo::with([
                'nguoiDung.sinhVien',
                'loaiChinhSach',
                'trangThai',
                'minhChungFiles',
                'phanTichAI'
            ])->findOrFail($maHoSo);

            // Get AI analysis
            $aiAnalysis = $hoSo->phanTichAI;

            // Format minh chung
            $minhChungs = $hoSo->minhChungFiles->map(function ($mc) {
                return [
                    'ma_minh_chung' => $mc->MaMinhChung,
                    'ten_file' => $mc->TenFile,
                    'url' => $mc->DuongDanFile,
                    'kieu_file' => $mc->KieuFile,
                    'kich_thuoc' => $mc->KichThuoc,
                    'thoi_gian_upload' => $mc->ThoiGianUpload,
                ];
            });

            // Format AI analysis
            $aiData = null;
            if ($aiAnalysis) {
                $aiData = [
                    'ma_phan_tich' => $aiAnalysis->MaPhanTich,
                    'loai_tai_lieu' => $aiAnalysis->LoaiTaiLieuOCR,
                    'overall_match_rate' => $aiAnalysis->TyLeKhop,
                    'ocr_confidence' => $aiAnalysis->DoTinCayOCR,
                    'status' => $aiAnalysis->TrangThaiXuLy,
                    'field_comparisons' => $aiAnalysis->KetQuaDoiChieu['field_comparisons'] ?? [],
                    'discrepancies' => $aiAnalysis->CanBaoLech ?? [],
                    'recommendation' => $aiAnalysis->KetQuaDoiChieu['recommendation'] ?? '',
                    'thoi_gian_phan_tich' => $aiAnalysis->ThoiGianPhanTich,
                ];
            }

            Log::info('AdminHoSoController::show - Viewed hồ sơ', [
                'ma_ho_so' => $maHoSo
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'ho_so' => [
                        'ma_ho_so' => $hoSo->MaHoSo,
                        'ma_so_sv' => $hoSo->nguoiDung?->sinhVien?->MaSoSV ?? 'N/A',
                        'ho_ten' => ($hoSo->nguoiDung?->sinhVien?->HoDem ?? '') . ' ' . ($hoSo->nguoiDung?->sinhVien?->Ten ?? ''),
                        'email' => $hoSo->nguoiDung?->Email ?? 'N/A',
                        'du_lieu_form' => $hoSo->du_lieu_form,
                        'loai_cs' => $hoSo->MaLoaiCS,
                        'loai_cs_name' => $hoSo->loaiChinhSach?->TenLoaiCS ?? 'Unknown',
                        'trang_thai' => $hoSo->MaTrangThai,
                        'trang_thai_name' => $hoSo->trangThai?->TenTrangThai ?? 'Unknown',
                        'ghi_chu' => $hoSo->GhiChu,
                        'ly_do_tu_choi' => $hoSo->LyDoTuChoi,
                        'ngay_nop' => $hoSo->NgayNop,
                        'ngay_cap_nhat' => $hoSo->NgayCapNhat,
                    ],
                    'ai_analysis' => $aiData,
                    'minh_chungs' => $minhChungs,
                ],
                'message' => 'Chi tiết hồ sơ'
            ], 200);
        } catch (Exception $e) {
            Log::error('AdminHoSoController::show - Error: ' . $e->getMessage(), [
                'ma_ho_so' => $maHoSo
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Hồ sơ không tìm thấy'
            ], 404);
        }
    }

    /**
     * PUT /api/admin/ho-so/{id}/duyet
     *
     * Cán bộ thẩm định và cập nhật trạng thái hồ sơ
     *
     * Request:
     * {
     *   "trang_thai": 6,  // 6=Đã duyệt, 8=Từ chối, 3=Đang bổ sung
     *   "ghi_chu": "Hồ sơ hợp lệ, tất cả thông tin đầy đủ",
     *   "ly_do_tu_choi": "Giấy tờ không rõ ràng"  // Only if trang_thai = 8
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "ma_ho_so": 1,
     *     "trang_thai": 6,
     *     "trang_thai_name": "Đã duyệt",
     *     "ngay_cap_nhat": "2026-06-09T15:30:00"
     *   },
     *   "message": "Cập nhật trạng thái hồ sơ thành công"
     * }
     */
    public function duyet(Request $request, $maHoSo)
    {
        try {
            $user = Auth::user();

            // Validate request
            $validator = Validator::make($request->all(), [
                'trang_thai' => 'required|integer|in:3,6,8',  // 3=Đang bổ sung, 6=Đã duyệt, 8=Từ chối
                'ghi_chu' => 'nullable|string|max:1000',
                'ly_do_tu_choi' => 'required_if:trang_thai,8|nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $hoSo = HoSo::findOrFail($maHoSo);

            // Check if hồ sơ is in a valid state for review
            $validStates = [2, 3, 4];  // Chờ thẩm định, Đang bổ sung, Chờ TP duyệt
            if (!in_array($hoSo->MaTrangThai, $validStates)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể thẩm định hồ sơ ở trạng thái hiện tại'
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Update hồ sơ
                $hoSo->update([
                    'MaTrangThai' => (int) $request->input('trang_thai'),
                    'GhiChu' => $request->input('ghi_chu'),
                    'LyDoTuChoi' => $request->input('ly_do_tu_choi'),
                    'NgayCapNhat' => now(),
                ]);

                // Create nhật ký xét duyệt
                NhatKyXetDuyet::create([
                    'MaHoSo' => $maHoSo,
                    'MaNguoiThucHien' => $user->MaNguoiDung,
                    'MaTrangThaiTruoc' => $hoSo->getOriginal('MaTrangThai'),
                    'MaTrangThaiSau' => (int) $request->input('trang_thai'),
                    'GhiChu' => $request->input('ghi_chu'),
                    'NgayThucHien' => now(),
                ]);

                DB::commit();

                // Send notification email (X2 - Phase 3)
                $notificationService = new NotificationService();
                $notificationService->guiEmailDoiTrangThai($hoSo, (int) $request->input('trang_thai'));

                Log::info('AdminHoSoController::duyet - Hồ sơ updated', [
                    'ma_ho_so' => $maHoSo,
                    'new_status' => $request->input('trang_thai'),
                    'can_bo' => $user->MaNguoiDung
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'ma_ho_so' => $hoSo->MaHoSo,
                        'trang_thai' => $hoSo->MaTrangThai,
                        'trang_thai_name' => $hoSo->trangThai?->TenTrangThai ?? 'Unknown',
                        'ngay_cap_nhat' => $hoSo->NgayCapNhat,
                    ],
                    'message' => 'Cập nhật trạng thái hồ sơ thành công'
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('AdminHoSoController::duyet - Transaction Error: ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi cập nhật trạng thái: ' . $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('AdminHoSoController::duyet - Error: ' . $e->getMessage(), [
                'ma_ho_so' => $maHoSo
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu'
            ], 500);
        }
    }
}
