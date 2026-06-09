<?php

namespace App\Http\Controllers\TruongPhong;

use App\Http\Controllers\Controller;
use App\Models\HoSo;
use App\Models\NhatKyXetDuyet;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HoSoController extends Controller
{
    /**
     * GET /api/truong-phong/ho-so
     * Danh sách hồ sơ chờ thẩm định cấp Trưởng phòng (trạng thái 4)
     */
    public function index(Request $request)
    {
        try {
            $query = HoSo::with(['nguoiDung', 'loaiChinhSach', 'dotThuHoSo', 'phanTichAI', 'minhChungFiles'])
                ->where('MaTrangThai', 4);  // Chờ TP duyệt

            // Filter loại chính sách
            if ($request->filled('loai_cs')) {
                $query->where('MaLoaiCS', $request->query('loai_cs'));
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->query('search');
                $query->whereHas('nguoiDung', function ($q) use ($search) {
                    $q->where('Email', 'like', "%$search%");
                })->orWhereHas('nguoiDung.sinhVien', function ($q) use ($search) {
                    $q
                        ->where('MaSoSV', 'like', "%$search%")
                        ->orWhere('HoTen', 'like', "%$search%");
                });
            }

            $perPage = $request->query('per_page', 20);
            $hoSos = $query->orderBy('NgayNop', 'asc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $hoSos->items(),
                'pagination' => [
                    'total' => $hoSos->total(),
                    'per_page' => $hoSos->perPage(),
                    'current_page' => $hoSos->currentPage(),
                    'last_page' => $hoSos->lastPage(),
                ],
                'message' => 'Danh sách hồ sơ chờ TP duyệt'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in TruongPhong\HoSoController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách'
            ], 500);
        }
    }

    /**
     * GET /api/truong-phong/ho-so/{id}
     * Chi tiết hồ sơ + kết quả OCR AI
     */
    public function show($maHoSo)
    {
        try {
            $hoSo = HoSo::with([
                'nguoiDung.sinhVien',
                'loaiChinhSach',
                'dotThuHoSo',
                'phanTichAI',
                'minhChungFiles',
                'nhatKyXetDuyets',
            ])->findOrFail($maHoSo);

            $sinhVien = $hoSo->nguoiDung->sinhVien;

            return response()->json([
                'success' => true,
                'data' => [
                    'ho_so' => [
                        'ma_ho_so' => $hoSo->MaHoSo,
                        'ma_so_sv' => $sinhVien->MaSoSV,
                        'ho_ten' => $sinhVien->HoTen,
                        'email' => $hoSo->nguoiDung->Email,
                        'loai_cs' => $hoSo->loaiChinhSach->TenLoaiCS ?? null,
                        'trang_thai' => $hoSo->MaTrangThai,
                        'du_lieu_form' => $hoSo->du_lieu_form,
                        'ghi_chu' => $hoSo->GhiChu,
                        'ngay_nop' => $hoSo->NgayNop,
                    ],
                    'ai_analysis' => $hoSo->phanTichAI ? [
                        'ty_le_khop' => $hoSo->phanTichAI->TyLeKhop,
                        'do_tin_cay' => $hoSo->phanTichAI->DoTinCayOCR,
                        'trang_thai' => $hoSo->phanTichAI->TrangThaiXuLy,
                        'ket_qua_doi_chieu' => $hoSo->phanTichAI->KetQuaDoiChieu,
                        'can_bao_lech' => $hoSo->phanTichAI->CanBaoLech,
                        'khuyen_nghi' => $hoSo->phanTichAI->KhuyenNghi,
                    ] : null,
                    'minh_chungs' => $hoSo->minhChungFiles->map(function ($mc) {
                        return [
                            'ma_minh_chung' => $mc->MaMinhChung,
                            'ten_file' => $mc->TenFile,
                            'url' => $mc->DuongDanFile,
                        ];
                    }),
                    'nhat_ky' => $hoSo->nhatKyXetDuyets->map(function ($nk) {
                        return [
                            'vai_tro' => $nk->VaiTro,
                            'hanh_dong' => $nk->HanhDong,
                            'ghi_chu' => $nk->GhiChu,
                            'thoi_gian' => $nk->ThoiGian,
                        ];
                    }),
                ],
                'message' => 'Chi tiết hồ sơ'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in TruongPhong\HoSoController@show', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hồ sơ'
            ], 404);
        }
    }

    /**
     * PUT /api/truong-phong/ho-so/{id}/duyet
     * Phê duyệt hồ sơ
     * Body: { trang_thai: 5|6|8|3, ly_do_tu_choi: '...', ghi_chu: '...' }
     */
    public function duyet($maHoSo, Request $request)
    {
        try {
            $validated = $request->validate([
                'trang_thai' => 'required|in:3,5,6,8',
                'ly_do_tu_choi' => 'required_if:trang_thai,8|string|max:500',
                'ghi_chu' => 'nullable|string|max:500',
            ], [
                'ly_do_tu_choi.required_if' => 'Lý do từ chối bắt buộc khi từ chối hồ sơ',
            ]);

            $hoSo = HoSo::findOrFail($maHoSo);

            // Check: chỉ từ trạng thái 4 (Chờ TP duyệt)
            if ($hoSo->MaTrangThai != 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hồ sơ không ở trạng thái chờ TP duyệt'
                ], 422);
            }

            $user = Auth::user();

            DB::transaction(function () use ($hoSo, $user, $validated) {
                $trangThaiMoi = $validated['trang_thai'];

                // Xác định hành động
                $hanhDong = match ($trangThaiMoi) {
                    3 => 'yeu_cau_bo_sung',
                    5 => 'chuyen_bgh',
                    6 => 'duyet',
                    8 => 'tu_choi',
                    default => 'unknown'
                };

                // Update HoSo
                $hoSo->update([
                    'MaTrangThai' => $trangThaiMoi,
                    'LyDoTuChoi' => $trangThaiMoi == 8 ? $validated['ly_do_tu_choi'] : null,
                    'GhiChu' => $validated['ghi_chu'] ?? $hoSo->GhiChu,
                ]);

                // Ghi nhật ký
                NhatKyXetDuyet::create([
                    'MaHoSo' => $maHoSo,
                    'MaNguoiThucHien' => $user->MaNguoiDung,
                    'VaiTro' => 'truong_phong',
                    'HanhDong' => $hanhDong,
                    'TrangThaiTruoc' => 4,
                    'TrangThaiSau' => $trangThaiMoi,
                    'GhiChu' => $validated['ghi_chu'] ?? null,
                ]);

                // Log
                $this->logSystemAction($user, 'duyet_ho_so_truong_phong',
                    "Hồ sơ ID: {$maHoSo}, Hành động: {$hanhDong}");
            });

            // Send notification email (X2 - Phase 3)
            $notificationService = new NotificationService();
            $hoSo->refresh();
            $notificationService->guiEmailDoiTrangThai($hoSo, $trangThaiMoi);

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_ho_so' => $maHoSo,
                    'trang_thai_moi' => $trangThaiMoi,
                ],
                'message' => 'Phê duyệt hồ sơ thành công'
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in TruongPhong\HoSoController@duyet', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi phê duyệt hồ sơ'
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
