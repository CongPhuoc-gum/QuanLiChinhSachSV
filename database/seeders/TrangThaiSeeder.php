<?php

namespace Database\Seeders;

use App\Models\TrangThai;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TrangThaiSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the TRANG_THAI table with 9 statuses
     */
    public function run(): void
    {
        $trangThais = [
            [
                'MaTrangThai' => 1,
                'TenTrangThai' => 'Chờ nộp hồ sơ',
                'MoTa' => 'Hồ sơ chưa được nộp',
            ],
            [
                'MaTrangThai' => 2,
                'TenTrangThai' => 'Chờ thẩm định',
                'MoTa' => 'Hồ sơ đã nộp, chờ cán bộ CTSV thẩm định',
            ],
            [
                'MaTrangThai' => 3,
                'TenTrangThai' => 'Đang bổ sung',
                'MoTa' => 'Hồ sơ cần bổ sung thêm minh chứng',
            ],
            [
                'MaTrangThai' => 4,
                'TenTrangThai' => 'Chờ Trưởng phòng duyệt',
                'MoTa' => 'Hồ sơ đã thẩm định, chờ Trưởng phòng CTSV ký duyệt',
            ],
            [
                'MaTrangThai' => 5,
                'TenTrangThai' => 'Chờ Ban Giám hiệu duyệt',
                'MoTa' => 'Hồ sơ đã được Trưởng phòng ký, chờ Ban Giám hiệu phê duyệt',
            ],
            [
                'MaTrangThai' => 6,
                'TenTrangThai' => 'Đã duyệt',
                'MoTa' => 'Hồ sơ đã được phê duyệt, chờ chi trả',
            ],
            [
                'MaTrangThai' => 7,
                'TenTrangThai' => 'Đã chi trả',
                'MoTa' => 'Hồ sơ đã được chi trả thành công',
            ],
            [
                'MaTrangThai' => 8,
                'TenTrangThai' => 'Từ chối',
                'MoTa' => 'Hồ sơ bị từ chối',
            ],
            [
                'MaTrangThai' => 9,
                'TenTrangThai' => 'Đã hủy',
                'MoTa' => 'Hồ sơ đã bị hủy',
            ],
        ];

        foreach ($trangThais as $trangThai) {
            TrangThai::updateOrCreate(
                ['MaTrangThai' => $trangThai['MaTrangThai']],
                $trangThai
            );
        }
    }
}
