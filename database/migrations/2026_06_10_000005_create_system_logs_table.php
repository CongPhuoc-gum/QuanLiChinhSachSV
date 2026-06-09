<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('SYSTEM_LOGS')) {
            Schema::create('SYSTEM_LOGS', function (Blueprint $table) {
                $table->id('MaLog');
                $table->unsignedBigInteger('MaNguoiDung')->nullable();
                $table->string('VaiTro', 50)->nullable();
                $table->string('HanhDong', 100)->comment('Ví dụ: cap_nhat_sinh_vien, import_excel, etc');
                $table->string('ChiTiet', 255)->nullable();
                $table->string('IPAddress', 50)->nullable();
                $table->string('UserAgent', 255)->nullable();
                $table->timestamp('ThoiGian')->useCurrent();

                $table->index(['MaNguoiDung', 'ThoiGian']);
                $table->index(['HanhDong', 'ThoiGian']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('SYSTEM_LOGS');
    }
};
