<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PhanTichAIHoSo Model
 * 
 * Kết quả AI gợi ý mức hưởng thụ khi cán bộ mở hồ sơ
 * Kết nối: KHO_TRI_THUC_AI ↔ HO_SO
 * Cán bộ mở hồ sơ → AI gợi ý mức hưởng → Cán bộ xem xét áp dụng
 */
class PhanTichAIHoSo extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaPhanTich';
    protected $table = 'PHAN_TICH_AI_HO_SO';
    protected $fillable = [
        'MaHoSo',
        'MaTriThuc',
        'MucHuongGoiY',
        'NoiDungGoiY',
        'DoTinCay',
    ];

    protected function casts(): array
    {
        return [
            'DoTinCay' => 'decimal:3',
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
     * Relationship: Một phân tích AI dựa trên một chunk tri thức
     */
    public function triThucAI(): BelongsTo
    {
        return $this->belongsTo(KhoTriThucAI::class, 'MaTriThuc', 'MaTriThuc');
    }

    /**
     * Scope: Lấy các phân tích có độ tin cậy cao (>= 0.8)
     */
    public function scopeDoTinCayCao($query)
    {
        return $query->where('DoTinCay', '>=', 0.8);
    }

    /**
     * Scope: Lấy các phân tích có độ tin cậy trung bình (0.5 - 0.8)
     */
    public function scopeDoTinCayTrungBinh($query)
    {
        return $query->whereBetween('DoTinCay', [0.5, 0.8]);
    }

    /**
     * Scope: Lấy các phân tích có độ tin cậy thấp (< 0.5)
     */
    public function scopeDoTinCayThap($query)
    {
        return $query->where('DoTinCay', '<', 0.5);
    }
}
