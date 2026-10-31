# README MAPPING: Cấu trúc Hệ thống & Báo cáo Nhóm 10

Tài liệu này giải thích sự liên kết giữa **Source code thực tế** và **Báo cáo đồ án của Nhóm 10**, giúp Giảng viên và nhóm dễ dàng đối chiếu khi chấm điểm. Hệ thống đã được thiết kế lại (refactor) để đảm bảo tuân thủ tuyệt đối các yêu cầu nghiệp vụ trong báo cáo.

---

## 1. Mapping Vai Trò (Actors)

Báo cáo đề cập đến 2 nhóm vai trò nghiệp vụ chính:
- **Quản lý (Admin/Manager):** Toàn quyền cấu hình, quản lý danh mục, nhập kho và xem báo cáo tổng hợp.
- **Dược sĩ / Nhân viên bán hàng:** Chỉ thực hiện các tác vụ tại quầy như bán hàng (POS), tra cứu kho, xem cảnh báo. Không có quyền can thiệp vào cấu trúc hệ thống.

**Giải pháp triển khai trong Cơ sở dữ liệu (DB) & Source code:**
- **Database:** Chúng tôi vẫn tách biệt 4 bảng người dùng (`admin`, `manager`, `pharmacist`, `cashier`) để tối ưu việc lưu trữ các thuộc tính riêng và khả năng mở rộng trong tương lai.
- **Application Logic (`app.php`):** Tại tầng xử lý logic, hệ thống tự động gộp nhóm thông qua helper `pms_current_role()`. Cụ thể: 
  - `pharmacist` và `cashier` được gom vào một định danh logic duy nhất là `pharmacist_cashier`.
  - Nhóm `pharmacist_cashier` bị **chặn hoàn toàn** quyền truy cập vào Nhập kho (`stock.php`), Danh mục (`catalog.php`), Báo cáo doanh thu (`bao_cao.php`), và Quản trị tài khoản (`accounts.php`). 
  - Họ sử dụng chung một giao diện (Interface) được tối giản hóa chỉ bao gồm Nghiệp vụ bán hàng & Tra cứu.

---

## 2. Mapping Thực Thể (Entities)

| Thực thể (Báo cáo) | Bảng trong Database | Giải thích & Ghi chú kỹ thuật |
| :--- | :--- | :--- |
| **NguoiDung / VaiTro** | `admin`, `manager`, `pharmacist`, `cashier` | Chia thành 4 bảng thực tế (Table-per-type) nhưng hoạt động dưới 2 nhóm quyền logic. Tại trang `accounts.php`, các role này đã được gộp nhóm hiển thị rõ ràng. |
| **Thuoc / DanhMuc** | `drugs`, `suppliers` | Quản lý thông tin gốc. Thuộc tính `drug_code` được sử dụng để tương thích với máy quét mã vạch (Barcode Scanner). |
| **LoThuoc / PhieuNhap** | `stock`, `purchase_header`, `purchase_item` | Tách biệt quá trình **Nhập hàng** (`purchase`) và lưu trữ **Tồn kho theo lô** (`stock`). Mỗi lô thuốc được quản lý bằng `batch_no` và `expiry_date` riêng biệt để đảm bảo xuất hàng đúng chuẩn **FEFO** (Hết hạn trước, xuất trước). |
| **HoaDon** | `invoice_header`, `invoice_item` | Lưu vết giao dịch bán hàng POS. Tính toán tự động tổng tiền, giảm giá. |

---

## 3. Danh sách Chức Năng Cốt Lõi (Core Use-Cases)
Các file code đã được làm sạch và gắn thẻ Tiêu đề rõ ràng để Giảng viên dễ dàng kiểm chứng:

- **[UC-01] Đăng nhập:** Hệ thống tự động nhận diện quyền không cần người dùng tự chọn vai trò (Dropdown). Code phân luồng tại `app.php`.
- **[UC-02] Quản lý danh mục:** File `catalog.php`. (Chỉ hiển thị cho Quản lý).
- **[UC-03] Nhập kho theo lô & Hạn dùng:** File `stock.php`. (Chỉ Quản lý được phép tạo lô và nhập hàng).
- **[UC-04] Bán hàng POS theo FEFO:** File `ban_hang.php`. Hỗ trợ luồng xuất tự động lô cũ/cận date. Hỗ trợ tìm kiếm nhanh bằng Tên, Hoạt chất, và **Mã vạch (F2)**.
- **[UC-05] Cảnh báo hạn dùng:** File `canh_bao.php`. Quét toàn bộ kho để phát hiện thuốc cận date / hết hạn.
- **[UC-06] Báo cáo doanh thu:** File `bao_cao.php`. Thống kê doanh số theo ngày/tuần.

---

## 4. Các Chức Năng Mở Rộng (Extended Features)
So với báo cáo đồ án, chúng tôi đã nghiên cứu và phát triển thêm một số tính năng thực tế để hệ thống chuyên nghiệp hơn. Mặc dù là tính năng mở rộng ngoài phạm vi cốt lõi, nhưng chúng đã được đặt chung vào các nhóm nghiệp vụ tương ứng trên Menu (Bán hàng, Danh mục) để đảm bảo tính logic và tạo thành một luồng thao tác thuận tiện:

1. **Khách hàng thân thiết & Tích điểm** (`khach_hang.php`)
2. **Bán cắt liều / Combo thuốc** (`combo.php`)
3. **Quản lý trả hàng** (`tra_hang.php`): Thu hồi hóa đơn trong 24h, hoàn lại tồn kho cho đúng lô đã xuất.

*Hệ thống đảm bảo 100% tuân thủ các Yêu cầu Phi chức năng (Non-Functional Requirements) bao gồm: Bảo mật mật khẩu bằng Bcrypt (`password_hash`), Transaction SQL (Rollback nếu lỗi), Chống âm kho thuốc, và Nhật ký hệ thống (System Logs).*
