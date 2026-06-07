<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LoaiChinhSach Model
 * 
 * Phân loại đơn chính sách theo biểu mẫu:
 * 1 = MGHP (BM.01) - Miễn Giảm Học Phí
 * 2 = TCXH (BM.02) - Trợ Cấp Xã Hội
 */
class LoaiChinhSach extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaLoaiCS';
    protected $keyType = 'int';
    public $incrementing = false;
    protected $table = 'LOAI_CHINH_SACH';
    protected $fillable = [
        'MaLoaiCS',
        'MaForm',
        'TenLoaiCS',
        'MoTa',
    ];

    /**
     * Relationship: Một loại chính sách có nhiều hồ sơ
     */
    public function hoSos(): HasMany
    {
        return $this->hasMany(HoSo::class, 'MaLoaiCS', 'MaLoaiCS');
    }
}
