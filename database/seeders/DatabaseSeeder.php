<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Danh mục cơ bản (parents) — phải chạy trước để tránh FK lỗi
        $this->call([
            // Danh mục trường/khoa/lớp/năm học
            KhoaSeeder::class,
            DanhMucLopSeeder::class,
            NamHocSeeder::class,
            DotThuHoSoSeeder::class,
            DanhMucHocPhanSeeder::class,
        ]);

        // 2) Lookup/Reference tables (roles, statuses, policy types)
        $this->call([
            VaiTroSeeder::class,
            TrangThaiSeeder::class,
            LoaiChinhSachSeeder::class,
            LoaiGiayToSeeder::class,
        ]);

        // 3) Người dùng (students & staff) — NguoiDungSeeder tạo NGUOI_DUNG và SINH_VIEN
        $this->call([
            NguoiDungSeeder::class,
        ]);

        // 4) Các bảng phụ thuộc vào NGUOI_DUNG (MaNguoiTao / MaNguoiDung)
        // LichSuTKBSeeder cần MaNguoiTao (cán bộ) từ NguoiDungSeeder
        $this->call([
            LichSuTKBSeeder::class,
        ]);

        // 5) Tạo Hồ sơ mẫu và AI phân tích (phải sau NguoiDungSeeder)
        $this->call([
            HoSoSeeder::class,
        ]);

        // 6) Tạo CONG_NO để kích hoạt trigger hoàn tiền (phải sau HoSoSeeder)
        $this->call([
            CongNoSeeder::class,
        ]);

        // 7) (Tùy chọn) Các seeder khác phụ thuộc có thể thêm ở đây
    }
}