<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LichSuTKBSeeder extends Seeder
{
    public function run(): void
    {
        // Find a staff user to use as MaNguoiTao (must exist after NguoiDungSeeder)
        $staff = DB::table('NGUOI_DUNG')->whereIn('MaVaiTro', [2,3,4,5])->first();

        if (!$staff) {
            // If no staff found, skip creating TKB rows — they depend on NGUOI_DUNG existing.
            return;
        }

        $maNguoiTao = $staff->MaNguoiDung;

        // Example Lớp học phần referencing MaHP and MaNamHoc (20242)
        $rows = [
            [
                'MaHP' => 'IT002',
                'TenLHP' => 'Lập trình căn bản - Nhóm 1',
                'MaNamHoc' => 20242,
                'GiangVien' => 'TS. Nguyễn Văn A',
                'Thu' => 2,
                'TuTiet' => 3,
                'DenTiet' => 5,
                'Phong' => 'E101',
                'SiSoDK' => 30,
                'SiSoHienTai' => 0,
                'LoaiDK' => 1,
                'MaNguoiTao' => $maNguoiTao,
                'GhiChu' => 'Seeded lớp học phần IT002',
            ],
            [
                'MaHP' => 'IT001',
                'TenLHP' => 'Nhập môn CNTT - Nhóm 1',
                'MaNamHoc' => 20242,
                'GiangVien' => 'TS. B',
                'Thu' => 3,
                'TuTiet' => 1,
                'DenTiet' => 3,
                'Phong' => 'E102',
                'SiSoDK' => 40,
                'SiSoHienTai' => 0,
                'LoaiDK' => 1,
                'MaNguoiTao' => $maNguoiTao,
                'GhiChu' => 'Seeded lớp học phần IT001',
            ],
        ];

        foreach ($rows as $r) {
            DB::table('LICH_SU_TKB')->updateOrInsert(
                [
                    'MaHP' => $r['MaHP'],
                    'TenLHP' => $r['TenLHP'],
                    'MaNamHoc' => $r['MaNamHoc'],
                ],
                $r
            );
        }
    }
}