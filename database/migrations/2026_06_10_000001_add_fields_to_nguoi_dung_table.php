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
        Schema::table('NGUOI_DUNG', function (Blueprint $table) {
            // Thêm trường để hỗ trợ Phase 2
            if (!Schema::hasColumn('NGUOI_DUNG', 'is_active')) {
                $table->boolean('is_active')->default(1)->after('TrangThai')->comment('1=hoạt động, 0=khóa tạm thời (bảo lưu)');
            }
            if (!Schema::hasColumn('NGUOI_DUNG', 'is_blocked')) {
                $table->boolean('is_blocked')->default(0)->after('is_active')->comment('1=bị chặn vĩnh viễn (tốt nghiệp)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('NGUOI_DUNG', function (Blueprint $table) {
            if (Schema::hasColumn('NGUOI_DUNG', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('NGUOI_DUNG', 'is_blocked')) {
                $table->dropColumn('is_blocked');
            }
        });
    }
};
