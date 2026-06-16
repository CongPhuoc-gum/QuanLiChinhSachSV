<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KhoaSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['MaKhoa' => 'CNTT', 'TenKhoa' => 'Khoa Công nghệ Thông tin'],
            ['MaKhoa' => 'DDT',  'TenKhoa' => 'Khoa Điện - Điện tử'],
            ['MaKhoa' => 'CK',   'TenKhoa' => 'Khoa Cơ khí'],
            ['MaKhoa' => 'KT',   'TenKhoa' => 'Khoa Kinh tế'],
            ['MaKhoa' => 'XD',   'TenKhoa' => 'Khoa Xây dựng'],
        ];

        foreach ($items as $row) {
            DB::table('KHOA')->updateOrInsert(
                ['MaKhoa' => $row['MaKhoa']],
                ['TenKhoa' => $row['TenKhoa']]
            );
        }
    }
}