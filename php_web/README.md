# Hệ thống Quản lý Nhà thuốc (QL_HT) — Hướng dẫn chạy trên XAMPP

Dự án PHP thuần (không dùng Composer/Laravel) + MySQL, dùng `mysqli`. Đây là hệ thống quản lý bán hàng, kho thuốc, hoá đơn, khách hàng... cho nhà thuốc.

## 1. Yêu cầu hệ thống

- **XAMPP** với **PHP 8.0 trở lên** (code dùng union return type `bool|string` nên bắt buộc PHP 8+, không chạy được trên PHP 7.x).
- **MySQL/MariaDB** (đi kèm sẵn trong XAMPP).
- Extension PHP cần bật (mặc định XAMPP đã bật sẵn, không cần chỉnh gì):
  - `mysqli`
  - `curl` (dùng cho tính năng tạo mã QR thanh toán VietQR trong `vietqr_api.php`)

> Tải XAMPP tại: https://www.apachefriends.org/ — chọn bản có PHP 8.0/8.1/8.2.

## 2. Copy source code vào thư mục htdocs

1. Cài đặt XAMPP xong, mở thư mục cài đặt, tìm thư mục `htdocs` (ví dụ: `C:\xampp\htdocs` trên Windows, hoặc `/Applications/XAMPP/htdocs` trên macOS).
2. Copy toàn bộ thư mục dự án (thư mục chứa `index.php`, `app.php`, `connect_db.php`...) vào trong `htdocs`. Ví dụ đặt tên thư mục là `QL_HT`, sao cho đường dẫn cuối cùng là:
   ```
   htdocs/QL_HT/index.php
   htdocs/QL_HT/app.php
   htdocs/QL_HT/connect_db.php
   ...
   ```
   (Tên thư mục có thể tuỳ ý vì code không có đường dẫn tuyệt đối cố định — chỉ ảnh hưởng tới URL truy cập ở Bước 5.)

## 3. Khởi động Apache và MySQL

1. Mở **XAMPP Control Panel**.
2. Nhấn **Start** ở dòng **Apache** và dòng **MySQL**. Cả hai chuyển sang màu xanh là đã chạy thành công.

## 4. Tạo database và import dữ liệu

1. Truy cập phpMyAdmin: mở trình duyệt vào `http://localhost/phpmyadmin`.
2. Tạo một database mới tên đúng là **`npm2006`** (đây là tên database mặc định mà `connect_db.php` đang cấu hình sẵn, đặt đúng tên này thì không cần sửa code). Charset chọn `utf8mb4_unicode_ci`.
3. Chọn database `npm2006` vừa tạo → tab **Import** → chọn file `pms.sql` trong thư mục dự án → nhấn **Go** để import cấu trúc bảng + dữ liệu mẫu.
4. (Tuỳ chọn) File `migration_v2.sql` bổ sung thêm cột/bảng cho các báo cáo mở rộng. Bạn **không bắt buộc** phải import file này bằng tay vì `connect_db.php` đã tự động chạy các câu lệnh `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` tương đương mỗi khi có người truy cập trang. Nếu muốn import tay cho chắc chắn thì làm tương tự bước 3 với file `migration_v2.sql` sau khi đã import `pms.sql`.

### Nếu muốn dùng tên database khác

Mặc định `connect_db.php` cấu hình:
```php
DB_HOST = 'localhost'
DB_NAME = 'npm2006'
DB_USER = 'root'
DB_PASS = ''  (rỗng)
```
Đây chính là cấu hình mặc định của XAMPP (user `root`, không mật khẩu) nên thường **không cần sửa gì**. Nếu bạn đặt tên database khác hoặc MySQL của bạn có mật khẩu cho user `root`, mở file `connect_db.php` và sửa 4 giá trị tương ứng.

## 5. Truy cập ứng dụng

Mở trình duyệt và vào:
```
http://localhost/QL_HT/index.php
```
(thay `QL_HT` bằng tên thư mục bạn đã đặt ở Bước 2).

Lần đầu truy cập, `connect_db.php` sẽ tự động tạo thêm vài bảng còn thiếu (`drugs`, `suppliers`, `system_logs`, `purchase_header`, `purchase_item`, `canh_bao`...) nếu chưa có — đây là hành vi bình thường, không phải lỗi.

## 6. Tài khoản đăng nhập mặc định (dữ liệu mẫu trong `pms.sql`)

| Vai trò | Tài khoản | Mật khẩu |
|---|---|---|
| Admin | `admin` | `admin123` |
| Quản lý (Manager) | `chien` | `chien123` |
| Quản lý (Manager) | `loc` | `loc123` |
| Thu ngân (Cashier) | `phuong` | `phuong123` |
| Thu ngân (Cashier) | `cuong` | `cuong123` |
| Dược sĩ (Pharmacist) | `kien` | `kien123` |
| Dược sĩ (Pharmacist) | `duocsi` | `duocsi123` |

## 7. Một số lưu ý khi chạy trên XAMPP

- **Thư mục `backups/`**: tính năng sao lưu/khôi phục database (`restore.php`) ghi file `.sql` vào thư mục `backups/` trong project. Trên Windows/macOS với XAMPP mặc định, thư mục `htdocs` thường đã có quyền ghi sẵn nên không cần chỉnh gì; nếu gặp lỗi "Không thể ghi file backup", kiểm tra lại quyền ghi của thư mục `backups/`.
- **Cổng Apache**: nếu XAMPP báo lỗi Apache không khởi động được (thường do cổng 80 bị Skype/IIS chiếm), đổi cổng Apache sang `8080` trong `httpd.conf`, khi đó truy cập bằng `http://localhost:8080/QL_HT/index.php`.
- **Tính năng VietQR** (`vietqr_api.php`) gọi ra API bên ngoài (`api.vietqr.io`) nên máy chạy XAMPP cần có kết nối Internet để hiện được mã QR thanh toán.
- Toàn bộ giao diện dùng tiếng Việt, khuyến khích để charset UTF-8 mặc định của XAMPP/MySQL 8+ để hiển thị đúng dấu tiếng Việt.

## 8. Xử lý lỗi thường gặp

- **"Database connection failed..."**: kiểm tra MySQL trong XAMPP đã Start chưa, tên database đã đúng là `npm2006` (hoặc đúng tên bạn cấu hình trong `connect_db.php`) chưa.
- **Trang trắng / lỗi PHP**: kiểm tra phiên bản PHP trong XAMPP phải từ **8.0 trở lên** (Config → chọn PHP version nếu dùng XAMPP có nhiều bản PHP).
- **Không đăng nhập được dù đúng tài khoản**: kiểm tra đã import đủ dữ liệu từ `pms.sql` (bảng `admin`, `manager`, `cashier`, `pharmacist` phải có dữ liệu).
