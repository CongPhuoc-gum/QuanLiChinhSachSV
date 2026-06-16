<?php

namespace App\Http\Controllers;

use App\Models\DangKyHocPhan;
use App\Models\DotThuHoSo;
use App\Models\LichSuTKB;
use App\Models\NamHoc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Exception;

/**
 * DangKyHocPhanController - PHASE X1
 *
 * Sinh viên từ Kỳ 2 trở đi tự đăng ký học phần
 * - Xem danh sách lớp học phần đang mở
 * - Đăng ký học phần (auto-detect học lại)
 * - Xem danh sách môn đã đăng ký
 * - Hủy đăng ký học phần
 */
class DangKyHocPhanController extends Controller
{
    /**
     * Return the user identifier column used in DANG_KY_HOC_PHAN table.
     */
    private function userColumn(): string
    {
        return Schema::hasColumn('DANG_KY_HOC_PHAN', 'MaNguoiDung') ? 'MaNguoiDung' : 'MaSinhVien';
    }

    /**
     * Return the current user's identifier value that matches userColumn().
     */
    private function userIdentifier($user)
    {
        if ($this->userColumn() === 'MaNguoiDung') {
            return $user->MaNguoiDung;
        }

        return $user->sinhVien?->MaSoSV ?? $user->MaNguoiDung;
    }
    /**
     * GET /api/dang-ky-hoc-phan/lop-mo
     *
     * Xem danh sách lớp học phần đang mở
     * Hệ thống tự động tính IsHocLai dựa trên lịch sử
     *
     * Query Parameters:
     * - hoc_ky (string, required): "HK1", "HK2", etc.
     * - nam_hoc (string, required): "2025-2026", etc.
     * - ma_mon_hoc (string, optional): Tìm kiếm theo mã môn
     * - ten_mon_hoc (string, optional): Tìm kiếm theo tên môn
     * - per_page: Số bản ghi/trang (default: 20)
     * - page: Trang (default: 1)
     *
     * Response:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "ma_lop_hoc_phan": 1,
     *       "ma_mon_hoc": "CTDL001",
     *       "ten_mon_hoc": "Cấu trúc dữ liệu",
     *       "so_tin_chi": 3,
     *       "giang_vien": "TS. Nguyễn Văn A",
     *       "hoc_ky": "HK1",
     *       "nam_hoc": "2025-2026",
     *       "thu_tiet": "Thứ 2, tiết 7-9",
     *       "phong": "E101",
     *       "si_so_dk": 30,
     *       "si_so_hien_tai": 25,
     *       "is_hoc_lai": false,
     *       "da_dang_ky": false
     *     }
     *   ],
     *   "pagination": {...},
     *   "message": "Danh sách lớp học phần đang mở"
     * }
     */
    public function lopMo(Request $request)
    {
        try {
            $user = Auth::user();
            $maSinhVien = $user->sinhVien?->MaSoSV;

            if (!$maSinhVien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Người dùng không phải là sinh viên'
                ], 403);
            }

            // Validate required params
            $validator = Validator::make($request->all(), [
                'hoc_ky' => 'required|string',
                'nam_hoc' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thiếu tham số bắt buộc',
                    'errors' => $validator->errors()
                ], 422);
            }

            $hocKy = $request->input('hoc_ky');
            $namHoc = $request->input('nam_hoc');
            $maMonHoc = $request->input('ma_mon_hoc');
            $tenMonHoc = $request->input('ten_mon_hoc');

            // Find năm học
            $namHocObj = NamHoc::where('TenNamHoc', $namHoc)->first();
            if (!$namHocObj) {
                return response()->json([
                    'success' => false,
                    'message' => 'Năm học không tồn tại'
                ], 404);
            }

            // Query lớp học phần theo năm học
            $query = LichSuTKB::where('MaNamHoc', $namHocObj->MaNamHoc)
                ->whereNull('NguonNhap')  // Lớp do cán bộ import (Nó chưa được SV chọn)
                ->orWhere('NguonNhap', 'can_bo_import')
                ->with('hocPhan');

            // Filter by mã môn
            if ($maMonHoc) {
                $query->where('MaHP', 'like', '%' . $maMonHoc . '%');
            }

            // Filter by tên môn
            if ($tenMonHoc) {
                $query->where('TenLHP', 'like', '%' . $tenMonHoc . '%');
            }

            // Paginate
            $perPage = min((int) $request->input('per_page', 20), 100);
            $lopHocPhans = $query->orderBy('MaTKB')->paginate($perPage);

            // Format response + calculate isHocLai
            $data = $lopHocPhans->map(function ($lop) use ($user) {
                // Check if student already registered this class
                $daDangKy = DangKyHocPhan::where('MaTKB', $lop->MaTKB)
                    ->where($this->userColumn(), $this->userIdentifier($user))
                    ->exists();

                // Check if student took this course in previous semester (isHocLai)
                $isHocLai = false;
                $previousSemester = $this->getPreviousSemester($lop);
                if ($previousSemester) {
                        $hocTruocDay = LichSuTKB::where('MaHP', $lop->MaHP)
                        ->where('MaNamHoc', $previousSemester['MaNamHoc'])
                        ->whereHas('dangKyHocPhans', function ($q) use ($user) {
                            $q->where($this->userColumn(), $this->userIdentifier($user));
                        })
                        ->exists();

                    $isHocLai = $hocTruocDay;
                }

                return [
                    'ma_lop_hoc_phan' => $lop->MaTKB,
                    'ma_mon_hoc' => $lop->MaHP,
                    'ten_mon_hoc' => $lop->hocPhan?->TenHP ?? 'N/A',
                    'so_tin_chi' => $lop->hocPhan?->SoTinChi ?? 0,
                    'giang_vien' => $lop->GiangVien ?? 'N/A',
                    'hoc_ky' => $lop->namHoc?->HocKy ?? 'N/A',
                    'nam_hoc' => $lop->namHoc?->TenNamHoc ?? 'N/A',
                    'thu_tiet' => $this->formatThuTiet($lop->Thu, $lop->TuTiet, $lop->DenTiet),
                    'phong' => $lop->Phong ?? 'N/A',
                    'si_so_dk' => $lop->SiSoDK ?? 0,
                    'si_so_hien_tai' => $lop->SiSoHienTai ?? 0,
                    'is_hoc_lai' => $isHocLai,
                    'da_dang_ky' => $daDangKy,
                ];
            });

            Log::info('DangKyHocPhanController::lopMo - Listed open classes', [
                'ma_sinh_vien' => $maSinhVien,
                'hoc_ky' => $hocKy,
                'nam_hoc' => $namHoc
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $lopHocPhans->total(),
                    'per_page' => $lopHocPhans->perPage(),
                    'current_page' => $lopHocPhans->currentPage(),
                    'last_page' => $lopHocPhans->lastPage(),
                ],
                'message' => 'Danh sách lớp học phần đang mở'
            ], 200);
        } catch (Exception $e) {
            Log::error('DangKyHocPhanController::lopMo - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu'
            ], 500);
        }
    }

    /**
     * POST /api/dang-ky-hoc-phan
     *
     * Đăng ký học phần
     * - Tự động detect học lại
     * - Bọc trong transaction
     *
     * Body:
     * {
     *   "ma_lop_hoc_phan_ids": [1, 3, 5]
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "da_dang_ky": [
     *       { "ma_mon_hoc": "CTDL001", "ten_mon_hoc": "...", "is_hoc_lai": false }
     *     ],
     *     "that_bai": [
     *       { "ma_lop_hoc_phan_id": 5, "ly_do": "Đã đăng ký môn này rồi" }
     *     ]
     *   },
     *   "message": "Đăng ký thành công 2/3 môn học"
     * }
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            $maSinhVien = $user->sinhVien?->MaSoSV;

            if (!$maSinhVien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Người dùng không phải là sinh viên'
                ], 403);
            }

            // Validate
            $validator = Validator::make($request->all(), [
                'ma_lop_hoc_phan_ids' => 'required|array|min:1',
                'ma_lop_hoc_phan_ids.*' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            $maLopIds = $request->input('ma_lop_hoc_phan_ids');
            $daDangKy = [];
            $thatBai = [];

            DB::beginTransaction();

            try {
                foreach ($maLopIds as $maLopId) {
                    try {
                        $lop = LichSuTKB::findOrFail($maLopId);

                        // Check if already registered
                        $existsReg = DangKyHocPhan::where('MaTKB', $maLopId)
                            ->where($this->userColumn(), $this->userIdentifier($user))
                            ->exists();

                        if ($existsReg) {
                            $thatBai[] = [
                                'ma_lop_hoc_phan_id' => $maLopId,
                                'ly_do' => 'Đã đăng ký lớp này rồi'
                            ];
                            continue;
                        }

                        // Detect học lại
                        $isHocLai = false;
                        $previousSemester = $this->getPreviousSemester($lop);
                        if ($previousSemester) {
                            $hocTruocDay = LichSuTKB::where('MaHP', $lop->MaHP)
                                ->where('MaNamHoc', $previousSemester['MaNamHoc'])
                                ->whereHas('dangKyHocPhans', function ($q) use ($user) {
                                        $q->where($this->userColumn(), $this->userIdentifier($user));
                                    })
                                ->exists();

                            $isHocLai = $hocTruocDay;
                        }

                        // Create registration (set correct user column)
                        $createData = [
                            'MaTKB' => $maLopId,
                            'IsHocLai' => $isHocLai ? 1 : 0,
                            'NguonNhap' => 'sinh_vien_tu_chon',
                        ];
                        $createData[$this->userColumn()] = $this->userIdentifier($user);

                        DangKyHocPhan::create($createData);

                        $daDangKy[] = [
                            'ma_mon_hoc' => $lop->MaHP,
                            'ten_mon_hoc' => $lop->hocPhan?->TenHP ?? 'N/A',
                            'is_hoc_lai' => $isHocLai,
                        ];

                        Log::info('DangKyHocPhanController::store - Registered class', [
                            'ma_sinh_vien' => $maSinhVien,
                            'ma_lop_hoc_phan' => $maLopId,
                            'is_hoc_lai' => $isHocLai
                        ]);
                    } catch (Exception $e) {
                        $thatBai[] = [
                            'ma_lop_hoc_phan_id' => $maLopId,
                            'ly_do' => $e->getMessage()
                        ];
                    }
                }

                DB::commit();

                $tongDangKy = count($maLopIds);
                $tongThanhCong = count($daDangKy);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'da_dang_ky' => $daDangKy,
                        'that_bai' => $thatBai,
                    ],
                    'message' => "Đăng ký thành công {$tongThanhCong}/{$tongDangKy} môn học"
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('DangKyHocPhanController::store - Transaction Error: ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi xử lý đăng ký: ' . $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('DangKyHocPhanController::store - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu'
            ], 500);
        }
    }

    /**
     * GET /api/dang-ky-hoc-phan/cua-toi
     *
     * Xem danh sách môn đã đăng ký
     *
     * Query Parameters:
     * - hoc_ky (optional): Lọc theo học kỳ
     * - nam_hoc (optional): Lọc theo năm học
     * - per_page: Số bản ghi/trang (default: 20)
     * - page: Trang (default: 1)
     *
     * Response:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "ma_lop_hoc_phan": 1,
     *       "ma_mon_hoc": "CTDL001",
     *       "ten_mon_hoc": "Cấu trúc dữ liệu",
     *       "so_tin_chi": 3,
     *       "giang_vien": "TS. Nguyễn Văn A",
     *       "is_hoc_lai": false,
     *       "hoc_ky": "HK1",
     *       "nam_hoc": "2025-2026",
     *       "ghi_chu": ""
     *     }
     *   ],
     *   "pagination": {...},
     *   "message": "Danh sách môn đã đăng ký"
     * }
     */
   public function cuaToi(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.'
                ], 401);
            }

            // Lọc chính xác theo cột phù hợp trong bảng DANG_KY_HOC_PHAN
            // Một số môi trường dùng `MaNguoiDung`, một số dùng `MaSinhVien`.
            // Kiểm tra tồn tại cột để tránh SQL errors.

            if (Schema::hasColumn('DANG_KY_HOC_PHAN', 'MaNguoiDung')) {
                $query = DangKyHocPhan::where('MaNguoiDung', $user->MaNguoiDung);
            } else {
                $maSinhVien = $user->sinhVien?->MaSoSV ?? $user->MaNguoiDung;
                $query = DangKyHocPhan::where('MaSinhVien', $maSinhVien);
            }

            // Sửa lỗi logic 500: Lọc theo Học Kỳ
            if ($request->filled('hoc_ky')) {
                $hocKy = $request->input('hoc_ky');
                $query->whereHas('lichSuTKB.namHoc', function ($q) use ($hocKy) {
                    $q->where('HocKy', $hocKy);
                });
            }

            // Sửa lỗi logic 500: Lọc theo Năm Học (Bọc nhóm toán tử orWhere bằng Closure để tránh gãy SQL)
            if ($request->filled('nam_hoc')) {
                $namHoc = $request->input('nam_hoc');
                $query->whereHas('lichSuTKB.namHoc', function ($q) use ($namHoc) {
                    $q->where(function ($innerQuery) use ($namHoc) {
                        $innerQuery->where('TenNamHoc', $namHoc)
                                   ->orWhere('MaNamHoc', $namHoc);
                    });
                });
            }

            // Eager load tối ưu hóa quan hệ dữ liệu
            $listDangKy = $query->with(['lichSuTKB.hocPhan', 'lichSuTKB.namHoc'])->get();

            // Format dữ liệu và sử dụng toán tử điều kiện tránh lỗi gọi thuộc tính trên Object Null
            $formattedData = $listDangKy->map(function ($dk) {
                $tkb = $dk->lichSuTKB;
                $hocPhan = $tkb ? $tkb->hocPhan : null;

                return [
                    'MaTKB' => $dk->MaTKB,
                    'TenLHP' => $tkb ? ($tkb->TenLHP ?: ($hocPhan ? $hocPhan->TenHP : 'Môn học chưa xếp lớp')) : 'N/A',
                    'Thu' => $tkb ? $tkb->Thu : '?',
                    'TuTiet' => $tkb ? $tkb->TuTiet : 0,
                    'DenTiet' => $tkb ? $tkb->DenTiet : 0,
                    'Phong' => $tkb ? $tkb->Phong : 'N/A',
                    'GiangVien' => $tkb ? $tkb->GiangVien : 'Chưa phân công',
                    'SoTinChi' => $hocPhan ? $hocPhan->SoTinChi : 0,
                    'IsHocLai' => (bool)$dk->IsHocLai,
                    'HocKy' => ($tkb && $tkb->namHoc) ? $tkb->namHoc->HocKy : 'N/A',
                    'TenNamHoc' => ($tkb && $tkb->namHoc) ? $tkb->namHoc->TenNamHoc : 'N/A',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'message' => 'Tải danh sách đăng ký học phần thành công.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('CRASH_API_CUA_TOI_DANGKY: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi máy chủ khi xử lý danh sách học phần.',
                'error_debug' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * DELETE /api/dang-ky-hoc-phan/{maLichSuTKB}
     *
     * Hủy đăng ký học phần
     * - Chỉ hủy được nếu đợt thu hồ sơ chưa đóng
     * - Chỉ hủy được môn của chính SV
     *
     * Response:
     * {
     *   "success": true,
     *   "message": "Đã hủy đăng ký môn CTDL001"
     * }
     */
    public function destroy(Request $request, $maTKB)
    {
        try {
            $user = Auth::user();
            $maSinhVien = $user->sinhVien?->MaSoSV;

            if (!$maSinhVien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Người dùng không phải là sinh viên'
                ], 403);
            }

            // Find registration
            $dk = DangKyHocPhan::where('MaTKB', $maTKB)
                ->where($this->userColumn(), $this->userIdentifier($user))
                ->with('lichSuTKB')
                ->firstOrFail();

            // Check if collection period is open
            $dot = DotThuHoSo::where('TrangThaiDot', 0)->first();  // TrangThaiDot = 0 means open
            if (!$dot) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đợt thu hồ sơ đã đóng, không thể hủy đăng ký'
                ], 400);
            }

            // Delete registration
            $maMonHoc = $dk->lichSuTKB->MaHP;
            $dk->delete();

            Log::info('DangKyHocPhanController::destroy - Cancelled registration', [
                'ma_sinh_vien' => $maSinhVien,
                'ma_tkb' => $maTKB,
                'ma_mon_hoc' => $maMonHoc
            ]);

            return response()->json([
                'success' => true,
                'message' => "Đã hủy đăng ký môn {$maMonHoc}"
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn đăng ký không tìm thấy'
            ], 404);
        } catch (Exception $e) {
            Log::error('DangKyHocPhanController::destroy - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu'
            ], 500);
        }
    }

    /**
     * Helper: Get previous semester info
     */
    private function getPreviousSemester(LichSuTKB $lop): ?array
    {
        $currentSemester = $lop->namHoc;
        if (!$currentSemester) {
            return null;
        }

        // Simple logic: if current is HK1, previous is HK2 of previous year
        // if current is HK2, previous is HK1 of same year
        $currentHocKy = $currentSemester->HocKy;
        $currentYear = $currentSemester->TenNamHoc;

        if ($currentHocKy === 'HK1') {
            // Previous is HK2 of previous year
            $yearParts = explode('-', $currentYear);
            if (count($yearParts) === 2) {
                $prevYear = ((int) $yearParts[0] - 1) . '-' . $yearParts[0];
                $prevNamHoc = NamHoc::where('TenNamHoc', $prevYear)
                    ->where('HocKy', 'HK2')
                    ->first();
                if ($prevNamHoc) {
                    return ['MaNamHoc' => $prevNamHoc->MaNamHoc, 'TenNamHoc' => $prevYear, 'HocKy' => 'HK2'];
                }
            }
        } else {
            // Previous is HK1 of same year
            $prevNamHoc = NamHoc::where('TenNamHoc', $currentYear)
                ->where('HocKy', 'HK1')
                ->first();
            if ($prevNamHoc) {
                return ['MaNamHoc' => $prevNamHoc->MaNamHoc, 'TenNamHoc' => $currentYear, 'HocKy' => 'HK1'];
            }
        }

        return null;
    }

    /**
     * Helper: Format thu/tiet to readable format
     */
    private function formatThuTiet($thu, $tuTiet, $denTiet): string
    {
        $thuNames = [
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            0 => 'Chủ nhật',
        ];

        $thuName = $thuNames[$thu] ?? 'N/A';
        return "{$thuName}, tiết {$tuTiet}-{$denTiet}";
    }
}