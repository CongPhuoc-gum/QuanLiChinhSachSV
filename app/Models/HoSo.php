<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

/**
 * HoSo Model
 *
 * Đơn đăng ký chính sách (BM.01 hoặc BM.02)
 * Trạng thái: 1=Chờ nộp | 2=Chờ thẩm định | 3=Đang bổ sung | 4=Chờ TP duyệt
 *            5=Chờ BGH duyệt | 6=Đã duyệt | 7=Đã chi trả | 8=Từ chối | 9=Đã hủy
 */
class HoSo extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaHoSo';
    protected $table = 'HO_SO';

    protected $fillable = [
        'MaNguoiDung',
        'MaDot',
        'MaLoaiCS',
        'MaTrangThai',
        'GhiChu',
        'LyDoTuChoi',
        'du_lieu_form',
    ];

    protected function casts(): array
    {
        return [
            'NgayNop' => 'datetime',
            'NgayCapNhat' => 'datetime',
            'du_lieu_form' => 'array',
        ];
    }

    /**
     * Relationship: Một hồ sơ thuộc một người dùng (sinh viên)
     */
    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'MaNguoiDung', 'MaNguoiDung');
    }

    /**
     * Relationship: Một hồ sơ thuộc một đợt thu hồ sơ
     */
    public function dotThuHoSo(): BelongsTo
    {
        return $this->belongsTo(DotThuHoSo::class, 'MaDot', 'MaDot');
    }

    /**
     * Relationship: Một hồ sơ thuộc một loại chính sách
     */
    public function loaiChinhSach(): BelongsTo
    {
        return $this->belongsTo(LoaiChinhSach::class, 'MaLoaiCS', 'MaLoaiCS');
    }

    /**
     * Relationship: Một hồ sơ có một trạng thái
     */
    public function trangThai(): BelongsTo
    {
        return $this->belongsTo(TrangThai::class, 'MaTrangThai', 'MaTrangThai');
    }

    /**
     * Relationship: Một hồ sơ có nhiều minh chứng file
     */
    public function minhChungFiles(): HasMany
    {
        return $this->hasMany(MinhChungFile::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một hồ sơ có nhiều nhật ký xét duyệt
     */
    public function nhatKyXetDuyets(): HasMany
    {
        return $this->hasMany(NhatKyXetDuyet::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một hồ sơ có một quyết định ban hành
     */
    public function quyetDinhBanHanh(): HasOne
    {
        return $this->hasOne(QuyetDinhBanHanh::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một hồ sơ có một công nợ (MGHP)
     */
    public function congNo(): HasOne
    {
        return $this->hasOne(CongNo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một hồ sơ có nhiều giao dịch nội bộ
     */
    public function giaoDichNoiBos(): HasMany
    {
        return $this->hasMany(GiaoDichNoiBo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một hồ sơ có một phân tích AI
     */
    public function phanTichAI(): HasOne
    {
        return $this->hasOne(PhanTichAIHoSo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Một hồ sơ có nhiều giai đoạn xét duyệt
     */
    public function approvalStages(): HasMany
    {
        return $this->hasMany(HoSoApprovalStage::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Scope: Lấy các hồ sơ chờ thẩm định
     */
    public function scopeChoThamDinh($query)
    {
        return $query->where('MaTrangThai', 2);
    }

    /**
     * Scope: Lấy các hồ sơ đã duyệt
     */
    public function scopeDaDuyet($query)
    {
        return $query->where('MaTrangThai', 6);
    }

    /**
     * Scope: Lấy các hồ sơ đã chi trả
     */
    public function scopeDaChiTra($query)
    {
        return $query->where('MaTrangThai', 7);
    }

    /**
     * Scope: Lấy các hồ sơ bị từ chối
     */
    public function scopeTuChoi($query)
    {
        return $query->where('MaTrangThai', 8);
    }

    /**
     * Scope: Lấy các hồ sơ MGHP
     */
    public function scopeMGHP($query)
    {
        return $query->where('MaLoaiCS', 1);
    }

    /**
     * Scope: Lấy các hồ sơ TCXH
     */
    public function scopeTCXH($query)
    {
        return $query->where('MaLoaiCS', 2);
    }
}
