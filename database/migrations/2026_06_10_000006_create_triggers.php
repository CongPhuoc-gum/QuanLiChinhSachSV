<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // TRIGGER: TRG_TinhToanCongNo
        // Kích hoạt sau khi HO_SO được cập nhật trạng thái = 6 (Đã duyệt)
        DB::unprepared('
            DROP TRIGGER IF EXISTS TRG_TinhToanCongNo;
        ');

        DB::unprepared('
            CREATE TRIGGER TRG_TinhToanCongNo
            AFTER UPDATE ON HO_SO
            FOR EACH ROW
            BEGIN
                DECLARE v_muc_huong INT;
                DECLARE v_hoc_phi DECIMAL(15, 2);
                DECLARE v_hoc_ky VARCHAR(10);
                DECLARE v_nam_hoc VARCHAR(20);

                -- Chỉ xử lý nếu cập nhật trạng thái thành 6 (Đã duyệt)
                IF NEW.MaTrangThai = 6 AND OLD.MaTrangThai != 6 THEN
                    -- Lấy mức hưởng từ PHAN_TICH_AI_HO_SO
                    SELECT JSON_EXTRACT(KetQuaDoiChieu, "$.muc_huong") INTO v_muc_huong
                    FROM PHAN_TICH_AI_HO_SO
                    WHERE MaHoSo = NEW.MaHoSo
                    LIMIT 1;

                    -- Nếu không có AI analysis, mặc định 0%
                    IF v_muc_huong IS NULL THEN
                        SET v_muc_huong = 0;
                    END IF;

                    -- Tính tiền miễn giảm (tạm thời, chưa có HocPhi cụ thể)
                    SET v_hoc_phi = 5000000; -- Mặc định 5M (sẽ update từ TKB)

                    -- Lấy từ DOT_THU_HO_SO
                    SELECT HocKy, NamHoc INTO v_hoc_ky, v_nam_hoc
                    FROM DOT_THU_HO_SO
                    WHERE MaDot = NEW.MaDot
                    LIMIT 1;

                    -- Insert hoặc Update CONG_NO
                    INSERT INTO CONG_NO 
                    (MaSinhVien, MaHoSo, HocKy, NamHoc, HocPhiPhaiDong, SoTienMienGiam, SoTienDaDong, TrangThai)
                    VALUES (NEW.MaNguoiDung, NEW.MaHoSo, v_hoc_ky, v_nam_hoc, v_hoc_phi, 
                            ROUND(v_hoc_phi * v_muc_huong / 100, 2), 0, "cho_dong")
                    ON DUPLICATE KEY UPDATE
                        SoTienMienGiam = ROUND(v_hoc_phi * v_muc_huong / 100, 2),
                        SoTienPhaiDong = v_hoc_phi - ROUND(v_hoc_phi * v_muc_huong / 100, 2);
                END IF;
            END;
        ');

        // TRIGGER: TRG_TaoPhuongThucHoanTien
        // Kích hoạt sau khi TienDuMGHP > 0 được cập nhật vào CONG_NO
        DB::unprepared('
            DROP TRIGGER IF EXISTS TRG_TaoPhuongThucHoanTien;
        ');

        DB::unprepared('
            CREATE TRIGGER TRG_TaoPhuongThucHoanTien
            AFTER UPDATE ON CONG_NO
            FOR EACH ROW
            BEGIN
                DECLARE v_so_tai_khoan VARCHAR(30);
                DECLARE v_ten_ngan_hang VARCHAR(100);

                -- Chỉ xử lý nếu TienDuMGHP > 0 và chưa có giao dịch hoàn tiền
                IF NEW.TienDuMGHP > 0 AND 
                   NOT EXISTS (
                       SELECT 1 FROM GIAO_DICH_NOI_BO 
                       WHERE MaHoSo = NEW.MaHoSo 
                       AND LoaiGiaoDich = "hoan_tien_mghp"
                   ) THEN

                    -- Lấy số tài khoản từ SINH_VIEN
                    SELECT SoTaiKhoan, TenNganHang INTO v_so_tai_khoan, v_ten_ngan_hang
                    FROM SINH_VIEN
                    WHERE MaNguoiDung = NEW.MaSinhVien
                    LIMIT 1;

                    -- Tạo giao dịch hoàn tiền
                    INSERT INTO GIAO_DICH_NOI_BO
                    (MaSinhVien, MaHoSo, LoaiGiaoDich, SoTien, SoTaiKhoan, TenNganHang, TrangThai, GhiChu)
                    VALUES (NEW.MaSinhVien, NEW.MaHoSo, "hoan_tien_mghp", NEW.TienDuMGHP, 
                            v_so_tai_khoan, v_ten_ngan_hang, "cho_xu_ly", 
                            "Hoàn tiền dư miễn giảm học phí");
                END IF;
            END;
        ');

        // TRIGGER: TRG_TruyCapCongNo (Auto-calculate SoTienPhaiDong)
        // Calculated column đã được định nghĩa trong migration
        // Nhưng ta có thể thêm trigger để ensure consistency
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS TRG_TinhToanCongNo;');
        DB::unprepared('DROP TRIGGER IF EXISTS TRG_TaoPhuongThucHoanTien;');
    }
};
