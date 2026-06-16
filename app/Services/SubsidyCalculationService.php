<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * SubsidyCalculationService - Tính toán trợ cấp xã hội TCXH
 *
 * Sinh viên hỏi: "Con sẽ được tính bao nhiêu tiền trợ cấp mỗi tháng?"
 * AI trả lời dựa trên công thức tính theo Nghị định 81/2021
 *
 * TCXH có 2 phần:
 * 1. Miễn học phí: 25%, 50%, 100%
 * 2. Trợ cấp tiền mặt: hàng tháng
 */
class SubsidyCalculationService
{
    /**
     * TÍNH TOÁN TRỢ CẤP XÃ HỘI
     *
     * POST /api/chatbot/calculate-subsidy
     * Body: {
     *   "loai_doi_tuong": "con_liet_si",  // con_liet_si, con_thuong_binh, con_hy_sinh, etc
     *   "thuong_binh_loai": 1,             // Nếu thương binh: loại 1-4 (optional)
     *   "so_thang_hoc": 4                  // Số tháng học trong kỳ
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "loai_doi_tuong": "con_liet_si",
     *     "tien_huong_hang_thang": 2000000,
     *     "so_thang_hoc": 4,
     *     "tong_tien_huong": 8000000,
     *     "muc_mien_hoc_phi": 100,
     *     "tien_hoc_phi_mien": "Toàn bộ học phí",
     *     "basis": "Điều 5, Nghị định 81/2021 - Con liệt sĩ",
     *     "ghi_chu": "Con liệt sĩ được miễn 100% học phí và nhận trợ cấp 2.000.000 VND/tháng..."
     *   }
     * }
     *
     * @param array $data
     * @return array
     */
    public function calculateSubsidy(array $data): array
    {
        try {
            $loaiDoiTuong = $data['loai_doi_tuong'] ?? null;
            $thuongBinhLoai = $data['thuong_binh_loai'] ?? null;
            $soThangHoc = $data['so_thang_hoc'] ?? 4;

            if (!$loaiDoiTuong) {
                return [
                    'success' => false,
                    'message' => 'Loại đối tượng không được chỉ định',
                ];
            }

            // Get subsidy rules
            $subsidy = $this->getSubsidyRules($loaiDoiTuong, $thuongBinhLoai);

            if (!$subsidy) {
                return [
                    'success' => false,
                    'message' => 'Loại đối tượng không hợp lệ hoặc không được hưởng trợ cấp',
                ];
            }

            // Calculate total
            $tongTienHuong = $subsidy['tien_hang_thang'] * $soThangHoc;

            return [
                'success' => true,
                'data' => [
                    'loai_doi_tuong' => $loaiDoiTuong,
                    'tien_huong_hang_thang' => $subsidy['tien_hang_thang'],
                    'so_thang_hoc' => $soThangHoc,
                    'tong_tien_huong' => $tongTienHuong,
                    'muc_mien_hoc_phi' => $subsidy['muc_mien'],
                    'tien_hoc_phi_mien' => $this->formatMienDescription($subsidy['muc_mien']),
                    'basis' => $subsidy['basis'],
                    'ghi_chu' => $this->buildSubsidyExplanation($loaiDoiTuong, $subsidy, $tongTienHuong),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('SubsidyCalculationService::calculateSubsidy - Error', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Lỗi tính toán trợ cấp',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * LẤY QUY TẮC TRỢ CẤP
     *
     * Dựa trên: Nghị định 81/2021
     * Điều 5: Con liệt sĩ, con thương binh
     * Điều 6: Con cán bộ hy sinh, thanh niên xung phong
     *
     * @param string $loaiDoiTuong
     * @param int|null $loai
     * @return array|null
     */
    private function getSubsidyRules(string $loaiDoiTuong, ?int $loai = null): ?array
    {
        $rules = [
            // ĐIỀU 5: Con liệt sĩ
            'con_liet_si' => [
                'tien_hang_thang' => 2000000,  // 2 triệu/tháng
                'muc_mien' => 100,  // Miễn 100%
                'basis' => 'Điều 5, Nghị định 81/2021 - Con liệt sĩ',
                'description' => 'Con liệt sĩ được hưởng: Miễn 100% học phí + Trợ cấp 2.000.000 VND/tháng',
            ],
            // ĐIỀU 4-5: Con thương binh (tùy theo loại)
            'con_thuong_binh_loai_1' => [
                'tien_hang_thang' => 2000000,  // 2 triệu/tháng (tương đương liệt sĩ)
                'muc_mien' => 100,  // Miễn 100%
                'basis' => 'Điều 4, Nghị định 81/2021 - Con thương binh cấp 1',
                'description' => 'Con thương binh cấp 1 được hưởng: Miễn 100% học phí + Trợ cấp 2.000.000 VND/tháng',
            ],
            'con_thuong_binh_loai_2' => [
                'tien_hang_thang' => 1500000,  // 1.5 triệu/tháng
                'muc_mien' => 100,  // Miễn 100%
                'basis' => 'Điều 4, Nghị định 81/2021 - Con thương binh cấp 2',
                'description' => 'Con thương binh cấp 2 được hưởng: Miễn 100% học phí + Trợ cấp 1.500.000 VND/tháng',
            ],
            'con_thuong_binh_loai_3' => [
                'tien_hang_thang' => 1200000,  // 1.2 triệu/tháng
                'muc_mien' => 70,  // Giảm 70%
                'basis' => 'Điều 4, Nghị định 81/2021 - Con thương binh cấp 3',
                'description' => 'Con thương binh cấp 3 được hưởng: Giảm 70% học phí + Trợ cấp 1.200.000 VND/tháng',
            ],
            'con_thuong_binh_loai_4' => [
                'tien_hang_thang' => 1000000,  // 1 triệu/tháng
                'muc_mien' => 50,  // Giảm 50%
                'basis' => 'Điều 4, Nghị định 81/2021 - Con thương binh cấp 4',
                'description' => 'Con thương binh cấp 4 được hưởng: Giảm 50% học phí + Trợ cấp 1.000.000 VND/tháng',
            ],
            // ĐIỀU 6: Con cán bộ hy sinh
            'con_can_bo_hy_sinh' => [
                'tien_hang_thang' => 1500000,
                'muc_mien' => 50,
                'basis' => 'Điều 6, Nghị định 81/2021 - Con cán bộ hy sinh',
                'description' => 'Con cán bộ hy sinh được hưởng: Giảm 50% học phí + Trợ cấp 1.500.000 VND/tháng',
            ],
            // ĐIỀU 6: Thanh niên xung phong
            'thanh_nien_xung_phong' => [
                'tien_hang_thang' => 1000000,
                'muc_mien' => 25,
                'basis' => 'Điều 6, Nghị định 81/2021 - Thanh niên xung phong',
                'description' => 'Thanh niên xung phong được hưởng: Giảm 25% học phí + Trợ cấp 1.000.000 VND/tháng',
            ],
            // ĐIỀU 6: Con người tàn tật
            'con_nguoi_tan_tat' => [
                'tien_hang_thang' => 1200000,
                'muc_mien' => 50,
                'basis' => 'Điều 6, Nghị định 81/2021 - Con người tàn tật',
                'description' => 'Con người tàn tật được hưởng: Giảm 50% học phí + Trợ cấp 1.200.000 VND/tháng',
            ],
        ];

        // Nếu thương binh + có loại
        if (str_contains($loaiDoiTuong, 'thuong_binh') && $loai) {
            $key = "con_thuong_binh_loai_{$loai}";
            return $rules[$key] ?? null;
        }

        return $rules[$loaiDoiTuong] ?? null;
    }

    /**
     * FORMAT MIỄN DESCRIPTION
     *
     * @param int $mucMien (0-100)
     * @return string
     */
    private function formatMienDescription(int $mucMien): string
    {
        return match ($mucMien) {
            100 => 'Miễn 100% học phí',
            70 => 'Giảm 70% học phí (đóng 30%)',
            50 => 'Giảm 50% học phí (đóng 50%)',
            25 => 'Giảm 25% học phí (đóng 75%)',
            default => "Giảm {$mucMien}% học phí (đóng " . (100 - $mucMien) . '%)',
        };
    }

    /**
     * BUILD SUBSIDY EXPLANATION (Chi tiết giải thích)
     *
     * @param string $loaiDoiTuong
     * @param array $subsidy
     * @param int $tongTienHuong
     * @return string
     */
    private function buildSubsidyExplanation(
        string $loaiDoiTuong,
        array $subsidy,
        int $tongTienHuong
    ): string {
        $tienHangThang = number_format($subsidy['tien_hang_thang'], 0, ',', '.');
        $tongTien = number_format($tongTienHuong, 0, ',', '.');
        $mucMien = $subsidy['muc_mien'];

        $mienDescription = match ($mucMien) {
            100 => 'miễn 100% học phí',
            70 => 'giảm 70% học phí (chỉ đóng 30%)',
            50 => 'giảm 50% học phí (chỉ đóng 50%)',
            default => "giảm {$mucMien}% học phí",
        };

        return "Bạn được hưởng trợ cấp xã hội {$tienHangThang} VND/tháng "
            . "({$tongTien} VND/kỳ 4 tháng) và {$mienDescription}. "
            . $subsidy['basis'] . '. '
            . 'Trợ cấp sẽ được chi qua tài khoản ngân hàng hàng tháng.';
    }

    /**
     * FORMAT TIỀN VIỆT (Thêm ký hiệu đơn vị)
     *
     * @param int $amount
     * @return string
     */
    public function formatMoney(int $amount): string
    {
        if ($amount >= 1000000) {
            $millions = $amount / 1000000;
            return number_format($millions, 1, ',', '.') . ' triệu VND';
        }
        return number_format($amount, 0, ',', '.') . ' VND';
    }
}
