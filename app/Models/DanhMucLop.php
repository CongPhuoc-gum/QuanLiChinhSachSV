<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DanhMucLop Model
 * 
 * Danh mục lớp sinh hoạt theo Khoa và khóa học
 */
class DanhMucLop extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaLop';
    protected $table = 'DANH_MUC_LOP';
    protected $fillable = [
        'TenLop',
        'MaKhoa',
        'KhoaHoc',
    ];

    protected function casts(): array
    {
        return [
            'KhoaHoc' => 'integer',
        ];
    }

    /**
     * Relationship: Một lớp thuộc một khoa
     */
    public function khoa(): BelongsTo
    {
        return $this->belongsTo(Khoa::class, 'MaKhoa', 'MaKhoa');
    }

    /**
     * Relationship: Một lớp có nhiều sinh viên
     */
    public function sinhViens(): HasMany
    {
        return $this->hasMany(SinhVien::class, 'MaLop', 'MaLop');
    }
}
