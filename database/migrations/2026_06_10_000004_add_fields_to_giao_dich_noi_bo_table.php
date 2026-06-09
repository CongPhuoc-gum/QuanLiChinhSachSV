<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('GIAO_DICH_NOI_BO', function (Blueprint $table) {
            // Thêm cột nếu chưa có
            if (!Schema::hasColumn('GIAO_DICH_NOI_BO', 'MaNguoiDuyetLenh')) {
                $table->unsignedBigInteger('MaNguoiDuyetLenh')->nullable()->after('MaSinhVien')->comment('Cán bộ tài vụ xác nhận');
            }
            if (!Schema::hasColumn('GIAO_DICH_NOI_BO', 'MaGiaoDichNganHang')) {
                $table->string('MaGiaoDichNganHang', 100)->nullable()->after('TrangThai')->comment('Mã giao dịch từ ngân hàng');
            }
            if (!Schema::hasColumn('GIAO_DICH_NOI_BO', 'NgayHoanThanh')) {
                $table->timestamp('NgayHoanThanh')->nullable()->after('NgayTao')->comment('Ngày hoàn thành chuyển khoản');
            }
        });
    }

    public function down(): void
    {
        Schema::table('GIAO_DICH_NOI_BO', function (Blueprint $table) {
            if (Schema::hasColumn('GIAO_DICH_NOI_BO', 'MaNguoiDuyetLenh')) {
                $table->dropColumn('MaNguoiDuyetLenh');
            }
            if (Schema::hasColumn('GIAO_DICH_NOI_BO', 'MaGiaoDichNganHang')) {
                $table->dropColumn('MaGiaoDichNganHang');
            }
            if (Schema::hasColumn('GIAO_DICH_NOI_BO', 'NgayHoanThanh')) {
                $table->dropColumn('NgayHoanThanh');
            }
        });
    }
};
