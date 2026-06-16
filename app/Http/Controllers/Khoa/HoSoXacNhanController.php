<?php

namespace App\Http\Controllers\Khoa;

use App\Http\Controllers\Controller;
use App\Models\HoSo;
use App\Models\HoSoApprovalStage;
use App\Models\NhatKyXetDuyet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Khoa\HoSoXacNhanController - Khoa xác nhận hồ sơ
 *
 * Quy trình:
 * 1. Sinh viên nộp hồ sơ
 * 2. Khoa xác nhận tính đúng đắn của form + minh chứng
 * 3. Nếu OK → chuyển đến CTSV thẩm định
 * 4. Nếu thiếu → yêu cầu bổ sung
 */
class HoSoXacNhanController extends Controller
{
    /**
     * GET /api/khoa/ho-so-xac-nhan
     * Danh sách hồ sơ chờ khoa xác nhận
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            // Lấy khoa của cán bộ
            $khoa = DB::table('DANH_MUC_LOP')
                ->join('SINH_VIEN', 'DANH_MUC_LOP.MaKhoa', '=', 'SINH_VIEN.MaKhoa')
                ->where('DANH_MUC_LOP.MaKhoa', '>', 0)
                ->select('DANH_MUC_LOP.MaKhoa')
                ->distinct()
                ->first();

            if (!$khoa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy khoa của bạn',
                ], 403);
            }

            // Danh sách hồ sơ chờ Khoa xác nhận (Stage 1)
            $hoSos = HoSo::with([
                'nguoiDung.sinhVien.lop.khoa',
                'loaiChinhSach',
                'minhChungFiles',
                'approvalStages',
            ])
                ->whereHas('nguoiDung.sinhVien.lop.khoa', fn($q) => $q->where('MaKhoa', $khoa->MaKhoa))
                ->whereHas('approvalStages', fn($q) => $q->where('GiaiDoan', 1)->where('TrangThai', 0))
                ->orderBy('NgayNop', 'asc')
                ->paginate($request->query('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $hoSos->items(),
                'pagination' => [
                    'total' => $hoSos->total(),
                    'per_page' => $hoSos->perPage(),
                    'current_page' => $hoSos->currentPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in Khoa\HoSoXacNhanController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy danh sách',
            ], 500);
        }
    }

    /**
     * GET /api/khoa/ho-so-xac-nhan/{maHoSo}
     * Chi tiết hồ sơ cần xác nhận
     */
    public function show($maHoSo)
    {
        try {
            $hoSo = HoSo::with([
                'nguoiDung.sinhVien.lop.khoa',
                'loaiChinhSach',
                'minhChungFiles',
                'approvalStages',
                'nhatKyXetDuyets',
            ])->findOrFail($maHoSo);

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_ho_so' => $hoSo->MaHoSo,
                    'sinh_vien' => [
                        'ma_so_sv' => $hoSo->nguoiDung->sinhVien->MaSoSV,
                        'ho_ten' => $hoSo->nguoiDung->sinhVien->HoTen,
                        'lop' => $hoSo->nguoiDung->sinhVien->lop->TenLop,
                        'khoa' => $hoSo->nguoiDung->sinhVien->lop->khoa->TenKhoa,
                    ],
                    'du_lieu_form' => $hoSo->du_lieu_form,
                    'minh_chungs' => $hoSo->minhChungFiles->map(fn($m) => [
                        'ma_minh_chung' => $m->MaMinhChung,
                        'ten_file' => $m->TenFile,
                        'url' => $m->URLMinhChung,
                    ]),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hồ sơ không tìm thấy',
            ], 404);
        }
    }

    /**
     * POST /api/khoa/ho-so-xac-nhan/{maHoSo}/duyet
     * Khoa xác nhận hồ sơ
     *
     * Body: {
     *   "duyet": true/false,
     * "ghi_chu": "Hồ sơ đầy đủ" hoặc "Thiếu giấy CCCD"
     * }
     */
    public function approveHoSo(Request $request, $maHoSo)
    {
        try {
            $request->validate([
                'duyet' => 'required|boolean',
                'ghi_chu' => 'required|string|max:500',
            ]);

            $user = Auth::user();
            $hoSo = HoSo::findOrFail($maHoSo);
            $duyet = $request->input('duyet');
            $ghiChu = $request->input('ghi_chu');

            DB::transaction(function () use ($hoSo, $user, $duyet, $ghiChu, $maHoSo) {
                // 1. Update approval stage
                $stage = HoSoApprovalStage::where('MaHoSo', $maHoSo)
                    ->where('GiaiDoan', 1)
                    ->first();

                if ($duyet) {
                    // Khoa xác nhận → chuyển đến CTSV (Stage 2)
                    $stage->update([
                        'TrangThai' => 1,
                        'NguoiXacNhan' => $user->MaNguoiDung,
                        'ThoiGianXacNhan' => now(),
                        'GhiChu' => $ghiChu,
                    ]);

                    // Tạo stage mới: Chờ CTSV thẩm định
                    HoSoApprovalStage::create([
                        'MaHoSo' => $maHoSo,
                        'GiaiDoan' => 2,
                        'TrangThai' => 0,
                    ]);
                } else {
                    // Khoa từ chối → yêu cầu bổ sung
                    $stage->update([
                        'TrangThai' => -1,
                        'NguoiXacNhan' => $user->MaNguoiDung,
                        'ThoiGianXacNhan' => now(),
                        'GhiChu' => "TỪ CHỐI: {$ghiChu}",
                    ]);

                    // Quay lại trạng thái "Đang bổ sung" (MaTrangThai = 3)
                    $hoSo->update(['MaTrangThai' => 3]);
                }

                // 2. Ghi nhật ký
                NhatKyXetDuyet::create([
                    'MaHoSo' => $maHoSo,
                    'MaNguoiThucHien' => $user->MaNguoiDung,
                    'HanhDong' => $duyet ? 'Khoa xác nhận' : 'Khoa yêu cầu bổ sung',
                    'TrangThaiTruoc' => 2,
                    'TrangThaiSau' => $duyet ? 2 : 3,
                    'GhiChu' => $ghiChu,
                    'MayTinh' => gethostname(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => $duyet ? 'Xác nhận hồ sơ thành công' : 'Yêu cầu bổ sung hồ sơ',
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in Khoa\HoSoXacNhanController@approveHoSo', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xác nhận hồ sơ',
            ], 500);
        }
    }
}
