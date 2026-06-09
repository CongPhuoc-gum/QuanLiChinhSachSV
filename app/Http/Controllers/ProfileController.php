<?php

namespace App\Http\Controllers;

use App\Models\SinhVien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * GET /api/profile
     * Lấy thông tin profile của sinh viên đăng nhập
     */
    public function show()
    {
        try {
            $user = Auth::user();
            $sinhVien = SinhVien::where('MaNguoiDung', $user->MaNguoiDung)
                ->with('lop.khoa')
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_nguoi_dung' => $sinhVien->MaNguoiDung,
                    'ma_so_sv' => $sinhVien->MaSoSV,
                    'ho_ten' => $sinhVien->HoTen,
                    'email' => $user->Email,
                    'ngay_sinh' => $sinhVien->NgaySinh,
                    'gioi_tinh' => $sinhVien->GioiTinh,
                    'cccd' => $sinhVien->CCCD,
                    'lop' => $sinhVien->lop->TenLop ?? null,
                    'khoa' => $sinhVien->lop->khoa->TenKhoa ?? null,
                    'dan_toc' => $sinhVien->DanToc,
                    'dien_thoai' => $sinhVien->SoDienThoai,
                    'dia_chi_thuong_tru' => $sinhVien->DiaChiThuongTru,
                    'tinh_thuong_tru' => $sinhVien->TinhThuongTru,
                    'dia_chi_tam_tru' => $sinhVien->DiaChiTamTru,
                    'tinh_tam_tru' => $sinhVien->TinhTamTru,
                    'doi_tuong_cs' => $sinhVien->DoiTuongCS,
                ],
                'message' => 'Lấy profile thành công'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in ProfileController@show', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy profile'
            ], 500);
        }
    }

    /**
     * PUT /api/profile
     * Cập nhật thông tin profile của sinh viên
     * Fields cho phép sửa: dien_thoai, dia_chi_tam_tru, so_tai_khoan_ngan_hang, ten_ngan_hang
     */
    public function update(Request $request)
    {
        try {
            $user = Auth::user();
            $sinhVien = SinhVien::findOrFail($user->MaNguoiDung);

            // Validate
            $validated = $request->validate([
                'dien_thoai' => 'nullable|regex:/^0[0-9]{9}$/',
                'dia_chi_tam_tru' => 'nullable|string|max:255',
                'tinh_tam_tru' => 'nullable|string|max:100',
            ], [
                'dien_thoai.regex' => 'Số điện thoại phải có định dạng 0XXXXXXXXX',
                'dia_chi_tam_tru.max' => 'Địa chỉ tạm trú không vượt quá 255 ký tự',
            ]);

            // Update SinhVien
            $sinhVien->update([
                'SoDienThoai' => $validated['dien_thoai'] ?? $sinhVien->SoDienThoai,
                'DiaChiTamTru' => $validated['dia_chi_tam_tru'] ?? $sinhVien->DiaChiTamTru,
                'TinhTamTru' => $validated['tinh_tam_tru'] ?? $sinhVien->TinhTamTru,
            ]);

            // Log activity
            \Log::info('Student updated profile', [
                'ma_nguoi_dung' => $user->MaNguoiDung,
                'email' => $user->Email,
                'dien_thoai' => $validated['dien_thoai'] ?? null,
            ]);

            // Ghi system log
            $this->logSystemAction($user, 'cap_nhat_profile', 'Cập nhật thông tin cá nhân');

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_so_sv' => $sinhVien->MaSoSV,
                    'ho_ten' => $sinhVien->HoTen,
                    'dien_thoai' => $sinhVien->SoDienThoai,
                    'dia_chi_tam_tru' => $sinhVien->DiaChiTamTru,
                    'tinh_tam_tru' => $sinhVien->TinhTamTru,
                ],
                'message' => 'Cập nhật profile thành công'
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in ProfileController@update', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật profile'
            ], 500);
        }
    }

    /**
     * Helper: Ghi log hệ thống
     */
    private function logSystemAction($user, $action, $detail = null)
    {
        try {
            DB::table('SYSTEM_LOGS')->insert([
                'MaNguoiDung' => $user->MaNguoiDung,
                'VaiTro' => $user->vaiTro->TenVaiTro ?? 'unknown',
                'HanhDong' => $action,
                'ChiTiet' => $detail,
                'IPAddress' => request()->ip(),
                'UserAgent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('Cannot log system action', ['error' => $e->getMessage()]);
        }
    }
}
