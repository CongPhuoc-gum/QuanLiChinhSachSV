<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * PhanTichAIHoSo Model - DAY 3
 *
 * Kết quả OCR & So khớp từ Gemini Vision
 * Lưu trữ toàn bộ kết quả phân tích OCR, tỷ lệ khớp, cảnh báo
 */
class PhanTichAIHoSo extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaPhanTich';
    protected $table = 'PHAN_TICH_AI_HO_SO';

    protected $fillable = [
        'MaHoSo',
        'KetQuaDoiChieu',
        'TyLeKhop',
        'CanBaoLech',
        'ThoiGianPhanTich',
        'LoaiTaiLieuOCR',
        'URLAnh',
        'DoTinCayOCR',
        'TrangThaiXuLy',
        'GhiChuAdmin',
    ];

    protected function casts(): array
    {
        return [
            'KetQuaDoiChieu' => 'array',  // JSON từ Gemini + so khớp
            'TyLeKhop' => 'float',  // 0.0 - 1.0
            'DoTinCayOCR' => 'float',
            'CanBaoLech' => 'array',  // Array discrepancies
            'ThoiGianPhanTich' => 'datetime',
        ];
    }

    /**
     * Relationship: Một phân tích AI thuộc một hồ sơ
     */
    public function hoSo(): BelongsTo
    {
        return $this->belongsTo(HoSo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Scope: Lấy các phân tích hợp lệ (khớp > 95%)
     */
    public function scopeHopLe($query)
    {
        return $query->where('TyLeKhop', '>=', 0.95);
    }

    /**
     * Scope: Lấy các phân tích cảnh báo (80-95%)
     */
    public function scopeCanhBao($query)
    {
        return $query->whereBetween('TyLeKhop', [0.8, 0.95]);
    }

    /**
     * Scope: Lấy các phân tích cần thẩm định (< 80%)
     */
    public function scopeCanThamDinh($query)
    {
        return $query->where('TyLeKhop', '<', 0.8);
    }
}
