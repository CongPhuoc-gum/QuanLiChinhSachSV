<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DanhMucLopSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the first auto-increment record has MaLop = 1 by explicitly inserting MaLop = 1.
        // If MaLop already exists the updateOrInsert will skip creating duplicates.
        $rows = [
            [
                'MaLop' => 1,
                'TenLop' => '21CNTT1',
                'MaKhoa' => 'CNTT',
                'KhoaHoc' => 2021,
            ],
            [
                'TenLop' => '22CNTT1',
                'MaKhoa' => 'CNTT',
                'KhoaHoc' => 2022,
            ],
            [
                'TenLop' => '21DDT1',
                'MaKhoa' => 'DDT',
                'KhoaHoc' => 2021,
            ],
        ];

        foreach ($rows as $row) {
            // If MaLop specified, use it as key; otherwise match by TenLop
            if (isset($row['MaLop'])) {
                $key = ['MaLop' => $row['MaLop']];
                $data = $row;
                unset($data['MaLop']);
                DB::table('DANH_MUC_LOP')->updateOrInsert($key, $data);
            } else {
                DB::table('DANH_MUC_LOP')->updateOrInsert(
                    ['TenLop' => $row['TenLop']],
                    [
                        'MaKhoa' => $row['MaKhoa'],
                        'KhoaHoc' => $row['KhoaHoc'],
                    ]
                );
            }
        }
    }
}