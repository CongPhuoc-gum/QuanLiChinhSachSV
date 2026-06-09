<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('LICH_SU_TKB', function (Blueprint $table) {
            if (!Schema::hasColumn('LICH_SU_TKB', 'MaLopHocPhan')) {
                $table->unsignedBigInteger('MaLopHocPhan')->nullable()->after('MaLichSuTKB');
            }
            if (!Schema::hasColumn('LICH_SU_TKB', 'NguonNhap')) {
                $table
                    ->enum('NguonNhap', ['can_bo_import', 'sinh_vien_tu_chon'])
                    ->default('can_bo_import')
                    ->after('IsHocLai');
            }
        });
    }

    public function down(): void
    {
        Schema::table('LICH_SU_TKB', function (Blueprint $table) {
            if (Schema::hasColumn('LICH_SU_TKB', 'MaLopHocPhan')) {
                $table->dropColumn('MaLopHocPhan');
            }
            if (Schema::hasColumn('LICH_SU_TKB', 'NguonNhap')) {
                $table->dropColumn('NguonNhap');
            }
        });
    }
};
