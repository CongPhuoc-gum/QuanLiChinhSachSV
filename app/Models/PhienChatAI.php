<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class PhienChatAI extends Model
{
    protected $table = 'phien_chat_ai';
    protected $primaryKey = 'MaPhien';
    public $timestamps = false;

    protected $fillable = [
        'MaNguoiDung',
        'ThoiGianBatDau',
        'ThoiGianKetThuc',
        'DiemDanhGia',
        'GhiChuDanhGia'
    ];

    protected $casts = [
        'ThoiGianBatDau' => 'datetime',
        'ThoiGianKetThuc' => 'datetime',
        'DiemDanhGia' => 'integer'
    ];

    /**
     * Mối quan hệ: Một phiên chat có nhiều tin nhắn
     */
    public function tinNhans(): HasMany
    {
        return $this->hasMany(TinNhanAI::class, 'MaPhien', 'MaPhien');
    }

    /**
     * Mối quan hệ: Phiên chat thuộc một người dùng (sinh viên)
     */
    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiDung', 'MaNguoiDung');
    }
}
