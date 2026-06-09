<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HoSo;
use App\Models\LoaiChinhSach;
use App\Models\PhanTichAIHoSo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AdminDashboardController - DAY 4
 *
 * Cung cấp dữ liệu thống kê cho Admin Dashboard
 */
class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard-stats
     *
     * Lấy toàn bộ thống kê cho Dashboard
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "overview": {
     *       "tong_ho_so": 150,
     *       "cho_tham_dinh": 25,
     *       "da_duyet": 100,
     *       "tu_choi": 10,
     *       "dang_bo_sung": 5,
     *       "da_chi_tra": 80
     *     },
     *     "ai_stats": {
     *       "auto_approved": 85,
     *       "warning_cases": 30,
     *       "need_review": 15,
     *       "pending": 20
     *     },
     *     "policy_stats": [
     *       {
     *         "loai_cs": 1,
     *         "ten_loai": "MGHP",
     *         "so_ho_so": 80,
     *         "da_duyet": 60,
     *         "dang_xu_ly": 15,
     *         "tu_choi": 5,
     *         "ty_le_thaydung": 75.0
     *       }
     *     ],
     *     "success_rate": 86.67,
     *     "ai_accuracy": 91.25
     *   },
     *   "message": "Dữ liệu thống kê Dashboard"
     * }
     */
    public function stats()
    {
        try {
            // 1. OVERVIEW STATS
            $overview = [
                'tong_ho_so' => HoSo::count(),
                'cho_tham_dinh' => HoSo::where('MaTrangThai', 2)->count(),
                'da_duyet' => HoSo::where('MaTrangThai', 6)->count(),
                'tu_choi' => HoSo::where('MaTrangThai', 8)->count(),
                'dang_bo_sung' => HoSo::where('MaTrangThai', 3)->count(),
                'da_chi_tra' => HoSo::where('MaTrangThai', 7)->count(),
                'cho_tp_duyet' => HoSo::where('MaTrangThai', 4)->count(),
                'cho_bgh_duyet' => HoSo::where('MaTrangThai', 5)->count(),
            ];

            // 2. AI STATISTICS
            $aiStats = [
                'auto_approved' => PhanTichAIHoSo::where('TrangThaiXuLy', 'APPROVED')->count(),
                'warning_cases' => PhanTichAIHoSo::where('TrangThaiXuLy', 'WARNING')->count(),
                'need_review' => PhanTichAIHoSo::where('TrangThaiXuLy', 'NEED_REVIEW')->count(),
                'pending' => PhanTichAIHoSo::where('TrangThaiXuLy', 'PENDING')->count(),
            ];

            // 3. POLICY TYPE STATISTICS
            $policyStats = LoaiChinhSach::with('hoSos')
                ->get()
                ->map(function ($policy) {
                    $totalHoSo = $policy->hoSos()->count();
                    $daDuyet = $policy->hoSos()->where('MaTrangThai', 6)->count();
                    $dangXuLy = $policy
                        ->hoSos()
                        ->whereIn('MaTrangThai', [2, 3, 4, 5])
                        ->count();
                    $tuChoi = $policy->hoSos()->where('MaTrangThai', 8)->count();

                    return [
                        'loai_cs' => $policy->MaLoaiCS,
                        'ten_loai' => $policy->TenLoaiCS ?? 'Unknown',
                        'so_ho_so' => $totalHoSo,
                        'da_duyet' => $daDuyet,
                        'dang_xu_ly' => $dangXuLy,
                        'tu_choi' => $tuChoi,
                        'ty_le_thaydung' => $totalHoSo > 0 ? round(($daDuyet / $totalHoSo) * 100, 2) : 0,
                    ];
                })
                ->toArray();

            // 4. SUCCESS RATE (Approved / Total)
            $successRate = $overview['tong_ho_so'] > 0
                ? round(($overview['da_duyet'] / $overview['tong_ho_so']) * 100, 2)
                : 0;

            // 5. AI ACCURACY (Approved / Total processed by AI)
            $totalAiProcessed = $aiStats['auto_approved'] + $aiStats['warning_cases'] + $aiStats['need_review'];
            $aiAccuracy = $totalAiProcessed > 0
                ? round(($aiStats['auto_approved'] / $totalAiProcessed) * 100, 2)
                : 0;

            // 6. RECENT ACTIVITIES (Last 10 reviews)
            $recentActivities = DB::table('NHAT_KY_XET_DUYET')
                ->join('NGUOI_DUNG', 'NHAT_KY_XET_DUYET.MaNguoiThucHien', '=', 'NGUOI_DUNG.MaNguoiDung')
                ->select(
                    'NHAT_KY_XET_DUYET.MaHoSo',
                    'NHAT_KY_XET_DUYET.MaTrangThaiSau',
                    'NHAT_KY_XET_DUYET.GhiChu',
                    'NHAT_KY_XET_DUYET.NgayThucHien',
                    'NGUOI_DUNG.Email'
                )
                ->orderBy('NHAT_KY_XET_DUYET.NgayThucHien', 'desc')
                ->limit(10)
                ->get()
                ->toArray();

            Log::info('AdminDashboardController::stats - Generated dashboard stats', [
                'total_ho_so' => $overview['tong_ho_so'],
                'ai_processed' => $totalAiProcessed
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $overview,
                    'ai_stats' => $aiStats,
                    'policy_stats' => $policyStats,
                    'success_rate' => $successRate,
                    'ai_accuracy' => $aiAccuracy,
                    'recent_activities' => $recentActivities,
                    'generated_at' => now(),
                ],
                'message' => 'Dữ liệu thống kê Dashboard'
            ], 200);
        } catch (Exception $e) {
            Log::error('AdminDashboardController::stats - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy dữ liệu thống kê'
            ], 500);
        }
    }

    /**
     * GET /api/admin/dashboard-ai-warning
     *
     * Lấy danh sách hồ sơ bị AI cảnh báo (WARNING)
     * Để Admin có thể xem xét chi tiết
     *
     * Response:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "ma_ho_so": 5,
     *       "ho_ten": "Trần Văn B",
     *       "ty_le_khop": 0.85,
     *       "discrepancies": [{"field": "ho_ten", "severity": "warning"}],
     *       "recommendation": "⚠️ Xét duyệt có điều kiện"
     *     }
     *   ],
     *   "total": 30,
     *   "message": "Danh sách hồ sơ bị cảnh báo"
     * }
     */
    public function aiWarnings(Request $request)
    {
        try {
            $warnings = PhanTichAIHoSo::where('TrangThaiXuLy', 'WARNING')
                ->with(['hoSo.nguoiDung.sinhVien'])
                ->orderBy('ThoiGianPhanTich', 'desc')
                ->paginate(20);

            $data = $warnings->map(function ($item) {
                return [
                    'ma_ho_so' => $item->MaHoSo,
                    'ho_ten' => ($item->hoSo->nguoiDung?->sinhVien?->HoDem ?? '') . ' '
                        . ($item->hoSo->nguoiDung?->sinhVien?->Ten ?? ''),
                    'ma_so_sv' => $item->hoSo->nguoiDung?->sinhVien?->MaSoSV ?? 'N/A',
                    'ty_le_khop' => $item->TyLeKhop,
                    'discrepancies' => $item->CanBaoLech ?? [],
                    'recommendation' => $item->KetQuaDoiChieu['recommendation'] ?? '',
                    'thoi_gian_phan_tich' => $item->ThoiGianPhanTich,
                ];
            });

            Log::info('AdminDashboardController::aiWarnings - Fetched warning cases', [
                'total' => $warnings->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $warnings->total(),
                    'per_page' => $warnings->perPage(),
                    'current_page' => $warnings->currentPage(),
                ],
                'message' => 'Danh sách hồ sơ bị cảnh báo'
            ], 200);
        } catch (Exception $e) {
            Log::error('AdminDashboardController::aiWarnings - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy danh sách cảnh báo'
            ], 500);
        }
    }

    /**
     * GET /api/admin/dashboard-ai-need-review
     *
     * Lấy danh sách hồ sơ cần thẩm định lại (NEED_REVIEW)
     * Độ khớp < 80%
     *
     * Response:
     * {
     *   "success": true,
     *   "data": [...],
     *   "total": 15,
     *   "message": "Danh sách hồ sơ cần thẩm định"
     * }
     */
    public function aiNeedReview(Request $request)
    {
        try {
            $needReview = PhanTichAIHoSo::where('TrangThaiXuLy', 'NEED_REVIEW')
                ->with(['hoSo.nguoiDung.sinhVien'])
                ->orderBy('TyLeKhop', 'asc')
                ->paginate(20);

            $data = $needReview->map(function ($item) {
                return [
                    'ma_ho_so' => $item->MaHoSo,
                    'ho_ten' => ($item->hoSo->nguoiDung?->sinhVien?->HoDem ?? '') . ' '
                        . ($item->hoSo->nguoiDung?->sinhVien?->Ten ?? ''),
                    'ma_so_sv' => $item->hoSo->nguoiDung?->sinhVien?->MaSoSV ?? 'N/A',
                    'ty_le_khop' => $item->TyLeKhop,
                    'discrepancies' => $item->CanBaoLech ?? [],
                    'recommendation' => $item->KetQuaDoiChieu['recommendation'] ?? '',
                    'thoi_gian_phan_tich' => $item->ThoiGianPhanTich,
                ];
            });

            Log::info('AdminDashboardController::aiNeedReview - Fetched need review cases', [
                'total' => $needReview->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $needReview->total(),
                    'per_page' => $needReview->perPage(),
                    'current_page' => $needReview->currentPage(),
                ],
                'message' => 'Danh sách hồ sơ cần thẩm định'
            ], 200);
        } catch (Exception $e) {
            Log::error('AdminDashboardController::aiNeedReview - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy danh sách cần thẩm định'
            ], 500);
        }
    }
}
