<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DotThuHoSoSeeder extends Seeder
{
    public function run(): void
    {
        // Use MaNamHoc = 20242 (must exist from NamHocSeeder)
        $rows = [
            [
                'TenDot' => 'Đợt 1 - HK2 2024-2025',
                'MaNamHoc' => 20242,
                'NgayBatDau' => '2025-02-01',
                'NgayKetThuc' => '2025-03-01',
                'TrangThaiDot' => 1, // 1 = đang mở
            ],
        ];

        foreach ($rows as $r) {
            DB::table('DOT_THU_HO_SO')->updateOrInsert(
                ['TenDot' => $r['TenDot'], 'MaNamHoc' => $r['MaNamHoc']],
                [
                    'NgayBatDau' => $r['NgayBatDau'],
                    'NgayKetThuc' => $r['NgayKetThuc'],
                    'TrangThaiDot' => $r['TrangThaiDot'],
                ]
            );
        }
    }
}