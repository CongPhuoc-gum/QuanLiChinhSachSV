<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GiaoDichNoiBo Model
 * 
 * Lệnh chi trả nội bộ — Tài vụ xem và CK thủ công
 * LoaiGiaoDich: 1=TCXH | 2=Hoàn tiền MGHP dư
 * TrangThai: 1=Chờ | 2=Đang | 3=Đã CK | 4=Thất bại | 5=Đã hủy
 * 
 * Trigger tự tạo lệnh hoàn tiền khi MGHP có tiền dư
 */
class GiaoDichNoiBo extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaGiaoDich';
    protected $table = 'GIAO_DICH_NOI_BO';
    protected $fillable = [
        'MaNguoiDung',
        'MaHoSo',
        'MaTaiKhoan',
        'SoTienChuyen',
        'LoaiGiaoDich',
        'TrangThai',
        'MaNguoiDuyetLenh',
        'MaSoGiaoDichNH',
        'GhiChu',
    ];

    protected function casts(): array
    {
        return [
            'SoTienChuyen' => 'decimal:0',
            'LoaiGiaoDich' => 'integer',
            'TrangThai' => 'integer',
            'NgayTaoLenh' => 'datetime',
            'NgayThucHien' => 'datetime',
        ];
    }

    /**
     * Relationship: Một giao dịch nội bộ thuộc một người dùng (sinh viên nhận tiền)
     */
    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một giao dịch nội bộ thuộc một hồ sơ
     */
    public function hoSo(): BelongsTo
    {
        return $this->belongsTo(HoSo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một giao dịch nội bộ có thể thuộc một tài khoản ngân hàng
     */
    public function taiKhoan(): BelongsTo
    {
        return $this->belongsTo(TaiKhoanNganHangSV::class, 'MaTaiKhoan', 'MaTaiKhoan');
    }

    /**
     * Relationship: Một giao dịch nội bộ được duyệt bởi một người dùng (cán bộ Tài vụ)
     */
    public function nguoiDuyetLenh(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiDuyetLenh', 'MaNguoiDung');
    }

    /**
     * Scope: Lấy các giao dịch chờ xử lý
     */
    public function scopeChoXuLy($query)
    {
        return $query->where('TrangThai', 1);
    }

    /**
     * Scope: Lấy các giao dịch đang xử lý
     */
    public function scopeDangXuLy($query)
    {
        return $query->where('TrangThai', 2);
    }

    /**
     * Scope: Lấy các giao dịch đã chuyển khoản
     */
    public function scopeDaChuyenKhoan($query)
    {
        return $query->where('TrangThai', 3);
    }

    /**
     * Scope: Lấy các giao dịch TCXH
     */
    public function scopeTCXH($query)
    {
        return $query->where('LoaiGiaoDich', 1);
    }

    /**
     * Scope: Lấy các giao dịch hoàn tiền MGHP
     */
    public function scopeHoanTienMGHP($query)
    {
        return $query->where('LoaiGiaoDich', 2);
    }
}
