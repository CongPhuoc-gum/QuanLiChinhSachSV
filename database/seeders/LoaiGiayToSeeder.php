<?php

namespace Database\Seeders;

use App\Models\LoaiGiayTo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LoaiGiayToSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the LOAI_GIAY_TO table with document types
     */
    public function run(): void
    {
        $loaiGiayTos = [
            [
                'MaLoaiGiayTo' => 1,
                'TenLoaiGiayTo' => 'Hộ nghèo',
                'MoTa' => 'Giấy chứng nhận hộ nghèo từ chính quyền địa phương',
                'BatBuoc' => true,
            ],
            [
                'MaLoaiGiayTo' => 2,
                'TenLoaiGiayTo' => 'Khai sinh',
                'MoTa' => 'Giấy khai sinh hoặc chứng thực khai sinh',
                'BatBuoc' => false,
            ],
            [
                'MaLoaiGiayTo' => 3,
                'TenLoaiGiayTo' => 'CCCD/CMND',
                'MoTa' => 'Căn cước công dân hoặc chứng minh nhân dân',
                'BatBuoc' => true,
            ],
            [
                'MaLoaiGiayTo' => 4,
                'TenLoaiGiayTo' => 'Sổ hộ khẩu',
                'MoTa' => 'Bản sao sổ hộ khẩu',
                'BatBuoc' => false,
            ],
            [
                'MaLoaiGiayTo' => 5,
                'TenLoaiGiayTo' => 'Giấy xác nhận hoàn cảnh',
                'MoTa' => 'Giấy xác nhận hoàn cảnh khó khăn từ chính quyền địa phương',
                'BatBuoc' => true,
            ],
            [
                'MaLoaiGiayTo' => 6,
                'TenLoaiGiayTo' => 'Giấy xác nhận đối tượng chính sách',
                'MoTa' => 'Giấy xác nhận đối tượng chính sách từ chính quyền địa phương',
                'BatBuoc' => false,
            ],
            [
                'MaLoaiGiayTo' => 7,
                'TenLoaiGiayTo' => 'Giấy xác nhận tình trạng hôn nhân',
                'MoTa' => 'Giấy xác nhận tình trạng hôn nhân của bố mẹ',
                'BatBuoc' => false,
            ],
            [
                'MaLoaiGiayTo' => 8,
                'TenLoaiGiayTo' => 'Giấy xác nhận người phụ thuộc',
                'MoTa' => 'Giấy xác nhận người phụ thuộc từ chính quyền địa phương',
                'BatBuoc' => false,
            ],
            [
                'MaLoaiGiayTo' => 9,
                'TenLoaiGiayTo' => 'Bằng cấp/Chứng chỉ',
                'MoTa' => 'Bằng cấp hoặc chứng chỉ liên quan đến chính sách',
                'BatBuoc' => false,
            ],
            [
                'MaLoaiGiayTo' => 10,
                'TenLoaiGiayTo' => 'Giấy xác nhận thu nhập',
                'MoTa' => 'Giấy xác nhận thu nhập của gia đình',
                'BatBuoc' => false,
            ],
        ];

        foreach ($loaiGiayTos as $loaiGiayTo) {
            LoaiGiayTo::updateOrCreate(
                ['MaLoaiGiayTo' => $loaiGiayTo['MaLoaiGiayTo']],
                $loaiGiayTo
            );
        }
    }
}
