<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('LICH_SU_TKB', function (Blueprint $table) {
            $table->id('MaLichSuTKB');
            $table->unsignedBigInteger('MaSinhVien')->comment('FK to SINH_VIEN.MaNguoiDung');
            $table->string('MaMonHoc', 20)->comment('Mã môn học');
            $table->string('TenMonHoc', 200);
            $table->integer('SoTinChi')->default(3);
            $table->string('HocKy', 10);  // 1, 2, 3...
            $table->string('NamHoc', 20);  // 2023-2024
            $table->boolean('IsHocLai')->default(0)->comment('1=học lại (không tính diện miễn giảm)');
            $table->timestamp('NgayTao')->useCurrent();
            $table->timestamp('NgayCapNhat')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('MaSinhVien')->references('MaNguoiDung')->on('SINH_VIEN')->onDelete('cascade');
            $table->index(['MaSinhVien', 'HocKy', 'NamHoc']);
            $table->index(['MaSinhVien', 'IsHocLai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LICH_SU_TKB');
    }
};
