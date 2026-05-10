<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DanhMucHocPhan Model
 * 
 * Danh mục môn học chuẩn — dùng để xác định học lại
 * MaHP là định danh so sánh (không phải tên lớp)
 */
class DanhMucHocPhan extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaHP';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'DANH_MUC_HOC_PHAN';
    protected $fillable = [
        'MaHP',
        'TenHP',
        'SoTinChi',
        'DonGia',
        'Cap',
        'GhiChu',
    ];

    protected function casts(): array
    {
        return [
            'SoTinChi' => 'integer',
            'DonGia' => 'decimal:0',
        ];
    }

    /**
     * Relationship: Một môn học có nhiều lớp học phần
     */
    public function lichSuTKBs(): HasMany
    {
        return $this->hasMany(LichSuTKB::class, 'MaHP', 'MaHP');
    }
}
