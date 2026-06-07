<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * TrichDanTinNhanAI Model
 * 
 * Bảng N-N: Tin nhắn AI ↔ Chunk tri thức (RAG citation)
 * Lưu vết chunk nào AI dùng để trả lời — phục vụ kỹ thuật RAG
 */
class TrichDanTinNhanAI extends Pivot
{
    public $timestamps = false;
    protected $table = 'TRICH_DAN_TIN_NHAN_AI';
    protected $fillable = [
        'MaTinNhan',
        'MaTriThuc',
        'DiemTuongDong',
        'ThuTuUuTien',
    ];

    protected function casts(): array
    {
        return [
            'DiemTuongDong' => 'decimal:3',
            'ThuTuUuTien' => 'integer',
        ];
    }

    /**
     * Relationship: Một trích dẫn thuộc một tin nhắn
     */
    public function tinNhan()
    {
        return $this->belongsTo(TinNhanAI::class, 'MaTinNhan', 'MaTinNhan');
    }

    /**
     * Relationship: Một trích dẫn thuộc một chunk tri thức
     */
    public function triThucAI()
    {
        return $this->belongsTo(KhoTriThucAI::class, 'MaTriThuc', 'MaTriThuc');
    }
}
