<?php

namespace App\Http\Controllers;

use App\Models\CanBo;
use App\Models\NguoiDung;
use App\Models\SinhVien;
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
     * Quên mật khẩu (Admin cấp lại)
     *
     * POST /api/auth/forgot-password
     * Body: { "email_or_mssv": "20210001" hoặc "sv@example.com" }
     * Response: { "success": true, "message": "Admin sẽ cấp lại mật khẩu..." }
     *
     * Flow: SV hỏi → Admin xác nhận danh tính → Admin reset password về mặc định
     */
    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email_or_mssv' => 'required|string',
            ]);

            $identifier = $request->input('email_or_mssv');

            // Tìm sinh viên bằng MSSV hoặc Email
            $sinhVien = SinhVien::where('MaSoSV', $identifier)->first();

            if (!$sinhVien) {
                $nguoiDung = NguoiDung::where('Email', $identifier)->first();
                if (!$nguoiDung) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy tài khoản',
                    ], 404);
                }
                $sinhVien = SinhVien::where('MaNguoiDung', $nguoiDung->MaNguoiDung)->first();
            }

            if (!$sinhVien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tài khoản sinh viên',
                ], 404);
            }

            // Ghi nhận yêu cầu reset
            \Log::info('Student forgot password', [
                'ma_so_sv' => $sinhVien->MaSoSV,
                'email' => $sinhVien->nguoiDung->Email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Yêu cầu reset mật khẩu đã được gửi. Vui lòng liên hệ Phòng CTSV để xác nhận danh tính và nhận mật khẩu mới.',
                'data' => [
                    'ma_so_sv' => $sinhVien->MaSoSV,
                    'ho_ten' => $sinhVien->HoTen,
                    'email' => $sinhVien->nguoiDung->Email,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in AuthController@forgotPassword', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu',
            ], 500);
        }
    }

    /**
     * Admin: Reset mật khẩu sinh viên về mặc định
     *
     * POST /api/admin/reset-password
     * Body: { "email_or_mssv": "20210001" }
     * Response: { "success": true, "new_password": "MSSV@2024", "message": "..." }
     *
     * (Chỉ admin mới được reset)
     */
    public function resetPasswordAdmin(Request $request)
    {
        try {
            // Validate admin token
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user || $user->MaVaiTro != 1) {  // 1 = Admin
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ admin mới được phép reset mật khẩu',
                ], 403);
            }

            $request->validate([
                'email_or_mssv' => 'required|string',
            ]);

            $identifier = $request->input('email_or_mssv');

            // Tìm sinh viên
            $sinhVien = SinhVien::where('MaSoSV', $identifier)->first();

            if (!$sinhVien) {
                $nguoiDung = NguoiDung::where('Email', $identifier)->first();
                if (!$nguoiDung) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy tài khoản',
                    ], 404);
                }
                $sinhVien = SinhVien::where('MaNguoiDung', $nguoiDung->MaNguoiDung)->first();
            }

            $nguoiDung = $sinhVien->nguoiDung;

            // Tạo mật khẩu mặc định = MSSV@2024
            $defaultPassword = $sinhVien->MaSoSV . '@2024';
            $nguoiDung->MatKhau = Hash::make($defaultPassword);
            $nguoiDung->save();

            // Ghi log
            \Log::warning('Admin reset student password', [
                'ma_so_sv' => $sinhVien->MaSoSV,
                'admin' => \Illuminate\Support\Facades\Auth::user()->Email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reset mật khẩu thành công',
                'data' => [
                    'ma_so_sv' => $sinhVien->MaSoSV,
                    'ho_ten' => $sinhVien->HoTen,
                    'email' => $nguoiDung->Email,
                    'mat_khau_moi' => $defaultPassword,
                    'ghi_chu' => 'Mật khẩu mặc định: MSSV@2024. Sinh viên cần đổi mật khẩu lần đầu đăng nhập.',
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in AuthController@resetPasswordAdmin', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi reset mật khẩu',
            ], 500);
        }
    }

    /**
     * Sinh viên: Đổi mật khẩu trong profile
     *
     * POST /api/auth/change-password
     * Body: { "mat_khau_cu": "old", "mat_khau_moi": "new", "xac_nhan_mat_khau": "new" }
     * Response: { "success": true, "message": "Đổi mật khẩu thành công" }
     */
    public function changePassword(Request $request)
    {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();

            $request->validate([
                'mat_khau_cu' => 'required|string|min:6',
                'mat_khau_moi' => 'required|string|min:6|confirmed',
            ]);

            // Check mật khẩu cũ
            if (!Hash::check($request->input('mat_khau_cu'), $user->MatKhau)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mật khẩu cũ không chính xác',
                ], 422);
            }

            // Không được dùng lại mật khẩu cũ
            if (Hash::check($request->input('mat_khau_moi'), $user->MatKhau)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mật khẩu mới phải khác mật khẩu cũ',
                ], 422);
            }

            // Update
            $user->MatKhau = Hash::make($request->input('mat_khau_moi'));
            $user->save();

            \Log::info('Student changed password', [
                'email' => $user->Email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đổi mật khẩu thành công',
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in AuthController@changePassword', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi đổi mật khẩu',
            ], 500);
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

    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
            }

            // Tự động nhận diện sinh viên hoặc cán bộ giống logic login
            $sinhVien = \App\Models\SinhVien::where('MaSoSV', $user->sinhVien?->MaSoSV)->first();

            return response()->json([
                'success' => true,
                'user' => [
                    'MaNguoiDung' => $user->MaNguoiDung,
                    'Email' => $user->Email,
                    'MaVaiTro' => $user->MaVaiTro,
                    'TenVaiTro' => $user->vaiTro?->TenVaiTro ?? 'Sinh viên',
                    'HoTen' => $sinhVien ? $sinhVien->HoTen : 'Người dùng hệ thống',
                    'MaSoSV' => $sinhVien ? $sinhVien->MaSoSV : null,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý phiên đăng nhập',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
