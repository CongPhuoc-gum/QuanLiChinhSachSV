<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NhatKyXetDuyet Model
 * 
 * Nhật ký chi tiết từng thao tác xét duyệt hồ sơ
 * Audit log từng thao tác của cán bộ
 */
class NhatKyXetDuyet extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaNhatKy';
    protected $table = 'NHAT_KY_XET_DUYET';
    protected $fillable = [
        'MaHoSo',
        'MaNguoiThucHien',
        'HanhDong',
        'TrangThaiTruoc',
        'TrangThaiSau',
        'GhiChu',
        'MayTinh',
    ];

    protected function casts(): array
    {
        return [
            'ThoiGian' => 'datetime',
        ];
    }

    /**
     * Relationship: Một nhật ký xét duyệt thuộc một hồ sơ
     */
    public function hoSo(): BelongsTo
    {
        return $this->belongsTo(HoSo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một nhật ký xét duyệt được thực hiện bởi một người dùng
     */
    public function nguoiThucHien(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiThucHien', 'MaNguoiDung');
    }

    /**
     * Relationship: Trạng thái trước
     */
    public function trangThaiTruoc(): BelongsTo
    {
        return $this->belongsTo(TrangThai::class, 'TrangThaiTruoc', 'MaTrangThai');
    }

    /**
     * Relationship: Trạng thái sau
     */
    public function trangThaiSau(): BelongsTo
    {
        return $this->belongsTo(TrangThai::class, 'TrangThaiSau', 'MaTrangThai');
    }
}
