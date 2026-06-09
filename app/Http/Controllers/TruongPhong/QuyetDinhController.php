<?php

namespace App\Http\Controllers\TruongPhong;

use App\Http\Controllers\Controller;
use App\Models\HoSo;
use App\Models\QuyetDinhBanHanh;
use App\Models\SinhVien;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class QuyetDinhController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * GET /api/truong-phong/quyet-dinh
     * Danh sách quyết định đã ban hành
     */
    public function index(Request $request)
    {
        try {
            $query = QuyetDinhBanHanh::with('dotThuHoSo');

            // Filter theo loại chính sách
            if ($request->filled('loai_cs')) {
                $query->whereHas('dotThuHoSo', function ($q) use ($request) {
                    $q->where('MaLoaiCS', $request->query('loai_cs'));
                });
            }

            $perPage = $request->query('per_page', 20);
            $quyetDinhs = $query->orderBy('NgayBanHanh', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $quyetDinhs->items(),
                'pagination' => [
                    'total' => $quyetDinhs->total(),
                    'per_page' => $quyetDinhs->perPage(),
                    'current_page' => $quyetDinhs->currentPage(),
                    'last_page' => $quyetDinhs->lastPage(),
                ],
                'message' => 'Danh sách quyết định'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in TruongPhong\QuyetDinhController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách'
            ], 500);
        }
    }

    /**
     * POST /api/truong-phong/quyet-dinh/xuat-bm03
     * Xuất file BM.03 (Quyết định miễn giảm học phí)
     */
    public function xuatBM03(Request $request)
    {
        try {
            $validated = $request->validate([
                'dot_thu_id' => 'required|exists:DOT_THU_HO_SO,MaDot',
                'so_quyet_dinh' => 'required|string|max:50',
                'nam_hoc_tu' => 'required|integer',
                'nam_hoc_den' => 'required|integer',
            ]);

            // Lấy tất cả hồ sơ đã duyệt của đợt này
            $hoSos = HoSo::where('MaDot', $validated['dot_thu_id'])
                ->where('MaTrangThai', 6)  // Đã duyệt
                ->with('nguoiDung.sinhVien')
                ->get();

            if ($hoSos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có hồ sơ đã duyệt để xuất'
                ], 422);
            }

            // Phân loại theo mức hưởng
            $mien100 = [];
            $giam70 = [];
            $giam50 = [];

            foreach ($hoSos as $hoSo) {
                $phanTichAI = $hoSo->phanTichAI;
                if ($phanTichAI && isset($phanTichAI->KetQuaDoiChieu['muc_huong'])) {
                    $mucHuong = $phanTichAI->KetQuaDoiChieu['muc_huong'];
                    if ($mucHuong == 100)
                        $mien100[] = $hoSo;
                    elseif ($mucHuong == 70)
                        $giam70[] = $hoSo;
                    elseif ($mucHuong == 50)
                        $giam50[] = $hoSo;
                }
            }

            // Tạo document Word
            $phpWord = new PhpWord();
            $section = $phpWord->addSection();

            // Header
            $header = $section->addHeader();
            $header->addText('UBND TP ĐÀ NẴNG - TRƯỜNG ĐẠI HỌC SƯ PHẠM KỸ THUẬT',
                array('align' => 'center', 'bold' => true));

            // Tiêu đề
            $section->addParagraph();
            $section->addText("Số: {$validated['so_quyet_dinh']}/QĐ-ĐHSPKT",
                array('align' => 'right', 'bold' => true, 'size' => 12));
            $section->addText('Đà Nẵng, ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y'),
                array('align' => 'right', 'size' => 11));

            $section->addParagraph();
            $title = $section->addTextRun();
            $title->addText('VỀ VIỆC MIỄN, GIẢM HỌC PHÍ HỌC KỲ ' . $validated['nam_hoc_tu']
                . " NĂM HỌC {$validated['nam_hoc_tu']}/{$validated['nam_hoc_den']}");
            $title->getFont()->setBold(true)->setSize(13);

            // Nội dung
            $section->addParagraph();
            $section->addText('ĐIỀU 1: Quyết định miễn, giảm học phí cho các sinh viên sau:', array('bold' => true));

            $section->addList(array(
                'Miễn 100% học phí: ' . count($mien100) . ' sinh viên',
                'Giảm 70% học phí: ' . count($giam70) . ' sinh viên',
                'Giảm 50% học phí: ' . count($giam50) . ' sinh viên',
            ));

            $section->addParagraph('Tổng cộng: ' . count($hoSos) . ' sinh viên');

            $section->addParagraph();
            $section->addText('ĐIỀU 2: Danh sách chi tiết kèm theo', array('bold' => true));

            // Save to temp file
            $fileName = 'QD_' . date('YmdHis') . '.docx';
            $tempPath = storage_path('app/temp/' . $fileName);
            if (!is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $xmlWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $xmlWriter->save($tempPath);

            // Upload lên Cloudinary
            $result = $this->cloudinaryService->upload($tempPath, 'quanlics/quyet_dinh/' . date('Y/m/d') . '/');
            if (!$result['success']) {
                throw new \Exception('Upload Cloudinary thất bại');
            }

            $urlBM03 = $result['url'];

            // Xuất danh sách Excel
            $excelPath = storage_path('app/temp/DanhSach_' . date('YmdHis') . '.xlsx');
            $this->xuatExcelDanhSach($hoSos, $mien100, $giam70, $giam50, $excelPath);

            // Upload Excel
            $resultExcel = $this->cloudinaryService->upload($excelPath, 'quanlics/quyet_dinh/' . date('Y/m/d') . '/');
            $urlExcel = $resultExcel['success'] ? $resultExcel['url'] : null;

            // Lưu vào DB
            DB::transaction(function () use ($validated, $urlBM03, $urlExcel) {
                QuyetDinhBanHanh::create([
                    'SoQD' => $validated['so_quyet_dinh'],
                    'MaDot' => $validated['dot_thu_id'],
                    'NgayBanHanh' => now(),
                    'URLFileBM03' => $urlBM03,
                    'URLFileExcel' => $urlExcel,
                    'MaNguoiKy' => Auth::user()->MaNguoiDung,
                    'TrangThai' => 1,
                ]);

                $this->logSystemAction(Auth::user(), 'xuat_ban_hanh_quyet_dinh',
                    "Xuất QĐ số: {$validated['so_quyet_dinh']}");
            });

            // Cleanup temp files
            @unlink($tempPath);
            @unlink($excelPath);

            return response()->json([
                'success' => true,
                'data' => [
                    'so_quyet_dinh' => $validated['so_quyet_dinh'],
                    'url_bm03' => $urlBM03,
                    'url_excel' => $urlExcel,
                    'thong_ke' => [
                        'mien_100' => count($mien100),
                        'giam_70' => count($giam70),
                        'giam_50' => count($giam50),
                        'tong_cong' => count($hoSos),
                    ],
                ],
                'message' => 'Xuất quyết định thành công'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error in TruongPhong\QuyetDinhController@xuatBM03', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xuất quyết định'
            ], 500);
        }
    }

    /**
     * GET /api/truong-phong/quyet-dinh/{id}/danh-sach-excel
     * Tải danh sách Excel
     */
    public function downloadExcel($maQD)
    {
        try {
            $quyetDinh = QuyetDinhBanHanh::findOrFail($maQD);

            if (!$quyetDinh->URLFileExcel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có file danh sách'
                ], 404);
            }

            // Redirect to Cloudinary URL
            return redirect($quyetDinh->URLFileExcel);
        } catch (\Exception $e) {
            \Log::error('Error in TruongPhong\QuyetDinhController@downloadExcel', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải file'
            ], 500);
        }
    }

    /**
     * Helper: Xuất Excel danh sách
     */
    private function xuatExcelDanhSach($hoSos, $mien100, $giam70, $giam50, $outputPath)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->setCellValue('A1', 'STT');
        $sheet->setCellValue('B1', 'Họ tên');
        $sheet->setCellValue('C1', 'MSSV');
        $sheet->setCellValue('D1', 'Lớp');
        $sheet->setCellValue('E1', 'Khoa');
        $sheet->setCellValue('F1', 'Mức hưởng (%)');
        $sheet->setCellValue('G1', 'Ghi chú');

        $row = 2;
        $stt = 1;

        // Miễn 100%
        foreach ($mien100 as $hoSo) {
            $sv = $hoSo->nguoiDung->sinhVien;
            $sheet->setCellValue("A{$row}", $stt++);
            $sheet->setCellValue("B{$row}", $sv->HoTen);
            $sheet->setCellValue("C{$row}", $sv->MaSoSV);
            $sheet->setCellValue("D{$row}", $sv->lop->TenLop ?? '');
            $sheet->setCellValue("E{$row}", $sv->lop->khoa->TenKhoa ?? '');
            $sheet->setCellValue("F{$row}", '100%');
            $sheet->setCellValue("G{$row}", 'Miễn toàn bộ');
            $row++;
        }

        // Giảm 70%
        foreach ($giam70 as $hoSo) {
            $sv = $hoSo->nguoiDung->sinhVien;
            $sheet->setCellValue("A{$row}", $stt++);
            $sheet->setCellValue("B{$row}", $sv->HoTen);
            $sheet->setCellValue("C{$row}", $sv->MaSoSV);
            $sheet->setCellValue("D{$row}", $sv->lop->TenLop ?? '');
            $sheet->setCellValue("E{$row}", $sv->lop->khoa->TenKhoa ?? '');
            $sheet->setCellValue("F{$row}", '70%');
            $sheet->setCellValue("G{$row}", 'Giảm 70%');
            $row++;
        }

        // Giảm 50%
        foreach ($giam50 as $hoSo) {
            $sv = $hoSo->nguoiDung->sinhVien;
            $sheet->setCellValue("A{$row}", $stt++);
            $sheet->setCellValue("B{$row}", $sv->HoTen);
            $sheet->setCellValue("C{$row}", $sv->MaSoSV);
            $sheet->setCellValue("D{$row}", $sv->lop->TenLop ?? '');
            $sheet->setCellValue("E{$row}", $sv->lop->khoa->TenKhoa ?? '');
            $sheet->setCellValue("F{$row}", '50%');
            $sheet->setCellValue("G{$row}", 'Giảm 50%');
            $row++;
        }

        // Auto width
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(20);

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
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
