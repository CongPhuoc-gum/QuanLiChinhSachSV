<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * MinhChungFile Model
 *
 * File minh chứng hồ sơ lưu trên Cloudinary (DAY 2)
 * Fields: MaMinhChung, MaHoSo, TenFile, DuongDanFile (URL), PublicIdCloudinary, KichThuoc, KieuFile, ThoiGianUpload
 */
class MinhChungFile extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaMinhChung';
    protected $table = 'MINH_CHUNG_FILE';

    protected $fillable = [
        'MaHoSo',
        'TenFile',
        'DuongDanFile',
        'PublicIdCloudinary',
        'KichThuoc',
        'KieuFile',
        'ThoiGianUpload',
    ];

    protected function casts(): array
    {
        return [
            'ThoiGianUpload' => 'datetime',
            'KichThuoc' => 'integer',
        ];
    }

    /**
     * Relationship: Một minh chứng file thuộc một hồ sơ
     */
    public function hoSo(): BelongsTo
    {
        return $this->belongsTo(HoSo::class, 'MaHoSo', 'MaHoSo');
    }
}
