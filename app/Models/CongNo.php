<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CongNo Model
 * 
 * Công nợ học phí và ghi nhận miễn giảm (BM.01)
 * Tất cả cột tiền dùng DECIMAL(18,0)
 * 
 * Trigger tự tính:
 * - SoTienConLai = HocPhiPhaiDong - SoTienMienGiam (nếu > 0)
 * - TienDuMGHP = SoTienMienGiam - HocPhiPhaiDong (nếu > 0, thì hoàn tiền)
 */
class CongNo extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaCongNo';
    protected $table = 'CONG_NO';
    protected $fillable = [
        'MaNguoiDung',
        'MaHoSo',
        'MaNamHoc',
        'HocPhiPhaiDong',
        'SoTienMienGiam',
        'SoTienConLai',
        'TienDuMGHP',
    ];

    protected function casts(): array
    {
        return [
            'HocPhiPhaiDong' => 'decimal:0',
            'SoTienMienGiam' => 'decimal:0',
            'SoTienConLai' => 'decimal:0',
            'TienDuMGHP' => 'decimal:0',
            'NgayCapNhat' => 'datetime',
        ];
    }

    /**
     * Relationship: Một công nợ thuộc một người dùng (sinh viên)
     */
    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một công nợ thuộc một hồ sơ
     */
    public function hoSo(): BelongsTo
    {
        return $this->belongsTo(HoSo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một công nợ thuộc một năm học
     */
    public function namHoc(): BelongsTo
    {
        return $this->belongsTo(NamHoc::class, 'MaNamHoc', 'MaNamHoc');
    }

    /**
     * Relationship: Một công nợ có nhiều giao dịch nội bộ (hoàn tiền)
     */
    public function giaoDichNoiBos(): HasMany
    {
        return $this->hasMany(GiaoDichNoiBo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Scope: Lấy các công nợ có tiền dư (cần hoàn tiền)
     */
    public function scopeCoDuMGHP($query)
    {
        return $query->where('TienDuMGHP', '>', 0);
    }

    /**
     * Scope: Lấy các công nợ còn nợ
     */
    public function scopeConNo($query)
    {
        return $query->where('SoTienConLai', '>', 0);
    }

    /**
     * Scope: Lấy các công nợ đã thanh toán hết
     */
    public function scopeDaThanhToan($query)
    {
        return $query->where('SoTienConLai', 0);
    }
}
