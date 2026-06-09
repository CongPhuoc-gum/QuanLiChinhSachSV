<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

/**
 * TinNhanAI Model
 *
 * Tin nhắn trong phiên hội thoại AI
 * VaiTro: 'user' | 'assistant' | 'system'
 */
class TinNhanAI extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaTinNhan';
    protected $table = 'TIN_NHAN_AI';

    protected $fillable = [
        'MaPhien',
        'VaiTro',
        'NoiDung',
        'ThoiGian',
        'TokenSuDung',
    ];

    protected function casts(): array
    {
        return [
            'ThoiGian' => 'datetime',
            'TokenSuDung' => 'integer',
        ];
    }

    /**
     * Relationship: Một tin nhắn thuộc một phiên chat
     */
    public function phien(): BelongsTo
    {
        return $this->belongsTo(PhienChatAI::class, 'MaPhien', 'MaPhien');
    }

    /**
     * Relationship: Một tin nhắn có nhiều trích dẫn tri thức (N-N)
     */
    public function triThucAIs(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                KhoTriThucAI::class,
                'TRICH_DAN_TIN_NHAN_AI',
                'MaTinNhan',
                'MaTriThuc'
            )
            ->withPivot('DiemTuongDong', 'ThuTuUuTien')
            ->using(TrichDanTinNhanAI::class);
    }

    /**
     * Scope: Lấy các tin nhắn từ người dùng
     */
    public function scopeFromUser($query)
    {
        return $query->where('VaiTro', 'user');
    }

    /**
     * Scope: Lấy các tin nhắn từ trợ lý AI
     */
    public function scopeFromAssistant($query)
    {
        return $query->where('VaiTro', 'assistant');
    }

    /**
     * Scope: Lấy các tin nhắn hệ thống
     */
    public function scopeSystem($query)
    {
        return $query->where('VaiTro', 'system');
    }
}
