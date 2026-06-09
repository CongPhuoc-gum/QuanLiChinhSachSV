<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ComparisonService - DAY 3
 *
 * So khớp dữ liệu OCR từ Gemini Vision với form_data ban đầu
 * Tự động cập nhật trạng thái hồ sơ dựa trên độ trùng khớp
 */
class ComparisonService
{
    // Loại bản ghi OCR
    const OCR_TYPE_CCCD = 'cccd';
    const OCR_TYPE_HO_KHAU = 'ho_khau';
    const OCR_TYPE_HO_NGHEO = 'ho_ngheo';
    const OCR_TYPE_KHAI_SINH = 'khai_sinh';
    // Hệ số trọng số cho các trường (0-1)
    const WEIGHT_HO_TEN = 0.35;
    const WEIGHT_ID = 0.35;
    const WEIGHT_OTHERS = 0.3;
    // Ngưỡng độ tin cay
    const CONFIDENCE_HIGH = 0.95;  // >= 95% → Hợp lệ
    const CONFIDENCE_MEDIUM = 0.8;  // >= 80% → Cảnh báo
    const CONFIDENCE_LOW = 0.6;  // < 80% → Cần thẩm định

    /**
     * So khớp dữ liệu OCR với form_data gốc
     *
     * @param array $ocrData Dữ liệu trích xuất từ Gemini Vision
     * @param array $formData Dữ liệu sinh viên nhập vào Day 2
     * @param string $ocrType Loại giấy tờ (cccd, ho_khau, etc.)
     * @return array Kết quả so khớp chi tiết
     *
     * Example:
     * {
     *   "success": true,
     *   "overall_match_rate": 0.98,
     *   "status": "APPROVED",
     *   "field_comparisons": [
     *     {"field": "ho_ten", "original": "Nguyễn A", "ocr": "Nguyễn Văn A", "match": 0.95, "note": "Tên gần giống"}
     *   ],
     *   "message": "...",
     *   "recommendation": "Tự động duyệt",
     *   "discrepancies": []
     * }
     */
    public function compareOcrWithForm(
        array $ocrData,
        array $formData,
        string $ocrType = self::OCR_TYPE_CCCD
    ): array {
        try {
            // 1. Normalize dữ liệu
            $ocrNormalized = $this->normalizeOcrData($ocrData, $ocrType);
            $formNormalized = $this->normalizeFormData($formData);

            // 2. So khớp từng trường chính
            $fieldComparisons = $this->compareFields($ocrNormalized, $formNormalized);

            // 3. Tính toán tỷ lệ trùng khớp tổng thể
            $overallMatch = $this->calculateOverallMatch($fieldComparisons);

            // 4. Phát hiện sai lệch
            $discrepancies = $this->identifyDiscrepancies($fieldComparisons);

            // 5. Xác định trạng thái hồ sơ
            $status = $this->determineStatus($overallMatch, $discrepancies);

            // 6. Tạo thông báo & khuyến nghị
            $recommendation = $this->generateRecommendation($status, $overallMatch, $discrepancies);

            Log::info('ComparisonService::compareOcrWithForm - Result', [
                'overall_match' => $overallMatch,
                'status' => $status,
                'discrepancy_count' => count($discrepancies)
            ]);

            return [
                'success' => true,
                'overall_match_rate' => $overallMatch,
                'status' => $status,
                'field_comparisons' => $fieldComparisons,
                'discrepancies' => $discrepancies,
                'message' => $this->getStatusMessage($status),
                'recommendation' => $recommendation,
                'timestamp' => now()
            ];
        } catch (Exception $e) {
            Log::error('ComparisonService::compareOcrWithForm - Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi so khớp dữ liệu: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'overall_match_rate' => 0
            ];
        }
    }

    /**
     * Normalize OCR data theo loại giấy tờ
     */
    private function normalizeOcrData(array $ocrData, string $ocrType): array
    {
        $normalized = [];

        switch ($ocrType) {
            case self::OCR_TYPE_CCCD:
                $normalized = [
                    'ho_ten' => $this->normalizeString($ocrData['ho_ten'] ?? ''),
                    'id_number' => $this->normalizeString($ocrData['id_number'] ?? ''),
                    'ngay_sinh' => $this->normalizeDate($ocrData['ngay_sinh'] ?? ''),
                ];
                break;

            case self::OCR_TYPE_HO_KHAU:
                $normalized = [
                    'chu_ho' => $this->normalizeString($ocrData['chu_ho'] ?? ''),
                    'so_ho_khau' => $this->normalizeString($ocrData['so_ho_khau'] ?? ''),
                    'dia_chi' => $this->normalizeString($ocrData['dia_chi'] ?? ''),
                ];
                break;

            case self::OCR_TYPE_HO_NGHEO:
                $normalized = [
                    'chu_ho' => $this->normalizeString($ocrData['chu_ho'] ?? ''),
                    'ma_ho_ngheo' => $this->normalizeString($ocrData['ma_ho_ngheo'] ?? ''),
                    'ngay_cap' => $this->normalizeDate($ocrData['ngay_cap'] ?? ''),
                ];
                break;

            default:
                $normalized = $ocrData;
        }

        $normalized['confidence'] = (float) ($ocrData['confidence'] ?? 0.8);
        return $normalized;
    }

    /**
     * Normalize form data (từ sinh viên nhập)
     */
    private function normalizeFormData(array $formData): array
    {
        return [
            'ho_ten' => $this->normalizeString($formData['ho_ten'] ?? ''),
            'ma_so_sv' => $this->normalizeString($formData['ma_so_sv'] ?? ''),
            'dien_thoai' => $this->normalizeString($formData['dien_thoai'] ?? ''),
            'ngay_sinh' => $this->normalizeDate($formData['ngay_sinh'] ?? ''),
        ];
    }

    /**
     * So khớp từng trường
     */
    private function compareFields(array $ocrData, array $formData): array
    {
        $comparisons = [];

        // So khớp Họ Tên
        $hoten_match = $this->calculateStringSimilarity(
            $ocrData['ho_ten'] ?? '',
            $formData['ho_ten'] ?? ''
        );
        $comparisons[] = [
            'field' => 'ho_ten',
            'original' => $formData['ho_ten'] ?? '',
            'ocr' => $ocrData['ho_ten'] ?? '',
            'match' => $hoten_match,
            'weight' => self::WEIGHT_HO_TEN,
            'note' => $hoten_match >= 0.9 ? 'Trùng khớp' : ($hoten_match >= 0.7 ? 'Gần giống' : 'Khác biệt')
        ];

        // So khớp ID/Mã số
        $id_match = $this->calculateStringSimilarity(
            $ocrData['id_number'] ?? $ocrData['so_ho_khau'] ?? $ocrData['ma_ho_ngheo'] ?? '',
            $formData['ma_so_sv'] ?? ''
        );
        $comparisons[] = [
            'field' => 'id',
            'original' => $formData['ma_so_sv'] ?? '',
            'ocr' => $ocrData['id_number'] ?? $ocrData['so_ho_khau'] ?? $ocrData['ma_ho_ngheo'] ?? '',
            'match' => $id_match,
            'weight' => self::WEIGHT_ID,
            'note' => $id_match >= 0.9 ? 'Trùng khớp' : ($id_match >= 0.7 ? 'Gần giống' : 'Khác biệt')
        ];

        // So khớp ngày sinh / ngày cấp (nếu có)
        if (!empty($ocrData['ngay_sinh']) || !empty($ocrData['ngay_cap'])) {
            $date_ocr = $ocrData['ngay_sinh'] ?? $ocrData['ngay_cap'] ?? '';
            $date_match = $this->calculateDateSimilarity($date_ocr, $formData['ngay_sinh'] ?? '');
            $comparisons[] = [
                'field' => 'date',
                'original' => $formData['ngay_sinh'] ?? '',
                'ocr' => $date_ocr,
                'match' => $date_match,
                'weight' => self::WEIGHT_OTHERS,
                'note' => $date_match >= 0.95 ? 'Trùng khớp' : ($date_match >= 0.7 ? 'Gần giống' : 'Khác biệt')
            ];
        }

        return $comparisons;
    }

    /**
     * Tính toán tỷ lệ trùng khớp tổng thể (weighted average)
     */
    private function calculateOverallMatch(array $comparisons): float
    {
        if (empty($comparisons)) {
            return 0.0;
        }

        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($comparisons as $comp) {
            $match = (float) ($comp['match'] ?? 0);
            $weight = (float) ($comp['weight'] ?? 0);
            $weightedSum += $match * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? ($weightedSum / $totalWeight) : 0.0;
    }

    /**
     * Phát hiện sai lệch
     */
    private function identifyDiscrepancies(array $comparisons): array
    {
        $discrepancies = [];

        foreach ($comparisons as $comp) {
            $match = (float) ($comp['match'] ?? 0);

            // Nếu độ trùng khớp < 80% → sai lệch
            if ($match < 0.8) {
                $discrepancies[] = [
                    'field' => $comp['field'],
                    'original' => $comp['original'],
                    'ocr' => $comp['ocr'],
                    'match_rate' => $match,
                    'severity' => $match >= 0.6 ? 'warning' : 'error',
                    'message' => $comp['note']
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Xác định trạng thái hồ sơ dựa trên độ trùng khớp
     */
    private function determineStatus(float $overallMatch, array $discrepancies): string
    {
        if ($overallMatch >= self::CONFIDENCE_HIGH) {
            return 'APPROVED';  // Hợp lệ - tự động duyệt
        } elseif ($overallMatch >= self::CONFIDENCE_MEDIUM && count($discrepancies) <= 1) {
            return 'WARNING';  // Cảnh báo - cần kiểm tra
        } else {
            return 'NEED_REVIEW';  // Cần thẩm định lại
        }
    }

    /**
     * Tạo khuyến nghị dựa trên kết quả so khớp
     */
    private function generateRecommendation(string $status, float $overallMatch, array $discrepancies): string
    {
        switch ($status) {
            case 'APPROVED':
                return "✅ Tự động duyệt hồ sơ - Dữ liệu trùng khớp hoàn toàn ({$overallMatch}%)";

            case 'WARNING':
                $details = implode(', ', array_map(fn($d) => $d['field'], $discrepancies));
                return "⚠️ Xét duyệt có điều kiện - Kiểm tra lại các trường: {$details}";

            case 'NEED_REVIEW':
                return "❌ Cần thẩm định lại - Độ trùng khớp thấp ({$overallMatch}%), có sai lệch đáng kể";

            default:
                return 'Xin vui lòng xét duyệt hồ sơ';
        }
    }

    /**
     * Thông báo trạng thái
     */
    private function getStatusMessage(string $status): string
    {
        $messages = [
            'APPROVED' => 'Hồ sơ hợp lệ - thông tin trùng khớp',
            'WARNING' => 'Cảnh báo - phát hiện một số sai lệch nhỏ',
            'NEED_REVIEW' => 'Cần thẩm định lại - phát hiện sai lệch đáng kể'
        ];

        return $messages[$status] ?? 'Trạng thái không xác định';
    }

    /** ==================== PRIVATE HELPER METHODS ==================== */

    /**
     * Normalize string: trim, lowercase, remove accents
     */
    private function normalizeString(string $str): string
    {
        // Trim
        $str = trim($str);

        // Lowercase
        $str = strtolower($str);

        // Remove extra spaces
        $str = preg_replace('/\s+/', ' ', $str);

        // Remove common accents (simplified - chỉ xử lý tiếng Việt phổ biến)
        $from = [
            'á', 'à', 'ả', 'ã', 'ạ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ',
            'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ',
            'é', 'è', 'ẻ', 'ẽ', 'ẹ',
            'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ',
            'í', 'ì', 'ỉ', 'ĩ', 'ị',
            'ó', 'ò', 'ỏ', 'õ', 'ọ',
            'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ',
            'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ',
            'ú', 'ù', 'ủ', 'ũ', 'ụ',
            'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự',
            'ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ',
            'đ'
        ];
        $to = [
            'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
            'a', 'a', 'a', 'a', 'a', 'a',
            'e', 'e', 'e', 'e', 'e',
            'e', 'e', 'e', 'e', 'e', 'e',
            'i', 'i', 'i', 'i', 'i',
            'o', 'o', 'o', 'o', 'o',
            'o', 'o', 'o', 'o', 'o', 'o',
            'o', 'o', 'o', 'o', 'o', 'o',
            'u', 'u', 'u', 'u', 'u',
            'u', 'u', 'u', 'u', 'u', 'u',
            'y', 'y', 'y', 'y', 'y',
            'd'
        ];
        $str = str_replace($from, $to, $str);

        return $str;
    }

    /**
     * Normalize date format (DD/MM/YYYY)
     */
    private function normalizeDate(string $dateStr): string
    {
        if (empty($dateStr)) {
            return '';
        }

        // Try to parse various date formats
        $patterns = [
            '/(\d{2})\/(\d{2})\/(\d{4})/' => '$1/$2/$3',  // DD/MM/YYYY
            '/(\d{4})-(\d{2})-(\d{2})/' => '$3/$2/$1',  // YYYY-MM-DD
            '/(\d{2})-(\d{2})-(\d{4})/' => '$1/$2/$3',  // DD-MM-YYYY
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $dateStr, $matches)) {
                return preg_replace($pattern, $replacement, $dateStr);
            }
        }

        return $dateStr;
    }

    /**
     * Tính độ tương tự giữa hai chuỗi (0-1)
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        $str1 = $this->normalizeString($str1);
        $str2 = $this->normalizeString($str2);

        if (empty($str1) || empty($str2)) {
            return $str1 === $str2 ? 1.0 : 0.0;
        }

        // Exact match
        if ($str1 === $str2) {
            return 1.0;
        }

        // Levenshtein distance
        $len = max(strlen($str1), strlen($str2));
        if ($len === 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);
        $similarity = 1 - ($distance / $len);

        return max(0.0, $similarity);
    }

    /**
     * Tính độ tương tự ngày tháng (0-1)
     */
    private function calculateDateSimilarity(string $date1, string $date2): float
    {
        $date1 = $this->normalizeDate($date1);
        $date2 = $this->normalizeDate($date2);

        // Exact match
        if ($date1 === $date2) {
            return 1.0;
        }

        // Parse and compare components
        preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $date1, $m1);
        preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $date2, $m2);

        if (empty($m1) || empty($m2)) {
            return $this->calculateStringSimilarity($date1, $date2);
        }

        $year1 = $m1[3] ?? '';
        $year2 = $m2[3] ?? '';

        // Year match is most important
        if ($year1 === $year2) {
            $month_match = ($m1[2] ?? '') === ($m2[2] ?? '') ? 1.0 : 0.5;
            $day_match = ($m1[1] ?? '') === ($m2[1] ?? '') ? 1.0 : 0.8;
            return ($month_match + $day_match) / 2;
        }

        return 0.5;
    }
}
