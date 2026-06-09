<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * DangKyHocPhan Model
 *
 * Trung gian SINH_VIEN ↔ LICH_SU_TKB
 * IsHocLai được trigger TRG_KiemTraHocLai tự động cập nhật
 *
 * Logic 'Học lại': Trigger kiểm tra nếu sinh viên đã từng đăng ký
 * cùng MaHP (môn học chuẩn) ở bất kỳ lớp nào trước đó
 */
class DangKyHocPhan extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaDangKy';
    protected $table = 'DANG_KY_HOC_PHAN';

    protected $fillable = [
        'MaSinhVien',
        'MaTKB',
        'IsHocLai',
        'DiemThi',
        'KetQua',
        'MaNguoiDung',
        'NgayDangKy',
        'NguonNhap',
    ];

    protected function casts(): array
    {
        return [
            'NgayDangKy' => 'datetime',
            'IsHocLai' => 'boolean',
            'DiemThi' => 'decimal:2',
            'KetQua' => 'integer',
        ];
    }

    /**
     * Relationship: Một đăng ký học phần thuộc một sinh viên
     */
    public function sinhVien(): BelongsTo
    {
        return $this->belongsTo(SinhVien::class, 'MaSinhVien', 'MaNguoiDung');
    }

    /**
     * Relationship: Một đăng ký học phần thuộc một người dùng
     */
    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một đăng ký học phần thuộc một lớp học phần
     */
    public function lichSuTKB(): BelongsTo
    {
        return $this->belongsTo(LichSuTKB::class, 'MaTKB', 'MaTKB');
    }

    /**
     * Scope: Lấy các đăng ký học lại
     */
    public function scopeHocLai($query)
    {
        return $query->where('IsHocLai', 1);
    }

    /**
     * Scope: Lấy các đăng ký bình thường (không học lại)
     */
    public function scopeBinhThuong($query)
    {
        return $query->where('IsHocLai', 0);
    }

    /**
     * Scope: Lấy các đăng ký đã có kết quả
     */
    public function scopeCoDiem($query)
    {
        return $query->whereNotNull('DiemThi');
    }

    /**
     * Scope: Lấy các đăng ký đạt
     */
    public function scopeDat($query)
    {
        return $query->where('KetQua', 1);
    }

    /**
     * Scope: Lấy các đăng ký không đạt
     */
    public function scopeKhongDat($query)
    {
        return $query->where('KetQua', 2);
    }
}
