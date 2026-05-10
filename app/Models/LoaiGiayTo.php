<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LoaiGiayTo Model
 * 
 * Danh mục loại giấy tờ minh chứng hồ sơ
 */
class LoaiGiayTo extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaLoaiGiayTo';
    protected $keyType = 'int';
    public $incrementing = false;
    protected $table = 'LOAI_GIAY_TO';
    protected $fillable = [
        'MaLoaiGiayTo',
        'TenLoaiGiayTo',
        'MoTa',
        'BatBuoc',
    ];

    protected function casts(): array
    {
        return [
            'BatBuoc' => 'boolean',
        ];
    }

    /**
     * Relationship: Một loại giấy tờ có nhiều minh chứng file
     */
    public function minhChungFiles(): HasMany
    {
        return $this->hasMany(MinhChungFile::class, 'MaLoaiGiayTo', 'MaLoaiGiayTo');
    }
}
