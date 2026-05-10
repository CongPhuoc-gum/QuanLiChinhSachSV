<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PhienChatAI Model
 * 
 * Phiên hội thoại với AI chatbot
 */
class PhienChatAI extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaPhien';
    protected $table = 'PHIEN_CHAT_AI';
    protected $fillable = [
        'MaNguoiDung',
        'DiemDanhGia',
        'GhiChuDanhGia',
    ];

    protected function casts(): array
    {
        return [
            'ThoiGianBatDau' => 'datetime',
            'ThoiGianKetThuc' => 'datetime',
            'DiemDanhGia' => 'integer',
        ];
    }

    /**
     * Relationship: Một phiên chat thuộc một người dùng
     */
    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một phiên chat có nhiều tin nhắn
     */
    public function tinNhans(): HasMany
    {
        return $this->hasMany(TinNhanAI::class, 'MaPhien', 'MaPhien');
    }

    /**
     * Scope: Lấy các phiên chat đang diễn ra
     */
    public function scopeDangDienRa($query)
    {
        return $query->whereNull('ThoiGianKetThuc');
    }

    /**
     * Scope: Lấy các phiên chat đã kết thúc
     */
    public function scopeDaKetThuc($query)
    {
        return $query->whereNotNull('ThoiGianKetThuc');
    }

    /**
     * Scope: Lấy các phiên chat có đánh giá
     */
    public function scopeCoDanhGia($query)
    {
        return $query->whereNotNull('DiemDanhGia');
    }
}
