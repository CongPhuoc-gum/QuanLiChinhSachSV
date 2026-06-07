<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QuyetDinhBanHanh Model
 * 
 * Quyết định ban hành chính thức, lưu file PDF
 */
class QuyetDinhBanHanh extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaQuyetDinh';
    protected $table = 'QUYET_DINH_BAN_HANH';
    protected $fillable = [
        'SoQD',
        'NgayBanHanh',
        'URL_FilePDF',
        'MaHoSo',
        'MaNguoiKy',
    ];

    protected function casts(): array
    {
        return [
            'NgayBanHanh' => 'date',
        ];
    }

    /**
     * Relationship: Một quyết định ban hành thuộc một hồ sơ
     */
    public function hoSo(): BelongsTo
    {
        return $this->belongsTo(HoSo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một quyết định ban hành được ký bởi một người dùng
     */
    public function nguoiKy(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiKy', 'MaNguoiDung');
    }
}
