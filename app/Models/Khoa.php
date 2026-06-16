<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

/**
 * Khoa Model - Bộ phận của trường (Khoa Sư Phạm, Khoa Kỹ Thuật, v.v.)
 */
class Khoa extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaKhoa';
    protected $table = 'KHOA';

    protected $fillable = [
        'TenKhoa',
        'MoTa',
    ];

    /**
     * Relationship: Một khoa có nhiều lớp
     */
    public function lops(): HasMany
    {
        return $this->hasMany(DanhMucLop::class, 'MaKhoa', 'MaKhoa');
    }
}
