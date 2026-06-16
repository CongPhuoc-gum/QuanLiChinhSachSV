<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NamHocSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'MaNamHoc' => 20241,
                'TenNamHoc' => '2024-2025',
                'HocKy' => 1,
                'NgayBatDau' => '2024-09-02',
                'NgayKetThuc' => '2025-01-15',
                'IsActive' => 0,
            ],
            [
                'MaNamHoc' => 20242,
                'TenNamHoc' => '2024-2025',
                'HocKy' => 2,
                'NgayBatDau' => '2025-02-10',
                'NgayKetThuc' => '2025-06-15',
                'IsActive' => 1,
            ],
        ];

        foreach ($rows as $r) {
            DB::table('NAM_HOC')->updateOrInsert(
                ['MaNamHoc' => $r['MaNamHoc']],
                [
                    'TenNamHoc' => $r['TenNamHoc'],
                    'HocKy' => $r['HocKy'],
                    'NgayBatDau' => $r['NgayBatDau'],
                    'NgayKetThuc' => $r['NgayKetThuc'],
                    'IsActive' => $r['IsActive'],
                ]
            );
        }
    }
}