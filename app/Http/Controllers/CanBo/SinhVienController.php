<?php

namespace App\Http\Controllers\CanBo;

use App\Http\Controllers\Controller;
use App\Models\NguoiDung;
use App\Models\SinhVien;
use App\Models\VaiTro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SinhVienController extends Controller
{
    /**
     * GET /api/can-bo/sinh-vien
     * Danh sách sinh viên với filter
     */
    public function index(Request $request)
    {
        try {
            $query = SinhVien::with(['nguoiDung', 'lop.khoa']);

            // Filter theo trang_thai
            if ($request->filled('trang_thai')) {
                $trangThai = $request->query('trang_thai');  // dang_hoc, bao_luu, tot_nghiep
                $mapping = [
                    'dang_hoc' => ['is_active' => 1, 'is_blocked' => 0],
                    'bao_luu' => ['is_active' => 0, 'is_blocked' => 0],
                    'tot_nghiep' => ['is_blocked' => 1],
                ];

                if (isset($mapping[$trangThai])) {
                    foreach ($mapping[$trangThai] as $col => $val) {
                        $query->whereHas('nguoiDung', function ($q) use ($col, $val) {
                            $q->where($col, $val);
                        });
                    }
                }
            }

            // Filter theo khoa
            if ($request->filled('khoa')) {
                $query->whereHas('lop.khoa', function ($q) use ($request) {
                    $q->where('MaKhoa', $request->query('khoa'));
                });
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->query('search');
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('MaSoSV', 'like', "%$search%")
                        ->orWhere('HoTen', 'like', "%$search%")
                        ->orWhereHas('nguoiDung', function ($qq) use ($search) {
                            $qq->where('Email', 'like', "%$search%");
                        });
                });
            }

            $perPage = $request->query('per_page', 20);
            $sinhViens = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $sinhViens->items(),
                'pagination' => [
                    'total' => $sinhViens->total(),
                    'per_page' => $sinhViens->perPage(),
                    'current_page' => $sinhViens->currentPage(),
                    'last_page' => $sinhViens->lastPage(),
                ],
                'message' => 'Danh sách sinh viên'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in SinhVienController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách sinh viên'
            ], 500);
        }
    }

    /**
     * GET /api/can-bo/sinh-vien/{id}
     * Chi tiết sinh viên
     */
    public function show($maNguoiDung)
    {
        try {
            $sinhVien = SinhVien::with(['nguoiDung', 'lop.khoa', 'taiKhoanNganHangs'])
                ->findOrFail($maNguoiDung);

            $user = $sinhVien->nguoiDung;

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
                    'trang_thai' => $this->getTrangThaiSV($user),
                    'tai_khoan_ngan_hang' => $sinhVien->taiKhoanNganHangs->map(function ($tk) {
                        return [
                            'so_tai_khoan' => $tk->SoTaiKhoan,
                            'ten_ngan_hang' => $tk->TenNganHang,
                            'is_default' => $tk->IsDefault,
                        ];
                    }),
                ],
                'message' => 'Chi tiết sinh viên'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in SinhVienController@show', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sinh viên'
            ], 404);
        }
    }

    /**
     * PUT /api/can-bo/sinh-vien/{id}/trang-thai
     * Cập nhật trạng thái sinh viên
     * trang_thai: dang_hoc, bao_luu, tot_nghiep
     */
    public function updateTrangThai($maNguoiDung, Request $request)
    {
        try {
            $validated = $request->validate([
                'trang_thai' => 'required|in:dang_hoc,bao_luu,tot_nghiep',
                'ghi_chu' => 'nullable|string|max:500',
            ]);

            $user = NguoiDung::findOrFail($maNguoiDung);
            $sinhVien = SinhVien::findOrFail($maNguoiDung);

            DB::transaction(function () use ($user, $sinhVien, $validated) {
                $trangThai = $validated['trang_thai'];

                if ($trangThai == 'dang_hoc') {
                    $user->update(['is_active' => 1, 'is_blocked' => 0]);
                } elseif ($trangThai == 'bao_luu') {
                    $user->update(['is_active' => 0, 'is_blocked' => 0]);
                } elseif ($trangThai == 'tot_nghiep') {
                    $user->update(['is_blocked' => 1]);
                }

                // Log
                \Log::info('Updated student status', [
                    'ma_nguoi_dung' => $maNguoiDung,
                    'trang_thai' => $trangThai,
                    'ghi_chu' => $validated['ghi_chu'] ?? null,
                    'updated_by' => Auth::user()->MaNguoiDung,
                ]);

                $this->logSystemAction(Auth::user(), 'cap_nhat_trang_thai_sinh_vien',
                    "Cập nhật trạng thái: {$trangThai} | SV: {$sinhVien->MaSoSV}");
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_so_sv' => $sinhVien->MaSoSV,
                    'ho_ten' => $sinhVien->HoTen,
                    'trang_thai' => $validated['trang_thai'],
                ],
                'message' => 'Cập nhật trạng thái thành công'
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error in SinhVienController@updateTrangThai', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật trạng thái'
            ], 500);
        }
    }

    /**
     * POST /api/can-bo/sinh-vien/import-excel
     * Import sinh viên từ file Excel
     */
    public function importExcel(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls|max:5120',
            ]);

            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->path());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            DB::transaction(function () use ($rows, &$successCount, &$errorCount, &$errors) {
                // Skip header row
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];

                    if (empty($row[0]))
                        continue;  // Skip empty rows

                    try {
                        $maSoSV = trim($row[4] ?? '');  // Column E

                        // Check if exists
                        $existing = SinhVien::where('MaSoSV', $maSoSV)->first();
                        if ($existing) {
                            $errorCount++;
                            $errors[] = [
                                'row' => $i + 1,
                                'ma_so_sv' => $maSoSV,
                                'reason' => 'MSSV đã tồn tại',
                            ];
                            continue;
                        }

                        // Extract CMND (column 3)
                        $cmnd = trim($row[3] ?? '');
                        $password = $maSoSV . substr($cmnd, -4);

                        // Create NguoiDung
                        $email = $maSoSV . '@ute.udn.vn';
                        $user = NguoiDung::create([
                            'Email' => $email,
                            'MatKhau' => Hash::make($password),
                            'MaVaiTro' => 2,  // sinh_vien
                            'TrangThai' => 1,
                        ]);

                        // Create SinhVien
                        SinhVien::create([
                            'MaNguoiDung' => $user->MaNguoiDung,
                            'MaSoSV' => $maSoSV,
                            'HoTen' => trim($row[0] ?? ''),
                            'NgaySinh' => $row[1] ?? null,
                            'GioiTinh' => $row[2] ?? null,
                            'CCCD' => $cmnd,
                            'MaLop' => $row[5] ?? null,
                            'SoDienThoai' => trim($row[7] ?? ''),
                            'DiaChiThuongTru' => trim($row[8] ?? ''),
                            'TinhThuongTru' => trim($row[9] ?? ''),
                            'DanToc' => trim($row[10] ?? ''),
                        ]);

                        $successCount++;
                    } catch (\Exception $e) {
                        $errorCount++;
                        $errors[] = [
                            'row' => $i + 1,
                            'ma_so_sv' => $row[4] ?? 'unknown',
                            'reason' => $e->getMessage(),
                        ];
                    }
                }

                // Log activity
                $this->logSystemAction(Auth::user(), 'import_sinh_vien_excel',
                    "Nhập {$successCount} sinh viên, {$errorCount} lỗi");
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'errors' => $errors,
                ],
                'message' => "Nhập {$successCount} sinh viên thành công, {$errorCount} lỗi"
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in SinhVienController@importExcel', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi nhập file Excel'
            ], 500);
        }
    }

    /**
     * Helper: Xác định trạng thái sinh viên
     */
    private function getTrangThaiSV($user)
    {
        if ($user->is_blocked)
            return 'tot_nghiep';
        if (!$user->is_active)
            return 'bao_luu';
        return 'dang_hoc';
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
