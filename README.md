# HỆ THỐNG QUẢN LÝ NHÀ THUỐC TÍCH HỢP TRÍ TUỆ NHÂN TẠO (PharmaAI)

> **Đồ án môn học / Hệ thống Quản trị Dược phẩm & Trợ lý AI Y tế Chuẩn GPP**  
> **Nhóm sinh viên thực hiện:**
> - **Thạch Minh Quân** (Trưởng nhóm / Kiến trúc hệ thống & Backend)
> - **Nguyễn Trung Kiên** (Nghiệp vụ Dược & AI Copilot Engine)
> - **Lê Huy Hoàng** (Frontend / UI-UX & Kiểm thử chất lượng)
>
> **Môi trường triển khai:** Linux cPanel, Python 3.11, FastAPI, Tailwind CSS, SQLite/MySQL.  
> **Tên miền tham chiếu:** `https://quanlynhathuoc.azmedia247.com/`

---

## 1. Giới Thiệu Tổng Quan

Hệ thống **PharmaAI** được phát triển nhằm giải quyết các bài toán cốt lõi trong vận hành nhà thuốc chuẩn GPP (Thực hành tốt nhà thuốc) và GSP (Thực hành tốt bảo quản thuốc):
- Quản lý danh mục thuốc theo hoạt chất, biệt dược, phân loại bắt buộc kê đơn (**Rx**) và không kê đơn (**OTC**).
- Quản trị đa lô sản xuất (Batches), theo dõi ngày sản xuất (MFG) và hạn dùng (EXP).
- Tự động hóa giải thuật xuất kho theo nguyên tắc **FEFO (First Expired, First Out)**, tuyệt đối ngăn chặn việc xuất bán các lô đã hết hạn.
- Bán thuốc quầy POS, tính toán chiết khấu, in hóa đơn và hướng dẫn sử dụng.
- Cảnh báo phân tầng tự động: thuốc cận hạn và thuốc có tồn kho dưới định mức an toàn.
- **Trợ lý Trí tuệ Nhân tạo (AI Copilot)** hỗ trợ nhân viên nội bộ:
  1. Tóm tắt nhanh thông tin thuốc tham khảo từ dữ liệu đã duyệt.
  2. Phân tích rủi ro tài chính của các lô cận hạn và sinh kế hoạch xử lý kho.
  3. Chatbot hỏi đáp Quy trình thao tác chuẩn nội bộ (SOP-01 đến SOP-04).
  4. Hệ thống **AI Guardrails** ngăn chặn 100% các câu hỏi vi phạm đạo đức y tế (kê đơn tự ý, chẩn đoán bệnh từ xa, câu hỏi độc hại/nguy cấp).

---

## 2. Cấu Trúc Dự Án (Project Structure)

```
/home/gkxmmtkm/AI/
├── app/
│   ├── __init__.py
│   ├── main.py                # FastAPI Application & UI Router Mount
│   ├── database.py            # SQLite schema, seed data, connection pool
│   ├── models.py              # Pydantic schemas (Request/Response)
│   ├── auth.py                # Role-based Access Control (Admin, Pharmacist, Cashier)
│   ├── inventory_service.py   # Thuật toán xuất kho FEFO & logic cảnh báo
│   ├── ai_guardrails.py       # Bộ lọc an toàn AI y tế & Disclaimer wrapper
│   ├── ai_engine.py           # Core AI processing (Tóm tắt, Phân tích cận hạn, Chat SOP)
│   └── routes/
│       ├── auth_routes.py     # API đăng nhập & hồ sơ
│       ├── medicine_routes.py # API danh mục thuốc, nhóm thuốc, đơn vị
│       ├── batch_routes.py    # API nhập lô, quản lý trạng thái lô, cảnh báo date
│       ├── invoice_routes.py  # API bán hàng POS & hóa đơn FEFO
│       ├── report_routes.py   # API thống kê doanh thu, tồn kho, top bán chạy
│       └── ai_routes.py       # API AI Copilot & nhật ký audit logs
├── docs/
│   ├── KT1_PhanTichNghiepVu_ERD_GioiHanAI.md      # Báo cáo KT1 (Nghiệp vụ, ERD, AI Bounds)
│   ├── KT2_API_GiaoDien_LogicTonKho_FEFO.md       # Báo cáo KT2 (API specs, UI, FEFO logic)
│   ├── KT3_ThietKePrompt_Guardrails_TestReport.md  # Báo cáo KT3 (Prompts, Guardrails, PyTest)
│   ├── CuoiKy_BaoCao_DaoDucAI_SecurityReview.md    # Báo cáo Cuối Kỳ (Đạo đức AI, OWASP)
│   └── CuoiKy_Slide_ThuyetTrinh.md                 # Kịch bản Slide bảo vệ đồ án
├── templates/                 # Giao diện Web HTML (Jinja2)
│   ├── layout.html            # Khung giao diện chính, sidebar, header, disclaimer cố định
│   ├── dashboard.html         # Bảng điều khiển tổng quan
│   ├── medicines.html         # Quản lý danh mục thuốc & AI tóm tắt
│   ├── batches.html           # Quản lý lô hàng & theo dõi hạn sử dụng FEFO
│   ├── sales.html             # Quầy bán thuốc POS & in hóa đơn
│   ├── alerts.html            # Cảnh báo cận hạn & thiếu hụt tồn kho
│   ├── ai_copilot.html        # Trung tâm AI Trợ lý Dược sĩ (Chatbot, Báo cáo, SOP)
│   ├── reports.html           # Báo cáo doanh thu & kho vận
│   ├── about.html             # Thông tin thành viên nhóm & SDLC
│   └── login.html             # Đăng nhập & chuyển đổi vai trò
├── tests/                     # Bộ kiểm thử tự động hóa (PyTest)
│   ├── conftest.py
│   ├── test_inventory_fefo.py # Test FEFO, chặn lô quá hạn, trừ tồn kho
│   ├── test_ai_guardrails.py  # Test câu hỏi vượt quyền, an toàn prompt, dữ liệu thiếu
│   └── test_api_endpoints.py  # Test toàn bộ các endpoint RESTful
├── pharmacy.db                # Cơ sở dữ liệu SQLite
├── pytest.ini                 # Cấu hình PyTest
└── requirements.txt           # Danh mục thư viện phụ thuộc
```

---

## 3. Minh Chứng Các Giai Đoạn Vòng Đời Phần Mềm (SDLC)

| Giai đoạn | Nội dung thực hiện & Minh chứng | Tài liệu tương ứng |
|---|---|---|
| **KT1** | Phân tích quy trình GSP/GPP, quản lý biệt dược, thiết kế ERD 10 bảng, xác định giới hạn đạo đức AI (Red Lines). | `docs/KT1_PhanTichNghiepVu_ERD_GioiHanAI.md` |
| **KT2** | Xây dựng RESTful API chuẩn OpenAPI, giải thuật xuất kho FEFO (ACID Transactions), giao diện Web Dashboard trực quan. | `docs/KT2_API_GiaoDien_LogicTonKho_FEFO.md` |
| **KT3** | Thiết kế System Prompt, bộ lọc Guardrails chặn câu hỏi vượt quyền, PyTest tự động hóa 15 test cases (100% Passed). | `docs/KT3_ThietKePrompt_Guardrails_TestReport.md` |
| **Cuối kỳ** | Hoàn thiện báo cáo Đạo đức Trí tuệ Nhân tạo y tế, kiểm tra an toàn bảo mật OWASP, kịch bản slide thuyết trình. | `docs/CuoiKy_BaoCao_DaoDucAI_SecurityReview.md`<br>`docs/CuoiKy_Slide_ThuyetTrinh.md` |

---

## 4. Hướng Dẫn Cài Đặt & Chạy Hệ Thống

### 4.1. Kích hoạt môi trường ảo Python
```bash
cd /home/gkxmmtkm/AI
source venv/bin/activate
```

### 4.2. Chạy bộ kiểm thử tự động (Automated Test Suite)
```bash
./venv/bin/pytest tests/ -v
```
Kết quả kỳ vọng: **15 passed (100%)**.

### 4.3. Khởi động Web Server (Uvicorn / FastAPI)
```bash
./venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
```
- **Giao diện Dashboard:** `http://localhost:8000/`
- **Swagger API Docs:** `http://localhost:8000/docs`
- **Tài khoản mẫu đăng nhập:**
  - **Quản lý (Admin):** `admin` / `admin123`
  - **Dược sĩ (Pharmacist):** `pharmacist` / `duocsi123`
  - **Thu ngân (Cashier):** `cashier` / `thungan123`

---

## 5. Tuyên Bố Về Đạo Đức & Giới Hạn Trách Nhiệm AI (Disclaimer)

⚠️ **CẢNH BÁO QUAN TRỌNG:**
Hệ thống AI này được thiết kế và huấn luyện nhằm phục vụ mục đích **hỗ trợ tra cứu nghiệp vụ nội bộ** cho nhân viên nhà thuốc. Hệ thống **tuyệt đối không đưa ra lời khuyên điều trị, không chỉ định liều dùng lâm sàng** và **không thay thế vai trò chuyên môn của Bác sĩ hoặc Dược sĩ phụ trách**. Mọi quyết định xuất bán thuốc bắt buộc phải tuân thủ quy chế chuyên môn ngành dược hiện hành.
