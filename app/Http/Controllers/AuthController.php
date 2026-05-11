<?php

namespace App\Http\Controllers;

use App\Models\NguoiDung;
use App\Models\SinhVien;
use App\Models\CanBo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login endpoint
     * 
     * Hỗ trợ 2 loại đăng nhập:
     * 1. Sinh viên: MaSoSV + MatKhau
     * 2. Cán bộ: Email + MatKhau
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Xác định loại đăng nhập dựa trên input
        $isSinhVien = $request->has('MaSoSV');

        if ($isSinhVien) {
            return $this->loginSinhVien($request);
        } else {
            return $this->loginCanBo($request);
        }
    }

    /**
     * Đăng nhập cho sinh viên
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function loginSinhVien(Request $request)
    {
        // Validate input
        $request->validate([
            'MaSoSV' => 'required|string',
            'MatKhau' => 'required|string',
        ]);

        // Tìm sinh viên theo MaSoSV
        $sinhVien = SinhVien::where('MaSoSV', $request->MaSoSV)->first();

        if (!$sinhVien) {
            throw ValidationException::withMessages([
                'MaSoSV' => ['Mã số sinh viên không tồn tại.'],
            ]);
        }

        // Lấy thông tin người dùng
        $nguoiDung = $sinhVien->nguoiDung;

        if (!$nguoiDung) {
            throw ValidationException::withMessages([
                'MaSoSV' => ['Tài khoản không tồn tại.'],
            ]);
        }

        // Kiểm tra mật khẩu
        if (!Hash::check($request->MatKhau, $nguoiDung->MatKhau)) {
            throw ValidationException::withMessages([
                'MatKhau' => ['Mật khẩu không chính xác.'],
            ]);
        }

        // Kiểm tra trạng thái tài khoản
        if ($nguoiDung->TrangThai != 1) {
            throw ValidationException::withMessages([
                'MaSoSV' => ['Tài khoản đã bị khóa hoặc xóa.'],
            ]);
        }

        // Tạo token
        $token = $nguoiDung->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'MaNguoiDung' => $nguoiDung->MaNguoiDung,
                'Email' => $nguoiDung->Email,
                'MaVaiTro' => $nguoiDung->MaVaiTro,
                'TenVaiTro' => $nguoiDung->vaiTro->TenVaiTro,
                'HoTen' => $sinhVien->HoTen,
                'MaSoSV' => $sinhVien->MaSoSV,
                'SoDienThoai' => $sinhVien->SoDienThoai,
            ],
        ], 200);
    }

    /**
     * Đăng nhập cho cán bộ
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function loginCanBo(Request $request)
    {
        // Validate input
        $request->validate([
            'Email' => 'required|email',
            'MatKhau' => 'required|string',
        ]);

        // Tìm người dùng theo Email
        $nguoiDung = NguoiDung::where('Email', $request->Email)->first();

        if (!$nguoiDung) {
            throw ValidationException::withMessages([
                'Email' => ['Email không tồn tại.'],
            ]);
        }

        // Kiểm tra mật khẩu
        if (!Hash::check($request->MatKhau, $nguoiDung->MatKhau)) {
            throw ValidationException::withMessages([
                'MatKhau' => ['Mật khẩu không chính xác.'],
            ]);
        }

        // Kiểm tra trạng thái tài khoản
        if ($nguoiDung->TrangThai != 1) {
            throw ValidationException::withMessages([
                'Email' => ['Tài khoản đã bị khóa hoặc xóa.'],
            ]);
        }

        // Lấy thông tin cán bộ
        $canBo = $nguoiDung->canBo;

        // Tạo token
        $token = $nguoiDung->createToken('auth_token')->plainTextToken;

        $userData = [
            'MaNguoiDung' => $nguoiDung->MaNguoiDung,
            'Email' => $nguoiDung->Email,
            'MaVaiTro' => $nguoiDung->MaVaiTro,
            'TenVaiTro' => $nguoiDung->vaiTro->TenVaiTro,
        ];

        // Thêm thông tin cán bộ nếu có
        if ($canBo) {
            $userData['HoTen'] = $canBo->HoTen;
            $userData['MaNhanVien'] = $canBo->MaNhanVien;
            $userData['PhongBan'] = $canBo->PhongBan;
            $userData['ChucVu'] = $canBo->ChucVu;
            $userData['SoDienThoai'] = $canBo->SoDienThoai;
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $userData,
        ], 200);
    }

    /**
     * Logout endpoint
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đã đăng xuất thành công.',
        ], 200);
    }
}
