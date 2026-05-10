<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LichSuTKB Model
 * 
 * Lớp học phần cụ thể từng học kỳ
 * Ví dụ: MaHP='TDTT01' → TenLHP='Bóng chuyền Nhóm 1' (HK1)
 *                      → TenLHP='Pickleball Nhóm 3'  (HK2)
 * Trigger so sánh MaHP → IsHocLai = 1 dù TenLHP khác nhau
 */
class LichSuTKB extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaTKB';
    protected $table = 'LICH_SU_TKB';
    protected $fillable = [
        'MaHP',
        'TenLHP',
        'MaNamHoc',
        'GiangVien',
        'Thu',
        'TuTiet',
        'DenTiet',
        'Phong',
        'SiSoDK',
        'SiSoHienTai',
        'LoaiDK',
        'MaNguoiTao',
        'GhiChu',
    ];

    protected function casts(): array
    {
        return [
            'Thu' => 'integer',
            'TuTiet' => 'integer',
            'DenTiet' => 'integer',
            'SiSoDK' => 'integer',
            'SiSoHienTai' => 'integer',
            'LoaiDK' => 'integer',
        ];
    }

    /**
     * Relationship: Một lớp học phần thuộc một môn học chuẩn
     */
    public function hocPhan(): BelongsTo
    {
        return $this->belongsTo(DanhMucHocPhan::class, 'MaHP', 'MaHP');
    }

    /**
     * Relationship: Một lớp học phần thuộc một năm học
     */
    public function namHoc(): BelongsTo
    {
        return $this->belongsTo(NamHoc::class, 'MaNamHoc', 'MaNamHoc');
    }

    /**
     * Relationship: Một lớp học phần được tạo bởi một người dùng
     */
    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiTao', 'MaNguoiDung');
    }

    /**
     * Relationship: Một lớp học phần có nhiều đăng ký học phần
     */
    public function dangKyHocPhans(): HasMany
    {
        return $this->hasMany(DangKyHocPhan::class, 'MaTKB', 'MaTKB');
    }

    /**
     * Scope: Lấy các lớp học lại
     */
    public function scopeHocLai($query)
    {
        return $query->where('LoaiDK', 2);
    }

    /**
     * Scope: Lấy các lớp cải thiện
     */
    public function scopeCaiThien($query)
    {
        return $query->where('LoaiDK', 3);
    }

    /**
     * Scope: Lấy các lớp bình thường
     */
    public function scopeBinhThuong($query)
    {
        return $query->where('LoaiDK', 1);
    }
}
