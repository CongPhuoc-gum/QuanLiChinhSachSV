<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * VaiTro Model
 * 
 * 5 vai trò trong quy trình phê duyệt đa cấp:
 * 1 = Sinh viên
 * 2 = Cán bộ CTSV
 * 3 = Trưởng phòng CTSV
 * 4 = Ban Giám hiệu
 * 5 = Cán bộ Tài vụ
 */
class VaiTro extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaVaiTro';
    protected $keyType = 'int';
    public $incrementing = false;
    protected $table = 'VAI_TRO';
    protected $fillable = [
        'MaVaiTro',
        'TenVaiTro',
        'MoTa',
    ];

    /**
     * Relationship: Một vai trò có nhiều người dùng
     */
    public function nguoiDungs(): HasMany
    {
        return $this->hasMany(NguoiDung::class, 'MaVaiTro', 'MaVaiTro');
    }
}
