<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CongNoSeeder extends Seeder
{
    public function run(): void
    {
        // Tìm 1 sinh viên có tồn tại (được seed bởi NguoiDungSeeder)
        $student = DB::table('NGUOI_DUNG')
            ->join('SINH_VIEN', 'NGUOI_DUNG.MaNguoiDung', '=', 'SINH_VIEN.MaNguoiDung')
            ->select('NGUOI_DUNG.MaNguoiDung')
            ->first();

        if (!$student) {
            return;
        }

        // Tìm 1 HoSo đã tạo (HoSoSeeder phải chạy trước)
        $hoSo = DB::table('HO_SO')->where('MaNguoiDung', $student->MaNguoiDung)->orderBy('MaHoSo')->first();
        if (!$hoSo) {
            return;
        }

        // Chọn MaNamHoc hiện có (ví dụ 20242)
        $namHoc = DB::table('NAM_HOC')->orderBy('MaNamHoc', 'desc')->first();
        if (!$namHoc) {
            return;
        }

        $now = Carbon::now()->toDateTimeString();

        // Tạo CONG_NO sao cho SoTienMienGiam > HocPhiPhaiDong để kích hoạt TienDuMGHP > 0
        $maCongNo = DB::table('CONG_NO')->insertGetId([
            'MaNguoiDung' => $student->MaNguoiDung,
            'MaHoSo' => $hoSo->MaHoSo,
            'MaNamHoc' => $namHoc->MaNamHoc,
            'HocPhiPhaiDong' => 1000000,    // 1,000,000
            'SoTienMienGiam' => 1200000,    // 1,200,000 (lớn hơn để tạo dư)
            'SoTienConLai' => 0,            // trigger sẽ cập nhật
            'TienDuMGHP' => 0,              // trigger sẽ cập nhật
            'NgayCapNhat' => $now,
        ]);

        // Nếu trigger hoạt động, sẽ có một lệnh trong GIAO_DICH_NOI_BO được tạo tự động.
    }
}