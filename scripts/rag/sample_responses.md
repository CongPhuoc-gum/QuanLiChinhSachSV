# Mẫu trả lời tự động & quy trình CTSV

File nguồn: `scripts/rag/docs/nghidinh_238_2025.txt` và `scripts/rag/docs/nghidinh_238_2025_summary.md`

1) Mẫu trả lời ngắn — câu hỏi hồ sơ
- Câu hỏi: "Cần nộp giấy tờ gì để được miễn học phí?"
- Trả lời mẫu (ngắn, có dẫn nguồn):

  Sinh viên vui lòng nộp: (1) Đơn đề nghị miễn/giảm học phí; (2) Giấy xác nhận/Quyết định liên quan theo đối tượng (ví dụ: giấy xác nhận người có công, giấy xác nhận mồ côi, giấy xác nhận khuyết tật, giấy xác nhận hộ nghèo/cận nghèo, giấy xác nhận đăng ký thường trú); (3) Bản sao y Giấy khai sinh; (4) Bản sao y Căn cước công dân; (5) Đơn cam kết. CTSV sẽ kiểm tra và ra quyết định mức miễn/giảm theo Nghị định 238/2025. (Nguồn: Nghị định 238/2025 — mục Đối tượng & Quy định chung)

2) Mẫu trả lời chi tiết theo đối tượng (ví dụ: con liệt sỹ)
- Câu hỏi: "Tôi là con liệt sỹ, cần giấy tờ gì?"
- Trả lời mẫu: "Bạn thuộc Đối tượng 1 — Hồ sơ gồm: Đơn đề nghị miễn/giảm học phí; Giấy xác nhận thân nhân người có công theo Pháp lệnh/02/2020/UBTVQH; Bản sao y Giấy khai sinh; Bản sao y Căn cước công dân; Đơn cam kết. Nộp tại Phòng Công tác sinh viên để CTSV xác minh và trình cấp có thẩm quyền quyết định miễn học phí. (Nguồn: Nghị định 238/2025 — Đối tượng 1)"

3) Mẫu trả lời khi cần xác minh thêm (bot đặt câu hỏi làm rõ)
- Nếu thông tin thiếu: "Mình cần biết bạn thuộc đối tượng nào trong danh sách (ví dụ: con liệt sỹ, khuyết tật, mồ côi, dân tộc...), hoặc bạn đã có Giấy xác nhận/Quyết định nào chưa?"

4) Mẫu thông báo ngắn cho sinh viên (bản tin CTSV)

Kính gửi sinh viên,

Phòng Công tác sinh viên thông báo về chế độ miễn, giảm học phí theo Nghị định 238/2025: sinh viên thuộc đối tượng quy định nộp hồ sơ một lần gồm Đơn đề nghị, giấy xác nhận theo đối tượng (ví dụ: giấy xác nhận người có công, giấy xác nhận mồ côi, giấy xác nhận khuyết tật, giấy xác nhận hộ nghèo/cận nghèo hoặc giấy xác nhận đăng ký thường trú), bản sao y Giấy khai sinh, bản sao y Căn cước công dân và đơn cam kết. CTSV sẽ kiểm tra hồ sơ và thông báo kết quả. Chi tiết xem: liên hệ Phòng CTSV hoặc xem hướng dẫn tại [nơi lưu văn bản nội bộ].

5) Quy trình CTSV — xác minh & ra quyết định (gợi ý để tích hợp vào bot)
- Bước 1: Nhận hồ sơ từ sinh viên; kiểm tra tính đầy đủ theo checklist tương ứng với đối tượng.
- Bước 2: Xác minh giấy tờ (so sánh bản sao với bản chính nếu cần; đối với giấy xác nhận do UBND/cơ quan có thẩm quyền cấp, kiểm tra ngày/tiêu đề/đóng dấu).
- Bước 3: Kiểm tra điều kiện áp dụng (ví dụ: độ tuổi 16–22 cho Đối tượng 2; cư trú ở vùng khó khăn cho Đối tượng 5/6; danh sách dân tộc rất ít người theo Nghị định).
- Bước 4: CTSV lập tờ trình/đề xuất mức miễn/giảm cho cấp có thẩm quyền của trường (kèm danh sách mã SV và chứng từ scan).
- Bước 5: Cập nhật kết quả lên hệ thống sinh viên và thông báo công khai cho SV (email/portal).

6) Example automated reply including CTSV step (để bot trả lại cho SV):

"Bạn thuộc Đối tượng 4 (dân tộc thiểu số, cha/mẹ/ông bà thuộc hộ nghèo/cận nghèo). Hồ sơ cần: Đơn đề nghị; Bản sao y Giấy khai sinh; Giấy xác nhận hộ nghèo/cận nghèo do UBND xã; Bản sao Căn cước; Đơn cam kết. CTSV sẽ kiểm tra và trình duyệt; nếu hồ sơ hợp lệ, bạn được miễn học phí (nguồn: Nghị định 238/2025). Hãy nộp hồ sơ tại Phòng CTSV hoặc upload qua cổng nội bộ. Nếu cần, tôi có thể hướng dẫn mẫu đơn và checklist cụ thể." 

7) Ghi chú kỹ thuật cho tích hợp RAG/chatbot
- Khi trả lời tự động, hãy luôn: (a) chạy retrieval để lấy đoạn có liên quan; (b) chèn trích dẫn ngắn (tên file hoặc mục); (c) nếu không chắc chắn, hỏi lại; (d) gắn trạng thái "CTSV cần xác minh" nếu câu trả lời đòi hỏi phê duyệt thủ công.

---

File này dùng làm nguồn mẫu cho `PROMPT_TEMPLATE.md` khi xây dựng runtime RAG trả lời cho sinh viên.
