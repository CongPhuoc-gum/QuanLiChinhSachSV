<?php

namespace App\Http\Controllers\TaiVu;

use App\Http\Controllers\Controller;
use App\Models\GiaoDichNoiBo;
use App\Models\HoSo;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LenhChiController extends Controller
{
    /**
     * GET /api/tai-vu/lenh-chi
     * Danh sách lệnh chi
     */
    public function index(Request $request)
    {
        try {
            $query = GiaoDichNoiBo::with('nguoiDung.sinhVien');

            // Filter theo loại giao dịch
            if ($request->filled('loai_giao_dich')) {
                $query->where('LoaiGiaoDich', $request->query('loai_giao_dich'));
            }

            // Filter theo trạng thái
            if ($request->filled('trang_thai')) {
                $query->where('TrangThai', $request->query('trang_thai'));
            }

            $perPage = $request->query('per_page', 20);
            $lenhChis = $query->orderBy('NgayTao', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $lenhChis->items(),
                'pagination' => [
                    'total' => $lenhChis->total(),
                    'per_page' => $lenhChis->perPage(),
                    'current_page' => $lenhChis->currentPage(),
                    'last_page' => $lenhChis->lastPage(),
                ],
                'message' => 'Danh sách lệnh chi'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in TaiVu\LenhChiController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách'
            ], 500);
        }
    }

    /**
     * GET /api/tai-vu/lenh-chi/{id}
     * Chi tiết lệnh chi
     */
    public function show($maGiaoDich)
    {
        try {
            $giaoDich = GiaoDichNoiBo::with(['nguoiDung.sinhVien', 'hoSo'])->findOrFail($maGiaoDich);

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_giao_dich' => $giaoDich->MaGiaoDich,
                    'ma_so_sv' => $giaoDich->nguoiDung->sinhVien->MaSoSV,
                    'ho_ten' => $giaoDich->nguoiDung->sinhVien->HoTen,
                    'loai_giao_dich' => $giaoDich->LoaiGiaoDich,
                    'so_tien' => $giaoDich->SoTien,
                    'so_tai_khoan' => $giaoDich->SoTaiKhoan,
                    'ten_ngan_hang' => $giaoDich->TenNganHang,
                    'trang_thai' => $giaoDich->TrangThai,
                    'ma_giao_dich_ngan_hang' => $giaoDich->MaGiaoDichNganHang,
                    'ngay_tao' => $giaoDich->NgayTao,
                    'ngay_hoan_thanh' => $giaoDich->NgayHoanThanh,
                    'ghi_chu' => $giaoDich->GhiChu,
                ],
                'message' => 'Chi tiết lệnh chi'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in TaiVu\LenhChiController@show', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lệnh chi'
            ], 404);
        }
    }

    /**
     * PUT /api/tai-vu/lenh-chi/{id}/xac-nhan
     * Xác nhận đã chuyển khoản
     */
    public function xacNhanChuyen($maGiaoDich, Request $request)
    {
        try {
            $validated = $request->validate([
                'ma_giao_dich_ngan_hang' => 'required|string|max:100',
                'ngay_hoan_thanh' => 'required|date',
                'ghi_chu' => 'nullable|string|max:500',
            ]);

            $giaoDich = GiaoDichNoiBo::findOrFail($maGiaoDich);

            // Check trạng thái
            if ($giaoDich->TrangThai != 'cho_xu_ly') {
                return response()->json([
                    'success' => false,
                    'message' => 'Lệnh chi không ở trạng thái chờ xử lý'
                ], 422);
            }

            $user = Auth::user();

            DB::transaction(function () use ($giaoDich, $user, $validated) {
                // Update GiaoDichNoiBo
                $giaoDich->update([
                    'TrangThai' => 'hoan_thanh',
                    'MaGiaoDichNganHang' => $validated['ma_giao_dich_ngan_hang'],
                    'NgayHoanThanh' => $validated['ngay_hoan_thanh'],
                    'GhiChu' => $validated['ghi_chu'],
                    'MaNguoiDuyetLenh' => $user->MaNguoiDung,
                ]);

                // Update HoSo status to 7 (Đã chi trả)
                if ($giaoDich->MaHoSo) {
                    $hoSo = HoSo::where('MaHoSo', $giaoDich->MaHoSo)->first();
                    if ($hoSo) {
                        $hoSo->update(['MaTrangThai' => 7]);

                        // Send notification email (X2 - Phase 3)
                        $notificationService = new NotificationService();
                        $notificationService->guiEmailDoiTrangThai($hoSo, 7);
                    }
                }

                // Log
                \Log::info('Confirmed bank transfer', [
                    'ma_giao_dich' => $maGiaoDich,
                    'confirmed_by' => $user->MaNguoiDung,
                ]);

                $this->logSystemAction($user, 'xac_nhan_chuyen_khoan',
                    "Lệnh chi ID: {$maGiaoDich}, Mã GD NH: {$validated['ma_giao_dich_ngan_hang']}");
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_giao_dich' => $maGiaoDich,
                    'trang_thai_moi' => 'hoan_thanh',
                ],
                'message' => 'Xác nhận chuyển khoản thành công'
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in TaiVu\LenhChiController@xacNhanChuyen', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xác nhận chuyển khoản'
            ], 500);
        }
    }

    /**
     * GET /api/tai-vu/cong-no
     * Danh sách công nợ tổng hợp
     */
    public function congNo(Request $request)
    {
        try {
            $query = DB::table('CONG_NO')
                ->join('SINH_VIEN', 'CONG_NO.MaSinhVien', '=', 'SINH_VIEN.MaNguoiDung')
                ->join('NGUOI_DUNG', 'SINH_VIEN.MaNguoiDung', '=', 'NGUOI_DUNG.MaNguoiDung')
                ->select(
                    'CONG_NO.MaCongNo',
                    'SINH_VIEN.MaSoSV',
                    'SINH_VIEN.HoTen',
                    'CONG_NO.HocKy',
                    'CONG_NO.NamHoc',
                    'CONG_NO.HocPhiPhaiDong',
                    'CONG_NO.SoTienMienGiam',
                    'CONG_NO.SoTienPhaiDong',
                    'CONG_NO.SoTienDaDong',
                    'CONG_NO.TienDuMGHP',
                    'CONG_NO.TrangThai',
                    'NGUOI_DUNG.Email'
                );

            // Filter theo trạng thái
            if ($request->filled('trang_thai')) {
                $query->where('CONG_NO.TrangThai', $request->query('trang_thai'));
            }

            $perPage = $request->query('per_page', 20);
            $congNos = $query->orderBy('CONG_NO.NgayCapNhat', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $congNos->items(),
                'pagination' => [
                    'total' => $congNos->total(),
                    'per_page' => $congNos->perPage(),
                    'current_page' => $congNos->currentPage(),
                    'last_page' => $congNos->lastPage(),
                ],
                'message' => 'Danh sách công nợ'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in TaiVu\LenhChiController@congNo', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách công nợ'
            ], 500);
        }
    }

    /**
     * Helper: Ghi log hệ thống
     */
    private function logSystemAction($user, $action, $detail = null)
    {
        try {
            DB::table('SYSTEM_LOGS')->insert([
                'MaNguoiDung' => $user->MaNguoiDung,
                'VaiTro' => $user->vaiTro->TenVaiTro ?? 'unknown',
                'HanhDong' => $action,
                'ChiTiet' => $detail,
                'IPAddress' => request()->ip(),
                'UserAgent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('Cannot log system action', ['error' => $e->getMessage()]);
        }
    }
}
