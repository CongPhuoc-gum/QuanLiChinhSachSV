<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * DAY 3: Tạo bảng lưu trữ kết quả OCR & So khớp từ Gemini Vision
     */
    public function up(): void
    {
        Schema::create('PHAN_TICH_AI_HO_SO', function (Blueprint $table) {
            // Primary Key
            $table->id('MaPhanTich');

            // Foreign Keys
            $table->unsignedBigInteger('MaHoSo');
            $table->foreign('MaHoSo')->references('MaHoSo')->on('ho_so')->onDelete('cascade');

            // OCR & Comparison Results
            $table->string('LoaiTaiLieuOCR')->nullable();  // cccd, ho_khau, ho_ngheo, khai_sinh
            $table->longText('URLAnh')->nullable();  // URL ảnh từ Cloudinary
            $table->json('KetQuaDoiChieu')->nullable();  // Kết quả so khớp chi tiết (từ ComparisonService)

            // Metrics
            $table->float('TyLeKhop', 3, 2)->default(0.0);  // 0.0 - 1.0 (tỷ lệ trùng khớp)
            $table->float('DoTinCayOCR', 3, 2)->default(0.8);  // 0.0 - 1.0 (độ tin cậy OCR từ Gemini)

            // Discrepancies & Warnings
            $table->json('CanBaoLech')->nullable();  // Array các sai lệch phát hiện

            // Status & Timestamps
            $table->string('TrangThaiXuLy')->default('PENDING');  // APPROVED, WARNING, NEED_REVIEW, PENDING
            $table->timestamp('ThoiGianPhanTich')->nullable();
            $table->text('GhiChuAdmin')->nullable();  // Admin notes

            // Indexes
            $table->index('MaHoSo');
            $table->index('TrangThaiXuLy');
            $table->index('TyLeKhop');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('PHAN_TICH_AI_HO_SO');
    }
};
