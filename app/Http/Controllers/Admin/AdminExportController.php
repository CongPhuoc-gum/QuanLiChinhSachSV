<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AdminExportController - DAY 4
 *
 * Xử lý xuất dữ liệu hồ sơ ra CSV/Excel
 */
class AdminExportController extends Controller
{
    private ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * GET /api/admin/export/ho-so-csv
     *
     * Xuất danh sách hồ sơ dạng CSV
     *
     * Query Parameters:
     * - loai_cs: Loại chính sách (1=MGHP, 2=TCXH)
     * - trang_thai: Trạng thái hồ sơ
     * - da_duyet: Boolean, chỉ lấy hồ sơ đã duyệt
     *
     * Response: File CSV download
     */
    public function exportHoSoCSV(Request $request)
    {
        try {
            // Prepare filters
            $filters = [];
            if ($request->has('loai_cs') && !empty($request->input('loai_cs'))) {
                $filters['loai_cs'] = (int) $request->input('loai_cs');
            }

            if ($request->has('trang_thai') && !empty($request->input('trang_thai'))) {
                $filters['trang_thai'] = (int) $request->input('trang_thai');
            }

            if ($request->has('da_duyet')) {
                $filters['da_duyet'] = filter_var($request->input('da_duyet'), FILTER_VALIDATE_BOOLEAN);
            }

            // Export data
            $csvData = $this->exportService->exportToCSV($filters);

            if (!$csvData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi xuất dữ liệu'
                ], 500);
            }

            // Get summary
            $summary = $this->exportService->getExportSummary(
                $filters['loai_cs'] ?? null,
                $filters['trang_thai'] ?? null
            );

            // Format CSV string
            $csvContent = $this->exportService->formatCSVString($csvData);

            // Generate filename
            $fileName = $this->exportService->generateFileName($filters['loai_cs'] ?? null);

            Log::info('AdminExportController::exportHoSoCSV - Exported', [
                'file' => $fileName,
                'records' => $csvData['total'],
                'filters' => $filters
            ]);

            // Return CSV file download
            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '.csv"')
                ->header('Pragma', 'no-cache')
                ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                ->header('Expires', '0');
        } catch (Exception $e) {
            Log::error('AdminExportController::exportHoSoCSV - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xuất file CSV: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/export/summary
     *
     * Lấy thống kê tóm tắt cho báo cáo
     *
     * Query Parameters:
     * - loai_cs: Loại chính sách
     * - trang_thai: Trạng thái
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "tong_so_ho_so": 150,
     *     "da_duyet": 100,
     *     "tu_choi": 10,
     *     "dang_xu_ly": 40,
     *     "ty_le_duyet": 66.67
     *   },
     *   "message": "Thống kê xuất"
     * }
     */
    public function exportSummary(Request $request)
    {
        try {
            $loaiCs = $request->has('loai_cs') ? (int) $request->input('loai_cs') : null;
            $trangThai = $request->has('trang_thai') ? (int) $request->input('trang_thai') : null;

            $summary = $this->exportService->getExportSummary($loaiCs, $trangThai);

            if (isset($summary['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $summary['error']
                ], 500);
            }

            Log::info('AdminExportController::exportSummary - Generated', [
                'loai_cs' => $loaiCs,
                'trang_thai' => $trangThai
            ]);

            return response()->json([
                'success' => true,
                'data' => $summary,
                'message' => 'Thống kê xuất'
            ], 200);
        } catch (Exception $e) {
            Log::error('AdminExportController::exportSummary - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi tính toán thống kê'
            ], 500);
        }
    }
}
