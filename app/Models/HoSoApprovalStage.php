<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * HoSoApprovalStage - Giai đoạn xét duyệt hồ sơ
 *
 * Quy trình: Sinh viên → Khoa xác nhận → CTSV thẩm định → Trưởng phòng → BGH
 *
 * Stage:
 * 1 = Chờ khoa xác nhận
 * 2 = Khoa đã xác nhận, chờ CTSV thẩm định
 * 3 = CTSV thẩm định xong, chờ Trưởng phòng
 * 4 = Trưởng phòng duyệt, chờ BGH
 * 5 = BGH duyệt xong (Hoàn tất)
 */
class HoSoApprovalStage extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'MaGiaiDoan';
    protected $table = 'HO_SO_APPROVAL_STAGE';

    protected $fillable = [
        'MaHoSo',
        'GiaiDoan',
        'NguoiXacNhan',
        'ThoiGianXacNhan',
        'GhiChu',
        'TrangThai',
    ];

    protected function casts(): array
    {
        return [
            'ThoiGianXacNhan' => 'datetime',
        ];
    }

    /**
     * Relationship: Một giai đoạn thuộc một hồ sơ
     */
    public function hoSo(): BelongsTo
    {
        return $this->belongsTo(HoSo::class, 'MaHoSo', 'MaHoSo');
    }

    /**
     * Relationship: Người xác nhận (cán bộ Khoa)
     */
    public function nguoiXacNhan(): BelongsTo
    {
        return $this->belongsTo(NguoiDung::class, 'NguoiXacNhan', 'MaNguoiDung');
    }

    /**
     * Stage names
     */
    public static function getStageName(int $stage): string
    {
        return match ($stage) {
            1 => 'Chờ Khoa xác nhận',
            2 => 'Khoa đã xác nhận, chờ CTSV',
            3 => 'CTSV đã thẩm định, chờ Trưởng phòng',
            4 => 'Trưởng phòng đã duyệt, chờ BGH',
            5 => 'BGH duyệt xong (Hoàn tất)',
            default => 'Không xác định',
        };
    }
}
