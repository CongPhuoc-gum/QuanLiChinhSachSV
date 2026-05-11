<?php

namespace Database\Seeders;

use App\Models\VaiTro;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VaiTroSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the VAI_TRO table with 5 roles
     */
    public function run(): void
    {
        $vaiTros = [
            [
                'MaVaiTro' => 1,
                'TenVaiTro' => 'Sinh viên',
                'MoTa' => 'Sinh viên nộp đơn xin chính sách',
            ],
            [
                'MaVaiTro' => 2,
                'TenVaiTro' => 'Cán bộ CTSV',
                'MoTa' => 'Cán bộ công tác sinh viên - thẩm định hồ sơ',
            ],
            [
                'MaVaiTro' => 3,
                'TenVaiTro' => 'Trưởng phòng CTSV',
                'MoTa' => 'Trưởng phòng công tác sinh viên - ký duyệt quyết định',
            ],
            [
                'MaVaiTro' => 4,
                'TenVaiTro' => 'Ban Giám hiệu',
                'MoTa' => 'Ban Giám hiệu - phê duyệt cuối cùng',
            ],
            [
                'MaVaiTro' => 5,
                'TenVaiTro' => 'Cán bộ Tài vụ',
                'MoTa' => 'Cán bộ tài vụ - xử lý chi trả',
            ],
        ];

        foreach ($vaiTros as $vaiTro) {
            // Sử dụng updateOrCreate để tránh lỗi Duplicate entry
            // Tham số 1: Điều kiện tìm kiếm (khóa chính)
            // Tham số 2: Dữ liệu cần cập nhật hoặc tạo mới
            VaiTro::updateOrCreate(
                ['MaVaiTro' => $vaiTro['MaVaiTro']], 
                $vaiTro
            );
        }
    }
}