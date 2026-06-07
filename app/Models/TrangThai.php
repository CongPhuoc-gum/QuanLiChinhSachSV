<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TrangThai Model
 * 
 * Trạng thái quy trình xét duyệt hồ sơ:
 * 1 = Chờ nộp hồ sơ
 * 2 = Chờ thẩm định
 * 3 = Đang bổ sung
 * 4 = Chờ Trưởng phòng duyệt
 * 5 = Chờ Ban Giám hiệu duyệt
 * 6 = Đã duyệt
 * 7 = Đã chi trả
 * 8 = Từ chối
 * 9 = Đã hủy
 */
class TrangThai extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaTrangThai';
    protected $keyType = 'int';
    public $incrementing = false;
    protected $table = 'TRANG_THAI';
    protected $fillable = [
        'MaTrangThai',
        'TenTrangThai',
        'MoTa',
    ];

    /**
     * Relationship: Một trạng thái có nhiều hồ sơ
     */
    public function hoSos(): HasMany
    {
        return $this->hasMany(HoSo::class, 'MaTrangThai', 'MaTrangThai');
    }

    /**
     * Relationship: Một trạng thái có nhiều nhật ký xét duyệt (trạng thái trước)
     */
    public function nhatKyTruocs(): HasMany
    {
        return $this->hasMany(NhatKyXetDuyet::class, 'TrangThaiTruoc', 'MaTrangThai');
    }

    /**
     * Relationship: Một trạng thái có nhiều nhật ký xét duyệt (trạng thái sau)
     */
    public function nhatKySaus(): HasMany
    {
        return $this->hasMany(NhatKyXetDuyet::class, 'TrangThaiSau', 'MaTrangThai');
    }
}
