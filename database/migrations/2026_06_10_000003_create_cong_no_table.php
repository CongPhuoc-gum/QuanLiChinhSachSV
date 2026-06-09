<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('CONG_NO')) {
            Schema::create('CONG_NO', function (Blueprint $table) {
                $table->id('MaCongNo');
                $table->unsignedBigInteger('MaSinhVien')->comment('FK to SINH_VIEN.MaNguoiDung');
                $table->unsignedBigInteger('MaHoSo')->nullable()->comment('FK to HO_SO.MaHoSo');
                $table->string('HocKy', 10);
                $table->string('NamHoc', 20);
                $table->decimal('HocPhiPhaiDong', 15, 2)->default(0);
                $table->decimal('SoTienMienGiam', 15, 2)->default(0);
                $table->decimal('SoTienPhaiDong', 15, 2)->default(0)->storedAs('HocPhiPhaiDong - SoTienMienGiam');
                $table->decimal('SoTienDaDong', 15, 2)->default(0);
                $table->decimal('TienDuMGHP', 15, 2)->default(0)->comment('Tiền dư miễn giảm để hoàn trả');
                $table->enum('TrangThai', ['cho_dong', 'da_dong', 'qua_han'])->default('cho_dong');
                $table->timestamp('NgayCapNhat')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('MaSinhVien')->references('MaNguoiDung')->on('SINH_VIEN')->onDelete('cascade');
                $table->foreign('MaHoSo')->references('MaHoSo')->on('HO_SO')->onDelete('set null');
                $table->index(['MaSinhVien', 'HocKy', 'NamHoc']);
                $table->index(['TrangThai']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('CONG_NO');
    }
};
