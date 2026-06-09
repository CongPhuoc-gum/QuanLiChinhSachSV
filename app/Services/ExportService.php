<?php

namespace App\Services;

use App\Models\HoSo;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ExportService - DAY 4
 *
 * Xử lý xuất dữ liệu hồ sơ ra CSV/Excel
 * Hỗ trợ lọc theo chính sách và trạng thái
 */
class ExportService
{
    /**
     * Xuất danh sách hồ sơ được duyệt thành CSV
     *
     * @param array $filters Bộ lọc (loai_cs, trang_thai, etc.)
     * @return array Dữ liệu CSV (header + rows)
     *
     * Format CSV:
     * Mã SV, Họ Tên, Email, Loại Chính Sách, Trạng Thái, Ngày Nộp, Ghi Chú
     */
    public function exportToCSV(array $filters = []): array
    {
        try {
            $query = HoSo::with([
                'nguoiDung.sinhVien',
                'loaiChinhSach',
                'trangThai',
                'phanTichAI'
            ]);

            // Apply filters
            if (isset($filters['loai_cs'])) {
                $query->where('MaLoaiCS', (int) $filters['loai_cs']);
            }

            if (isset($filters['trang_thai'])) {
                $query->where('MaTrangThai', (int) $filters['trang_thai']);
            }

            if (isset($filters['da_duyet']) && $filters['da_duyet']) {
                $query->where('MaTrangThai', 6);  // Only approved
            }

            // Get data
            $hoSos = $query->orderBy('MaHoSo', 'desc')->get();

            // Header
            $header = [
                'Mã SV',
                'Họ Tên',
                'Email',
                'Loại Chính Sách',
                'Trạng Thái',
                'AI Status',
                'Độ Khớp AI',
                'Ngày Nộp',
                'Ghi Chú'
            ];

            // Rows
            $rows = $hoSos->map(function ($hoSo) {
                return [
                    $hoSo->nguoiDung?->sinhVien?->MaSoSV ?? 'N/A',
                    ($hoSo->nguoiDung?->sinhVien?->HoDem ?? '') . ' ' . ($hoSo->nguoiDung?->sinhVien?->Ten ?? ''),
                    $hoSo->nguoiDung?->Email ?? 'N/A',
                    $hoSo->loaiChinhSach?->TenLoaiCS ?? 'N/A',
                    $hoSo->trangThai?->TenTrangThai ?? 'Unknown',
                    $hoSo->phanTichAI?->TrangThaiXuLy ?? 'PENDING',
                    $hoSo->phanTichAI?->TyLeKhop ?? 'N/A',
                    $hoSo->NgayNop?->format('d/m/Y H:i') ?? 'N/A',
                    $hoSo->GhiChu ?? '',
                ];
            })->toArray();

            Log::info('ExportService::exportToCSV - Exported data', [
                'count' => count($rows),
                'filters' => $filters
            ]);

            return [
                'success' => true,
                'headers' => $header,
                'rows' => $rows,
                'total' => count($rows),
                'exported_at' => now(),
            ];
        } catch (Exception $e) {
            Log::error('ExportService::exportToCSV - Error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format CSV data to downloadable string
     *
     * @param array $csvData Data từ exportToCSV()
     * @return string CSV formatted string
     */
    public function formatCSVString(array $csvData): string
    {
        if (!$csvData['success']) {
            return '';
        }

        $output = '';

        // Header row
        $output .= implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $csvData['headers'])) . "\n";

        // Data rows
        foreach ($csvData['rows'] as $row) {
            $output .= implode(',', array_map(fn($cell) => '"' . str_replace('"', '""', (string) $cell) . '"', $row)) . "\n";
        }

        return $output;
    }

    /**
     * Sinh ra tên file xuất dựa trên loại chính sách
     *
     * @param int|null $loaiCs Mã loại chính sách
     * @return string Tên file (không có extension)
     */
    public function generateFileName(?int $loaiCs = null): string
    {
        $date = now()->format('Y-m-d_H-i-s');
        $policyName = match ($loaiCs) {
            1 => 'MGHP',
            2 => 'TCXH',
            default => 'All'
        };

        return "DanhSach_{$policyName}_{$date}";
    }

    /**
     * Get summary statistics for export
     *
     * @param int|null $loaiCs Filter by policy type
     * @param int|null $trangThai Filter by status
     * @return array Summary stats
     */
    public function getExportSummary(?int $loaiCs = null, ?int $trangThai = null): array
    {
        try {
            $query = HoSo::query();

            if ($loaiCs) {
                $query->where('MaLoaiCS', $loaiCs);
            }

            if ($trangThai) {
                $query->where('MaTrangThai', $trangThai);
            }

            $total = $query->count();
            $approved = (clone $query)->where('MaTrangThai', 6)->count();
            $rejected = (clone $query)->where('MaTrangThai', 8)->count();
            $pending = (clone $query)->whereIn('MaTrangThai', [2, 3, 4, 5])->count();

            $approvalRate = $total > 0 ? round(($approved / $total) * 100, 2) : 0;

            return [
                'tong_so_ho_so' => $total,
                'da_duyet' => $approved,
                'tu_choi' => $rejected,
                'dang_xu_ly' => $pending,
                'ty_le_duyet' => $approvalRate,
            ];
        } catch (Exception $e) {
            Log::error('ExportService::getExportSummary - Error: ' . $e->getMessage());

            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}
