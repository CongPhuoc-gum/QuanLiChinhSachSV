-- ======================================================================
-- HỆ THỐNG QUẢN LÝ CHÍNH SÁCH SINH VIÊN
-- MySQL 8.x — Phiên bản FINAL (chuyển đổi từ SQL Server)
-- Trường: UTE Đà Nẵng (UTEUDN)
-- ======================================================================
-- KHÁC BIỆT CHÍNH KHI CHUYỂN SANG MySQL:
--   SQL Server          →  MySQL
--   GO                  →  (bỏ, không cần)
--   IDENTITY(1,1)       →  AUTO_INCREMENT
--   NVARCHAR(n)         →  VARCHAR(n) CHARACTER SET utf8mb4
--   DATETIME2           →  DATETIME
--   BIT                 →  TINYINT
--   TOP 1               →  LIMIT 1
--   GETDATE()           →  NOW()
--   CREATE OR ALTER     →  DROP + CREATE
--   TRIGGER NESTLEVEL() →  kiểm tra biến @trigger_flag
-- ======================================================================

-- Tạo database nếu chưa có, rồi dùng nó
CREATE DATABASE IF NOT EXISTS QuanLyChinhSachSV
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE QuanLyChinhSachSV;

-- Tắt kiểm tra FK trong lúc tạo bảng (tránh lỗi thứ tự)
SET FOREIGN_KEY_CHECKS = 0;

-- Xóa bảng cũ nếu tồn tại (đúng thứ tự phụ thuộc)
DROP TABLE IF EXISTS TRICH_DAN_TIN_NHAN_AI;
DROP TABLE IF EXISTS PHAN_TICH_AI_HO_SO;
DROP TABLE IF EXISTS TIN_NHAN_AI;
DROP TABLE IF EXISTS PHIEN_CHAT_AI;
DROP TABLE IF EXISTS KHO_TRI_THUC_AI;
DROP TABLE IF EXISTS NHAT_KY_XET_DUYET;
DROP TABLE IF EXISTS QUYET_DINH_BAN_HANH;
DROP TABLE IF EXISTS LICH_SU_TRANG_THAI;
DROP TABLE IF EXISTS MINH_CHUNG_FILE;
DROP TABLE IF EXISTS GIAO_DICH_NOI_BO;
DROP TABLE IF EXISTS CONG_NO;
DROP TABLE IF EXISTS HO_SO;
DROP TABLE IF EXISTS DANG_KY_HOC_PHAN;
DROP TABLE IF EXISTS LICH_SU_TKB;
DROP TABLE IF EXISTS DANH_MUC_HOC_PHAN;
DROP TABLE IF EXISTS TAI_KHOAN_NGAN_HANG_SV;
DROP TABLE IF EXISTS SINH_VIEN;
DROP TABLE IF EXISTS CAN_BO;
DROP TABLE IF EXISTS NGUOI_DUNG;
DROP TABLE IF EXISTS DOT_THU_HO_SO;
DROP TABLE IF EXISTS NAM_HOC;
DROP TABLE IF EXISTS DANH_MUC_LOP;
DROP TABLE IF EXISTS KHOA;
DROP TABLE IF EXISTS LOAI_GIAY_TO;
DROP TABLE IF EXISTS LOAI_CHINH_SACH;
DROP TABLE IF EXISTS TRANG_THAI;
DROP TABLE IF EXISTS VAI_TRO;

SET FOREIGN_KEY_CHECKS = 1;

-- ======================================================================
-- PHẦN 1: DANH MỤC HỆ THỐNG
-- ======================================================================

-- ----------------------------------------------------------------------
-- Bảng: VAI_TRO
-- 5 vai trò trong quy trình phê duyệt đa cấp:
--   1=SinhVien | 2=CanBoCTSV | 3=TruongPhongCTSV | 4=BanGiamHieu | 5=CanBoTaiVu
-- ----------------------------------------------------------------------
CREATE TABLE VAI_TRO (
    MaVaiTro  TINYINT      NOT NULL,
    TenVaiTro VARCHAR(50)  NOT NULL COMMENT 'Tên vai trò hiển thị trên UI',
    MoTa      TEXT         NULL     COMMENT 'Mô tả chi tiết nghiệp vụ của vai trò',
    PRIMARY KEY (MaVaiTro),
    UNIQUE KEY UQ_VaiTro_Ten (TenVaiTro)
) ENGINE=InnoDB COMMENT='Phân quyền truy cập theo nghiệp vụ';

INSERT INTO VAI_TRO VALUES
    (1, 'SinhVien',        'Sinh viên: đăng ký hồ sơ, xem trạng thái, chat AI'),
    (2, 'CanBoCTSV',       'Cán bộ CTSV: tiếp nhận, thẩm định, yêu cầu bổ sung'),
    (3, 'TruongPhongCTSV', 'Trưởng phòng CTSV: phê duyệt hồ sơ, ký quyết định'),
    (4, 'BanGiamHieu',     'Ban Giám hiệu: phê duyệt trường hợp đặc biệt'),
    (5, 'CanBoTaiVu',      'Cán bộ Tài vụ: xem lệnh chi, xác nhận chuyển khoản thủ công');

-- ----------------------------------------------------------------------
-- Bảng: TRANG_THAI
-- Nhãn nghiệp vụ quy trình — không dùng số bước cứng
-- 1=Chờ nộp | 2=Chờ thẩm định | 3=Đang bổ sung | 4=Chờ TP duyệt
-- 5=Chờ BGH duyệt | 6=Đã duyệt | 7=Đã chi trả | 8=Từ chối | 9=Đã hủy
-- ----------------------------------------------------------------------
CREATE TABLE TRANG_THAI (
    MaTrangThai  TINYINT     NOT NULL,
    TenTrangThai VARCHAR(60) NOT NULL COMMENT 'Nhãn hiển thị trên giao diện',
    MoTa         TEXT        NULL,
    PRIMARY KEY (MaTrangThai),
    UNIQUE KEY UQ_TrangThai_Ten (TenTrangThai)
) ENGINE=InnoDB COMMENT='Trạng thái quy trình xét duyệt hồ sơ';

INSERT INTO TRANG_THAI VALUES
    (1, 'Chờ nộp hồ sơ',            'Sinh viên chưa nộp đủ giấy tờ minh chứng'),
    (2, 'Chờ thẩm định',            'Hồ sơ chờ Cán bộ CTSV xem xét'),
    (3, 'Đang bổ sung',             'Cán bộ yêu cầu SV bổ sung giấy tờ còn thiếu'),
    (4, 'Chờ Trưởng phòng duyệt',  'Thẩm định xong, chờ Trưởng phòng CTSV ký'),
    (5, 'Chờ Ban Giám hiệu duyệt', 'Trường hợp đặc biệt, chờ BGH phê duyệt'),
    (6, 'Đã duyệt',                'Hồ sơ được chấp thuận, chờ Tài vụ xử lý chi trả'),
    (7, 'Đã chi trả',              'Tài vụ đã xác nhận thực hiện chi trả thành công'),
    (8, 'Từ chối',                 'Hồ sơ không đạt yêu cầu — có ghi rõ lý do'),
    (9, 'Đã hủy',                  'Sinh viên tự rút hồ sơ');

-- ----------------------------------------------------------------------
-- Bảng: LOAI_CHINH_SACH
-- 1=MGHP (BM.01) | 2=TCXH (BM.02)
-- ----------------------------------------------------------------------
CREATE TABLE LOAI_CHINH_SACH (
    MaLoaiCS  TINYINT     NOT NULL,
    MaForm    VARCHAR(10) NOT NULL COMMENT 'Mã biểu mẫu: BM.01, BM.02',
    TenLoaiCS VARCHAR(100)NOT NULL,
    MoTa      TEXT        NULL,
    PRIMARY KEY (MaLoaiCS),
    UNIQUE KEY UQ_LoaiCS_MaForm (MaForm)
) ENGINE=InnoDB COMMENT='Phân loại đơn chính sách theo biểu mẫu';

INSERT INTO LOAI_CHINH_SACH VALUES
    (1, 'BM.01', 'Miễn Giảm Học Phí (MGHP)',
        'Miễn/giảm HP theo NĐ81. Nếu miễn giảm > HP phải đóng thì phần dư hoàn về TK SV.'),
    (2, 'BM.02', 'Trợ Cấp Xã Hội (TCXH)',
        'Trợ cấp hàng tháng. Hệ thống ghi nhận lệnh chi để Tài vụ chuyển khoản thủ công.');

-- ----------------------------------------------------------------------
-- Bảng: LOAI_GIAY_TO
-- Danh mục chuẩn hóa các loại giấy tờ minh chứng
-- ----------------------------------------------------------------------
CREATE TABLE LOAI_GIAY_TO (
    MaLoaiGiayTo  TINYINT      NOT NULL,
    TenLoaiGiayTo VARCHAR(100) NOT NULL,
    MoTa          TEXT         NULL,
    BatBuoc       TINYINT   NOT NULL DEFAULT 0 COMMENT '1=bắt buộc, 0=tùy chọn',
    PRIMARY KEY (MaLoaiGiayTo),
    UNIQUE KEY UQ_LoaiGT_Ten (TenLoaiGiayTo)
) ENGINE=InnoDB COMMENT='Danh mục loại giấy tờ minh chứng hồ sơ';

INSERT INTO LOAI_GIAY_TO VALUES
    (1, 'Giấy xác nhận hộ nghèo/cận nghèo', 'Do UBND cấp xã/phường cấp, còn hiệu lực', 1),
    (2, 'Giấy khai sinh',                   'Bản sao có công chứng', 1),
    (3, 'Căn cước công dân',                'Mặt trước và mặt sau, còn hiệu lực', 1),
    (4, 'Sổ hộ khẩu / Giấy đăng ký thường trú', 'Trang bìa và trang thông tin', 0),
    (5, 'Giấy xác nhận dân tộc thiểu số',   'Do cơ quan có thẩm quyền cấp', 0),
    (6, 'Giấy xác nhận con thương binh/liệt sĩ', 'Kèm quyết định công nhận', 0),
    (7, 'Giấy xác nhận con người có công',  'Do Sở Lao động TB&XH cấp', 0),
    (8, 'Hóa đơn/Phiếu thu học phí',        'Xác minh mức HP thực tế phải đóng', 0),
    (9, 'Giấy tờ khác',                     'Giấy tờ bổ sung theo yêu cầu cán bộ', 0);

-- ----------------------------------------------------------------------
-- Bảng: KHOA — MaKhoa dùng VARCHAR(10) mã chữ do trường quy định
-- ----------------------------------------------------------------------
CREATE TABLE KHOA (
    MaKhoa  VARCHAR(10)  NOT NULL COMMENT 'Mã chữ: CNTT, DDT, CK...',
    TenKhoa VARCHAR(100) NOT NULL,
    PRIMARY KEY (MaKhoa)
) ENGINE=InnoDB COMMENT='Danh mục Khoa của trường';

INSERT INTO KHOA VALUES
    ('CNTT', 'Khoa Công nghệ Thông tin'),
    ('DDT',  'Khoa Điện - Điện tử'),
    ('CK',   'Khoa Cơ khí'),
    ('KT',   'Khoa Kinh tế'),
    ('XD',   'Khoa Xây dựng');

-- ----------------------------------------------------------------------
-- Bảng: DANH_MUC_LOP — thêm KhoaHoc (SMALLINT) năm nhập học
-- ----------------------------------------------------------------------
CREATE TABLE DANH_MUC_LOP (
    MaLop    INT          NOT NULL AUTO_INCREMENT,
    TenLop   VARCHAR(50)  NOT NULL COMMENT 'Mã lớp: 21CNTT1, 22DDT2...',
    MaKhoa   VARCHAR(10)  NOT NULL,
    KhoaHoc  SMALLINT     NOT NULL COMMENT 'Năm bắt đầu khóa: 2021, 2022...',
    PRIMARY KEY (MaLop),
    CONSTRAINT FK_Lop_Khoa  FOREIGN KEY (MaKhoa) REFERENCES KHOA(MaKhoa),
    CONSTRAINT CHK_Lop_KhoaHoc CHECK (KhoaHoc BETWEEN 2000 AND 2100)
) ENGINE=InnoDB COMMENT='Danh mục lớp sinh hoạt theo Khoa và khóa học';

-- ----------------------------------------------------------------------
-- Bảng: NAM_HOC — MaNamHoc dùng MEDIUMINT (VD: 20241 = NH2024-2025 HK1)
-- ----------------------------------------------------------------------
CREATE TABLE NAM_HOC (
    MaNamHoc    MEDIUMINT   NOT NULL COMMENT 'Mã số: 20241=HK1/2024-2025',
    TenNamHoc   VARCHAR(20) NOT NULL COMMENT 'Hiển thị: 2024-2025',
    HocKy       TINYINT     NOT NULL COMMENT '1, 2, hoặc 3 (hè)',
    NgayBatDau  DATE        NOT NULL,
    NgayKetThuc DATE        NOT NULL,
    IsActive    TINYINT  NOT NULL DEFAULT 1 COMMENT '1=đang diễn ra',
    PRIMARY KEY (MaNamHoc),
    CONSTRAINT CHK_NamHoc_HK CHECK (HocKy IN (1, 2, 3))
) ENGINE=InnoDB COMMENT='Năm học và học kỳ';

INSERT INTO NAM_HOC VALUES
    (20241, '2024-2025', 1, '2024-09-02', '2025-01-15', 0),
    (20242, '2024-2025', 2, '2025-02-10', '2025-06-15', 1);

-- ----------------------------------------------------------------------
-- Bảng: DOT_THU_HO_SO — Đợt thu nhận hồ sơ trong học kỳ
-- ----------------------------------------------------------------------
CREATE TABLE DOT_THU_HO_SO (
    MaDot        INT         NOT NULL AUTO_INCREMENT,
    TenDot       VARCHAR(100)NOT NULL COMMENT 'VD: Đợt 1 - HK2 2024-2025',
    MaNamHoc     MEDIUMINT   NOT NULL,
    NgayBatDau   DATE        NOT NULL,
    NgayKetThuc  DATE        NOT NULL,
    TrangThaiDot TINYINT  NOT NULL DEFAULT 1 COMMENT '1=Đang mở, 0=Đã đóng',
    PRIMARY KEY (MaDot),
    CONSTRAINT FK_Dot_NamHoc FOREIGN KEY (MaNamHoc) REFERENCES NAM_HOC(MaNamHoc)
) ENGINE=InnoDB COMMENT='Đợt thu hồ sơ chính sách';

-- ======================================================================
-- PHẦN 2: NGƯỜI DÙNG & SINH VIÊN
-- ======================================================================

-- ----------------------------------------------------------------------
-- Bảng: NGUOI_DUNG — Tài khoản đăng nhập (tách khỏi thông tin cá nhân)
-- ----------------------------------------------------------------------
CREATE TABLE NGUOI_DUNG (
    MaNguoiDung INT          NOT NULL AUTO_INCREMENT,
    Email       VARCHAR(150) NOT NULL,
    MatKhau     VARCHAR(256) NOT NULL COMMENT 'Hash bcrypt',
    MaVaiTro    TINYINT      NOT NULL,
    TrangThai   TINYINT      NOT NULL DEFAULT 1
        COMMENT '1=HoatDong, 2=KhoaTam, 3=Xoa',
    NgayTao     DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (MaNguoiDung),
    UNIQUE KEY UQ_NguoiDung_Email (Email),
    CONSTRAINT FK_NguoiDung_VaiTro FOREIGN KEY (MaVaiTro) REFERENCES VAI_TRO(MaVaiTro),
    CONSTRAINT CHK_NguoiDung_TT    CHECK (TrangThai IN (1, 2, 3))
) ENGINE=InnoDB COMMENT='Tài khoản đăng nhập hệ thống';

-- ----------------------------------------------------------------------
-- Bảng: CAN_BO — Thông tin cá nhân cán bộ (quan hệ 1-1 với NGUOI_DUNG)
-- ----------------------------------------------------------------------
CREATE TABLE CAN_BO (
    MaNguoiDung INT          NOT NULL COMMENT 'PK đồng thời là FK',
    MaNhanVien  VARCHAR(20)  NOT NULL COMMENT 'Mã NV do trường cấp',
    HoTen       VARCHAR(150) NOT NULL,
    PhongBan    VARCHAR(100) NULL COMMENT 'Phòng CTSV, Phòng Tài vụ...',
    ChucVu      VARCHAR(100) NULL,
    SoDienThoai VARCHAR(15)  NULL,
    PRIMARY KEY (MaNguoiDung),
    UNIQUE KEY UQ_CanBo_MaNV      (MaNhanVien),
    CONSTRAINT FK_CanBo_NguoiDung FOREIGN KEY (MaNguoiDung) REFERENCES NGUOI_DUNG(MaNguoiDung)
) ENGINE=InnoDB COMMENT='Thông tin cá nhân cán bộ';

-- ----------------------------------------------------------------------
-- Bảng: SINH_VIEN — Thông tin cá nhân SV (quan hệ 1-1 với NGUOI_DUNG)
-- Bao gồm địa chỉ thường trú, tạm trú, dân tộc, đối tượng CS
-- ----------------------------------------------------------------------
CREATE TABLE SINH_VIEN (
    MaNguoiDung       INT          NOT NULL COMMENT 'PK đồng thời là FK',
    MaSoSV            VARCHAR(20)  NOT NULL COMMENT 'Mã SV do trường cấp',
    HoTen             VARCHAR(150) NOT NULL,
    NgaySinh          DATE         NULL,
    GioiTinh          TINYINT      NULL COMMENT '1=Nam, 2=Nữ, 3=Khác',
    CCCD              VARCHAR(12)  NULL COMMENT '12 chữ số',
    MaLop             INT          NOT NULL,
    SoDienThoai       VARCHAR(15)  NULL,
    -- Địa chỉ thường trú (hộ khẩu/CCCD) — xét điều kiện pháp lý chính sách
    DiaChiThuongTru   VARCHAR(400) NULL,
    TinhThuongTru     VARCHAR(60)  NULL,
    -- Địa chỉ tạm trú (nơi ở khi đi học) — dùng để liên lạc
    DiaChiTamTru      VARCHAR(400) NULL,
    TinhTamTru        VARCHAR(60)  NULL,
    -- Ảnh hưởng đến mức miễn giảm theo Nghị định 81
    DanToc            VARCHAR(50)  NULL,
    DoiTuongCS        VARCHAR(200) NULL COMMENT 'Hộ nghèo, Dân tộc thiểu số...',
    PRIMARY KEY (MaNguoiDung),
    UNIQUE KEY UQ_SV_MaSoSV    (MaSoSV),
    UNIQUE KEY UQ_SV_CCCD      (CCCD),
    CONSTRAINT FK_SV_NguoiDung FOREIGN KEY (MaNguoiDung) REFERENCES NGUOI_DUNG(MaNguoiDung),
    CONSTRAINT FK_SV_Lop       FOREIGN KEY (MaLop)       REFERENCES DANH_MUC_LOP(MaLop),
    CONSTRAINT CHK_SV_GioiTinh CHECK (GioiTinh IN (1, 2, 3))
) ENGINE=InnoDB COMMENT='Thông tin cá nhân sinh viên';

-- ----------------------------------------------------------------------
-- Bảng: TAI_KHOAN_NGAN_HANG_SV
-- Tách riêng TK NH khỏi SINH_VIEN. Hệ thống CHỈ lưu số TK để
-- Cán bộ Tài vụ tra cứu và CK thủ công. KHÔNG có API ngân hàng.
-- ----------------------------------------------------------------------
CREATE TABLE TAI_KHOAN_NGAN_HANG_SV (
    MaTaiKhoan      INT          NOT NULL AUTO_INCREMENT,
    MaSinhVien      INT          NOT NULL,
    SoTaiKhoan      VARCHAR(20)  NOT NULL COMMENT 'Chỉ chữ số',
    TenNganHang     VARCHAR(60)  NOT NULL COMMENT 'BIDV, VietinBank...',
    ChiNhanh        VARCHAR(100) NULL,
    TenChuTaiKhoan  VARCHAR(150) NOT NULL COMMENT 'Phải khớp tên trên thẻ',
    LoaiThe         TINYINT      NOT NULL DEFAULT 1
        COMMENT '1=Thẻ SV tích hợp NH, 2=TK cá nhân',
    IsDefault       TINYINT   NOT NULL DEFAULT 1 COMMENT '1=TK mặc định nhận tiền',
    NgayCapNhat     DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (MaTaiKhoan),
    UNIQUE KEY UQ_TKNH_SoTK     (SoTaiKhoan),
    CONSTRAINT FK_TKNH_SinhVien FOREIGN KEY (MaSinhVien) REFERENCES SINH_VIEN(MaNguoiDung),
    CONSTRAINT CHK_TKNH_LoaiThe CHECK (LoaiThe IN (1, 2))
) ENGINE=InnoDB COMMENT='Tài khoản ngân hàng tích hợp thẻ sinh viên';

-- ======================================================================
-- PHẦN 3: MÔN HỌC & LỊCH SỬ TKB (LOGIC HỌC LẠI)
-- ======================================================================

-- ----------------------------------------------------------------------
-- Bảng: DANH_MUC_HOC_PHAN — Kho môn học CHUẨN, MaHP là định danh so sánh
-- ----------------------------------------------------------------------
CREATE TABLE DANH_MUC_HOC_PHAN (
    MaHP     VARCHAR(15)    NOT NULL COMMENT 'Mã chuẩn: TDTT01, IT001...',
    TenHP    VARCHAR(200)   NOT NULL COMMENT 'Tên môn chuẩn (không phải tên lớp)',
    SoTinChi TINYINT        NOT NULL,
    DonGia   DECIMAL(18,0)  NULL COMMENT 'Đơn giá/tín chỉ (VNĐ)',
    Cap      VARCHAR(40)    NULL COMMENT 'Đại cương, Cơ sở ngành, Chuyên ngành',
    GhiChu   VARCHAR(300)   NULL,
    PRIMARY KEY (MaHP),
    CONSTRAINT CHK_HP_TinChi CHECK (SoTinChi BETWEEN 1 AND 10)
) ENGINE=InnoDB COMMENT='Danh mục môn học chuẩn — dùng để xác định học lại';

INSERT INTO DANH_MUC_HOC_PHAN VALUES
    ('TDTT01', 'Giáo dục thể chất 1', 1, NULL, 'Đại cương', NULL),
    ('TDTT02', 'Giáo dục thể chất 2', 1, NULL, 'Đại cương', NULL),
    ('IT001',  'Nhập môn CNTT',        3, NULL, 'Đại cương', NULL),
    ('IT002',  'Lập trình căn bản',    3, NULL, 'Cơ sở ngành', NULL);

-- ----------------------------------------------------------------------
-- Bảng: LICH_SU_TKB — Lớp học phần cụ thể từng học kỳ
-- Ví dụ: MaHP='TDTT01' → TenLHP='Bóng chuyền Nhóm 1' (HK1)
--                        → TenLHP='Pickleball Nhóm 3'  (HK2)
-- Trigger so sánh MaHP → IsHocLai = 1 dù TenLHP khác nhau
-- ----------------------------------------------------------------------
CREATE TABLE LICH_SU_TKB (
    MaTKB       INT          NOT NULL AUTO_INCREMENT,
    MaHP        VARCHAR(15)  NOT NULL COMMENT 'Mã môn chuẩn (FK)',
    TenLHP      VARCHAR(200) NOT NULL COMMENT 'Tên lớp HP cụ thể kỳ này',
    MaNamHoc    MEDIUMINT    NOT NULL,
    GiangVien   VARCHAR(100) NULL,
    Thu         TINYINT      NULL COMMENT '2–7 (Thứ 2 đến Thứ 7)',
    TuTiet      TINYINT      NULL,
    DenTiet     TINYINT      NULL,
    Phong       VARCHAR(20)  NULL,
    SiSoDK      SMALLINT     NULL COMMENT 'Sĩ số tối đa',
    SiSoHienTai SMALLINT     NOT NULL DEFAULT 0,
    LoaiDK      TINYINT      NOT NULL DEFAULT 1
        COMMENT '1=Bình thường, 2=Học lại, 3=Cải thiện',
    MaNguoiTao  INT          NOT NULL COMMENT 'Cán bộ tạo lịch',
    GhiChu      VARCHAR(300) NULL,
    PRIMARY KEY (MaTKB),
    CONSTRAINT FK_TKB_HP        FOREIGN KEY (MaHP)       REFERENCES DANH_MUC_HOC_PHAN(MaHP),
    CONSTRAINT FK_TKB_NamHoc    FOREIGN KEY (MaNamHoc)   REFERENCES NAM_HOC(MaNamHoc),
    CONSTRAINT FK_TKB_NguoiTao  FOREIGN KEY (MaNguoiTao) REFERENCES NGUOI_DUNG(MaNguoiDung),
    CONSTRAINT CHK_TKB_Thu      CHECK (Thu BETWEEN 2 AND 7),
    CONSTRAINT CHK_TKB_LoaiDK   CHECK (LoaiDK IN (1, 2, 3))
) ENGINE=InnoDB COMMENT='Lớp học phần cụ thể từng học kỳ';

-- ----------------------------------------------------------------------
-- Bảng: DANG_KY_HOC_PHAN — Trung gian SINH_VIEN ↔ LICH_SU_TKB
-- IsHocLai được trigger TRG_KiemTraHocLai tự động cập nhật
-- ----------------------------------------------------------------------
CREATE TABLE DANG_KY_HOC_PHAN (
    MaDangKy   INT           NOT NULL AUTO_INCREMENT,
    MaSinhVien INT           NOT NULL,
    MaTKB      INT           NOT NULL,
    NgayDangKy DATETIME      NOT NULL DEFAULT NOW(),
    IsHocLai   TINYINT    NOT NULL DEFAULT 0 COMMENT 'Trigger tự cập nhật',
    DiemThi    DECIMAL(4,2)  NULL,
    KetQua     TINYINT       NULL
        COMMENT '1=Đạt, 2=Không đạt, 3=Vắng thi, 4=Chưa thi',
    PRIMARY KEY (MaDangKy),
    UNIQUE KEY UQ_DangKy_SV_TKB   (MaSinhVien, MaTKB),
    CONSTRAINT FK_DangKy_SinhVien FOREIGN KEY (MaSinhVien) REFERENCES SINH_VIEN(MaNguoiDung),
    CONSTRAINT FK_DangKy_TKB      FOREIGN KEY (MaTKB)      REFERENCES LICH_SU_TKB(MaTKB),
    CONSTRAINT CHK_DangKy_Diem    CHECK (DiemThi BETWEEN 0 AND 10),
    CONSTRAINT CHK_DangKy_KetQua  CHECK (KetQua IN (1, 2, 3, 4))
) ENGINE=InnoDB COMMENT='Đăng ký học phần — tự động phát hiện học lại qua trigger';

-- Trigger: Kiểm tra học lại dựa trên MaHP chuẩn (không phải tên lớp)
DELIMITER $$
CREATE TRIGGER TRG_KiemTraHocLai
AFTER INSERT ON DANG_KY_HOC_PHAN
FOR EACH ROW
BEGIN
    DECLARE v_MaHP VARCHAR(15);

    -- Lấy MaHP của lớp vừa đăng ký
    SELECT MaHP INTO v_MaHP
    FROM LICH_SU_TKB
    WHERE MaTKB = NEW.MaTKB;

    -- Kiểm tra SV đã từng đăng ký cùng MaHP ở bất kỳ lớp nào trước đó
    IF EXISTS (
        SELECT 1
        FROM DANG_KY_HOC_PHAN dk
        INNER JOIN LICH_SU_TKB tkb ON dk.MaTKB = tkb.MaTKB
        WHERE dk.MaSinhVien = NEW.MaSinhVien
          AND tkb.MaHP      = v_MaHP
          AND dk.MaDangKy  <> NEW.MaDangKy
    ) THEN
        -- Cập nhật IsHocLai = 1 cho bản ghi vừa insert
        UPDATE DANG_KY_HOC_PHAN
        SET IsHocLai = 1
        WHERE MaDangKy = NEW.MaDangKy;
    END IF;
END$$
DELIMITER ;

-- ======================================================================
-- PHẦN 4: HỒ SƠ & QUY TRÌNH XÉT DUYỆT ĐA CẤP
-- ======================================================================

-- ----------------------------------------------------------------------
-- Bảng: HO_SO — Đơn đăng ký chính sách (BM.01 hoặc BM.02)
-- ----------------------------------------------------------------------
CREATE TABLE HO_SO (
    MaHoSo      INT         NOT NULL AUTO_INCREMENT,
    MaNguoiDung INT         NOT NULL COMMENT 'Sinh viên nộp đơn',
    MaDot       INT         NOT NULL,
    MaLoaiCS    TINYINT     NOT NULL COMMENT '1=MGHP, 2=TCXH',
    MaTrangThai TINYINT     NOT NULL DEFAULT 1,
    NgayNop     DATETIME    NOT NULL DEFAULT NOW(),
    NgayCapNhat DATETIME    NOT NULL DEFAULT NOW(),
    GhiChu      TEXT        NULL,
    LyDoTuChoi  VARCHAR(400)NULL COMMENT 'Bắt buộc điền nếu TrangThai=8',
    PRIMARY KEY (MaHoSo),
    CONSTRAINT FK_HoSo_NguoiDung FOREIGN KEY (MaNguoiDung) REFERENCES NGUOI_DUNG(MaNguoiDung),
    CONSTRAINT FK_HoSo_Dot       FOREIGN KEY (MaDot)       REFERENCES DOT_THU_HO_SO(MaDot),
    CONSTRAINT FK_HoSo_LoaiCS    FOREIGN KEY (MaLoaiCS)    REFERENCES LOAI_CHINH_SACH(MaLoaiCS),
    CONSTRAINT FK_HoSo_TrangThai FOREIGN KEY (MaTrangThai) REFERENCES TRANG_THAI(MaTrangThai)
) ENGINE=InnoDB COMMENT='Hồ sơ đăng ký chính sách của sinh viên';

-- ----------------------------------------------------------------------
-- Bảng: MINH_CHUNG_FILE — File đính kèm lưu trên Cloudinary
-- ----------------------------------------------------------------------
CREATE TABLE MINH_CHUNG_FILE (
    MaMinhChung    INT          NOT NULL AUTO_INCREMENT,
    MaHoSo         INT          NOT NULL,
    MaLoaiGiayTo   TINYINT      NOT NULL COMMENT 'FK -> LOAI_GIAY_TO',
    URL_Cloudinary VARCHAR(255) NOT NULL,
    PublicId       VARCHAR(255) NULL COMMENT 'ID Cloudinary dùng để xóa file',
    NgayTaiLen     DATETIME     NOT NULL DEFAULT NOW(),
    PRIMARY KEY (MaMinhChung),
    CONSTRAINT FK_MinhChung_HoSo      FOREIGN KEY (MaHoSo)       REFERENCES HO_SO(MaHoSo),
    CONSTRAINT FK_MinhChung_LoaiGiayTo FOREIGN KEY (MaLoaiGiayTo) REFERENCES LOAI_GIAY_TO(MaLoaiGiayTo)
) ENGINE=InnoDB COMMENT='File minh chứng hồ sơ lưu trên Cloudinary';

-- ----------------------------------------------------------------------
-- Bảng: NHAT_KY_XET_DUYET — Audit log từng thao tác của cán bộ
-- ----------------------------------------------------------------------
CREATE TABLE NHAT_KY_XET_DUYET (
    MaNhatKy        INT          NOT NULL AUTO_INCREMENT,
    MaHoSo          INT          NOT NULL,
    MaNguoiThucHien INT          NOT NULL,
    HanhDong        VARCHAR(100) NOT NULL
        COMMENT 'Tiếp nhận, Thẩm định, Yêu cầu bổ sung, Phê duyệt, Từ chối...',
    ThoiGian        DATETIME     NOT NULL DEFAULT NOW(),
    TrangThaiTruoc  TINYINT      NULL,
    TrangThaiSau    TINYINT      NULL,
    GhiChu          TEXT         NULL,
    MayTinh         VARCHAR(50)  NULL COMMENT 'IP máy thực hiện (audit bảo mật)',
    PRIMARY KEY (MaNhatKy),
    CONSTRAINT FK_NK_HoSo           FOREIGN KEY (MaHoSo)          REFERENCES HO_SO(MaHoSo),
    CONSTRAINT FK_NK_NguoiThucHien  FOREIGN KEY (MaNguoiThucHien) REFERENCES NGUOI_DUNG(MaNguoiDung),
    CONSTRAINT FK_NK_TrangThaiTruoc FOREIGN KEY (TrangThaiTruoc)  REFERENCES TRANG_THAI(MaTrangThai),
    CONSTRAINT FK_NK_TrangThaiSau   FOREIGN KEY (TrangThaiSau)    REFERENCES TRANG_THAI(MaTrangThai)
) ENGINE=InnoDB COMMENT='Nhật ký chi tiết từng thao tác xét duyệt hồ sơ';

-- ----------------------------------------------------------------------
-- Bảng: QUYET_DINH_BAN_HANH — Quyết định chính thức, lưu file PDF
-- ----------------------------------------------------------------------
CREATE TABLE QUYET_DINH_BAN_HANH (
    MaQuyetDinh INT          NOT NULL AUTO_INCREMENT,
    SoQD        VARCHAR(30)  NOT NULL COMMENT 'VD: 123/QĐ-UTEUDN',
    NgayBanHanh DATE         NOT NULL,
    URL_FilePDF VARCHAR(255) NULL,
    MaHoSo      INT          NOT NULL,
    MaNguoiKy   INT          NOT NULL COMMENT 'Trưởng phòng hoặc BGH ký',
    PRIMARY KEY (MaQuyetDinh),
    UNIQUE KEY UQ_QD_SoQD    (SoQD),
    CONSTRAINT FK_QD_HoSo    FOREIGN KEY (MaHoSo)    REFERENCES HO_SO(MaHoSo),
    CONSTRAINT FK_QD_NguoiKy FOREIGN KEY (MaNguoiKy) REFERENCES NGUOI_DUNG(MaNguoiDung)
) ENGINE=InnoDB COMMENT='Quyết định ban hành chính thức';

-- ======================================================================
-- PHẦN 5: TÀI CHÍNH — MGHP & TCXH
-- ======================================================================
-- LƯU Ý: Hệ thống CHỈ ghi nhận lệnh chi trả nội bộ.
--         Cán bộ Tài vụ xem danh sách rồi CK thủ công.
--         KHÔNG có API ngân hàng tự động.

-- ----------------------------------------------------------------------
-- Bảng: CONG_NO — Học phí và MGHP (BM.01). Tất cả cột tiền DECIMAL(18,0)
-- ----------------------------------------------------------------------
CREATE TABLE CONG_NO (
    MaCongNo       INT           NOT NULL AUTO_INCREMENT,
    MaNguoiDung    INT           NOT NULL,
    MaHoSo         INT           NOT NULL,
    MaNamHoc       MEDIUMINT     NOT NULL,
    HocPhiPhaiDong DECIMAL(18,0) NOT NULL COMMENT 'HP gốc trước miễn giảm (VNĐ)',
    SoTienMienGiam DECIMAL(18,0) NOT NULL DEFAULT 0,
    SoTienConLai   DECIMAL(18,0) NOT NULL DEFAULT 0 COMMENT 'Trigger tự tính',
    TienDuMGHP     DECIMAL(18,0) NOT NULL DEFAULT 0 COMMENT 'Trigger tự tính, nếu >0 thì hoàn tiền',
    NgayCapNhat    DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (MaCongNo),
    CONSTRAINT FK_CongNo_NguoiDung FOREIGN KEY (MaNguoiDung) REFERENCES NGUOI_DUNG(MaNguoiDung),
    CONSTRAINT FK_CongNo_HoSo      FOREIGN KEY (MaHoSo)      REFERENCES HO_SO(MaHoSo),
    CONSTRAINT FK_CongNo_NamHoc    FOREIGN KEY (MaNamHoc)     REFERENCES NAM_HOC(MaNamHoc),
    CONSTRAINT CHK_CongNo_HP       CHECK (HocPhiPhaiDong >= 0),
    CONSTRAINT CHK_CongNo_MienGiam CHECK (SoTienMienGiam >= 0)
) ENGINE=InnoDB COMMENT='Công nợ học phí và ghi nhận miễn giảm (BM.01)';

-- Trigger: Tự tính SoTienConLai và TienDuMGHP sau INSERT
DELIMITER $$
CREATE TRIGGER TRG_TinhToanCongNo_Insert
AFTER INSERT ON CONG_NO
FOR EACH ROW
BEGIN
    UPDATE CONG_NO SET
        SoTienConLai = CASE
            WHEN NEW.SoTienMienGiam >= NEW.HocPhiPhaiDong THEN 0
            ELSE NEW.HocPhiPhaiDong - NEW.SoTienMienGiam
        END,
        TienDuMGHP = CASE
            WHEN NEW.SoTienMienGiam > NEW.HocPhiPhaiDong
            THEN NEW.SoTienMienGiam - NEW.HocPhiPhaiDong
            ELSE 0
        END
    WHERE MaCongNo = NEW.MaCongNo;
END$$
DELIMITER ;

-- Trigger: Tự tính SoTienConLai và TienDuMGHP sau UPDATE
-- QUAN TRỌNG: Chỉ chạy khi HocPhiPhaiDong hoặc SoTienMienGiam thực sự thay đổi
-- Tránh đệ quy vô hạn: AFTER UPDATE → UPDATE SoTienConLai → AFTER UPDATE → ...
DELIMITER $$
CREATE TRIGGER TRG_TinhToanCongNo_Update
AFTER UPDATE ON CONG_NO
FOR EACH ROW
BEGIN
    -- Chỉ tính lại khi 2 cột đầu vào thay đổi, KHÔNG phải khi SoTienConLai/TienDuMGHP thay đổi
    IF (NEW.HocPhiPhaiDong <> OLD.HocPhiPhaiDong OR NEW.SoTienMienGiam <> OLD.SoTienMienGiam) THEN
        UPDATE CONG_NO SET
            SoTienConLai = CASE
                WHEN NEW.SoTienMienGiam >= NEW.HocPhiPhaiDong THEN 0
                ELSE NEW.HocPhiPhaiDong - NEW.SoTienMienGiam
            END,
            TienDuMGHP = CASE
                WHEN NEW.SoTienMienGiam > NEW.HocPhiPhaiDong
                THEN NEW.SoTienMienGiam - NEW.HocPhiPhaiDong
                ELSE 0
            END
        WHERE MaCongNo = NEW.MaCongNo;
    END IF;
END$$
DELIMITER ;

-- ----------------------------------------------------------------------
-- Bảng: GIAO_DICH_NOI_BO
-- LoaiGiaoDich: 1=TCXH | 2=Hoàn tiền MGHP dư
-- TrangThai: 1=Chờ | 2=Đang | 3=Đã CK | 4=Thất bại | 5=Đã hủy
-- ----------------------------------------------------------------------
CREATE TABLE GIAO_DICH_NOI_BO (
    MaGiaoDich       INT           NOT NULL AUTO_INCREMENT,
    MaNguoiDung      INT           NOT NULL COMMENT 'Sinh viên nhận tiền',
    MaHoSo           INT           NOT NULL,
    MaTaiKhoan       INT           NULL     COMMENT 'TK ngân hàng nhận',
    SoTienChuyen     DECIMAL(18,0) NOT NULL,
    LoaiGiaoDich     TINYINT       NOT NULL COMMENT '1=TCXH, 2=Hoàn tiền MGHP dư',
    TrangThai        TINYINT       NOT NULL DEFAULT 1,
    NgayTaoLenh      DATETIME      NOT NULL DEFAULT NOW(),
    NgayThucHien     DATETIME      NULL     COMMENT 'Ngày Tài vụ CK thủ công',
    MaNguoiDuyetLenh INT           NULL,
    MaSoGiaoDichNH   VARCHAR(50)   NULL     COMMENT 'Mã GD NH nhập tay sau khi CK',
    GhiChu           TEXT          NULL,
    PRIMARY KEY (MaGiaoDich),
    CONSTRAINT FK_GD_NguoiDung      FOREIGN KEY (MaNguoiDung)      REFERENCES NGUOI_DUNG(MaNguoiDung),
    CONSTRAINT FK_GD_HoSo           FOREIGN KEY (MaHoSo)           REFERENCES HO_SO(MaHoSo),
    CONSTRAINT FK_GD_TaiKhoan       FOREIGN KEY (MaTaiKhoan)       REFERENCES TAI_KHOAN_NGAN_HANG_SV(MaTaiKhoan),
    CONSTRAINT FK_GD_NguoiDuyetLenh FOREIGN KEY (MaNguoiDuyetLenh) REFERENCES NGUOI_DUNG(MaNguoiDung),
    CONSTRAINT CHK_GD_LoaiGD        CHECK (LoaiGiaoDich IN (1, 2)),
    CONSTRAINT CHK_GD_TrangThai     CHECK (TrangThai IN (1, 2, 3, 4, 5)),
    CONSTRAINT CHK_GD_SoTien        CHECK (SoTienChuyen > 0)
) ENGINE=InnoDB COMMENT='Lệnh chi trả nội bộ — Tài vụ xem và CK thủ công';

-- Trigger: Tự tạo lệnh hoàn tiền khi MGHP có tiền dư
-- (MySQL không có TRIGGER_NESTLEVEL nên dùng cách kiểm tra trực tiếp)
DELIMITER $$
CREATE TRIGGER TRG_TaoPhuongThucHoanTien
AFTER UPDATE ON CONG_NO
FOR EACH ROW
BEGIN
    DECLARE v_MaTaiKhoan INT;

    -- Tính trực tiếp từ nguồn (không dùng NEW.TienDuMGHP vì có thể chưa được cập nhật)
    DECLARE v_TienDu DECIMAL(18,0);
    SET v_TienDu = CASE
        WHEN NEW.SoTienMienGiam > NEW.HocPhiPhaiDong
        THEN NEW.SoTienMienGiam - NEW.HocPhiPhaiDong
        ELSE 0
    END;

    -- Chỉ xử lý khi có tiền dư và dữ liệu đầu vào thực sự thay đổi
    IF v_TienDu > 0 AND (NEW.HocPhiPhaiDong <> OLD.HocPhiPhaiDong OR NEW.SoTienMienGiam <> OLD.SoTienMienGiam) THEN
        -- Chưa tồn tại lệnh hoàn tiền hợp lệ cho hồ sơ này
        IF NOT EXISTS (
            SELECT 1 FROM GIAO_DICH_NOI_BO
            WHERE MaHoSo = NEW.MaHoSo
              AND LoaiGiaoDich = 2
              AND TrangThai <> 5
        ) THEN
            -- Lấy TK ngân hàng mặc định
            SELECT MaTaiKhoan INTO v_MaTaiKhoan
            FROM TAI_KHOAN_NGAN_HANG_SV
            WHERE MaSinhVien = NEW.MaNguoiDung
              AND IsDefault = 1
            LIMIT 1;

            INSERT INTO GIAO_DICH_NOI_BO
                (MaNguoiDung, MaHoSo, MaTaiKhoan, SoTienChuyen, LoaiGiaoDich, GhiChu)
            VALUES (
                NEW.MaNguoiDung,
                NEW.MaHoSo,
                v_MaTaiKhoan,
                v_TienDu,
                2,
                CONCAT('[Tự động] Hoàn tiền dư MGHP: ',
                    FORMAT(NEW.SoTienMienGiam, 0), ' đ (miễn giảm) - ',
                    FORMAT(NEW.HocPhiPhaiDong, 0), ' đ (học phí) = ',
                    FORMAT(v_TienDu, 0), ' đ (dư)')
            );
        END IF;
    END IF;
END$$
DELIMITER ;

-- ======================================================================
-- PHẦN 6: MODULE AI RAG
-- ======================================================================

-- ----------------------------------------------------------------------
-- Bảng: KHO_TRI_THUC_AI — Knowledge base Nghị định 81
-- ----------------------------------------------------------------------
CREATE TABLE KHO_TRI_THUC_AI (
    MaTriThuc     INT          NOT NULL AUTO_INCREMENT,
    TieuDe        VARCHAR(300) NOT NULL,
    NoiDungChunk  TEXT         NOT NULL COMMENT 'Nội dung chunk văn bản',
    VanBanNguon   VARCHAR(100) NOT NULL COMMENT 'Nghị định 81/2021/NĐ-CP',
    Chuong        VARCHAR(20)  NULL,
    Dieu          VARCHAR(20)  NULL,
    Khoan         VARCHAR(20)  NULL,
    Vector_ID     VARCHAR(100) NULL COMMENT 'ID trong Pinecone/ChromaDB',
    NgayCapNhat   DATETIME     NOT NULL DEFAULT NOW(),
    IsActive      TINYINT   NOT NULL DEFAULT 1,
    PRIMARY KEY (MaTriThuc)
) ENGINE=InnoDB COMMENT='Kho tri thức AI — chunks Nghị định 81 cho RAG';

-- ----------------------------------------------------------------------
-- Bảng: PHIEN_CHAT_AI — Phiên hội thoại
-- ----------------------------------------------------------------------
CREATE TABLE PHIEN_CHAT_AI (
    MaPhien         INT        NOT NULL AUTO_INCREMENT,
    MaNguoiDung     INT        NOT NULL,
    ThoiGianBatDau  DATETIME   NOT NULL DEFAULT NOW(),
    ThoiGianKetThuc DATETIME   NULL,
    DiemDanhGia     TINYINT    NULL COMMENT '1–5 sao SV đánh giá chất lượng AI',
    GhiChuDanhGia   VARCHAR(300) NULL,
    PRIMARY KEY (MaPhien),
    CONSTRAINT FK_Phien_NguoiDung FOREIGN KEY (MaNguoiDung) REFERENCES NGUOI_DUNG(MaNguoiDung),
    CONSTRAINT CHK_Phien_DiemDG   CHECK (DiemDanhGia BETWEEN 1 AND 5)
) ENGINE=InnoDB COMMENT='Phiên hội thoại với AI chatbot';

-- ----------------------------------------------------------------------
-- Bảng: TIN_NHAN_AI — Từng tin nhắn trong phiên chat
-- ----------------------------------------------------------------------
CREATE TABLE TIN_NHAN_AI (
    MaTinNhan   INT        NOT NULL AUTO_INCREMENT,
    MaPhien     INT        NOT NULL,
    VaiTro      VARCHAR(10)NOT NULL COMMENT 'user | assistant | system',
    NoiDung     TEXT       NOT NULL,
    ThoiGian    DATETIME   NOT NULL DEFAULT NOW(),
    TokenSuDung INT        NULL COMMENT 'Số token API tiêu thụ',
    PRIMARY KEY (MaTinNhan),
    CONSTRAINT FK_TinNhan_Phien   FOREIGN KEY (MaPhien) REFERENCES PHIEN_CHAT_AI(MaPhien),
    CONSTRAINT CHK_TinNhan_VaiTro CHECK (VaiTro IN ('user', 'assistant', 'system'))
) ENGINE=InnoDB COMMENT='Tin nhắn trong phiên hội thoại AI';

-- ======================================================================
-- PHẦN 6B: KẾT NỐI AI ↔ HỒ SƠ & TRI THỨC
-- ======================================================================

-- ----------------------------------------------------------------------
-- Bảng: PHAN_TICH_AI_HO_SO
-- Kết nối: KHO_TRI_THUC_AI ↔ HO_SO
-- Cán bộ mở hồ sơ → AI gợi ý mức hưởng → Cán bộ xem xét áp dụng
-- ----------------------------------------------------------------------
CREATE TABLE PHAN_TICH_AI_HO_SO (
    MaPhanTich       INT           NOT NULL AUTO_INCREMENT,
    MaHoSo           INT           NOT NULL COMMENT 'Hồ sơ được phân tích',
    MaTriThuc        INT           NOT NULL COMMENT 'Điều/Khoản AI căn cứ để gợi ý',
    MucHuongGoiY     VARCHAR(50)   NOT NULL COMMENT 'Miễn 100%, Giảm 70%, TCXH 400k/tháng...',
    NoiDungGoiY      TEXT          NOT NULL COMMENT 'AI giải thích lý do gợi ý',
    DoTinCay         DECIMAL(4,3)  NULL     COMMENT 'Độ tin cậy 0.000–1.000',
    ThoiGianPhanTich DATETIME      NOT NULL DEFAULT NOW(),
    PRIMARY KEY (MaPhanTich),
    -- Mỗi hồ sơ chỉ có một kết quả phân tích AI (quan hệ 1-1)
    UNIQUE KEY UQ_PhanTich_HoSo (MaHoSo),
    CONSTRAINT FK_PhanTich_HoSo    FOREIGN KEY (MaHoSo)    REFERENCES HO_SO(MaHoSo)
        ON DELETE NO ACTION,
    CONSTRAINT FK_PhanTich_TriThuc FOREIGN KEY (MaTriThuc)  REFERENCES KHO_TRI_THUC_AI(MaTriThuc)
        ON DELETE NO ACTION,
    CONSTRAINT CHK_PhanTich_DoTinCay CHECK (DoTinCay BETWEEN 0 AND 1)
) ENGINE=InnoDB COMMENT='Kết quả AI gợi ý mức hưởng thụ khi cán bộ mở hồ sơ';

-- ----------------------------------------------------------------------
-- Bảng: TRICH_DAN_TIN_NHAN_AI
-- Kết nối: TIN_NHAN_AI ↔ KHO_TRI_THUC_AI (N-N)
-- Lưu vết chunk nào AI dùng để trả lời — phục vụ kỹ thuật RAG
-- ----------------------------------------------------------------------
CREATE TABLE TRICH_DAN_TIN_NHAN_AI (
    MaTinNhan     INT          NOT NULL,
    MaTriThuc     INT          NOT NULL,
    DiemTuongDong DECIMAL(4,3) NULL COMMENT 'Cosine similarity 0.000–1.000',
    ThuTuUuTien   TINYINT      NULL COMMENT '1=chunk quan trọng nhất',
    PRIMARY KEY (MaTinNhan, MaTriThuc),
    -- CASCADE: Xóa TinNhan → tự xóa trích dẫn (hợp lý)
    CONSTRAINT FK_TrichDan_TinNhan FOREIGN KEY (MaTinNhan) REFERENCES TIN_NHAN_AI(MaTinNhan)
        ON DELETE CASCADE,
    -- NO ACTION: Không xóa trích dẫn khi xóa chunk tri thức
    CONSTRAINT FK_TrichDan_TriThuc FOREIGN KEY (MaTriThuc) REFERENCES KHO_TRI_THUC_AI(MaTriThuc)
        ON DELETE NO ACTION,
    CONSTRAINT CHK_TrichDan_DiemTD CHECK (DiemTuongDong BETWEEN 0 AND 1)
) ENGINE=InnoDB COMMENT='Bảng N-N: Tin nhắn AI ↔ Chunk tri thức (RAG citation)';

-- ======================================================================
-- PHẦN 7: INDEX TỐI ƯU HIỆU NĂNG
-- ======================================================================

CREATE INDEX IX_HoSo_NguoiDung_Dot    ON HO_SO               (MaNguoiDung, MaDot, MaTrangThai);
CREATE INDEX IX_DangKy_HocLai         ON DANG_KY_HOC_PHAN    (MaSinhVien, IsHocLai);
CREATE INDEX IX_GiaoDich_TrangThai     ON GIAO_DICH_NOI_BO    (TrangThai, LoaiGiaoDich, MaNguoiDung);
CREATE INDEX IX_TKNH_Default           ON TAI_KHOAN_NGAN_HANG_SV (MaSinhVien, IsDefault);
CREATE INDEX IX_PhienChat_NguoiDung    ON PHIEN_CHAT_AI       (MaNguoiDung, ThoiGianBatDau);
CREATE INDEX IX_NhatKy_HoSo            ON NHAT_KY_XET_DUYET   (MaHoSo, ThoiGian);
CREATE INDEX IX_PhanTich_TriThuc       ON PHAN_TICH_AI_HO_SO  (MaTriThuc);
CREATE INDEX IX_TrichDan_TriThuc       ON TRICH_DAN_TIN_NHAN_AI (MaTriThuc, DiemTuongDong);

-- ======================================================================
-- PHẦN 8: VIEWS NGHIỆP VỤ
-- ======================================================================

-- Danh sách SV học lại
CREATE OR REPLACE VIEW V_DANH_SACH_HOC_LAI AS
SELECT
    sv.MaSoSV,
    sv.HoTen,
    mhp.MaHP,
    mhp.TenHP       AS TenMonChuan,
    tkb.TenLHP      AS TenLopHocPhan,
    mhp.SoTinChi,
    nh.TenNamHoc,
    nh.HocKy,
    dk.NgayDangKy,
    dk.DiemThi,
    dk.KetQua
FROM DANG_KY_HOC_PHAN     dk
JOIN SINH_VIEN             sv  ON dk.MaSinhVien = sv.MaNguoiDung
JOIN LICH_SU_TKB           tkb ON dk.MaTKB      = tkb.MaTKB
JOIN DANH_MUC_HOC_PHAN     mhp ON tkb.MaHP      = mhp.MaHP
JOIN NAM_HOC               nh  ON tkb.MaNamHoc  = nh.MaNamHoc
WHERE dk.IsHocLai = 1;

-- Danh sách lệnh chi chờ Tài vụ xử lý
CREATE OR REPLACE VIEW V_LENH_CHI_CHO_XU_LY AS
SELECT
    gd.MaGiaoDich,
    CASE gd.LoaiGiaoDich
        WHEN 1 THEN 'Trợ cấp xã hội (TCXH)'
        WHEN 2 THEN 'Hoàn tiền dư MGHP'
    END                   AS LoaiGiaoDich,
    sv.MaSoSV,
    sv.HoTen,
    tknh.SoTaiKhoan,
    tknh.TenNganHang,
    tknh.TenChuTaiKhoan,
    gd.SoTienChuyen,
    gd.GhiChu,
    gd.NgayTaoLenh,
    lcs.MaForm            AS MaFormHoSo
FROM GIAO_DICH_NOI_BO       gd
JOIN NGUOI_DUNG              nd   ON gd.MaNguoiDung = nd.MaNguoiDung
JOIN SINH_VIEN               sv   ON sv.MaNguoiDung = nd.MaNguoiDung
JOIN HO_SO                   hs   ON gd.MaHoSo      = hs.MaHoSo
JOIN LOAI_CHINH_SACH         lcs  ON hs.MaLoaiCS    = lcs.MaLoaiCS
LEFT JOIN TAI_KHOAN_NGAN_HANG_SV tknh ON gd.MaTaiKhoan = tknh.MaTaiKhoan
WHERE gd.TrangThai = 1;

-- SV xem tổng hợp tài chính của mình
CREATE OR REPLACE VIEW V_TONG_HOP_TAI_CHINH_SV AS
SELECT
    sv.MaSoSV,
    sv.HoTen,
    lcs.MaForm            AS MaFormHoSo,
    hs.MaHoSo,
    tt.TenTrangThai       AS TrangThaiHoSo,
    cn.HocPhiPhaiDong,
    cn.SoTienMienGiam,
    cn.SoTienConLai,
    cn.TienDuMGHP,
    gd.SoTienChuyen       AS SoTienChiTra,
    CASE gd.LoaiGiaoDich
        WHEN 1 THEN 'TCXH'
        WHEN 2 THEN 'Hoàn tiền MGHP'
        ELSE NULL
    END                   AS LoaiChiTra,
    CASE gd.TrangThai
        WHEN 1 THEN 'Chờ xử lý'
        WHEN 2 THEN 'Đang xử lý'
        WHEN 3 THEN 'Đã chuyển khoản'
        WHEN 4 THEN 'Thất bại'
        WHEN 5 THEN 'Đã hủy'
        ELSE NULL
    END                   AS TrangThaiChiTra,
    gd.NgayThucHien       AS NgayChiTra
FROM HO_SO                   hs
JOIN NGUOI_DUNG               nd  ON hs.MaNguoiDung = nd.MaNguoiDung
JOIN SINH_VIEN                sv  ON sv.MaNguoiDung = nd.MaNguoiDung
JOIN TRANG_THAI               tt  ON hs.MaTrangThai  = tt.MaTrangThai
JOIN LOAI_CHINH_SACH          lcs ON hs.MaLoaiCS     = lcs.MaLoaiCS
LEFT JOIN CONG_NO             cn  ON cn.MaHoSo        = hs.MaHoSo
LEFT JOIN GIAO_DICH_NOI_BO    gd  ON gd.MaHoSo        = hs.MaHoSo;
