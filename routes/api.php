<?php

use App\Http\Controllers\BanGiamHieu\DashboardController;
use App\Http\Controllers\BanGiamHieu\HoSoController as BanGiamHieuHoSoController;
use App\Http\Controllers\CanBo\DotThuController;
use App\Http\Controllers\CanBo\SinhVienController;
use App\Http\Controllers\CanBo\TKBController;
use App\Http\Controllers\TaiVu\LenhChiController;
use App\Http\Controllers\TruongPhong\HoSoController as TruongPhongHoSoController;
use App\Http\Controllers\TruongPhong\QuyetDinhController;
use App\Http\Controllers\AiChatbotController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HoSoController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==================== AUTHENTICATION ====================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// ==================== PROTECTED ROUTES ====================
Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // ==================== PROFILE (PHASE A1) ====================
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // ==================== AI CHATBOT ====================
    // POST /api/chatbot/ask - Gửi câu hỏi đến AI
    // Body: { "question": "...", "phien_id": null }
    // Response: { "success": true, "phien_id": 1, "answer": "...", "citations": [...] }
    Route::post('/chatbot/ask', [AiChatbotController::class, 'ask']);

    // GET /api/chatbot/phien/{phienId} - Lấy lịch sử hội thoại 1 phiên
    // Response: { "success": true, "phien": {...}, "messages": [...] }
    Route::get('/chatbot/phien/{phienId}', [AiChatbotController::class, 'getPhien']);

    // POST /api/chatbot/phien/{phienId}/danh-gia - Đánh giá phiên chat
    // Body: { "diem": 4, "ghi_chu": "..." }
    Route::post('/chatbot/phien/{phienId}/danh-gia', [AiChatbotController::class, 'ratePhien']);

    // GET /api/chatbot/phien-list - Danh sách phiên chat của SV
    // Response: { "success": true, "data": [...], "total": 5 }
    Route::get('/chatbot/phien-list', [AiChatbotController::class, 'listPhien']);

    // ==================== HỒ SƠ & MINH CHỨNG (DAY 2) ====================
    // POST /api/ho-so/store - Nộp hồ sơ mới + minh chứng
    // Body: multipart/form-data { "ma_loai_cs": 1, "ma_dot": 1, "form_data": "{...}", "minh_chungs": [files...] }
    Route::post('/ho-so/store', [HoSoController::class, 'store']);

    // GET /api/ho-so - Danh sách hồ sơ của SV
    Route::get('/ho-so', [HoSoController::class, 'index']);

    // GET /api/ho-so/{maHoSo} - Chi tiết hồ sơ
    Route::get('/ho-so/{maHoSo}', [HoSoController::class, 'show']);

    // POST /api/ho-so/{maHoSo}/minh-chung-them - Thêm minh chứng
    Route::post('/ho-so/{maHoSo}/minh-chung-them', [HoSoController::class, 'addMinhChung']);

    // DELETE /api/ho-so/{maHoSo}/minh-chung/{maMinhChung} - Xóa minh chứng
    Route::delete('/ho-so/{maHoSo}/minh-chung/{maMinhChung}', [HoSoController::class, 'deleteMinhChung']);

    // ==================== NHẬT KỲ XÉT DUYỆT (PHASE X3) ====================
    // GET /api/ho-so/{maHoSo}/nhat-ky - Xem nhật ký chi tiết một hồ sơ
    Route::get('/ho-so/{maHoSo}/nhat-ky', [\App\Http\Controllers\NhatKyXetDuyetController::class, 'show']);

    // GET /api/ho-so/nhat-ky-tat-ca - Xem nhật ký tất cả hồ sơ
    Route::get('/ho-so/nhat-ky-tat-ca', [\App\Http\Controllers\NhatKyXetDuyetController::class, 'tatCa']);

    // ==================== OCR & AUTO-COMPARISON (DAY 3) ====================
    // POST /api/ho-so/{maHoSo}/process-ocr - Kích hoạt xử lý OCR (Gemini Vision + Auto-Comparison)
    // Body: { "process_all": false }
    // Response: { "success": true, "analysis_results": [...] }
    Route::post('/ho-so/{maHoSo}/process-ocr', [HoSoController::class, 'processOcr']);

    // ==================== ĐĂNG KÝ HỌC PHẦN (PHASE X1) ====================
    // GET /api/dang-ky-hoc-phan/lop-mo - Xem danh sách lớp học phần đang mở
    Route::get('/dang-ky-hoc-phan/lop-mo', [\App\Http\Controllers\DangKyHocPhanController::class, 'lopMo']);

    // POST /api/dang-ky-hoc-phan - Đăng ký học phần
    Route::post('/dang-ky-hoc-phan', [\App\Http\Controllers\DangKyHocPhanController::class, 'store']);

    // GET /api/dang-ky-hoc-phan/cua-toi - Xem danh sách môn đã đăng ký
    Route::get('/dang-ky-hoc-phan/cua-toi', [\App\Http\Controllers\DangKyHocPhanController::class, 'cuaToi']);

    // DELETE /api/dang-ky-hoc-phan/{maTKB} - Hủy đăng ký học phần
    Route::delete('/dang-ky-hoc-phan/{maTKB}', [\App\Http\Controllers\DangKyHocPhanController::class, 'destroy']);

    // ==================== CÁN BỘ CTSV (PHASE A-B) ====================
    Route::prefix('can-bo')->middleware('check.admin.role')->group(function () {
        // Phase A1: Sinh viên
        Route::get('/sinh-vien', [SinhVienController::class, 'index']);
        Route::get('/sinh-vien/{id}', [SinhVienController::class, 'show']);
        Route::put('/sinh-vien/{id}/trang-thai', [SinhVienController::class, 'updateTrangThai']);
        Route::post('/sinh-vien/import-excel', [SinhVienController::class, 'importExcel']);

        // Phase A3: TKB
        Route::post('/tkb/import-excel', [TKBController::class, 'importExcel']);
        Route::get('/tkb/{maSoSV}', [TKBController::class, 'getTKB']);

        // Phase B: Đợt thu
        Route::get('/dot-thu', [DotThuController::class, 'index']);
        Route::post('/dot-thu', [DotThuController::class, 'store']);
        Route::get('/dot-thu/{id}', [DotThuController::class, 'show']);
        Route::put('/dot-thu/{id}', [DotThuController::class, 'update']);
        Route::delete('/dot-thu/{id}', [DotThuController::class, 'destroy']);
        Route::put('/dot-thu/{id}/dong', [DotThuController::class, 'dongDotThu']);
    });

    // ==================== TRƯỞNG PHÒNG CTSV (PHASE C-D) ====================
    Route::prefix('truong-phong')->middleware('check.admin.role')->group(function () {
        // Phase C1: Hồ sơ
        Route::get('/ho-so', [TruongPhongHoSoController::class, 'index']);
        Route::get('/ho-so/{id}', [TruongPhongHoSoController::class, 'show']);
        Route::put('/ho-so/{id}/duyet', [TruongPhongHoSoController::class, 'duyet']);

        // Phase D: Quyết định
        Route::get('/quyet-dinh', [QuyetDinhController::class, 'index']);
        Route::post('/quyet-dinh/xuat-bm03', [QuyetDinhController::class, 'xuatBM03']);
        Route::get('/quyet-dinh/{id}/danh-sach-excel', [QuyetDinhController::class, 'downloadExcel']);
    });

    // ==================== BAN GIÁM HIỆU (PHASE C2-F) ====================
    Route::prefix('ban-giam-hieu')->middleware('check.admin.role')->group(function () {
        // Phase C2: Hồ sơ
        Route::get('/ho-so', [BanGiamHieuHoSoController::class, 'index']);
        Route::get('/ho-so/{id}', [BanGiamHieuHoSoController::class, 'show']);
        Route::put('/ho-so/{id}/duyet', [BanGiamHieuHoSoController::class, 'duyet']);

        // Phase F: Dashboard
        Route::get('/dashboard', [DashboardController::class, 'dashboard']);
    });

    // ==================== TÀI VỤ (PHASE E) ====================
    Route::prefix('tai-vu')->middleware('check.admin.role')->group(function () {
        // Phase E5: Lệnh chi
        Route::get('/lenh-chi', [LenhChiController::class, 'index']);
        Route::get('/lenh-chi/{id}', [LenhChiController::class, 'show']);
        Route::put('/lenh-chi/{id}/xac-nhan', [LenhChiController::class, 'xacNhanChuyen']);
        Route::get('/cong-no', [LenhChiController::class, 'congNo']);
    });

    // ==================== ADMIN DASHBOARD (DAY 4) ====================
    Route::middleware('check.admin.role')->prefix('admin')->group(function () {
        // Quản lý hồ sơ
        Route::get('/ho-so', [\App\Http\Controllers\Admin\AdminHoSoController::class, 'index']);
        Route::get('/ho-so/{id}', [\App\Http\Controllers\Admin\AdminHoSoController::class, 'show']);
        Route::put('/ho-so/{id}/duyet', [\App\Http\Controllers\Admin\AdminHoSoController::class, 'duyet']);

        // Dashboard thống kê
        Route::get('/dashboard-stats', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'stats']);
        Route::get('/dashboard-ai-warning', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'aiWarnings']);
        Route::get('/dashboard-ai-need-review', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'aiNeedReview']);

        // Xuất dữ liệu
        Route::get('/export/ho-so-csv', [\App\Http\Controllers\Admin\AdminExportController::class, 'exportHoSoCSV']);
        Route::get('/export/summary', [\App\Http\Controllers\Admin\AdminExportController::class, 'exportSummary']);
    });
});
