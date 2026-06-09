<?php

namespace App\Http\Controllers\CanBo;

use App\Http\Controllers\Controller;
use App\Models\LichSuTKB;
use App\Models\SinhVien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TKBController extends Controller
{
    /**
     * POST /api/can-bo/tkb/import-excel
     * Import TKB kỳ từ file Excel
     * Columns: ma_so_sv, ma_mon_hoc, ten_mon_hoc, so_tin_chi, hoc_ky, nam_hoc, is_hoc_lai (0/1)
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
            $dupeHocLais = 0;

            DB::transaction(function () use ($rows, &$successCount, &$errorCount, &$errors, &$dupeHocLais) {
                // Skip header row
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];

                    if (empty($row[0]))
                        continue;

                    try {
                        $maSoSV = trim($row[0] ?? '');
                        $maMonHoc = trim($row[1] ?? '');
                        $tenMonHoc = trim($row[2] ?? '');
                        $soTinChi = (int) ($row[3] ?? 3);
                        $hocKy = trim($row[4] ?? '');
                        $namHoc = trim($row[5] ?? '');
                        $isHocLai = (int) ($row[6] ?? 0);

                        // Find student
                        $sinhVien = SinhVien::where('MaSoSV', $maSoSV)->first();
                        if (!$sinhVien) {
                            throw new \Exception('Không tìm thấy MSSV');
                        }

                        // Check: nếu học lại, kiểm tra xem SV có học môn này trong kỳ trước
                        if ($isHocLai) {
                            $prevHocKy = (int) $hocKy - 1;
                            if ($prevHocKy > 0) {
                                $prevRecord = LichSuTKB::where('MaSinhVien', $sinhVien->MaNguoiDung)
                                    ->where('MaMonHoc', $maMonHoc)
                                    ->where('HocKy', $prevHocKy)
                                    ->first();

                                if (!$prevRecord) {
                                    $dupeHocLais++;
                                    // Vẫn chuẩn bị lưu nhưng log warning
                                    \Log::warning('Học lại không có tiền đề', [
                                        'ma_so_sv' => $maSoSV,
                                        'ma_mon_hoc' => $maMonHoc,
                                    ]);
                                }
                            }
                        }

                        // Create LichSuTKB
                        LichSuTKB::create([
                            'MaSinhVien' => $sinhVien->MaNguoiDung,
                            'MaMonHoc' => $maMonHoc,
                            'TenMonHoc' => $tenMonHoc,
                            'SoTinChi' => $soTinChi,
                            'HocKy' => $hocKy,
                            'NamHoc' => $namHoc,
                            'IsHocLai' => $isHocLai,
                        ]);

                        $successCount++;
                    } catch (\Exception $e) {
                        $errorCount++;
                        $errors[] = [
                            'row' => $i + 1,
                            'ma_so_sv' => $row[0] ?? 'unknown',
                            'reason' => $e->getMessage(),
                        ];
                    }
                }

                // Log activity
                $this->logSystemAction(Auth::user(), 'import_tkb_excel',
                    "Nhập {$successCount} bản ghi TKB, {$errorCount} lỗi, {$dupeHocLais} học lại không tiền đề");
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'dupe_hoc_lais' => $dupeHocLais,
                    'errors' => $errors,
                ],
                'message' => "Nhập {$successCount} bản ghi TKB thành công, {$errorCount} lỗi"
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in TKBController@importExcel', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi nhập file Excel TKB'
            ], 500);
        }
    }

    /**
     * GET /api/can-bo/tkb/{maSoSV}
     * Xem TKB của sinh viên
     */
    public function getTKB($maSoSV, Request $request)
    {
        try {
            $sinhVien = SinhVien::where('MaSoSV', $maSoSV)->firstOrFail();

            $query = LichSuTKB::where('MaSinhVien', $sinhVien->MaNguoiDung);

            // Filter theo học kỳ
            if ($request->filled('hoc_ky')) {
                $query->where('HocKy', $request->query('hoc_ky'));
            }

            // Filter theo năm học
            if ($request->filled('nam_hoc')) {
                $query->where('NamHoc', $request->query('nam_hoc'));
            }

            $tkbs = $query->get();

            // Tính tổng tín chỉ (bao gồm học lại)
            $tongTinChi = $tkbs->sum('SoTinChi');
            $tinChiHocLai = $tkbs->where('IsHocLai', 1)->sum('SoTinChi');
            $tinChiRealTinh = $tongTinChi - $tinChiHocLai;

            return response()->json([
                'success' => true,
                'data' => [
                    'ma_so_sv' => $maSoSV,
                    'ho_ten' => $sinhVien->HoTen,
                    'tong_tin_chi' => $tongTinChi,
                    'tin_chi_hoc_lai' => $tinChiHocLai,
                    'tin_chi_real_tinh' => $tinChiRealTinh,
                    'mon_hoc' => $tkbs->map(function ($tkb) {
                        return [
                            'ma_mon_hoc' => $tkb->MaMonHoc,
                            'ten_mon_hoc' => $tkb->TenMonHoc,
                            'so_tin_chi' => $tkb->SoTinChi,
                            'hoc_ky' => $tkb->HocKy,
                            'nam_hoc' => $tkb->NamHoc,
                            'is_hoc_lai' => (bool) $tkb->IsHocLai,
                        ];
                    }),
                ],
                'message' => 'Lấy TKB thành công'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in TKBController@getTKB', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy TKB'
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
