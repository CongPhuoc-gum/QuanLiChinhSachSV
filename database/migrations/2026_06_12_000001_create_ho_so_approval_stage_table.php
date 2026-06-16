<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only create if table doesn't exist
        if (!Schema::hasTable('HO_SO_APPROVAL_STAGE')) {
            Schema::create('HO_SO_APPROVAL_STAGE', function (Blueprint $table) {
                $table->id('MaGiaiDoan');
                $table->unsignedBigInteger('MaHoSo')->index();
                $table->tinyInteger('GiaiDoan')->comment('1=Khoa xác nhận, 2=CTSV thẩm định, 3=Trưởng phòng, 4=BGH, 5=Hoàn tất');
                $table->unsignedInteger('NguoiXacNhan')->nullable();
                $table->timestamp('ThoiGianXacNhan')->nullable();
                $table->text('GhiChu')->nullable();
                $table->tinyInteger('TrangThai')->default(0)->comment('0=Chưa xử lý, 1=Đã xác nhận, -1=Từ chối');

                $table->foreign('MaHoSo')->references('MaHoSo')->on('HO_SO')->onDelete('cascade');
                $table->foreign('NguoiXacNhan')->references('MaNguoiDung')->on('NGUOI_DUNG')->onDelete('set null');

                $table->index(['MaHoSo', 'GiaiDoan']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('HO_SO_APPROVAL_STAGE');
    }
};
