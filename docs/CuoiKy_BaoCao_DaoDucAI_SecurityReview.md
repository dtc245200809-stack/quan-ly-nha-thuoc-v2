# BÁO CÁO CUỐI KỲ: ĐẠO ĐỨC TRÍ TUỆ NHÂN TẠO Y TẾ & ĐÁNH GIÁ AN TOÀN BẢO MẬT (SECURITY REVIEW)

**Dự án:** Hệ thống Quản Lý Nhà Thuốc Có Tích Hợp AI (PharmaAI)  
**Nhóm thực hiện:**
- **Thạch Minh Quân** (Trưởng nhóm / Kiến trúc hệ thống)
- **Nguyễn Trung Kiên** (Nghiệp vụ Dược & AI Engine)
- **Lê Huy Hoàng** (Frontend / UI-UX & Kiểm thử)

---

## PHẦN 1: BÁO CÁO ĐẠO ĐỨC TRÍ TUỆ NHÂN TẠO TRONG Y DƯỢC (AI ETHICS REPORT)

### 1.1. Bối Cảnh & Các Rủi Ro Tiềm Ẩn Của AI Trong Ngành Dược
Thuốc là hàng hóa đặc biệt tác động trực tiếp đến tính mạng người bệnh. Khi ứng dụng các mô hình ngôn ngữ lớn (LLM) vào vận hành nhà thuốc, nếu không có cơ chế kiểm soát nghiêm ngặt, hệ thống có thể gặp các nguy cơ nghiêm trọng:
1. **Ảo giác thông tin (Hallucination):** AI có thể bịa đặt liều dùng, tác dụng điều trị hoặc cơ chế phối hợp thuốc sai lệch, dẫn tới nguy cơ ngộ độc thuốc hoặc sốc phản vệ cho bệnh nhân.
2. **Vượt thẩm quyền chuyên môn (Unlicensed Medical Practice):** AI không có chứng chỉ hành nghề y dược; việc AI tự ý đưa ra phác đồ điều trị hoặc chẩn đoán thay thế bác sĩ/dược sĩ là hành vi vi phạm pháp luật y tế Việt Nam.
3. **Phụ thuộc quá mức của nhân viên (Automation Bias):** Nhân viên bán thuốc có thể ỷ lại vào câu trả lời của máy mà bỏ qua các bước kiểm tra lâm sàng trực tiếp trên người bệnh.

### 1.2. Các Nguyên Tắc Đạo Đức Được Triển Khai Trong Hệ Thống
Để giải quyết triệt để các rủi ro trên, nhóm đã tuân thủ 5 nguyên tắc đạo đức AI quốc tế (theo khuyến nghị của WHO về AI trong y tế):
1. **Nguyên tắc "Human-in-the-loop" (Con người là trung tâm):** AI chỉ đóng vai trò trợ lý hỗ trợ tổng hợp thông tin và quy trình nội bộ. Mọi quyết định xuất bán thuốc kê đơn, hủy lô thuốc hoặc tư vấn cho khách hàng bắt buộc phải có sự xác nhận của Dược sĩ phụ trách chuyên môn.
2. **Nguyên tắc Minh bạch & Cảnh báo (Transparency & Disclaimers):** Mọi văn bản xuất bản từ AI đều được gắn nhãn nhận diện rõ ràng kèm câu khuyến cáo bắt buộc (Disclaimer).
3. **Nguyên tắc Giới hạn vùng an toàn (Safety Guardrails):** Hệ thống chặn đứng từ cấp độ tiền xử lý các truy vấn liên quan đến kê đơn tự ý, điều trị bệnh nan y, hoặc các hành vi nguy hiểm (tự tử, dùng quá liều).
4. **Nguyên tắc Giám sát & Giải trình (Accountability & Auditability):** Toàn bộ lịch sử trao đổi của người dùng với AI, bao gồm cả các câu hỏi vi phạm bị chặn, đều được lưu trữ đầy đủ vào cơ sở dữ liệu `ai_logs` để phục vụ công tác thanh tra và hậu kiểm.
5. **Nguyên tắc Công bằng & Không định kiến (Fairness):** AI chỉ đưa ra các thông tin dược lý khách quan dựa trên dược điển và tài liệu đã được thẩm định, không thiên vị hay ưu tiên bất kỳ nhãn hiệu thương mại nào mà không dựa trên chỉ định y khoa.

---

## PHẦN 2: RÀ SOÁT & ĐÁNH GIÁ AN TOÀN THÔNG TIN (SECURITY REVIEW)

Hệ thống được rà soát bảo mật theo tiêu chuẩn OWASP Top 10 API Security:

### 2.1. Kiểm soát Truy Cập & Phân Quyền (RBAC)
- Hệ thống chia làm 3 vai trò rõ rệt: `admin`, `pharmacist`, `cashier`.
- Các hành động mang tính rủi ro cao (thêm danh mục thuốc mới, nhập kho lô hàng, chuyển trạng thái lô sang tiêu hủy) đều được bảo vệ bằng middleware `require_roles("admin", "pharmacist")`.
- Thu ngân (`cashier`) chỉ có quyền lập hóa đơn bán lẻ và tra cứu tồn kho, không thể can thiệp vào cấu hình danh mục hoặc dữ liệu kho.

### 2.2. Bảo Vệ Dữ Liệu & Chống Tấn Công SQL Injection
- Toàn bộ các câu truy vấn tương tác cơ sở dữ liệu đều sử dụng kỹ thuật **Parameterized Queries** (Prepared Statements với `?`).
- Tuyệt đối không dùng kỹ thuật cộng chuỗi SQL trực tiếp, triệt tiêu 100% nguy cơ SQL Injection.

### 2.3. Phòng Chống Tấn Công XSS & CSRF
- Giao diện Frontend áp dụng hàm làm sạch mã `escapeHtml()` cho toàn bộ dữ liệu phản hồi từ AI và dữ liệu người dùng nhập trước khi render lên DOM.
- Các API RESTful được cấu hình CORS rõ ràng, kiểm soát chặt chẽ các Headers yêu cầu.

### 2.4. Tính Toàn Vẹn Của Giao Dịch Kho (ACID Transactions)
- Logic bán hàng và trừ tồn kho FEFO được bao bọc trong một Database Transaction duy nhất.
- Kiểm tra tính sẵn sàng của tồn kho trước khi ghi nhận, ngăn ngừa tình trạng Race Condition (hai thu ngân cùng bán một lô thuốc dẫn tới tồn kho âm).

---

## PHẦN 3: KẾT LUẬN & ĐỀ XUẤT PHÁT TRIỂN TIẾP THEO
Dự án đã chứng minh sự kết hợp hiệu quả giữa công nghệ Backend hiện đại (FastAPI), giải thuật kho dược chặt chẽ (FEFO) và công nghệ Trí tuệ Nhân tạo được kiểm soát nghiêm ngặt bằng AI Guardrails. Hệ thống sẵn sàng cho việc mở rộng tích hợp với các mô hình LLM lớn qua giao diện API đã chuẩn hóa.
