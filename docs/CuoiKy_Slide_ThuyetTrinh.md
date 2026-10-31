# KỊCH BẢN SLIDE THUYẾT TRÌNH BẢO VỆ ĐỀ TÀI CUỐI KỲ

**Tên đề tài:** Hệ Thống Quản Lý Nhà Thuốc Tích Hợp AI (PharmaAI)  
**Nhóm sinh viên thực hiện:**
1. **Thạch Minh Quân** (Trưởng nhóm / Trình bày Kiến trúc & Backend)
2. **Nguyễn Trung Kiên** (Trình bày Nghiệp vụ Dược & AI Copilot)
3. **Lê Huy Hoàng** (Trình bày Demo Hệ Thống, Testing & Đạo đức AI)

---

## SLIDE 1: TIÊU ĐỀ & GIỚI THIỆU
- **Tiêu đề:** Hệ Thống Quản Lý Nhà Thuốc Có Tích Hợp AI
- **Phụ đề:** Chuẩn hóa quy trình GSP/GPP, giải thuật xuất kho FEFO và Trợ lý AI có kiểm duyệt an toàn y tế
- **Thành viên nhóm:** Thạch Minh Quân, Nguyễn Trung Kiên, Lê Huy Hoàng

---

## SLIDE 2: ĐẶT VẤN ĐỀ & THÁCH THỨC THỰC TIỄN
- **Thách thức 1 - Thuốc quá hạn:** Nhà thuốc truyền thống thường xuyên gặp rủi ro thất thoát hàng chục triệu đồng do không kiểm soát chặt chẽ hạn sử dụng từng lô thuốc.
- **Thách thức 2 - Nghiệp vụ phức tạp:** Nhân viên mới khó nắm bắt ngay các quy trình thao tác chuẩn (SOP) về kiểm nhập, kiểm kê, bán thuốc kê đơn.
- **Thách thức 3 - Rủi ro khi dùng AI:** Nếu ứng dụng AI không có bộ lọc an toàn, AI có thể tự ý kê đơn bừa bãi, gây nguy hiểm trực tiếp cho sức khỏe bệnh nhân.

---

## SLIDE 3: GIẢI PHÁP & MỤC TIÊU DỰ ÁN
- Số hóa 100% danh mục thuốc, quản trị đa lô hàng theo từng ngày sản xuất và hạn dùng.
- Tự động hóa xuất kho theo nguyên tắc **FEFO (First Expired, First Out)**.
- Xây dựng **AI Copilot** với 3 tính năng cốt lõi:
  1. AI Tóm tắt thông tin thuốc tham khảo.
  2. AI Phân tích rủi ro lô cận hạn và đề xuất xử lý.
  3. AI Chatbot giải đáp SOP nội bộ.
- Xây dựng **AI Guardrails** ngăn chặn 100% câu hỏi vượt quyền.

---

## SLIDE 4: KIẾN TRÚC HỆ THỐNG & CÔNG NGHỆ SỬ DỤNG
- **Backend:** FastAPI (Python 3.11) - Hiệu năng cao, tự động sinh tài liệu Swagger/OpenAPI.
- **Database:** SQLite / MySQL - Chuẩn hóa 3NF gồm 10 bảng thực thể có ràng buộc khóa ngoại chặt chẽ.
- **Frontend:** HTML5, Tailwind CSS, Vanilla JS - Giao diện Dashboard chuyên nghiệp, tốc độ phản hồi tức thì.
- **AI Engine:** Structured Knowledge Engine kết hợp Regex Guardrails & Semantic Safety Filter.
- **Kiểm thử:** PyTest tự động hóa 15 kịch bản kiểm thử toàn diện.

---

## SLIDE 5: GIẢI THUẬT QUẢN TRỊ TỒN KHO FEFO
- Sắp xếp thứ tự ưu tiên xuất kho: `expiry_date ASC, import_date ASC`.
- Khóa tự động các lô hết hạn hoặc có trạng thái khác `active`.
- Đảm bảo tính toán giao dịch ACID: Trừ kho nguyên tử, không bao giờ xảy ra lỗi âm kho.

---

## SLIDE 6: HỆ THỐNG AI GUARDRAILS & ĐẠO ĐỨC AI
- **Ranh giới an toàn:** AI tuyệt đối không kê đơn, không chẩn đoán bệnh từ xa, không tư vấn trường hợp khẩn cấp.
- **Phản ứng thông minh:** Khi nhận prompt vi phạm, Guardrail kích hoạt từ chối ngay lập tức và giải thích lý do an toàn.
- **Bắt buộc Disclaimer:** 100% phản hồi từ AI đều có khuyến cáo pháp lý không thay thế Bác sĩ/Dược sĩ.
- **Nhật ký Audit:** Ghi nhận 100% câu hỏi và phản hồi vào cơ sở dữ liệu `ai_logs`.

---

## SLIDE 7: KẾT QUẢ KIỂM THỬ (MINH CHỨNG KT3)
- Trình chiếu kết quả chạy `pytest`: **15/15 Passed (100%)**.
- Thử nghiệm trực tiếp các câu hỏi vi phạm: AI chặn thành công các câu hỏi "Kê đơn thuốc", "Uống quá liều", "Chữa ung thư", "Tự tử".
- Xử lý dữ liệu thiếu: Báo lỗi có cấu trúc khi ID không tồn tại, không gây treo ứng dụng.

---

## SLIDE 8: DEMO TRỰC TIẾP HỆ THỐNG
1. Xem Dashboard tổng quan: Doanh thu, số lô cận hạn, tồn kho thấp.
2. Thử nghiệm nhập lô mới và theo dõi hạn sử dụng.
3. Thử nghiệm bán hàng quầy POS: Chọn thuốc, hệ thống tự động bóc tách lô FEFO.
4. Thử nghiệm AI Copilot: Hỏi đáp quy trình SOP và kích hoạt Guardrail an toàn.

---

## SLIDE 9: KẾT LUẬN & ĐỊNH HƯỚNG TƯƠNG LAI
- **Kết quả đạt được:** Hoàn thành đầy đủ và vượt mức các yêu cầu từ KT1, KT2, KT3 đến Cuối kỳ.
- **Định hướng phát triển:** Kết nối dữ liệu quốc gia về Dược phẩm (Dược thư Quốc gia Việt Nam), mở rộng tích hợp thanh toán mã QR động VietQR.
- Cảm ơn Thầy/Cô và Hội đồng đã lắng nghe!
