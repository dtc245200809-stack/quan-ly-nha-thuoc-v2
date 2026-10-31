# HỆ THỐNG QUẢN LÝ NHÀ THUỐC TÍCH HỢP TRÍ TUỆ NHÂN TẠO (PharmaAI)
> **Đồ án môn học / Hệ thống Quản trị Dược phẩm Chuẩn GPP & Trợ lý Google Gemini AI (Multi-Key Auto-Rotation)**  
> **Nhóm sinh viên thực hiện:**
> - **Thạch Minh Quân** (Trưởng nhóm / Kiến trúc hệ thống, Backend FastAPI & Tích hợp AI Engine)
> - **Nguyễn Trung Kiên** (Nghiệp vụ Dược lâm sàng & Xây dựng AI Copilot Prompts)
> - **Lê Huy Hoàng** (Frontend / UI-UX, Kiểm thử chất lượng QA & Documentation)
>
> **GitHub Repository:** [https://github.com/dtc245200809-stack/quan-ly-nha-thuoc-v2](https://github.com/dtc245200809-stack/quan-ly-nha-thuoc-v2)  
> **Website Demo Trực Tuyến:** [https://quanlynhathuoc.azmedia247.com/](https://quanlynhathuoc.azmedia247.com/)

---

## 1. Giới Thiệu & Bài Toán Nghiệp Vụ

Hệ thống **PharmaAI** được thiết kế đáp ứng đầy đủ tiêu chuẩn **GPP** (Thực hành tốt nhà thuốc) và **GSP** (Thực hành tốt bảo quản thuốc), kết hợp sức mạnh của **Google Gemini AI** để hỗ trợ nhân viên vận hành an toàn, chính xác:

- **Quản lý danh mục & phân loại:** Hoạt chất, biệt dược, nhóm tác dụng, thuốc kê đơn (**Rx**) và không kê đơn (**OTC**).
- **Quản lý lô & hạn sử dụng (FEFO):** Xuất kho tự động theo nguyên tắc **First Expired, First Out**; nghiêm cấm xuất bán lô đã quá hạn sử dụng.
- **Bán hàng POS:** Lập hóa đơn, trừ kho tự động theo từng lô, tạo mã VietQR thanh toán.
- **Cảnh báo thông minh:** Tự động phát hiện thuốc sắp hết hạn (30, 60, 90 ngày) và tồn kho dưới định mức tối thiểu.
- **Trợ lý AI Nhà Thuốc (Gemini Multi-Key Auto-Rotation):**
  1. *Tóm tắt thông tin thuốc:* Tóm tắt chỉ định, chống chỉ định, thận trọng từ dữ liệu đã duyệt cho nhân viên tra cứu nhanh.
  2. *Báo cáo thuốc cận hạn:* Phân tích rủi ro tài chính, đề xuất phương án xử lý kho (trả nhà cung cấp, xả hàng, hủy thuốc).
  3. *Chatbot Quy trình SOP:* Hỏi đáp quy trình nhập kho, kiểm kê, bảo quản thuốc chuẩn GPP (SOP-01 đến SOP-04).
  4. *AI Guardrails & Disclaimer:* Tự động chặn các câu hỏi yêu cầu kê đơn bừa bãi, chẩn đoán bệnh từ xa; luôn kèm khuyến nghị không thay thế dược sĩ/bác sĩ.

---

## 2. Điểm Nhấn Công Nghệ: Quản Lý Đa Key Gemini & Tự Động Xoay Key (Auto-Rotation)

Hệ thống giải quyết triệt để vấn đề giới hạn hạn mức (Rate Limit / Quota HTTP 429) của gói Gemini miễn phí bằng **Cơ chế Key Pool Đa Tầng**:
1. **Nạp nhiều API Key:** Admin có thể nhập nhiều Key Gemini từ các tài khoản Google khác nhau vào hệ thống.
2. **Tự động nhận diện lỗi 429:** Khi key hiện tại đạt ngưỡng hạn mức (HTTP 429 - Quota Exceeded), hệ thống tự động:
   - Đánh dấu key đó là `quota_exhausted`.
   - Ngay lập tức chuyển sang key tiếp theo trong danh sách để hoàn tất câu trả lời cho người dùng.
3. **Local Fallback Engine:** Nếu toàn bộ key trong danh sách đều cạn quota hoặc mất kết nối mạng, hệ thống tự động chuyển sang mô hình xử lý nội bộ (Local Fallback), bảo đảm hệ thống **luôn sẵn sàng 100% thời gian hoạt động (High Availability)**.
4. **Giám sát trực quan (Key Pool Status):** Admin có thể theo dõi số lượt sử dụng thành công, số lỗi, và trạng thái hoạt động của từng key trên giao diện quản trị.

---

## 3. Cấu Trúc Dự Án (Project Structure)

```
quan-ly-nha-thuoc-v2/
├── app/                                # Backend chính bằng FastAPI (Python 3.11)
│   ├── main.py                         # Điểm khởi chạy ứng dụng & mount router
│   ├── database.py                     # Schema 10 bảng SQLite, seed data mẫu
│   ├── models.py                       # Pydantic schemas xác thực dữ liệu vào/ra
│   ├── auth.py                         # Xác thực và phân quyền RBAC (Admin, Pharmacist, Cashier)
│   ├── inventory_service.py            # Giải thuật xuất kho FEFO (ACID) & cảnh báo kho
│   ├── ai_config_manager.py            # Quản lý Key Pool Gemini, cơ chế tự động xoay key khi dính 429
│   ├── ai_guardrails.py                # Lớp phòng vệ AI Guardrails chặn vi phạm quy chuẩn y tế
│   ├── ai_engine.py                    # Động cơ AI (Tóm tắt thuốc, phân tích cận hạn, Chat SOP)
│   └── routes/                         # Các RESTful API Endpoints
│       ├── auth_routes.py              # Đăng nhập & hồ sơ
│       ├── medicine_routes.py          # CRUD thuốc, nhóm thuốc, đơn vị tính
│       ├── batch_routes.py             # Nhập kho lô thuốc, theo dõi HSD
│       ├── invoice_routes.py           # Quầy bán POS, lập hóa đơn FEFO
│       ├── report_routes.py            # Thống kê doanh thu, tồn kho, top bán chạy
│       └── ai_routes.py                # Cấu hình AI, kiểm tra key, tóm tắt, chatbot
├── php_web/                            # Toàn bộ mã nguồn Web Portal PHP trên hosting cPanel
│   ├── index.php                       # Cổng đăng nhập phân quyền
│   ├── app.php                         # Core layout, sidebar, session, bảo mật RBAC
│   ├── ai_config.php                   # Giao diện Admin quản trị danh sách Đa Key Gemini
│   ├── ai_service.php                  # Module gọi Gemini API đa key + tự xoay key trên PHP
│   ├── ai_assistant.php                # Giao diện AI Copilot cho nhân viên
│   ├── ban_hang.php                    # Quầy POS bán hàng xuất kho FEFO
│   ├── stock.php                       # Quản lý kho và lô thuốc
│   ├── canh_bao.php                    # Cảnh báo hạn sử dụng & tồn kho dưới ngưỡng
│   ├── catalog.php                     # Danh mục thuốc và giá niêm yết
│   ├── bao_cao.php                     # Báo cáo doanh thu & xuất nhập tồn
│   └── connect_db.php                  # Kết nối MySQL Database
├── templates/                          # Giao diện Web FastAPI (Jinja2 + Tailwind CSS)
│   ├── layout.html                     # Khung sườn tổng thể + thanh cảnh báo y tế cố định
│   ├── dashboard.html                  # Tổng quan thống kê nhanh
│   ├── medicines.html                  # Danh mục thuốc & nút AI tóm tắt
│   ├── batches.html                    # Danh sách lô thuốc và HSD
│   ├── sales.html                      # Màn hình bán hàng POS
│   ├── alerts.html                     # Danh sách cảnh báo date & tồn kho
│   ├── ai_copilot.html                 # 6 Tab: Chat SOP, Tóm tắt thuốc, Báo cáo date, Cấu hình Key Pool
│   └── reports.html                    # Biểu đồ và bảng thống kê
├── tests/                              # Bộ kiểm thử tự động PyTest (18/18 Tests Passed)
│   ├── conftest.py                     # Cấu hình fixture SQLite bộ nhớ tạm
│   ├── test_inventory_fefo.py          # Kiểm thử giải thuật FEFO & chặn lô quá hạn (4 tests)
│   ├── test_ai_guardrails.py           # Kiểm thử bộ lọc an toàn AI y tế & Disclaimer (5 tests)
│   ├── test_api_endpoints.py           # Kiểm thử toàn bộ REST API endpoints (6 tests)
│   └── test_gemini_key_rotation.py     # Kiểm thử nạp Key Pool & tự xoay key khi 429 (3 tests)
├── docs/                               # Bộ hồ sơ tài liệu SDLC hoàn chỉnh
│   ├── KT1_PhanTichNghiepVu_ERD_GioiHanAI.md
│   ├── KT2_API_GiaoDien_LogicTonKho_FEFO.md
│   ├── KT3_ThietKePrompt_Guardrails_TestReport.md
│   ├── CuoiKy_BaoCao_DaoDucAI_SecurityReview.md
│   └── CuoiKy_Slide_ThuyetTrinh.md
├── ai_config.json                      # Tệp cấu hình danh sách Gemini API Keys và thống kê sử dụng
├── pharmacy.db                         # Database SQLite với dữ liệu mẫu chuẩn GPP
├── pytest.ini                          # Cấu hình đường dẫn test
└── requirements.txt                    # Thư viện phụ thuộc Python
```

---

## 4. Minh Chứng Các Giai Đoạn Vòng Đời Phần Mềm (SDLC)

| Giai đoạn | Mục tiêu & Nội dung thực hiện | Minh chứng tài liệu |
|---|---|---|
| **KT1** | Phân tích quy trình GPP/GSP, thiết kế ERD 10 thực thể, xây dựng Red Lines & ranh giới an toàn cho AI. | `docs/KT1_PhanTichNghiepVu_ERD_GioiHanAI.md` |
| **KT2** | Xây dựng RESTful API, hoàn thiện giao diện Dashboard, lập trình thuật toán FEFO đảm bảo tính toàn vẹn ACID. | `docs/KT2_API_GiaoDien_LogicTonKho_FEFO.md` |
| **KT3** | Thiết kế System Prompt, bộ lọc an toàn Guardrails, tích hợp Google Gemini AI đa key, viết PyTest tự động hóa. | `docs/KT3_ThietKePrompt_Guardrails_TestReport.md` |
| **Cuối kỳ** | Báo cáo Đạo đức Trí tuệ Nhân tạo trong Y tế, rà soát an toàn bảo mật OWASP Top 10, slide thuyết trình bảo vệ đồ án. | `docs/CuoiKy_BaoCao_DaoDucAI_SecurityReview.md`<br>`docs/CuoiKy_Slide_ThuyetTrinh.md` |

---

## 5. Hướng Dẫn Cài Đặt & Vận Hành

### 5.1. Môi trường Python (FastAPI Backend)
```bash
# 1. Di chuyển vào thư mục dự án
cd /home/gkxmmtkm/AI

# 2. Kích hoạt môi trường ảo Python
source venv/bin/activate

# 3. Cài đặt thư viện nếu cần
pip install -r requirements.txt

# 4. Chạy toàn bộ 18 bài kiểm thử tự động
pytest tests/ -v
# Kết quả kỳ vọng: 18 passed (100%)

# 5. Khởi động Web Server
uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
```
- **Trang chủ giao diện:** `http://localhost:8000/`
- **Tài liệu API Swagger UI:** `http://localhost:8000/docs`

---

### 5.2. Danh Sách Tài Khoản Đăng Nhập Hệ Thống

#### A. Cổng Web Trực Tuyến (PHP/MySQL - https://quanlynhathuoc.azmedia247.com/)
| Vai trò | Tên đăng nhập | Mật khẩu | Quyền hạn chính |
|---|---|---|---|
| **Quản trị viên (Admin)** | `admin` | `admin123` | Toàn quyền, cấu hình Key Gemini (`/ai_config.php`), quản trị tài khoản, backup/restore. |
| **Dược sĩ (Pharmacist)** | `duocsi` hoặc `kien` | `duocsi123` | Quản lý kho & lô thuốc, duyệt hạn dùng, dùng AI Trợ lý tra cứu. |
| **Thu ngân (Cashier)** | `phuong` hoặc `cuong` | `thungan123` | Bán hàng POS, in hóa đơn xuất kho FEFO tự động. |
| **Quản lý (Manager)** | `chien` hoặc `loc` | `quanly123` | Báo cáo doanh thu, xuất nhập tồn, giám sát kho. |

#### B. Cổng Local FastAPI (Python 3.11)
| Vai trò | Tên đăng nhập | Mật khẩu | Quyền hạn |
|---|---|---|---|
| **Admin** | `admin` | `admin123` | Quản trị viên cao nhất, cấu hình Key Pool AI. |
| **Dược sĩ** | `pharmacist` | `duocsi123` | Nghiệp vụ dược, AI Copilot. |
| **Thu ngân** | `cashier` | `thungan123` | Bán hàng POS theo lô FEFO. |

---

## 6. Hướng Dẫn Cấu Hình Đa Key Gemini AI Cho Quản Trị Viên

1. Đăng nhập bằng tài khoản `admin` / `admin123`.
2. Truy cập vào mục **Trợ lý AI** -> chọn **Cấu hình Đa Key AI** (hoặc mở trực tiếp đường dẫn `/ai_config.php`).
3. Dán danh sách các API Key của Google Gemini vào ô nhập liệu (mỗi dòng 1 key):
   ```text
   AIzaSyD-KeyThuNhatCuaBan123456
   AIzaSyB-KeyThuHaiCuaBan789012
   AIzaSyA-KeyThuBaCuaBan345678
   ```
4. Bấm **Lưu Cấu Hình Key Pool**.
5. Nhấn nút **Thử Nghiệm Kết Nối** để hệ thống gọi thử API Gemini. Bảng theo dõi trạng thái Key Pool sẽ cập nhật ngay lập tức:
   - Trạng thái `active`: Key sẵn sàng hoạt động.
   - Trạng thái `quota_exhausted`: Key đã hết lượt gọi miễn phí trong ngày (tự động bỏ qua chuyển sang key kế tiếp).
   - Số lượt sử dụng thành công & số lỗi được ghi nhận chi tiết.

---

## 7. Tuyên Bố Về Đạo Đức & Giới Hạn Trách Nhiệm AI (Disclaimer)

⚠️ **CẢNH BÁO QUAN TRỌNG:**
1. Hệ thống AI này được thiết kế và huấn luyện nhằm phục vụ mục đích **hỗ trợ tra cứu thông tin tham khảo nội bộ** cho nhân viên nhà thuốc.
2. Hệ thống **tuyệt đối không đưa ra lời khuyên điều trị, không chỉ định liều dùng lâm sàng, không chẩn đoán bệnh** và **không thay thế vai trò chuyên môn của Bác sĩ hoặc Dược sĩ có chứng chỉ hành nghề**.
3. 100% phản hồi từ AI đều được tự động đóng gói kèm thông điệp cảnh báo bắt buộc tuân thủ quy chuẩn y tế. Mọi quyết định xuất bán thuốc bắt buộc phải tuân theo đơn thuốc hợp lệ và quy chế chuyên môn ngành Dược hiện hành.
