<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * NguoiDung Model
 * 
 * Tài khoản đăng nhập hệ thống (tách khỏi thông tin cá nhân)
 * TrangThai: 1=HoatDong, 2=KhoaTam, 3=Xoa
 */
class NguoiDung extends Model
{
    use HasApiTokens;

    public $timestamps = false;
    protected $primaryKey = 'MaNguoiDung';
    protected $table = 'NGUOI_DUNG';
    protected $fillable = [
        'Email',
        'MatKhau',
        'MaVaiTro',
        'TrangThai',
    ];

    protected $hidden = [
        'MatKhau',
    ];

    protected function casts(): array
    {
        return [
            'NgayTao' => 'datetime',
            'TrangThai' => 'integer',
        ];
    }

    /**
     * Relationship: Một người dùng có một vai trò
     */
    public function vaiTro(): BelongsTo
    {
        return $this->belongsTo(VaiTro::class, 'MaVaiTro', 'MaVaiTro');
    }

    /**
     * Relationship: Một người dùng có thể là cán bộ (1-1)
     */
    public function canBo(): HasOne
    {
        return $this->hasOne(CanBo::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một người dùng có thể là sinh viên (1-1)
     */
    public function sinhVien(): HasOne
    {
        return $this->hasOne(SinhVien::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một người dùng có nhiều hồ sơ (nếu là sinh viên)
     */
    public function hoSos(): HasMany
    {
        return $this->hasMany(HoSo::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một người dùng có nhiều nhật ký xét duyệt (cán bộ thực hiện)
     */
    public function nhatKyXetDuyets(): HasMany
    {
        return $this->hasMany(NhatKyXetDuyet::class, 'MaNguoiThucHien', 'MaNguoiDung');
    }

    /**
     * Relationship: Một người dùng có nhiều quyết định ban hành (người ký)
     */
    public function quyetDinhBanHanhs(): HasMany
    {
        return $this->hasMany(QuyetDinhBanHanh::class, 'MaNguoiKy', 'MaNguoiDung');
    }

    /**
     * Relationship: Một người dùng có nhiều công nợ
     */
    public function congNos(): HasMany
    {
        return $this->hasMany(CongNo::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một người dùng có nhiều giao dịch nội bộ (người nhận tiền)
     */
    public function giaoDichNoiBos(): HasMany
    {
        return $this->hasMany(GiaoDichNoiBo::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một người dùng có nhiều giao dịch nội bộ (người duyệt lệnh)
     */
    public function giaoDichDuyetLenhs(): HasMany
    {
        return $this->hasMany(GiaoDichNoiBo::class, 'MaNguoiDuyetLenh', 'MaNguoiDung');
    }

    /**
     * Relationship: Một người dùng có nhiều phiên chat AI
     */
    public function phienChatAIs(): HasMany
    {
        return $this->hasMany(PhienChatAI::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một người dùng có nhiều lớp học phần được tạo
     */
    public function lichSuTKBsTao(): HasMany
    {
        return $this->hasMany(LichSuTKB::class, 'MaNguoiTao', 'MaNguoiDung');
    }
}
