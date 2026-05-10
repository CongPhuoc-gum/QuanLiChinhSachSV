<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * NamHoc Model
 * 
 * Năm học và học kỳ
 * MaNamHoc: MEDIUMINT (VD: 20241 = HK1/2024-2025)
 */
class NamHoc extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaNamHoc';
    protected $keyType = 'int';
    public $incrementing = false;
    protected $table = 'NAM_HOC';
    protected $fillable = [
        'MaNamHoc',
        'TenNamHoc',
        'HocKy',
        'NgayBatDau',
        'NgayKetThuc',
        'IsActive',
    ];

    protected function casts(): array
    {
        return [
            'HocKy' => 'integer',
            'NgayBatDau' => 'date',
            'NgayKetThuc' => 'date',
            'IsActive' => 'boolean',
        ];
    }

    /**
     * Relationship: Một năm học có nhiều đợt thu hồ sơ
     */
    public function dotThuHoSos(): HasMany
    {
        return $this->hasMany(DotThuHoSo::class, 'MaNamHoc', 'MaNamHoc');
    }

    /**
     * Relationship: Một năm học có nhiều lớp học phần
     */
    public function lichSuTKBs(): HasMany
    {
        return $this->hasMany(LichSuTKB::class, 'MaNamHoc', 'MaNamHoc');
    }

    /**
     * Relationship: Một năm học có nhiều công nợ
     */
    public function congNos(): HasMany
    {
        return $this->hasMany(CongNo::class, 'MaNamHoc', 'MaNamHoc');
    }
}
