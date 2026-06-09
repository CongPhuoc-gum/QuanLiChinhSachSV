<?php

namespace App\Http\Controllers\CanBo;

use App\Http\Controllers\Controller;
use App\Models\DotThuHoSo;
use App\Models\LoaiChinhSach;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DotThuController extends Controller
{
    /**
     * GET /api/can-bo/dot-thu
     * Danh sách đợt thu
     */
    public function index(Request $request)
    {
        try {
            $query = DotThuHoSo::with('loaiChinhSachs');

            // Filter theo học kỳ
            if ($request->filled('hoc_ky')) {
                $query->where('HocKy', $request->query('hoc_ky'));
            }

            // Filter theo năm học
            if ($request->filled('nam_hoc')) {
                $query->where('NamHoc', $request->query('nam_hoc'));
            }

            $perPage = $request->query('per_page', 20);
            $dotThus = $query->orderBy('NgayBatDau', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $dotThus->items(),
                'pagination' => [
                    'total' => $dotThus->total(),
                    'per_page' => $dotThus->perPage(),
                    'current_page' => $dotThus->currentPage(),
                    'last_page' => $dotThus->lastPage(),
                ],
                'message' => 'Danh sách đợt thu'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in DotThuController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách đợt thu'
            ], 500);
        }
    }

    /**
     * POST /api/can-bo/dot-thu
     * Tạo đợt thu mới
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'ten_dot' => 'required|string|max:200',
                'hoc_ky' => 'required|string|max:10',
                'nam_hoc' => 'required|string|max:20',
                'ngay_bat_dau' => 'required|date',
                'ngay_ket_thuc' => 'required|date|after:ngay_bat_dau',
                'ma_loai_cs' => 'required|array|min:1',
                'ma_loai_cs.*' => 'integer|exists:LOAI_CHINH_SACH,MaLoaiCS',
            ], [
                'ngay_ket_thuc.after' => 'Ngày kết thúc phải sau ngày bắt đầu',
                'ma_loai_cs.required' => 'Phải chọn ít nhất 1 loại chính sách',
            ]);

            // Check duplicate
            $existing = DotThuHoSo::where('HocKy', $validated['hoc_ky'])
                ->where('NamHoc', $validated['nam_hoc'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đợt thu cho kỳ này đã tồn tại'
                ], 422);
            }

            DB::transaction(function () use ($validated) {
                $dotThu = DotThuHoSo::create([
                    'TenDot' => $validated['ten_dot'],
                    'HocKy' => $validated['hoc_ky'],
                    'NamHoc' => $validated['nam_hoc'],
                    'NgayBatDau' => $validated['ngay_bat_dau'],
                    'NgayKetThuc' => $validated['ngay_ket_thuc'],
                ]);

                // Attach loại chính sách (nếu có bảng pivot)
                if (method_exists($dotThu, 'loaiChinhSachs')) {
                    $dotThu->loaiChinhSachs()->attach($validated['ma_loai_cs']);
                }

                // Log
                $this->logSystemAction(Auth::user(), 'tao_dot_thu',
                    "Tạo đợt thu: {$validated['ten_dot']} ({$validated['hoc_ky']} / {$validated['nam_hoc']})");
            });

            return response()->json([
                'success' => true,
                'message' => 'Tạo đợt thu thành công'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in DotThuController@store', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo đợt thu'
            ], 500);
        }
    }

    /**
     * GET /api/can-bo/dot-thu/{id}
     * Chi tiết đợt thu
     */
    public function show($maDot)
    {
        try {
            $dotThu = DotThuHoSo::with(['loaiChinhSachs', 'hoSos'])->findOrFail($maDot);

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_dot' => $dotThu->MaDot,
                    'ten_dot' => $dotThu->TenDot,
                    'hoc_ky' => $dotThu->HocKy,
                    'nam_hoc' => $dotThu->NamHoc,
                    'ngay_bat_dau' => $dotThu->NgayBatDau,
                    'ngay_ket_thuc' => $dotThu->NgayKetThuc,
                    'is_dong' => $dotThu->IsDong,
                    'loai_cs' => $dotThu->loaiChinhSachs->pluck('TenLoaiCS', 'MaLoaiCS'),
                    'tong_ho_so' => $dotThu->hoSos->count(),
                ],
                'message' => 'Chi tiết đợt thu'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in DotThuController@show', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đợt thu'
            ], 404);
        }
    }

    /**
     * PUT /api/can-bo/dot-thu/{id}
     * Cập nhật đợt thu (chỉ khi chưa có hồ sơ)
     */
    public function update($maDot, Request $request)
    {
        try {
            $dotThu = DotThuHoSo::findOrFail($maDot);

            // Check: đã có hồ sơ?
            if ($dotThu->hoSos()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể cập nhật đợt thu đã có hồ sơ'
                ], 422);
            }

            $validated = $request->validate([
                'ten_dot' => 'nullable|string|max:200',
                'ngay_bat_dau' => 'nullable|date',
                'ngay_ket_thuc' => 'nullable|date',
            ]);

            $dotThu->update($validated);

            $this->logSystemAction(Auth::user(), 'cap_nhat_dot_thu',
                "Cập nhật đợt thu ID: {$maDot}");

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đợt thu thành công'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in DotThuController@update', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật đợt thu'
            ], 500);
        }
    }

    /**
     * DELETE /api/can-bo/dot-thu/{id}
     * Xóa đợt thu (chỉ khi chưa có hồ sơ)
     */
    public function destroy($maDot)
    {
        try {
            $dotThu = DotThuHoSo::findOrFail($maDot);

            if ($dotThu->hoSos()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa đợt thu đã có hồ sơ'
                ], 422);
            }

            $dotThu->delete();

            $this->logSystemAction(Auth::user(), 'xoa_dot_thu',
                "Xóa đợt thu ID: {$maDot}");

            return response()->json([
                'success' => true,
                'message' => 'Xóa đợt thu thành công'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in DotThuController@destroy', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa đợt thu'
            ], 500);
        }
    }

    /**
     * PUT /api/can-bo/dot-thu/{id}/dong
     * Đóng đợt thu
     */
    public function dongDotThu($maDot)
    {
        try {
            $dotThu = DotThuHoSo::findOrFail($maDot);
            $dotThu->update(['IsDong' => 1]);

            $this->logSystemAction(Auth::user(), 'dong_dot_thu',
                "Đóng đợt thu ID: {$maDot}");

            return response()->json([
                'success' => true,
                'message' => 'Đóng đợt thu thành công'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in DotThuController@dongDotThu', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi đóng đợt thu'
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
