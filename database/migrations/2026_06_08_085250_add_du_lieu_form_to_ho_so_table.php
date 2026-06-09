<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ho_so', function (Blueprint $table) {
            // Thêm cột lưu trữ dữ liệu động của BM01/BM02 dưới dạng JSON
            $table->json('du_lieu_form')->nullable()->after('GhiChu');
        });
    }

    public function down(): void
    {
        Schema::table('ho_so', function (Blueprint $table) {
            $table->dropColumn('du_lieu_form');
        });
    }
};
