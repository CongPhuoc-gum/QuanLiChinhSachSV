<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class HoSoSeeder extends Seeder
{
    public function run(): void
    {
        // Tìm 1 sinh viên đã được seed bởi NguoiDungSeeder
        $student = DB::table('NGUOI_DUNG')
            ->join('SINH_VIEN', 'NGUOI_DUNG.MaNguoiDung', '=', 'SINH_VIEN.MaNguoiDung')
            ->select('NGUOI_DUNG.MaNguoiDung', 'SINH_VIEN.MaSoSV')
            ->first();

        if (!$student) {
            return;
        }

        // Lấy một MaDot có sẵn (DotThuHoSoSeeder phải chạy trước)
        $dot = DB::table('DOT_THU_HO_SO')->orderBy('MaDot')->first();
        if (!$dot) {
            return;
        }

        // Lấy MaLoaiCS tồn tại (ví dụ BM.01)
        $loai = DB::table('LOAI_CHINH_SACH')->orderBy('MaLoaiCS')->first();
        if (!$loai) {
            return;
        }

        // Tạo HO_SO mẫu (MaTrangThai = 6 = Đã duyệt)
        $now = Carbon::now()->toDateTimeString();

        $hoSoId = DB::table('HO_SO')->insertGetId([
            'MaNguoiDung' => $student->MaNguoiDung,
            'MaDot' => $dot->MaDot,
            'MaLoaiCS' => $loai->MaLoaiCS,
            'MaTrangThai' => 6,
            'NgayNop' => $now,
            'NgayCapNhat' => $now,
            'GhiChu' => 'Hồ sơ seed mẫu - đã duyệt',
            'LyDoTuChoi' => null,
        ]);

        if (!$hoSoId) {
            return;
        }

        // 1) Đắp dữ liệu mẫu vào bảng KHO_TRI_THUC_AI (Cách tiếp cận an toàn tuyệt đối)
        $dataTriThuc = [
            'MaTriThuc' => 1,
            'TieuDe' => 'Quy định miễn giảm học phí diện chính sách',
            'NoiDungChunk' => 'Mẫu đối chiếu dữ liệu AI xử lý tự động phân tích hồ sơ...',
            'VanBanNguon' => 'Nghị định 81/2021/NĐ-CP',
        ];

        // Check động xem schema thực tế có cột nào thì mới đẩy cột đó vào mảng insert
        if (Schema::hasColumn('KHO_TRI_THUC_AI', 'NgayCapNhat')) {
            $dataTriThuc['NgayCapNhat'] = $now;
        }
        if (Schema::hasColumn('KHO_TRI_THUC_AI', 'IsActive')) {
            $dataTriThuc['IsActive'] = 1;
        }

        DB::table('KHO_TRI_THUC_AI')->updateOrInsert(
            ['MaTriThuc' => 1],
            $dataTriThuc
        );

        // 2) Tạo bản ghi PHAN_TICH_AI_HO_SO liên kết với hồ sơ này
        DB::table('PHAN_TICH_AI_HO_SO')->insert([
            'MaHoSo'           => $hoSoId, 
            'MaTriThuc'        => 1, 
            'MucHuongGoiY'     => '100', // Đặt mức hưởng gợi ý để Trigger bóc tách dữ liệu test hoàn tiền
            'NoiDungGoiY'      => 'Hồ sơ hợp lệ, đề xuất miễn giảm 100% học phí theo chính sách.',
            'DoTinCay'         => 0.990,
            'ThoiGianPhanTich' => now(),
        ]);
    }
}