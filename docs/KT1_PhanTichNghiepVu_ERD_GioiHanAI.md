# BÁO CÁO GIAI ĐOẠN KT1: PHÂN TÍCH NGHIỆP VỤ NHÀ THUỐC, THIẾT KẾ ERD & GIỚI HẠN ĐẠO ĐỨC AI

**Dự án:** Hệ thống Quản Lý Nhà Thuốc Có Tích Hợp Trí Tuệ Nhân Tạo (PharmaAI)  
**Nhóm thực hiện:**
- **Thạch Minh Quân** (Trưởng nhóm / Kiến trúc hệ thống)
- **Nguyễn Trung Kiên** (Nghiệp vụ Dược & AI Engine)
- **Lê Huy Hoàng** (Frontend / UI-UX & Kiểm thử)
**Mức độ bài toán:** Nâng cao (Dữ liệu y tế nhạy cảm, kiểm soát chặt chẽ an toàn AI)

---

## 1. Phân Tích Nghiệp Vụ Nhà Thuốc Chuẩn GSP / GPP

Nhà thuốc bán lẻ và kho trung chuyển dược phẩm có các đặc thù khắt khe theo quy chuẩn của Bộ Y Tế:

1. **Quản lý danh mục thuốc & Dược lý:**
   - Phân biệt rõ **Thuốc kê đơn (Rx - Prescription Only)** và **Thuốc không kê đơn (OTC - Over The Counter)**. Thuốc Rx bắt buộc phải có thông tin đơn thuốc hợp lệ của bác sĩ điều trị mới được phép xuất kho bán lẻ.
   - Quản lý theo hoạt chất chính (active ingredient), hàm lượng (concentration), đường dùng (uống, tiêm, bôi ngoài da...), chống chỉ định và tương tác thuốc.

2. **Quản lý theo Lô sản xuất (Batch / Lot) & Hạn sử dụng (Expiration Date):**
   - Một loại thuốc có thể có nhiều lô nhập khác nhau với ngày sản xuất (MFG), hạn sử dụng (EXP), nhà cung cấp (Supplier) và giá nhập/giá bán riêng biệt.
   - Nguyên tắc xuất bán bắt buộc: **FEFO (First Expired, First Out)** - Lô có hạn sử dụng gần nhất phải luôn được ưu tiên xuất trước để giảm thiểu rủi ro thuốc quá hạn.
   - Chặn tuyệt đối việc xuất bán hoặc kê đơn các lô đã quá hạn sử dụng (`days_left < 0`).
   - Cảnh báo phân tầng:
     - 🔴 **Đã hết hạn:** Khóa xuất bán ngay lập tức, chuyển trạng thái `expired`, đưa vào khu vực Biệt trữ chờ tiêu hủy.
     - 🟡 **Khẩn cấp (≤ 30 ngày):** Ưu tiên xuất bán cho các đơn ngắn ngày hoặc làm việc với Nhà cung cấp để hoàn đổi lô mới.
     - 🟡 **Cảnh báo (31 - 60 ngày):** Dán nhãn theo dõi trực quan trên kệ thuốc.

3. **Bán hàng & Quản lý giao dịch POS:**
   - Thu ngân chọn thuốc, hệ thống tự động bóc tách và trừ số lượng theo từng lô theo thuật toán FEFO.
   - Tạo mã hóa đơn độc bản (`HDYYYYMMDDxxx`), lưu vết thu ngân phụ trách, in hóa đơn kèm hướng dẫn liều dùng cho bệnh nhân.

4. **Kiểm soát Tồn kho An toàn:**
   - Mỗi mặt hàng thuốc được thiết lập một ngưỡng tồn kho tối thiểu (`min_stock_alert`). Khi tổng tồn khả dụng của các lô active ≤ ngưỡng này, hệ thống tự động sinh cảnh báo thiếu hụt hàng để bộ phận cung ứng lên đơn đặt hàng.

---

## 2. Thiết Kế Cơ Sở Dữ Liệu Quan Hệ (ERD)

Cơ sở dữ liệu được chuẩn hóa ở dạng Chuẩn 3 (3NF) để đảm bảo toàn vẹn dữ liệu:

```
[users] 1 ──── n [invoices] (cashier_id)
[users] 1 ──── n [ai_logs]  (user_id)

[categories] 1 ──── n [medicines] (category_id)
[units]      1 ──── n [medicines] (unit_id)

[medicines] 1 ──── n [batches]       (medicine_id)
[suppliers] 1 ──── n [batches]       (supplier_id)

[invoices]  1 ──── n [invoice_items] (invoice_id)
[batches]   1 ──── n [invoice_items] (batch_id)
[medicines] 1 ──── n [invoice_items] (medicine_id)

[sop_documents] (Lưu trữ quy trình thao tác chuẩn nội bộ GSP/GPP)
[ai_logs]       (Lưu vết prompt, response, guardrail audit, timestamp)
```

### Chi tiết các bảng thực thể:
1. `users`: Phân quyền `admin` (Quản lý), `pharmacist` (Dược sĩ), `cashier` (Thu ngân).
2. `categories`: Nhóm dược lý (Hạ sốt giảm đau, Kháng sinh, Kháng viêm, Dạ dày...).
3. `units`: Đơn vị đóng gói (Viên, Vỉ, Hộp, Chai, Tuýp).
4. `suppliers`: Nhà cung cấp, công ty phân phối dược phẩm (DHG Pharma, Sanofi, Hậu Giang...).
5. `medicines`: Thông tin dược lý, hoạt chất, hàm lượng, chống chỉ định, cờ kê đơn `prescription_required`.
6. `batches`: Quản lý lô hàng, `import_date`, `manufacture_date`, `expiry_date`, `quantity_remaining`, `selling_price`, `status`.
7. `invoices`: Hóa đơn bán lẻ, `invoice_number`, `customer_name`, `final_amount`, `payment_method`.
8. `invoice_items`: Chi tiết thuốc và số lượng đã trừ theo từng lô cụ thể.
9. `sop_documents`: Tài liệu SOP phục vụ RAG/truy vấn của Chatbot AI.
10. `ai_logs`: Nhật ký kiểm soát an toàn AI phục vụ đối soát và audit.

---

## 3. Xác Định Phạm Vi & Giới Hạn Đạo Đức AI (AI Guardrails Scope)

Y tế là lĩnh vực đặc thù có rủi ro cao đến tính mạng và sức khỏe cộng đồng. Vì vậy, hệ thống đặt ra các ranh giới không được phép vượt qua (Red Lines):

### Những điều AI ĐƯỢC PHÉP làm:
- ✅ Tóm tắt thông tin thuốc từ cơ sở dữ liệu đã được duyệt (biệt dược, hoạt chất, đường dùng, điều kiện bảo quản).
- ✅ Quét cơ sở dữ liệu để sinh báo cáo phân tích thuốc cận hạn, tính toán giá trị tài chính rủi ro và gợi ý quy trình xử lý theo SOP.
- ✅ Trả lời câu hỏi của nhân viên nội bộ về Quy trình thao tác chuẩn (SOP-01 nhập kho, SOP-02 kiểm kê, SOP-03 hủy thuốc, SOP-04 bán hàng FEFO).

### Những điều AI TUYỆT ĐỐI BỊ CẤM (Blocked by Guardrails):
- ❌ KHÔNG kê đơn thuốc cho người dùng hoặc nhân viên ("Kê đơn giúp tôi...").
- ❌ KHÔNG chẩn đoán bệnh từ triệu chứng ("Tôi bị đau bụng thì uống thuốc gì?").
- ❌ KHÔNG tư vấn điều trị các bệnh nan y, hiểm nghèo ngoài phác đồ Bộ Y Tế ("Thuốc chữa khỏi ung thư").
- ❌ KHÔNG tư vấn cách dùng thuốc gây nguy hiểm, ngộ độc, quá liều hoặc tự tử.
- ❌ KHÔNG tự ý thay đổi liều lượng thuốc trong đơn của bác sĩ.

### Cơ chế bắt buộc:
100% câu trả lời xuất bản từ AI Engine bắt buộc phải đính kèm Disclaimer:
> *"⚠️ CẢNH BÁO QUAN TRỌNG: Thông tin do AI sinh ra chỉ có giá trị THAM KHẢO NỘI BỘ cho nhân viên nhà thuốc. Hệ thống AI KHÔNG đưa ra lời khuyên điều trị y tế, KHÔNG tự ý chỉ định liều dùng ngoài đơn, và KHÔNG thay thế vai trò chuyên môn của Bác sĩ điều trị hoặc Dược sĩ phụ trách chuyên môn."*
