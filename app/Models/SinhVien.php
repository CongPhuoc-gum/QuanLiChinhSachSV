<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SinhVien Model
 * 
 * Thông tin cá nhân sinh viên (quan hệ 1-1 với NGUOI_DUNG)
 * Bao gồm địa chỉ thường trú, tạm trú, dân tộc, đối tượng chính sách
 */
class SinhVien extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaNguoiDung';
    protected $keyType = 'int';
    public $incrementing = false;
    protected $table = 'SINH_VIEN';
    protected $fillable = [
        'MaSoSV',
        'HoTen',
        'NgaySinh',
        'GioiTinh',
        'CCCD',
        'MaLop',
        'SoDienThoai',
        'DiaChiThuongTru',
        'TinhThuongTru',
        'DiaChiTamTru',
        'TinhTamTru',
        'DanToc',
        'DoiTuongCS',
    ];

    protected function casts(): array
    {
        return [
            'NgaySinh' => 'date',
            'GioiTinh' => 'integer',
        ];
    }

    /**
     * Relationship: Một sinh viên là một người dùng
     */
    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một sinh viên thuộc một lớp
     */
    public function lop(): BelongsTo
    {
        return $this->belongsTo(DanhMucLop::class, 'MaLop', 'MaLop');
    }

    /**
     * Relationship: Một sinh viên có một tài khoản ngân hàng mặc định
     */
    public function taiKhoanNganHangMacDinh(): HasOne
    {
        return $this->hasOne(TaiKhoanNganHangSV::class, 'MaSinhVien', 'MaNguoiDung')
            ->where('IsDefault', 1);
    }

    /**
     * Relationship: Một sinh viên có nhiều tài khoản ngân hàng
     */
    public function taiKhoanNganHangs(): HasMany
    {
        return $this->hasMany(TaiKhoanNganHangSV::class, 'MaSinhVien', 'MaNguoiDung');
    }

    /**
     * Relationship: Một sinh viên có nhiều đăng ký học phần
     */
    public function dangKyHocPhans(): HasMany
    {
        return $this->hasMany(DangKyHocPhan::class, 'MaSinhVien', 'MaNguoiDung');
    }

    /**
     * Relationship: Một sinh viên có nhiều hồ sơ (thông qua NguoiDung)
     */
    public function hoSos(): HasMany
    {
        return $this->hasMany(HoSo::class, 'MaNguoiDung', 'MaNguoiDung');
    }
}
