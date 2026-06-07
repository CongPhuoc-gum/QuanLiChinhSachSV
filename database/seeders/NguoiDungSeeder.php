<?php

namespace Database\Seeders;

use App\Models\NguoiDung;
use App\Models\SinhVien;
use App\Models\CanBo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NguoiDungSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the NGUOI_DUNG table with sample users
     */
    public function run(): void
    {
        // ===== SINH VIÊN =====
        // Sinh viên 1
        $sinhVien1 = NguoiDung::updateOrCreate(
            ['Email' => 'sv001@ute.edu.vn'],
            [
                'MatKhau' => Hash::make('password123'),
                'MaVaiTro' => 1, // Sinh viên
                'TrangThai' => 1, // Hoạt động
            ]
        );

        SinhVien::updateOrCreate(
            ['MaSoSV' => '20210001'],
            [
                'MaNguoiDung' => $sinhVien1->MaNguoiDung,
                'HoTen' => 'Nguyễn Văn A',
                'NgaySinh' => '2003-01-15',
                'GioiTinh' => 1, // Nam
                'CCCD' => '123456789012',
                'MaLop' => 1,
                'SoDienThoai' => '0123456789',
                'DiaChiThuongTru' => '123 Đường A, Quận 1',
                'TinhThuongTru' => 'TP. Hồ Chí Minh',
                'DiaChiTamTru' => '456 Đường B, Quận 2',
                'TinhTamTru' => 'TP. Hồ Chí Minh',
                'DanToc' => 'Kinh',
                'DoiTuongCS' => 'Hộ nghèo',
            ]
        );

        // Sinh viên 2
        $sinhVien2 = NguoiDung::updateOrCreate(
            ['Email' => 'sv002@ute.edu.vn'],
            [
                'MatKhau' => Hash::make('password123'),
                'MaVaiTro' => 1, // Sinh viên
                'TrangThai' => 1, // Hoạt động
            ]
        );

        SinhVien::updateOrCreate(
            ['MaSoSV' => '20210002'],
            [
                'MaNguoiDung' => $sinhVien2->MaNguoiDung,
                'HoTen' => 'Trần Thị B',
                'NgaySinh' => '2003-05-20',
                'GioiTinh' => 2, // Nữ
                'CCCD' => '123456789013',
                'MaLop' => 1,
                'SoDienThoai' => '0987654321',
                'DiaChiThuongTru' => '789 Đường C, Quận 3',
                'TinhThuongTru' => 'TP. Hồ Chí Minh',
                'DiaChiTamTru' => '101 Đường D, Quận 4',
                'TinhTamTru' => 'TP. Hồ Chí Minh',
                'DanToc' => 'Kinh',
                'DoiTuongCS' => 'Hộ cận nghèo',
            ]
        );

        // ===== CÁN BỘ CTSV =====
        // Cán bộ CTSV 1
        $canBo1 = NguoiDung::updateOrCreate(
            ['Email' => 'canbo1@ute.edu.vn'],
            [
                'MatKhau' => Hash::make('password123'),
                'MaVaiTro' => 2, // Cán bộ CTSV
                'TrangThai' => 1, // Hoạt động
            ]
        );

        CanBo::updateOrCreate(
            ['MaNhanVien' => 'NV001'],
            [
                'MaNguoiDung' => $canBo1->MaNguoiDung,
                'HoTen' => 'Lê Văn C',
                'PhongBan' => 'Phòng Công Tác Sinh Viên',
                'ChucVu' => 'Cán bộ',
                'SoDienThoai' => '0111111111',
            ]
        );

        // Cán bộ CTSV 2
        $canBo2 = NguoiDung::updateOrCreate(
            ['Email' => 'canbo2@ute.edu.vn'],
            [
                'MatKhau' => Hash::make('password123'),
                'MaVaiTro' => 2, // Cán bộ CTSV
                'TrangThai' => 1, // Hoạt động
            ]
        );

        CanBo::updateOrCreate(
            ['MaNhanVien' => 'NV002'],
            [
                'MaNguoiDung' => $canBo2->MaNguoiDung,
                'HoTen' => 'Phạm Thị D',
                'PhongBan' => 'Phòng Công Tác Sinh Viên',
                'ChucVu' => 'Cán bộ',
                'SoDienThoai' => '0222222222',
            ]
        );

        // ===== TRƯỞNG PHÒNG CTSV =====
        $truongPhong = NguoiDung::updateOrCreate(
            ['Email' => 'truongphong@ute.edu.vn'],
            [
                'MatKhau' => Hash::make('password123'),
                'MaVaiTro' => 3, // Trưởng phòng CTSV
                'TrangThai' => 1, // Hoạt động
            ]
        );

        CanBo::updateOrCreate(
            ['MaNhanVien' => 'NV003'],
            [
                'MaNguoiDung' => $truongPhong->MaNguoiDung,
                'HoTen' => 'Hoàng Văn E',
                'PhongBan' => 'Phòng Công Tác Sinh Viên',
                'ChucVu' => 'Trưởng phòng',
                'SoDienThoai' => '0333333333',
            ]
        );

        // ===== BAN GIÁM HIỆU =====
        $banGiamHieu = NguoiDung::updateOrCreate(
            ['Email' => 'giamhieu@ute.edu.vn'],
            [
                'MatKhau' => Hash::make('password123'),
                'MaVaiTro' => 4, // Ban Giám hiệu
                'TrangThai' => 1, // Hoạt động
            ]
        );

        CanBo::updateOrCreate(
            ['MaNhanVien' => 'NV004'],
            [
                'MaNguoiDung' => $banGiamHieu->MaNguoiDung,
                'HoTen' => 'Võ Thị F',
                'PhongBan' => 'Ban Giám hiệu',
                'ChucVu' => 'Phó Giám đốc',
                'SoDienThoai' => '0444444444',
            ]
        );

        // ===== CÁN BỘ TÀI VỤ =====
        $canBoTaiVu = NguoiDung::updateOrCreate(
            ['Email' => 'taivụ@ute.edu.vn'],
            [
                'MatKhau' => Hash::make('password123'),
                'MaVaiTro' => 5, // Cán bộ Tài vụ
                'TrangThai' => 1, // Hoạt động
            ]
        );

        CanBo::updateOrCreate(
            ['MaNhanVien' => 'NV005'],
            [
                'MaNguoiDung' => $canBoTaiVu->MaNguoiDung,
                'HoTen' => 'Đặng Văn G',
                'PhongBan' => 'Phòng Tài vụ',
                'ChucVu' => 'Cán bộ',
                'SoDienThoai' => '0555555555',
            ]
        );
    }
}
