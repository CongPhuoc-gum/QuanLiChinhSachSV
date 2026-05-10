<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DotThuHoSo Model
 * 
 * Đợt thu nhận hồ sơ trong học kỳ
 */
class DotThuHoSo extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaDot';
    protected $table = 'DOT_THU_HO_SO';
    protected $fillable = [
        'TenDot',
        'MaNamHoc',
        'NgayBatDau',
        'NgayKetThuc',
        'TrangThaiDot',
    ];

    protected function casts(): array
    {
        return [
            'NgayBatDau' => 'date',
            'NgayKetThuc' => 'date',
            'TrangThaiDot' => 'boolean',
        ];
    }

    /**
     * Relationship: Một đợt thu hồ sơ thuộc một năm học
     */
    public function namHoc(): BelongsTo
    {
        return $this->belongsTo(NamHoc::class, 'MaNamHoc', 'MaNamHoc');
    }

    /**
     * Relationship: Một đợt thu hồ sơ có nhiều hồ sơ
     */
    public function hoSos(): HasMany
    {
        return $this->hasMany(HoSo::class, 'MaDot', 'MaDot');
    }
}
