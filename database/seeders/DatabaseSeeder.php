<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed danh mục chuẩn
        $this->call([
            VaiTroSeeder::class,
            TrangThaiSeeder::class,
            LoaiChinhSachSeeder::class,
            LoaiGiayToSeeder::class,
            NguoiDungSeeder::class,
        ]);
    }
}
