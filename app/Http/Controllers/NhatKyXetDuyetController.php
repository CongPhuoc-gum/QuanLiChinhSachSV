<?php

namespace App\Http\Controllers;

use App\Models\HoSo;
use App\Models\NhatKyXetDuyet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NhatKyXetDuyetController - PHASE X3
 *
 * Sinh viên xem lịch sử xét duyệt hồ sơ của mình
 * - Xem audit trail chi tiết của một hồ sơ
 * - Xem tóm tắt tất cả hồ sơ của sinh viên
 * - Security: Chỉ xem được hồ sơ của chính mình
 */
class NhatKyXetDuyetController extends Controller
{
    /**
     * Ánh xạ trạng thái sang tiếng Việt
     */
    private function getTrangThaiNames(): array
    {
        return [
            1 => 'Chờ nộp',
            2 => 'Chờ thẩm định',
            3 => 'Đang bổ sung',
            4 => 'Chờ TP duyệt',
            5 => 'Chờ BGH duyệt',
            6 => 'Đã duyệt',
            7 => 'Đã chi trả',
            8 => 'Từ chối',
            9 => 'Đã hủy',
        ];
    }

    /**
     * Ánh xạ vai trò sang tiếng Việt
     */
    private function getVaiTroNames(): array
    {
        return [
            1 => 'Sinh viên',
            2 => 'Cán bộ CTSV',
            3 => 'Trưởng phòng CTSV',
            4 => 'Ban Giám hiệu',
            5 => 'Cán bộ Tài vụ',
        ];
    }

    /**
     * Ánh xạ hành động sang tiếng Việt
     */
    private function getHanhDongNames(): array
    {
        return [
            'duyet' => 'Duyệt',
            'tu_choi' => 'Từ chối',
            'bo_sung' => 'Yêu cầu bổ sung',
            'chuyen_cap' => 'Chuyển cấp',
            'chi_tra' => 'Chi trả',
            'huy_duyet' => 'Hủy duyệt',
        ];
    }

    /**
     * GET /api/ho-so/{maHoSo}/nhat-ky
     *
     * Xem nhật ký xét duyệt chi tiết của một hồ sơ
     *
     * Security: Chỉ xem được hồ sơ của chính mình
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "ma_ho_so": 1,
     *     "trang_thai_hien_tai": 6,
     *     "ten_trang_thai": "Đã duyệt",
     *     "loai_chinh_sach": "Miễn giảm học phí (BM.01)",
     *     "dot_thu": "Đợt 1 - HK1 2025-2026",
     *     "ngay_nop": "2026-06-09",
     *     "nhat_ky": [
     *       {
     *         "thu_tu": 1,
     *         "vai_tro": "Cán bộ CTSV",
     *         "hanh_dong": "Chuyển sang thẩm định",
     *         "trang_thai_truoc": 1,
     *         "trang_thai_sau": 2,
     *         "ten_trang_thai_truoc": "Chờ nộp",
     *         "ten_trang_thai_sau": "Chờ thẩm định",
     *         "ghi_chu": null,
     *         "thoi_gian": "2026-06-09 08:30:00"
     *       }
     *     ]
     *   },
     *   "message": "Chi tiết nhật ký xét duyệt"
     * }
     */
    public function show(Request $request, $maHoSo)
    {
        try {
            $user = Auth::user();
            $maSinhVien = $user->sinhVien?->MaSoSV;

            if (!$maSinhVien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Người dùng không phải là sinh viên'
                ], 403);
            }

            // Get hồ sơ
            $hoSo = HoSo::with([
                'nguoiDung.sinhVien',
                'loaiChinhSach',
                'dotThuHoSo',
                'trangThai'
            ])->findOrFail($maHoSo);

            // Security: Chỉ xem được hồ sơ của chính mình
            if ($hoSo->nguoiDung->sinhVien->MaSoSV !== $maSinhVien) {
                Log::warning('NhatKyXetDuyetController::show - Unauthorized access attempt', [
                    'ma_ho_so' => $maHoSo,
                    'ma_sinh_vien' => $maSinhVien,
                    'ho_so_owner' => $hoSo->nguoiDung->sinhVien->MaSoSV
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xem hồ sơ này'
                ], 403);
            }

            // Get nhật ký
            $nhatKy = NhatKyXetDuyet::where('MaHoSo', $maHoSo)
                ->orderBy('ThoiGian', 'asc')
                ->get();

            $trangThaiNames = $this->getTrangThaiNames();
            $vaiTroNames = $this->getVaiTroNames();
            $hanhDongNames = $this->getHanhDongNames();

            // Format nhật ký
            $nhatKyFormatted = $nhatKy->map(function ($item, $index) use ($trangThaiNames, $vaiTroNames, $hanhDongNames) {
                return [
                    'thu_tu' => $index + 1,
                    'vai_tro' => $vaiTroNames[$item->nguoiThucHien?->MaVaiTro] ?? 'Unknown',
                    'hanh_dong' => $hanhDongNames[$item->HanhDong] ?? $item->HanhDong,
                    'trang_thai_truoc' => $item->TrangThaiTruoc,
                    'trang_thai_sau' => $item->TrangThaiSau,
                    'ten_trang_thai_truoc' => $trangThaiNames[$item->TrangThaiTruoc] ?? 'Unknown',
                    'ten_trang_thai_sau' => $trangThaiNames[$item->TrangThaiSau] ?? 'Unknown',
                    'ghi_chu' => $item->GhiChu,
                    'thoi_gian' => $item->ThoiGian,
                ];
            });

            Log::info('NhatKyXetDuyetController::show - Viewed audit trail', [
                'ma_ho_so' => $maHoSo,
                'ma_sinh_vien' => $maSinhVien
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_ho_so' => $hoSo->MaHoSo,
                    'trang_thai_hien_tai' => $hoSo->MaTrangThai,
                    'ten_trang_thai' => $trangThaiNames[$hoSo->MaTrangThai] ?? 'Unknown',
                    'loai_chinh_sach' => $hoSo->loaiChinhSach?->TenLoaiCS ?? 'N/A',
                    'dot_thu' => $hoSo->dotThuHoSo?->TenDot ?? 'N/A',
                    'ngay_nop' => $hoSo->NgayNop?->format('Y-m-d'),
                    'nhat_ky' => $nhatKyFormatted,
                ],
                'message' => 'Chi tiết nhật ký xét duyệt'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hồ sơ không tìm thấy'
            ], 404);
        } catch (Exception $e) {
            Log::error('NhatKyXetDuyetController::show - Error: ' . $e->getMessage(), [
                'ma_ho_so' => $maHoSo
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu'
            ], 500);
        }
    }

    /**
     * GET /api/ho-so/nhat-ky-tat-ca
     *
     * Xem tóm tắt nhật ký tất cả hồ sơ của sinh viên
     * Mỗi hồ sơ hiển thị hành động gần nhất
     *
     * Query Parameters:
     * - per_page: Số bản ghi/trang (default: 20)
     * - page: Trang (default: 1)
     *
     * Response:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "ma_ho_so": 1,
     *       "loai_chinh_sach": "Miễn giảm học phí (BM.01)",
     *       "dot_thu": "Đợt 1 - HK1 2025-2026",
     *       "trang_thai_hien_tai": "Đã duyệt",
     *       "hanh_dong_gan_nhat": "Trưởng phòng duyệt",
     *       "vai_tro_gan_nhat": "Trưởng phòng CTSV",
     *       "ngay_hanh_dong": "2026-06-10 14:20:00",
     *       "ngay_nop": "2026-06-09"
     *     }
     *   ],
     *   "pagination": {...},
     *   "message": "Danh sách nhật ký tất cả hồ sơ"
     * }
     */
    public function tatCa(Request $request)
    {
        try {
            $user = Auth::user();
            $maSinhVien = $user->sinhVien?->MaSoSV;

            if (!$maSinhVien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Người dùng không phải là sinh viên'
                ], 403);
            }

            // Get all hồ sơ của sinh viên
            $hoSos = HoSo::where('MaNguoiDung', $user->MaNguoiDung)
                ->with([
                    'loaiChinhSach',
                    'dotThuHoSo',
                    'trangThai',
                    'nhatKyXetDuyets' => function ($q) {
                        $q->orderBy('ThoiGian', 'desc')->limit(1);
                    },
                    'nhatKyXetDuyets.nguoiThucHien',
                ])
                ->orderBy('NgayNop', 'desc')
                ->paginate(min($request->input('per_page', 20), 100));

            $trangThaiNames = $this->getTrangThaiNames();
            $vaiTroNames = $this->getVaiTroNames();
            $hanhDongNames = $this->getHanhDongNames();

            // Format response
            $data = $hoSos->map(function ($hoSo) use ($trangThaiNames, $vaiTroNames, $hanhDongNames) {
                $hanhDongGanNhat = $hoSo->nhatKyXetDuyets->first();
                $vaiTroGanNhat = 'N/A';
                $hanhDongName = 'N/A';
                $ngayHanhDong = 'N/A';

                if ($hanhDongGanNhat) {
                    $vaiTroGanNhat = $vaiTroNames[$hanhDongGanNhat->nguoiThucHien?->MaVaiTro] ?? 'Unknown';
                    $hanhDongName = $hanhDongNames[$hanhDongGanNhat->HanhDong] ?? $hanhDongGanNhat->HanhDong;
                    $ngayHanhDong = $hanhDongGanNhat->ThoiGian;
                }

                return [
                    'ma_ho_so' => $hoSo->MaHoSo,
                    'loai_chinh_sach' => $hoSo->loaiChinhSach?->TenLoaiCS ?? 'N/A',
                    'dot_thu' => $hoSo->dotThuHoSo?->TenDot ?? 'N/A',
                    'trang_thai_hien_tai' => $trangThaiNames[$hoSo->MaTrangThai] ?? 'Unknown',
                    'hanh_dong_gan_nhat' => $hanhDongName,
                    'vai_tro_gan_nhat' => $vaiTroGanNhat,
                    'ngay_hanh_dong' => $ngayHanhDong,
                    'ngay_nop' => $hoSo->NgayNop?->format('Y-m-d'),
                ];
            });

            Log::info('NhatKyXetDuyetController::tatCa - Listed all audit trails', [
                'ma_sinh_vien' => $maSinhVien,
                'total' => $hoSos->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $hoSos->total(),
                    'per_page' => $hoSos->perPage(),
                    'current_page' => $hoSos->currentPage(),
                    'last_page' => $hoSos->lastPage(),
                    'from' => $hoSos->firstItem(),
                    'to' => $hoSos->lastItem(),
                ],
                'message' => 'Danh sách nhật ký tất cả hồ sơ'
            ], 200);
        } catch (Exception $e) {
            Log::error('NhatKyXetDuyetController::tatCa - Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý yêu cầu'
            ], 500);
        }
    }
}
