<?php

namespace App\Services;

use App\Models\HoSo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Gửi email thông báo khi hồ sơ đổi trạng thái
     */
    public function guiEmailDoiTrangThai(HoSo $hoSo, int $trangThaiMoi): void
    {
        try {
            $hoSo->load('nguoiDung.sinhVien', 'loaiChinhSach', 'phanTichAI');

            $sinhVien = $hoSo->nguoiDung->sinhVien;
            $email = $hoSo->nguoiDung->Email;

            if (!$email) {
                Log::warning('No email found for student', ['ma_ho_so' => $hoSo->MaHoSo]);
                return;
            }

            // Tạo nội dung email
            $emailContent = $this->taoNoiDungEmail($hoSo, $trangThaiMoi);

            // Gửi email
            Mail::send('emails.ho_so_doi_trang_thai', [
                'hoSo' => $hoSo,
                'sinhVien' => $sinhVien,
                'trangThaiMoi' => $trangThaiMoi,
                'subject' => $emailContent['subject'],
                'noidung' => $emailContent['body'],
            ], function ($message) use ($email, $emailContent) {
                $message
                    ->to($email)
                    ->subject($emailContent['subject']);
            });

            Log::info('Email sent successfully', [
                'ma_ho_so' => $hoSo->MaHoSo,
                'email' => $email,
                'trang_thai' => $trangThaiMoi,
            ]);
        } catch (\Exception $e) {
            // Lỗi gửi email KHÔNG làm rollback - chỉ log warning
            Log::warning('Failed to send email', [
                'ma_ho_so' => $hoSo->MaHoSo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tạo nội dung email theo trạng thái
     */
    private function taoNoiDungEmail(HoSo $hoSo, int $trangThai): array
    {
        $trangThaiNames = $this->getTrangThaiNames();

        $hoSo->load('phanTichAI');

        $mucHuong = null;
        if ($hoSo->phanTichAI && isset($hoSo->phanTichAI->KetQuaDoiChieu['muc_huong'])) {
            $mucHuong = $hoSo->phanTichAI->KetQuaDoiChieu['muc_huong'];
        }

        return match ($trangThai) {
            3 => [
                'subject' => '🔔 Hồ sơ của bạn cần bổ sung giấy tờ',
                'body' => $this->renderEmail3($hoSo),
            ],
            6 => [
                'subject' => '✅ Hồ sơ của bạn đã được duyệt',
                'body' => $this->renderEmail6($hoSo, $mucHuong),
            ],
            7 => [
                'subject' => '💰 Tiền hỗ trợ đã được chuyển khoản',
                'body' => $this->renderEmail7($hoSo),
            ],
            8 => [
                'subject' => '❌ Hồ sơ không được chấp thuận',
                'body' => $this->renderEmail8($hoSo),
            ],
            default => [
                'subject' => 'Thông báo về hồ sơ của bạn',
                'body' => 'Hồ sơ của bạn có cập nhật mới. Vui lòng đăng nhập hệ thống để kiểm tra.',
            ],
        };
    }

    /**
     * Email Status 3: Yêu cầu bổ sung
     */
    private function renderEmail3(HoSo $hoSo): string
    {
        $tenLoaiCS = $hoSo->loaiChinhSach?->TenLoaiCS ?? 'N/A';
        $hoTen = $hoSo->nguoiDung?->sinhVien?->HoTen ?? 'Sinh viên';
        $maSoSV = $hoSo->nguoiDung?->sinhVien?->MaSoSV ?? 'N/A';
        $ghiChu = $hoSo->GhiChu ?? 'Không có ghi chú';
        $ngayNop = $hoSo->NgayNop ?? 'N/A';

        return <<<HTML
            Kính gửi {$hoTen} - MSSV: {$maSoSV},

            Hệ thống đã nhận được hồ sơ của bạn. Tuy nhiên, để hoàn tất quá trình xét duyệt, 
            bạn cần bổ sung thêm giấy tờ như sau:

            <strong>Ghi chú từ cán bộ:</strong>
            {$ghiChu}

            Vui lòng đăng nhập vào hệ thống QUANLICS để xem chi tiết và nộp giấy tờ bổ sung.

            Loại chính sách: {$tenLoaiCS}
            Ngày nộp: {$ngayNop}

            ---
            Phòng Công tác Sinh viên - Trường ĐHSPKT Đà Nẵng
            HTML;
    }

    /**
     * Email Status 6: Đã duyệt
     */
    private function renderEmail6(HoSo $hoSo, $mucHuong): string
    {
        $tenMucHuong = match ($mucHuong) {
            100 => 'Miễn 100% học phí',
            70 => 'Giảm 70% học phí',
            50 => 'Giảm 50% học phí',
            default => 'N/A',
        };

        $hoTen = $hoSo->nguoiDung?->sinhVien?->HoTen ?? 'Sinh viên';
        $maSoSV = $hoSo->nguoiDung?->sinhVien?->MaSoSV ?? 'N/A';
        $tenLoaiCS = $hoSo->loaiChinhSach?->TenLoaiCS ?? 'N/A';
        $ngayDuyet = now()->format('d/m/Y H:i');

        return <<<HTML
            Kính gửi {$hoTen} - MSSV: {$maSoSV},

            Chúc mừng! Hồ sơ của bạn đã được xét duyệt và <strong>chấp thuận</strong> ✅

            <strong>Chi tiết quyết định:</strong>
            - Loại chính sách: {$tenLoaiCS}
            - Mức hưởng: {$tenMucHuong}
            - Ngày duyệt: {$ngayDuyet}

            <strong>Bước tiếp theo:</strong>
            Tiền hỗ trợ sẽ được chuyển khoản vào tài khoản ngân hàng bạn đã cung cấp trong vòng 5-7 ngày làm việc.
            Vui lòng kiểm tra tài khoản thường xuyên.

            Nếu có thắc mắc, vui lòng liên hệ Phòng Công tác Sinh viên.

            ---
            Phòng Công tác Sinh viên - Trường ĐHSPKT Đà Nẵng
            HTML;
    }

    /**
     * Email Status 7: Đã chi trả
     */
    private function renderEmail7(HoSo $hoSo): string
    {
        $hoTen = $hoSo->nguoiDung?->sinhVien?->HoTen ?? 'Sinh viên';
        $maSoSV = $hoSo->nguoiDung?->sinhVien?->MaSoSV ?? 'N/A';
        $tenLoaiCS = $hoSo->loaiChinhSach?->TenLoaiCS ?? 'N/A';
        $ngayChiTra = now()->format('d/m/Y H:i');

        return <<<HTML
            Kính gửi {$hoTen} - MSSV: {$maSoSV},

            Thông báo: Tiền hỗ trợ đã được chuyển khoản vào tài khoản của bạn 💰

            <strong>Chi tiết giao dịch:</strong>
            - Loại chính sách: {$tenLoaiCS}
            - Ngày chi trả: {$ngayChiTra}

            Vui lòng kiểm tra tài khoản ngân hàng bạn đã đăng ký.
            Nếu chưa nhận được tiền sau 7 ngày, vui lòng liên hệ ngay Phòng Công tác Sinh viên.

            ---
            Phòng Công tác Sinh viên - Trường ĐHSPKT Đà Nẵng
            HTML;
    }

    /**
     * Email Status 8: Từ chối
     */
    private function renderEmail8(HoSo $hoSo): string
    {
        $hoTen = $hoSo->nguoiDung?->sinhVien?->HoTen ?? 'Sinh viên';
        $maSoSV = $hoSo->nguoiDung?->sinhVien?->MaSoSV ?? 'N/A';
        $lyDo = $hoSo->LyDoTuChoi ?? 'Không đủ điều kiện theo quy định';
        $tenLoaiCS = $hoSo->loaiChinhSach?->TenLoaiCS ?? 'N/A';

        return <<<HTML
            Kính gửi {$hoTen} - MSSV: {$maSoSV},

            Hồ sơ của bạn không được chấp thuận ❌

            <strong>Lý do từ chối:</strong>
            {$lyDo}

            <strong>Loại chính sách:</strong> {$tenLoaiCS}

            <strong>Hướng dẫn:</strong>
            - Nếu lỗi do thiếu giấy tờ, bạn có thể nộp lại trong đợt thu tiếp theo
            - Vui lòng liên hệ Phòng Công tác Sinh viên để được hỗ trợ chi tiết

            ---
            Phòng Công tác Sinh viên - Trường ĐHSPKT Đà Nẵng
            HTML;
    }

    /**
     * Helper: Lấy tên trạng thái
     */
    private function getTrangThaiNames(): array
    {
        return [
            1 => 'Chờ nộp',
            2 => 'Chờ thẩm định',
            3 => 'Đang bổ sung',
            4 => 'Chờ TP duyệt',
            5 => 'Chờ BGH duyệt',
            6 => 'Đã duyệt',
            7 => 'Đã chi trả',
            8 => 'Từ chối',
            9 => 'Đã hủy',
        ];
    }
}
