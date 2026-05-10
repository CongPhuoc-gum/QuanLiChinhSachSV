<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KhoTriThucAI Model
 * 
 * Kho tri thức AI — chunks Nghị định 81 cho RAG
 * Knowledge base Nghị định 81/2021/NĐ-CP
 */
class KhoTriThucAI extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaTriThuc';
    protected $table = 'KHO_TRI_THUC_AI';
    protected $fillable = [
        'TieuDe',
        'NoiDungChunk',
        'VanBanNguon',
        'Chuong',
        'Dieu',
        'Khoan',
        'Vector_ID',
        'IsActive',
    ];

    protected function casts(): array
    {
        return [
            'NgayCapNhat' => 'datetime',
            'IsActive' => 'boolean',
        ];
    }

    /**
     * Relationship: Một chunk tri thức có nhiều phân tích AI
     */
    public function phanTichAIs(): HasMany
    {
        return $this->hasMany(PhanTichAIHoSo::class, 'MaTriThuc', 'MaTriThuc');
    }

    /**
     * Relationship: Một chunk tri thức có nhiều trích dẫn tin nhắn AI
     */
    public function trichDanTinNhans(): HasMany
    {
        return $this->hasMany(TrichDanTinNhanAI::class, 'MaTriThuc', 'MaTriThuc');
    }

    /**
     * Scope: Lấy các chunk đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('IsActive', 1);
    }

    /**
     * Scope: Lấy các chunk theo chương
     */
    public function scopeChuong($query, $chuong)
    {
        return $query->where('Chuong', $chuong);
    }

    /**
     * Scope: Lấy các chunk theo điều
     */
    public function scopeDieu($query, $dieu)
    {
        return $query->where('Dieu', $dieu);
    }
}
