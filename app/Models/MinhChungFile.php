<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MinhChungFile Model
 * 
 * File minh chứng hồ sơ lưu trên Cloudinary
 */
class MinhChungFile extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaMinhChung';
    protected $table = 'MINH_CHUNG_FILE';
    protected $fillable = [
        'MaHoSo',
        'MaLoaiGiayTo',
        'URL_Cloudinary',
        'PublicId',
    ];

    protected function casts(): array
    {
        return [
            'NgayTaiLen' => 'datetime',
        ];
    }

    /**
     * Relationship: Một minh chứng file thuộc một hồ sơ
     */
    public function hoSo(): BelongsTo
    {
        return $this->belongsTo(HoSo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một minh chứng file thuộc một loại giấy tờ
     */
    public function loaiGiayTo(): BelongsTo
    {
        return $this->belongsTo(LoaiGiayTo::class, 'MaLoaiGiayTo', 'MaLoaiGiayTo');
    }
}
