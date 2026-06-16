<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DanhMucHocPhanSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'MaHP' => 'TDTT01',
                'TenHP' => 'Giáo dục thể chất 1',
                'SoTinChi' => 1,
                'DonGia' => null,
                'Cap' => 'Đại cương',
                'GhiChu' => null,
            ],
            [
                'MaHP' => 'IT001',
                'TenHP' => 'Nhập môn CNTT',
                'SoTinChi' => 3,
                'DonGia' => 0,
                'Cap' => 'Đại cương',
                'GhiChu' => null,
            ],
            [
                'MaHP' => 'IT002',
                'TenHP' => 'Lập trình căn bản',
                'SoTinChi' => 3,
                'DonGia' => 500000,
                'Cap' => 'Cơ sở ngành',
                'GhiChu' => null,
            ],
        ];

        foreach ($rows as $r) {
            DB::table('DANH_MUC_HOC_PHAN')->updateOrInsert(
                ['MaHP' => $r['MaHP']],
                [
                    'TenHP' => $r['TenHP'],
                    'SoTinChi' => $r['SoTinChi'],
                    'DonGia' => $r['DonGia'],
                    'Cap' => $r['Cap'],
                    'GhiChu' => $r['GhiChu'],
                ]
            );
        }
    }
}