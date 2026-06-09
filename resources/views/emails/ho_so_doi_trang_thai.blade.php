<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #007bff;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 12px;
        }
        .content {
            margin: 20px 0;
            line-height: 1.8;
        }
        .info-box {
            background-color: #f9f9f9;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .info-box strong {
            color: #007bff;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .status-approved {
            color: #28a745;
            font-weight: bold;
        }
        .status-rejected {
            color: #dc3545;
            font-weight: bold;
        }
        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $subject }}</h1>
            <p>QUANLICS - Hệ thống quản lý chính sách hỗ trợ sinh viên</p>
        </div>

        <div class="content">
            {!! $noidung !!}
        </div>

        <div class="info-box">
            <strong>Thông tin hồ sơ:</strong><br>
            Loại chính sách: {{ $hoSo->loaiChinhSach->TenLoaiCS ?? 'N/A' }}<br>
            Đợt thu: {{ $hoSo->dotThuHoSo->TenDot ?? 'N/A' }}<br>
            Ngày nộp: {{ $hoSo->NgayNop?->format('d/m/Y') ?? 'N/A' }}
            @if($hoSo->GhiChu)
                <br><br>
                <strong>Ghi chú từ cán bộ:</strong><br>
                {{ $hoSo->GhiChu }}
            @endif
        </div>

        <div class="footer">
            <p>
                Phòng Công tác Sinh viên - Trường ĐHSPKT Đà Nẵng<br>
                Email: quanlics@ute.udn.vn | Điện thoại: (0236) 3822 XXX<br>
                <small>Đây là email tự động. Vui lòng không trả lời email này.</small>
            </p>
        </div>
    </div>
</body>
</html>
