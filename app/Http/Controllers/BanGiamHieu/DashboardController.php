<?php

namespace App\Http\Controllers\BanGiamHieu;

use App\Http\Controllers\Controller;
use App\Models\HoSo;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/ban-giam-hieu/dashboard
     * Dashboard cho Ban Giám hiệu
     */
    public function dashboard()
    {
        try {
            // Lấy năm học và kỳ hiện tại
            $currentNamHoc = date('Y') . '-' . (date('Y') + 1);
            $currentHocKy = $this->getCurrentHocKy();

            // Tổng số SV hưởng chính sách theo học kỳ (năm học gần nhất)
            $svByHocKy = DB::table('HO_SO')
                ->join('SINH_VIEN', 'HO_SO.MaNguoiDung', '=', 'SINH_VIEN.MaNguoiDung')
                ->join('DOT_THU_HO_SO', 'HO_SO.MaDot', '=', 'DOT_THU_HO_SO.MaDot')
                ->where('HO_SO.MaTrangThai', 6)  // Đã duyệt
                ->where('DOT_THU_HO_SO.NamHoc', $currentNamHoc)
                ->groupBy('DOT_THU_HO_SO.HocKy')
                ->selectRaw('DOT_THU_HO_SO.HocKy, COUNT(*) as tong_sv')
                ->get();

            // Tổng tiền miễn giảm theo loại chính sách
            $tongTienByCS = DB::table('CONG_NO')
                ->select('CONG_NO.*')
                ->whereRaw('CONG_NO.MaCongNo IN (
                    SELECT MaCongNo FROM CONG_NO 
                    WHERE HocKy = ? AND NamHoc = ?
                )', [$currentHocKy, $currentNamHoc])
                ->get()
                ->groupBy(function ($item) {
                    return $item->SoTienMienGiam > 0 ? 'mghp' : 'tcxh';
                })
                ->map(function ($group) {
                    return [
                        'so_ho_so' => $group->count(),
                        'tong_tien' => $group->sum('SoTienMienGiam'),
                    ];
                });

            // Phân bổ theo khoa (top 5)
            $byKhoa = DB::table('HO_SO')
                ->join('SINH_VIEN', 'HO_SO.MaNguoiDung', '=', 'SINH_VIEN.MaNguoiDung')
                ->join('DANH_MUC_LOP', 'SINH_VIEN.MaLop', '=', 'DANH_MUC_LOP.MaLop')
                ->join('KHOA', 'DANH_MUC_LOP.MaKhoa', '=', 'KHOA.MaKhoa')
                ->where('HO_SO.MaTrangThai', 6)
                ->groupBy('KHOA.MaKhoa', 'KHOA.TenKhoa')
                ->selectRaw('KHOA.TenKhoa, COUNT(*) as so_sv')
                ->orderByDesc('so_sv')
                ->limit(5)
                ->get();

            // Phân bổ theo mức hưởng (100%, 70%, 50%)
            $byMucHuong = DB::table('PHAN_TICH_AI_HO_SO')
                ->selectRaw("
                    CASE 
                        WHEN JSON_EXTRACT(KetQuaDoiChieu, '\$.muc_huong') = 100 THEN '100%'
                        WHEN JSON_EXTRACT(KetQuaDoiChieu, '\$.muc_huong') = 70 THEN '70%'
                        WHEN JSON_EXTRACT(KetQuaDoiChieu, '\$.muc_huong') = 50 THEN '50%'
                        ELSE 'Khác'
                    END as muc_huong,
                    COUNT(*) as so_sv
                ")
                ->groupBy('muc_huong')
                ->get();

            // Tổng tiền đã chi trả vs chờ chi trả
            $cashStatus = [
                'da_chi_tra' => DB::table('GIAO_DICH_NOI_BO')
                    ->where('TrangThai', 'hoan_thanh')
                    ->sum('SoTien'),
                'cho_xu_ly' => DB::table('GIAO_DICH_NOI_BO')
                    ->where('TrangThai', 'cho_xu_ly')
                    ->sum('SoTien'),
            ];

            // So sánh giữa các học kỳ (trend)
            $trendByHocKy = DB::table('HO_SO')
                ->join('DOT_THU_HO_SO', 'HO_SO.MaDot', '=', 'DOT_THU_HO_SO.MaDot')
                ->where('HO_SO.MaTrangThai', 6)
                ->groupBy('DOT_THU_HO_SO.HocKy', 'DOT_THU_HO_SO.NamHoc')
                ->selectRaw('DOT_THU_HO_SO.HocKy, DOT_THU_HO_SO.NamHoc, COUNT(*) as so_sv')
                ->orderBy('DOT_THU_HO_SO.NamHoc', 'desc')
                ->orderBy('DOT_THU_HO_SO.HocKy', 'desc')
                ->limit(6)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'current_nam_hoc' => $currentNamHoc,
                    'current_hoc_ky' => $currentHocKy,
                    'sv_by_hoc_ky' => $svByHocKy,
                    'tong_tien_by_cs' => $tongTienByCS,
                    'by_khoa' => $byKhoa,
                    'by_muc_huong' => $byMucHuong,
                    'cash_status' => $cashStatus,
                    'trend_by_hoc_ky' => $trendByHocKy,
                ],
                'message' => 'Dashboard thống kê'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in BanGiamHieu\DashboardController@dashboard', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy dữ liệu dashboard'
            ], 500);
        }
    }

    /**
     * Helper: Xác định học kỳ hiện tại
     */
    private function getCurrentHocKy()
    {
        $month = (int) date('m');
        if ($month >= 9) {
            return '1';
        } elseif ($month >= 3) {
            return '2';
        } else {
            return '3';
        }
    }
}
