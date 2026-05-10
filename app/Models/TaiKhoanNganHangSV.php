<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TaiKhoanNganHangSV Model
 * 
 * Tài khoản ngân hàng tích hợp thẻ sinh viên
 * Tách riêng TK NH khỏi SINH_VIEN. Hệ thống CHỈ lưu số TK để
 * Cán bộ Tài vụ tra cứu và CK thủ công. KHÔNG có API ngân hàng.
 */
class TaiKhoanNganHangSV extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaTaiKhoan';
    protected $table = 'TAI_KHOAN_NGAN_HANG_SV';
    protected $fillable = [
        'MaSinhVien',
        'SoTaiKhoan',
        'TenNganHang',
        'ChiNhanh',
        'TenChuTaiKhoan',
        'LoaiThe',
        'IsDefault',
    ];

    protected function casts(): array
    {
        return [
            'LoaiThe' => 'integer',
            'IsDefault' => 'boolean',
            'NgayCapNhat' => 'datetime',
        ];
    }

    /**
     * Relationship: Một tài khoản ngân hàng thuộc một sinh viên
     */
    public function sinhVien(): BelongsTo
    {
        return $this->belongsTo(SinhVien::class, 'MaSinhVien', 'MaNguoiDung');
    }

    /**
     * Relationship: Một tài khoản ngân hàng có nhiều giao dịch nội bộ
     */
    public function giaoDichNoiBos(): HasMany
    {
        return $this->hasMany(GiaoDichNoiBo::class, 'MaTaiKhoan', 'MaTaiKhoan');
    }
}
