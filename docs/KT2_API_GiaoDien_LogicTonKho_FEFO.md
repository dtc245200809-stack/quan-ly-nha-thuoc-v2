# BÁO CÁO GIAI ĐOẠN KT2: ĐẶC TẢ API, THIẾT KẾ GIAO DIỆN & GIẢI THUẬT TỒN KHO FEFO

**Dự án:** Hệ thống Quản Lý Nhà Thuốc Có Tích Hợp AI (PharmaAI)  
**Nhóm thực hiện:** Thạch Minh Quân, Nguyễn Trung Kiên, Lê Huy Hoàng

---

## 1. Thiết Kế Hệ Thống RESTful API (FastAPI)

Hệ thống cung cấp bộ API chuẩn OpenAPI / Swagger (truy cập tại `/docs`):

### Nhóm Xác Thực & Phân Quyền (`/api/auth`)
- `POST /api/auth/login`: Xác thực username/password, cấp phát bearer token và trả về thông tin người dùng (`admin`, `pharmacist`, `cashier`).
- `GET /api/auth/me`: Lấy thông tin tài khoản đang đăng nhập.

### Nhóm Danh Mục Thuốc (`/api/medicines`)
- `GET /api/medicines/`: Tra cứu danh sách thuốc, hỗ trợ lọc theo nhóm dược lý, phân loại kê đơn (`prescription_required`), tìm kiếm từ khóa. Tự động tính toán tổng tồn khả dụng của từng loại thuốc.
- `GET /api/medicines/{id}`: Chi tiết thuốc kèm danh sách tất cả các lô hàng hiện có.
- `POST /api/medicines/`: Thêm mới thuốc vào danh mục (chỉ quyền `admin` hoặc `pharmacist`).
- `GET /api/medicines/categories`: Danh mục nhóm dược lý.
- `GET /api/medicines/units`: Danh mục đơn vị tính.

### Nhóm Lô Hàng & Tồn Kho (`/api/batches`)
- `GET /api/batches/`: Danh sách lô hàng, tính toán số ngày còn lại (`days_left`), lọc theo trạng thái (`active`, `expired`, `disposed`).
- `POST /api/batches/`: Tiếp nhận và nhập lô hàng mới (kiểm tra điều kiện `expiry_date > manufacture_date` và `quantity > 0`).
- `PUT /api/batches/{id}/status`: Cập nhật trạng thái lô hàng (chuyển sang `expired` hoặc `disposed`).
- `GET /api/batches/alerts/expiry`: API cảnh báo lô sắp hết hạn theo ngưỡng ngày tùy chỉnh (mặc định 60 ngày).
- `GET /api/batches/alerts/low-stock`: API cảnh báo các mặt hàng có tồn kho khả dụng thấp hơn ngưỡng an toàn `min_stock_alert`.

### Nhóm Bán Hàng & Hóa Đơn (`/api/invoices`)
- `POST /api/invoices/`: Tạo giao dịch bán hàng POS, bóc tách và trừ tồn kho tự động theo giải thuật FEFO trong một Database Transaction (ACID).
- `GET /api/invoices/`: Lịch sử các hóa đơn đã xuất.
- `GET /api/invoices/{id}`: Chi tiết hóa đơn kèm danh sách thuốc và số lô xuất tương ứng.

### Nhóm Báo Cáo Thống Kê (`/api/reports`)
- `GET /api/reports/summary`: Tổng hợp doanh thu, tổng số hóa đơn, giá trị kho dược, số lô cận hạn.
- `GET /api/reports/revenue-by-day`: Biểu đồ doanh thu 14 ngày gần nhất.
- `GET /api/reports/top-selling`: Top dược phẩm bán chạy nhất.

---

## 2. Giải Thuật Quản Trị Tồn Kho Chuẩn FEFO (First Expired, First Out)

### Bài toán:
Khách hàng mua một loại thuốc với số lượng $Q$. Loại thuốc này có nhiều lô hàng khác nhau trong kho với hạn sử dụng khác nhau. Cần thuật toán lựa chọn lô nào xuất trước để tối ưu hạn dùng và không xuất phải thuốc hết hạn.

### Giải thuật `allocate_fefo`:
```python
def allocate_fefo(cursor, medicine_id: int, requested_qty: int):
    today = date.today().isoformat()
    
    # 1. Truy vấn các lô Active, còn hạn (expiry_date > today), số lượng > 0
    # Sắp xếp ưu tiên: expiry_date ASC (FEFO), import_date ASC (FIFO phụ)
    cursor.execute("""
        SELECT id, batch_number, expiry_date, quantity_remaining, selling_price
        FROM batches
        WHERE medicine_id = ? 
          AND status = 'active' 
          AND expiry_date > ?
          AND quantity_remaining > 0
        ORDER BY expiry_date ASC, import_date ASC, id ASC
    """, (medicine_id, today))

    available_batches = cursor.fetchall()
    total_available = sum(b["quantity_remaining"] for b in available_batches)

    # 2. Kiểm tra khả năng đáp ứng
    if total_available < requested_qty:
        raise ValueError("Không đủ tồn kho khả dụng theo nguyên tắc FEFO")

    # 3. Phân bổ tuần tự từ lô cận hạn nhất
    allocations = []
    qty_needed = requested_qty
    for b in available_batches:
        take = min(b["quantity_remaining"], qty_needed)
        allocations.append({
            "batch_id": b["id"],
            "batch_number": b["batch_number"],
            "expiry_date": b["expiry_date"],
            "quantity_to_take": take,
            "unit_price": b["selling_price"]
        })
        qty_needed -= take
        if qty_needed == 0:
            break

    return allocations
```

### Tính toán tính chất ACID:
Khi thanh toán hóa đơn, toàn bộ quá trình trừ số lượng `quantity_remaining` ở từng lô và ghi vào `invoice_items` được thực thi trong một `BEGIN TRANSACTION` và `COMMIT`. Nếu có bất kỳ lỗi nào xảy ra hoặc có sự tranh chấp số lượng giữa các quầy thu ngân, hệ thống tự động `ROLLBACK`, ngăn ngừa triệt để lỗi âm kho hoặc lệch số liệu.

---

## 3. Thiết Kế Giao Diện Người Dùng (UI/UX)
- Giao diện xây dựng theo triết lý Dashboard y tế hiện đại, responsive, trực quan với các màu sắc chuẩn ngành y (Teal, Emerald, Slate).
- Hệ thống hỗ trợ chuyển đổi vai trò linh hoạt giữa Quản lý, Dược sĩ và Thu ngân ở thanh tiêu đề.
- Banner cảnh báo Disclaimer luôn xuất hiện cố định ở đầu trang để nhắc nhở người dùng về giới hạn của AI.
