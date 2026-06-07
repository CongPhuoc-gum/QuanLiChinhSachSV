<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CanBo Model
 * 
 * Thông tin cá nhân cán bộ (quan hệ 1-1 với NGUOI_DUNG)
 * 5 vai trò: Sinh viên, Cán bộ CTSV, Trưởng phòng, Ban Giám hiệu, Tài vụ
 */
class CanBo extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaNguoiDung';
    protected $keyType = 'int';
    public $incrementing = false;
    protected $table = 'CAN_BO';
    protected $fillable = [
        'MaNhanVien',
        'HoTen',
        'PhongBan',
        'ChucVu',
        'SoDienThoai',
    ];

    /**
     * Relationship: Một cán bộ là một người dùng
     */
    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiDung', 'MaNguoiDung');
    }
}
