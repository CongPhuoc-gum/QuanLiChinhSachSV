<?php

namespace App\Services;

use App\Models\HoSo;
use App\Models\PhanTichAIHoSo;
use Illuminate\Support\Facades\Log;

/**
 * HoSoAnalysisService - Phân tích hồ sơ để tính mức giảm học phí
 *
 * Dựa vào Nghị định 81/2021, tự động:
 * 1. Phân tích thông tin sinh viên gửi
 * 2. Kiểm tra minh chứng
 * 3. Tính toán mức giảm học phí chuẩn
 * 4. Lưu kết quả phân tích
 */
class HoSoAnalysisService
{
    private GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Phân tích hồ sơ toàn diện
     *
     * POST /api/ho-so/{id}/analyze-for-reduction
     *
     * @param int $maHoSo
     * @param bool $processAll
     * @return array
     */
    public function analyzeHoSoForReduction(int $maHoSo, bool $processAll = false): array
    {
        try {
            Log::info('Starting HoSo analysis', ['ma_ho_so' => $maHoSo]);

            // 1. Get hồ sơ + form data
            $hoSo = HoSo::with(['loaiChinhSach', 'minhChungFiles'])->findOrFail($maHoSo);

            if (!$hoSo->du_lieu_form) {
                return [
                    'success' => false,
                    'message' => 'Hồ sơ không có dữ liệu biểu mẫu',
                ];
            }

            // 2. Parse form data
            $formData = is_array($hoSo->du_lieu_form)
                ? $hoSo->du_lieu_form
                : json_decode($hoSo->du_lieu_form, true);

            // 3. Determine policy type
            $policyType = $hoSo->MaLoaiCS === 1 ? 'MGHP' : 'TCXH';

            // 4. Analyze based on policy
            $analysis = match ($policyType) {
                'MGHP' => $this->analyzeMGHP($formData, $hoSo),
                'TCXH' => $this->analyzeTCXH($formData, $hoSo),
                default => ['success' => false, 'message' => 'Loại chính sách không hợp lệ'],
            };

            if (!$analysis['success']) {
                return $analysis;
            }

            // 5. Save analysis result
            $this->saveAnalysisResult($maHoSo, $analysis);

            return $analysis;
        } catch (\Exception $e) {
            Log::error('HoSoAnalysisService::analyzeHoSoForReduction - Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi phân tích hồ sơ',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * PHÂN TÍCH MGHP (Miễn Giảm Học Phí)
     *
     * Dựa vào Nghị định 81/2021:
     * - Điều 3: Con hộ nghèo → miễn 100%
     * - Điều 3: Cận nghèo → miễn 50%
     * - Điều 3: Hộ chính sách → miễn 50%
     * - Điều 4: Con thương binh → miễn 50%
     * - Điều 5: Con liệt sĩ → miễn 100%
     *
     * @param array $formData
     * @param HoSo $hoSo
     * @return array
     */
    private function analyzeMGHP(array $formData, HoSo $hoSo): array
    {
        try {
            $doiTuong = $formData['doi_tuong'] ?? null;

            if (!$doiTuong) {
                return [
                    'success' => false,
                    'message' => 'Đối tượng chính sách không rõ',
                ];
            }

            // Determine reduction percentage
            $reductionPercent = match ($doiTuong) {
                'ho_ngheo' => 100,  // Điều 3
                'ho_can_ngheo' => 50,  // Điều 3
                'ho_chinh_sach' => 50,  // Điều 3
                'con_liet_si' => 100,  // Điều 5
                'con_thuong_binh' => 50,  // Điều 4
                default => 0,
            };

            if ($reductionPercent === 0) {
                return [
                    'success' => false,
                    'message' => 'Đối tượng không được hưởng miễn/giảm học phí',
                ];
            }

            // Evidence checklist
            $evidenceStatus = $this->checkEvidenceMGHP($hoSo, $doiTuong);

            // Build analysis result
            $analysis = [
                'success' => true,
                'hoc_phan_id' => $hoSo->MaHoSo,
                'policy_type' => 'MGHP',
                'doi_tuong' => $doiTuong,
                'reduction_percent' => $reductionPercent,
                'reduction_text' => "Miễn {$reductionPercent}% học phí",
                'basis' => $this->getBasisForReduction($doiTuong),
                'evidence_status' => $evidenceStatus,
                'recommendation' => $this->getRecommendation($reductionPercent, $evidenceStatus),
                'status' => $this->determineStatus($reductionPercent, $evidenceStatus),
                'analyzed_at' => now()->toIso8601String(),
            ];

            Log::info('MGHP Analysis completed', [
                'ma_ho_so' => $hoSo->MaHoSo,
                'reduction' => "{$reductionPercent}%",
                'doi_tuong' => $doiTuong,
            ]);

            return $analysis;
        } catch (\Exception $e) {
            Log::error('HoSoAnalysisService::analyzeMGHP - Error', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi phân tích MGHP',
            ];
        }
    }

    /**
     * PHÂN TÍCH TCXH (Trợ Cấp Xã Hội)
     *
     * Dựa vào Nghị định 81/2021:
     * - Điều 5: Con liệt sĩ → trợ cấp + miễn 100%
     * - Điều 4: Con thương binh → trợ cấp + miễn 50%
     * - Điều 6: Thanh niên xung phong → trợ cấp
     *
     * @param array $formData
     * @param HoSo $hoSo
     * @return array
     */
    private function analyzeTCXH(array $formData, HoSo $hoSo): array
    {
        try {
            $loaiDoiTuong = $formData['loai_doi_tuong'] ?? null;
            $soTaiKhoan = $formData['so_tai_khoan_ngan_hang'] ?? null;
            $tenChuTaiKhoan = $formData['ten_chu_tai_khoan'] ?? null;

            if (!$loaiDoiTuong) {
                return [
                    'success' => false,
                    'message' => 'Loại đối tượng chính sách không rõ',
                ];
            }

            // Validate bank account
            if (empty($soTaiKhoan) || empty($tenChuTaiKhoan)) {
                return [
                    'success' => false,
                    'message' => 'Thiếu thông tin tài khoản ngân hàng',
                    'missing_fields' => ['so_tai_khoan_ngan_hang', 'ten_chu_tai_khoan'],
                ];
            }

            // Determine benefits
            $benefits = match ($loaiDoiTuong) {
                'con_liet_si' => [
                    'reduction_percent' => 100,
                    'subsidy_amount' => 2000000,  // 2 triệu/tháng
                    'basis' => 'Điều 5, Nghị định 81/2021',
                ],
                'con_thuong_binh' => [
                    'reduction_percent' => 50,
                    'subsidy_amount' => 1500000,  // 1.5 triệu/tháng
                    'basis' => 'Điều 4, Nghị định 81/2021',
                ],
                'con_can_bo_hy_sinh' => [
                    'reduction_percent' => 50,
                    'subsidy_amount' => 1500000,
                    'basis' => 'Điều 6, Nghị định 81/2021',
                ],
                'con_thanh_nien_xung_phong' => [
                    'reduction_percent' => 25,
                    'subsidy_amount' => 1000000,
                    'basis' => 'Điều 6, Nghị định 81/2021',
                ],
                default => null,
            };

            if (!$benefits) {
                return [
                    'success' => false,
                    'message' => 'Loại đối tượng không được hưởng trợ cấp',
                ];
            }

            // Evidence checklist
            $evidenceStatus = $this->checkEvidenceTCXH($hoSo, $loaiDoiTuong);

            // Build analysis result
            $analysis = [
                'success' => true,
                'hoc_phan_id' => $hoSo->MaHoSo,
                'policy_type' => 'TCXH',
                'loai_doi_tuong' => $loaiDoiTuong,
                'reduction_percent' => $benefits['reduction_percent'],
                'reduction_text' => "Miễn {$benefits['reduction_percent']}% học phí",
                'subsidy_amount' => $benefits['subsidy_amount'],
                'subsidy_text' => 'Trợ cấp: ' . number_format($benefits['subsidy_amount']) . ' VNĐ/tháng',
                'bank_account' => [
                    'so_tai_khoan' => substr_replace($soTaiKhoan, '****', 4, -2),  // Mask
                    'ten_chu_tai_khoan' => $tenChuTaiKhoan,
                ],
                'basis' => $benefits['basis'],
                'evidence_status' => $evidenceStatus,
                'recommendation' => $this->getRecommendationTCXH($benefits, $evidenceStatus),
                'status' => $this->determineStatus($benefits['reduction_percent'], $evidenceStatus),
                'analyzed_at' => now()->toIso8601String(),
            ];

            Log::info('TCXH Analysis completed', [
                'ma_ho_so' => $hoSo->MaHoSo,
                'reduction' => "{$benefits['reduction_percent']}%",
                'subsidy' => $benefits['subsidy_amount'],
            ]);

            return $analysis;
        } catch (\Exception $e) {
            Log::error('HoSoAnalysisService::analyzeTCXH - Error', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi phân tích TCXH',
            ];
        }
    }

    /**
     * Check minh chứng MGHP
     *
     * @param HoSo $hoSo
     * @param string $doiTuong
     * @return array
     */
    private function checkEvidenceMGHP(HoSo $hoSo, string $doiTuong): array
    {
        $minhChungs = $hoSo->minhChungFiles ?? [];
        $fileNames = array_map(fn($m) => strtolower($m->TenFile), $minhChungs);

        $required = [
            'cccd' => ['cccd', 'căn cước', 'khai sinh'],
            'ho_ngheo' => ['hộ nghèo', 'giấy chứng nhận'],
        ];

        $optional = ['thương binh', 'chính sách'];

        $checks = [
            'has_cccd' => $this->hasFileMatching($fileNames, $required['cccd']),
            'has_ho_ngheo_doc' => $this->hasFileMatching($fileNames, $required['ho_ngheo']),
            'has_supporting_doc' => $this->hasFileMatching($fileNames, $optional),
            'total_files' => count($minhChungs),
        ];

        return [
            'files_provided' => $checks,
            'complete' => $checks['has_cccd'] && $checks['has_ho_ngheo_doc'],
            'completeness_percent' => $this->calculateCompleteness($checks),
        ];
    }

    /**
     * Check minh chứng TCXH
     *
     * @param HoSo $hoSo
     * @param string $loaiDoiTuong
     * @return array
     */
    private function checkEvidenceTCXH(HoSo $hoSo, string $loaiDoiTuong): array
    {
        $minhChungs = $hoSo->minhChungFiles ?? [];
        $fileNames = array_map(fn($m) => strtolower($m->TenFile), $minhChungs);

        $required = [
            'cccd' => ['cccd', 'căn cước', 'khai sinh'],
            'liet_si_thang_binh' => ['liệt sĩ', 'thương binh', 'giấy chứng nhận'],
            'ngan_hang' => ['sổ tiết kiệm', 'tài khoản', 'ngân hàng'],
        ];

        $checks = [
            'has_cccd' => $this->hasFileMatching($fileNames, $required['cccd']),
            'has_liet_si_doc' => $this->hasFileMatching($fileNames, $required['liet_si_thang_binh']),
            'has_bank_doc' => $this->hasFileMatching($fileNames, $required['ngan_hang']),
            'total_files' => count($minhChungs),
        ];

        return [
            'files_provided' => $checks,
            'complete' => $checks['has_cccd'] && $checks['has_liet_si_doc'] && $checks['has_bank_doc'],
            'completeness_percent' => $this->calculateCompleteness($checks),
        ];
    }

    /**
     * Helper: Check if file matching keywords exists
     *
     * @param array $fileNames
     * @param array $keywords
     * @return bool
     */
    private function hasFileMatching(array $fileNames, array $keywords): bool
    {
        foreach ($fileNames as $fileName) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($fileName, $keyword) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Calculate evidence completeness percentage
     *
     * @param array $checks
     * @return int
     */
    private function calculateCompleteness(array $checks): int
    {
        $total = 0;
        $completed = 0;

        foreach ($checks as $key => $value) {
            if (strpos($key, 'has_') === 0) {
                $total++;
                if ($value)
                    $completed++;
            }
        }

        return $total > 0 ? (int) (($completed / $total) * 100) : 0;
    }

    /**
     * Get basis (Điều/Khoản) cho reduction
     *
     * @param string $doiTuong
     * @return string
     */
    private function getBasisForReduction(string $doiTuong): string
    {
        return match ($doiTuong) {
            'ho_ngheo' => 'Điều 3, Nghị định 81/2021 - Con hộ nghèo',
            'ho_can_ngheo' => 'Điều 3, Nghị định 81/2021 - Hộ cận nghèo',
            'ho_chinh_sach' => 'Điều 3, Nghị định 81/2021 - Hộ chính sách',
            'con_liet_si' => 'Điều 5, Nghị định 81/2021 - Con liệt sĩ',
            'con_thuong_binh' => 'Điều 4, Nghị định 81/2021 - Con thương binh',
            default => 'Nghị định 81/2021',
        };
    }

    /**
     * Get recommendation
     *
     * @param int $reductionPercent
     * @param array $evidenceStatus
     * @return string
     */
    private function getRecommendation(int $reductionPercent, array $evidenceStatus): string
    {
        $completeness = $evidenceStatus['completeness_percent'] ?? 0;

        if ($completeness === 100) {
            return "✅ Hồ sơ đầy đủ. Khuyến cáo phê duyệt miễn {$reductionPercent}% học phí.";
        } elseif ($completeness >= 80) {
            return "⚠️ Hồ sơ gần đầy đủ ({$completeness}%). Yêu cầu bổ sung một số minh chứng.";
        } else {
            return "❌ Hồ sơ không đầy đủ ({$completeness}%). Yêu cầu bổ sung minh chứng trước khi xét duyệt.";
        }
    }

    /**
     * Get recommendation for TCXH
     *
     * @param array $benefits
     * @param array $evidenceStatus
     * @return string
     */
    private function getRecommendationTCXH(array $benefits, array $evidenceStatus): string
    {
        $completeness = $evidenceStatus['completeness_percent'] ?? 0;
        $reduction = $benefits['reduction_percent'];
        $subsidy = number_format($benefits['subsidy_amount']);

        if ($completeness === 100) {
            return "✅ Hồ sơ đầy đủ. Khuyến cáo phê duyệt: Miễn {$reduction}% học phí + Trợ cấp {$subsidy} VNĐ/tháng.";
        } elseif ($completeness >= 80) {
            return "⚠️ Hồ sơ gần đầy đủ ({$completeness}%). Yêu cầu bổ sung minh chứng tài khoản ngân hàng.";
        } else {
            return "❌ Hồ sơ không đầy đủ ({$completeness}%). Yêu cầu bổ sung tất cả minh chứng bắt buộc.";
        }
    }

    /**
     * Determine overall status (APPROVED, PENDING, REJECTED)
     *
     * @param int $reductionPercent
     * @param array $evidenceStatus
     * @return string
     */
    private function determineStatus(int $reductionPercent, array $evidenceStatus): string
    {
        $completeness = $evidenceStatus['completeness_percent'] ?? 0;

        if ($completeness === 100) {
            return 'APPROVED';  // Tự động duyệt nếu đầy đủ
        } elseif ($completeness >= 80) {
            return 'PENDING_REVIEW';  // Cần cán bộ xem xét
        } else {
            return 'NEED_SUPPLEMENT';  // Yêu cầu bổ sung
        }
    }

    /**
     * Save analysis result to database
     *
     * @param int $maHoSo
     * @param array $analysis
     * @return void
     */
    private function saveAnalysisResult(int $maHoSo, array $analysis): void
    {
        try {
            PhanTichAIHoSo::updateOrCreate(
                ['MaHoSo' => $maHoSo],
                [
                    'LoaiTaiLieuOCR' => 'hoso_analysis',
                    'KetQuaDoiChieu' => $analysis,
                    'TyLeKhop' => $analysis['evidence_status']['completeness_percent'] ?? 0,
                    'DoTinCayOCR' => 0.95,
                    'TrangThaiXuLy' => $analysis['status'],
                    'GhiChuAdmin' => $analysis['recommendation'],
                    'ThoiGianPhanTich' => now(),
                ]
            );

            Log::info('Analysis result saved', ['ma_ho_so' => $maHoSo]);
        } catch (\Exception $e) {
            Log::error('HoSoAnalysisService::saveAnalysisResult - Error', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
