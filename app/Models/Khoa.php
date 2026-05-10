<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Khoa Model
 * 
 * Danh mục Khoa của trường
 */
class Khoa extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaKhoa';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'KHOA';
    protected $fillable = [
        'MaKhoa',
        'TenKhoa',
    ];

    /**
     * Relationship: Một khoa có nhiều lớp
     */
    public function danhMucLops(): HasMany
    {
        return $this->hasMany(DanhMucLop::class, 'MaKhoa', 'MaKhoa');
    }
}
