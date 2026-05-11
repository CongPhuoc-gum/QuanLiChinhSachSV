<?php

namespace Database\Seeders;

use App\Models\LoaiChinhSach;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LoaiChinhSachSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the LOAI_CHINH_SACH table with 2 policy types
     */
    public function run(): void
    {
        $loaiChinhSachs = [
            [
                'MaLoaiCS' => 1,
                'MaForm' => 'BM.01',
                'TenLoaiCS' => 'Miễn Giảm Học Phí',
                'MoTa' => 'Chính sách miễn giảm học phí cho sinh viên có hoàn cảnh khó khăn',
            ],
            [
                'MaLoaiCS' => 2,
                'MaForm' => 'BM.02',
                'TenLoaiCS' => 'Trợ Cấp Xã Hội',
                'MoTa' => 'Chính sách trợ cấp xã hội cho sinh viên đối tượng chính sách',
            ],
        ];

        foreach ($loaiChinhSachs as $loaiChinhSach) {
            LoaiChinhSach::updateOrCreate(
                ['MaLoaiCS' => $loaiChinhSach['MaLoaiCS']],
                $loaiChinhSach
            );
        }
    }
}
