import sqlite3
import os
import hashlib
from datetime import date, datetime, timedelta

DB_PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "pharmacy.db")

def get_db_connection():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn

def hash_password(password: str) -> str:
    return hashlib.sha256(password.encode("utf-8")).hexdigest()

def init_db():
    conn = get_db_connection()
    cursor = conn.cursor()

    # Bảng người dùng
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin', 'pharmacist', 'cashier')),
        is_active INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    """)

    # Bảng nhóm thuốc
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        description TEXT
    );
    """)

    # Bảng đơn vị tính
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS units (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL
    );
    """)

    # Bảng nhà cung cấp
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS suppliers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT,
        address TEXT,
        email TEXT,
        tax_code TEXT
    );
    """)

    # Bảng thuốc
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS medicines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        category_id INTEGER,
        unit_id INTEGER,
        active_ingredient TEXT NOT NULL,
        concentration TEXT,
        route TEXT DEFAULT 'Uống',
        min_stock_alert INTEGER DEFAULT 20,
        usage_instruction TEXT,
        contraindication TEXT,
        side_effects TEXT,
        storage_condition TEXT DEFAULT 'Nhiệt độ dưới 30°C, nơi khô ráo, tránh ánh sáng trực tiếp',
        prescription_required INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id),
        FOREIGN KEY (unit_id) REFERENCES units(id)
    );
    """)

    # Bảng lô thuốc (Batch/Lot)
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS batches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        batch_number TEXT NOT NULL,
        medicine_id INTEGER NOT NULL,
        supplier_id INTEGER,
        import_date DATE NOT NULL,
        manufacture_date DATE NOT NULL,
        expiry_date DATE NOT NULL,
        quantity_imported INTEGER NOT NULL,
        quantity_remaining INTEGER NOT NULL,
        import_price REAL NOT NULL,
        selling_price REAL NOT NULL,
        status TEXT DEFAULT 'active' CHECK(status IN ('active', 'expired', 'disposed', 'recalled')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (medicine_id) REFERENCES medicines(id),
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    );
    """)

    # Bảng hóa đơn bán lẻ
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_number TEXT UNIQUE NOT NULL,
        cashier_id INTEGER NOT NULL,
        customer_name TEXT,
        customer_phone TEXT,
        prescription_code TEXT,
        total_amount REAL NOT NULL,
        discount REAL DEFAULT 0,
        final_amount REAL NOT NULL,
        payment_method TEXT DEFAULT 'cash' CHECK(payment_method IN ('cash', 'transfer')),
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cashier_id) REFERENCES users(id)
    );
    """)

    # Chi tiết hóa đơn (trừ theo từng lô thuốc)
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS invoice_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_id INTEGER NOT NULL,
        batch_id INTEGER NOT NULL,
        medicine_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        unit_price REAL NOT NULL,
        subtotal REAL NOT NULL,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id),
        FOREIGN KEY (batch_id) REFERENCES batches(id),
        FOREIGN KEY (medicine_id) REFERENCES medicines(id)
    );
    """)

    # Bảng tài liệu SOP nội bộ (Quy trình nhà thuốc)
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS sop_documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        title TEXT NOT NULL,
        category TEXT NOT NULL,
        content TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    """)

    # Bảng nhật ký AI Audit
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS ai_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action_type TEXT NOT NULL,
        prompt TEXT NOT NULL,
        response TEXT NOT NULL,
        disclaimer_included INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
    """)

    conn.commit()
    seed_initial_data(conn)
    conn.close()

def seed_initial_data(conn):
    cursor = conn.cursor()

    # 1. Users
    cursor.execute("SELECT COUNT(*) FROM users")
    if cursor.fetchone()[0] == 0:
        users = [
            ("admin", hash_password("admin123"), "Dược Sĩ Trưởng (Quản Lý)", "admin"),
            ("duocsi1", hash_password("duocsi123"), "DS. Nguyễn Văn An", "pharmacist"),
            ("thungan1", hash_password("thungan123"), "TN. Trần Thị Mai", "cashier")
        ]
        cursor.executemany("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)", users)

    # 2. Categories
    cursor.execute("SELECT COUNT(*) FROM categories")
    if cursor.fetchone()[0] == 0:
        categories = [
            ("Giảm đau - Hạ sốt - Kháng viêm", "Thuốc dùng giảm đau, hạ nhiệt cơ thể, kháng viêm không steroid"),
            ("Kháng sinh - Kháng khuẩn", "Kháng sinh nhóm Penicillin, Cephalosporin, Macrolid - Bắt buộc đơn thuốc"),
            ("Tiêu hóa - Dạ dày", "Thuốc giảm tiết acid dạ dày, trào ngược dạ dày thực quản, men tiêu hóa"),
            ("Dị ứng - Hô hấp", "Thuốc kháng Histamin H1, giảm ngạt mũi, dị ứng thời tiết"),
            ("Vitamin & Khoáng chất", "Thực phẩm bảo vệ sức khỏe và bổ sung vi chất")
        ]
        cursor.executemany("INSERT INTO categories (name, description) VALUES (?, ?)", categories)

    # 3. Units
    cursor.execute("SELECT COUNT(*) FROM units")
    if cursor.fetchone()[0] == 0:
        units = [("Viên",), ("Vỉ",), ("Hộp",), ("Chai",), ("Gói",), ("Tuýp",)]
        cursor.executemany("INSERT INTO units (name) VALUES (?)", units)

    # 4. Suppliers
    cursor.execute("SELECT COUNT(*) FROM suppliers")
    if cursor.fetchone()[0] == 0:
        suppliers = [
            ("Công ty CP Dược Hậu Giang (DHG Pharma)", "0292.3891433", "288 Bis Nguyễn Chí Thanh, Cần Thơ", "dhgpharma@dhgpharma.com.vn", "1800156801"),
            ("Công ty CP Traphaco", "024.37341797", "75 Yên Ninh, Ba Đình, Hà Nội", "info@traphaco.com.vn", "0100108656"),
            ("Sanofi-Aventis Việt Nam", "028.38298526", "Số 10 Hàm Nghi, Q.1, TP.HCM", "contact@sanofi.com", "0300829871")
        ]
        cursor.executemany("INSERT INTO suppliers (name, phone, address, email, tax_code) VALUES (?, ?, ?, ?, ?)", suppliers)

    # 5. Medicines
    cursor.execute("SELECT COUNT(*) FROM medicines")
    if cursor.fetchone()[0] == 0:
        medicines = [
            (
                "THUOC001", "Hapacol 500", 1, 1, "Paracetamol", "500mg", "Uống", 30,
                "Giảm các cơn đau nhẹ đến vừa và hạ sốt: nhức đầu, đau họng, sốt sau tiêm chủng. Người lớn: 1-2 viên/lần, cách nhau 4-6 giờ. Tối đa 4g/ngày.",
                "Quá mẫn với Paracetamol. Bệnh nhân suy gan nặng, thiếu hụt enzym G6PD.",
                "Ban da, nổi mề đay, độc tính gan khi dùng quá liều hoặc dùng dài ngày kèm rượu bia.",
                "Bảo quản dưới 30°C, tránh ẩm và ánh sáng.", 0
            ),
            (
                "THUOC002", "Amoxicillin 500mg DHG", 2, 1, "Amoxicillin trihydrat", "500mg", "Uống", 20,
                "Kháng sinh điều trị nhiễm khuẩn đường hô hấp trên/dưới, nhiễm khuẩn tiết niệu, đường mật. Uống 1 viên mỗi 8 giờ theo chỉ định của bác sĩ.",
                "Tiền sử dị ứng với Penicillin hoặc Cephalosporin. Bệnh nhân tăng bạch cầu đơn nhân nhiễm khuẩn.",
                "Buồn nôn, tiêu chảy, phát ban dát sần. Cần ngừng thuốc ngay nếu có dấu hiệu sốc phản vệ.",
                "Bảo quản nơi khô mát, nhiệt độ không quá 30°C.", 1
            ),
            (
                "THUOC003", "Panadol Extra Đỏ", 1, 1, "Paracetamol + Caffeine", "500mg + 65mg", "Uống", 50,
                "Hạ sốt, giảm đau nhanh trong đau đầu, đau cơ, đau nửa đầu, đau răng. Uống 1-2 viên/lần sau ăn, cách 4-6 giờ.",
                "Trẻ em dưới 12 tuổi, mẫn cảm với paracetamol hoặc caffeine. Bệnh nhân bệnh tim mạch nặng.",
                "Bồn chồn, mất ngủ, tăng nhịp tim do caffeine, phản ứng dị ứng da.",
                "Nơi khô ráo, nhiệt độ dưới 30°C.", 0
            ),
            (
                "THUOC004", "Omeprazol 20mg", 3, 1, "Omeprazole", "20mg", "Uống", 15,
                "Điều trị loét dạ dày tá tràng, trào ngược dạ dày thực quản (GERD). Uống 1 viên vào buổi sáng trước bữa ăn 30-60 phút.",
                "Mẫn cảm với dẫn chất benzimidazol hoặc bất kỳ thành phần nào của thuốc. Không dùng phối hợp với Nelfinavir.",
                "Nhức đầu, chóng mặt, buồn nôn, đau bụng, tiêu chảy hoặc táo bón.",
                "Bảo quản trong bao bì kín, tránh ẩm, nhiệt độ dưới 25°C.", 1
            ),
            (
                "THUOC005", "Cetirizin 10mg", 4, 1, "Cetirizine dihydrochloride", "10mg", "Uống", 20,
                "Điều trị triệu chứng viêm mũi dị ứng theo mùa, mày đay mạn tính vô căn. Người lớn và trẻ trên 12 tuổi: 1 viên/ngày.",
                "Mẫn cảm với cetirizin hoặc hydroxyzine. Bệnh nhân suy thận giai đoạn cuối.",
                "Ngủ gà, buồn ngủ nhẹ, mệt mỏi, khô miệng, nhức đầu.",
                "Nhiệt độ dưới 30°C, tránh ánh sáng.", 0
            ),
            (
                "THUOC006", "Berberin BM 100mg", 3, 1, "Berberin clorid", "100mg", "Uống", 40,
                "Điều trị lỵ trực khuẩn, hội chứng lỵ, viêm ruột tiêu chảy do vi khuẩn. Người lớn uống 2-4 viên/lần, ngày 2 lần.",
                "Phụ nữ có thai (nguy cơ kích thích co bóp dạ con dẫn đến sẩy thai). Phụ nữ đang cho con bú.",
                "Táo bón khi dùng liều cao kéo dài, buồn nôn nhẹ.",
                "Nơi khô mát, tránh ánh sáng trực tiếp.", 0
            ),
            (
                "THUOC007", "Vitamin C 500mg Enervon", 5, 1, "Acid Ascorbic", "500mg", "Uống", 25,
                "Phòng và điều trị thiếu vitamin C, tăng cường sức đề kháng cơ thể. Uống 1 viên/ngày sau ăn sáng.",
                "Sỏi thận oxalat, tăng oxalat niệu, người thiếu hụt enzym G6PD.",
                "Tăng oxalat niệu, buồn nôn, ợ nóng, tiêu chảy khi dùng liều quá cao (>1g/ngày).",
                "Đóng kín nắp lọ, nhiệt độ dưới 30°C, tránh ánh sáng trực tiếp.", 0
            )
        ]
        cursor.executemany("""
        INSERT INTO medicines (code, name, category_id, unit_id, active_ingredient, concentration, route, min_stock_alert, usage_instruction, contraindication, side_effects, storage_condition, prescription_required)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, medicines)

    # 6. Batches (Bao gồm lô còn hạn dài, lô cận hạn 20 ngày, lô cận hạn 45 ngày, lô đã hết hạn để test cảnh báo)
    cursor.execute("SELECT COUNT(*) FROM batches")
    if cursor.fetchone()[0] == 0:
        today = date.today()
        batches = [
            # Lô bình thường, hạn còn xa (1-2 năm)
            ("LOT-HAP-2024-01", 1, 1, str(today - timedelta(days=60)), str(today - timedelta(days=90)), str(today + timedelta(days=500)), 500, 320, 1200, 2000, "active"),
            # Lô Hapacol CẬN HẠN (còn 25 ngày) để kích hoạt cảnh báo cận hạn
            ("LOT-HAP-2023-99", 1, 1, str(today - timedelta(days=340)), str(today - timedelta(days=360)), str(today + timedelta(days=25)), 200, 45, 1000, 1800, "active"),
            # Lô Amoxicillin bình thường
            ("LOT-AMX-2024-02", 2, 1, str(today - timedelta(days=30)), str(today - timedelta(days=40)), str(today + timedelta(days=400)), 300, 180, 1800, 3000, "active"),
            # Lô Amoxicillin CẬN HẠN (còn 45 ngày)
            ("LOT-AMX-2023-88", 2, 1, str(today - timedelta(days=320)), str(today - timedelta(days=340)), str(today + timedelta(days=45)), 150, 30, 1500, 2500, "active"),
            # Lô Panadol Extra bình thường
            ("LOT-PAN-2024-03", 3, 3, str(today - timedelta(days=40)), str(today - timedelta(days=50)), str(today + timedelta(days=600)), 600, 480, 1800, 2800, "active"),
            # Lô Omeprazol TỒN KHO THẤP (8 viên, dưới ngưỡng 15) và CẬN HẠN (còn 15 ngày)
            ("LOT-OME-2023-77", 4, 2, str(today - timedelta(days=350)), str(today - timedelta(days=370)), str(today + timedelta(days=15)), 100, 8, 1100, 2200, "active"),
            # Lô Cetirizin bình thường
            ("LOT-CET-2024-01", 5, 2, str(today - timedelta(days=20)), str(today - timedelta(days=30)), str(today + timedelta(days=700)), 400, 350, 700, 1500, "active"),
            # Lô Berberin bình thường
            ("LOT-BER-2024-01", 6, 2, str(today - timedelta(days=15)), str(today - timedelta(days=25)), str(today + timedelta(days=550)), 500, 420, 600, 1200, "active"),
            # Lô Berberin ĐÃ HẾT HẠN (âm 10 ngày) để kiểm tra logic FEFO chặn bán & hiển thị danh sách hủy
            ("LOT-BER-2022-EXP", 6, 2, str(today - timedelta(days=400)), str(today - timedelta(days=730)), str(today - timedelta(days=10)), 100, 15, 500, 1000, "expired"),
            # Lô Vitamin C bình thường
            ("LOT-VTC-2024-01", 7, 1, str(today - timedelta(days=10)), str(today - timedelta(days=20)), str(today + timedelta(days=360)), 200, 150, 1500, 2500, "active")
        ]
        cursor.executemany("""
        INSERT INTO batches (batch_number, medicine_id, supplier_id, import_date, manufacture_date, expiry_date, quantity_imported, quantity_remaining, import_price, selling_price, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, batches)

    # 7. SOP Documents (Quy trình chuẩn phục vụ AI Chatbot & Tra cứu nội bộ)
    cursor.execute("SELECT COUNT(*) FROM sop_documents")
    if cursor.fetchone()[0] == 0:
        sops = [
            (
                "SOP-01",
                "Quy trình kiểm nhập thuốc và tiếp nhận lô hàng (GSP)",
                "import",
                "Mục đích: Đảm bảo thuốc nhập vào kho nhà thuốc đúng chủng loại, nguồn gốc rõ ràng, đạt chuẩn chất lượng và nhiệt độ bảo quản.\n"
                "Các bước thực hiện:\n"
                "1. Tiếp nhận hóa đơn đỏ và phiếu giao hàng từ nhà phân phối được cấp phép.\n"
                "2. Kiểm tra tính nguyên vẹn của bao bì, niêm phong, nhãn phụ tiếng Việt, số đăng ký lưu hành (SĐK/GPNK).\n"
                "3. Đối chiếu số lô (Batch/Lot), ngày sản xuất (MFG), hạn dùng (EXP) trên thực tế với hóa đơn nhập.\n"
                "4. Quy định hạn dùng tối thiểu khi nhập: Thuốc phải có hạn dùng còn lại ít nhất 12 tháng (hoặc tối thiểu 50% tổng thời gian lưu hành đối với thuốc có hạn dùng dưới 2 năm).\n"
                "5. Kiểm tra nhiệt độ đối với hàng chuỗi lạnh (nếu có): phải đạt 2°C - 8°C và có thiết bị ghi nhiệt độ.\n"
                "6. Nhập thông tin vào phần mềm quản lý: số lô, hạn dùng, giá nhập, số lượng thực nhận.\n"
                "7. Ký biên bản giao nhận và lưu trữ chứng từ tối thiểu 1 năm sau khi thuốc hết hạn sử dụng."
            ),
            (
                "SOP-02",
                "Quy trình kiểm kê định kỳ và kiểm soát tồn kho",
                "inventory",
                "Mục đích: Đối chiếu số lượng thực tế với số liệu trên phần mềm, phát hiện kịp thời thuốc hư hỏng, cận hạn, chênh lệch số lượng.\n"
                "Các bước thực hiện:\n"
                "1. Định kỳ kiểm kê: Thực hiện định kỳ vào ngày cuối cùng của mỗi tháng hoặc đột xuất khi có yêu cầu thanh tra/chênh lệch lớn.\n"
                "2. Nguyên tắc kiểm kê: Áp dụng phương pháp kiểm kê kép (2 nhân viên/dược sĩ độc lập đếm và đối chiếu chéo).\n"
                "3. Kiểm tra cảm quan: Phát hiện viên nứt vỡ, ẩm mốc, biến màu, dung dịch bị lắng cặn hoặc biến tính.\n"
                "4. Phân loại thuốc cận hạn: Đánh dấu các lô thuốc có hạn sử dụng dưới 3 tháng (vàng) và dưới 1 tháng (đỏ).\n"
                "5. Xử lý sai lệch: Lập biên bản giải trình chênh lệch, tìm nguyên nhân (thất thoát, nhập sai đơn vị vỉ/hộp, xuất kho chưa bấm bill).\n"
                "6. Trình Dược sĩ Trưởng phê duyệt biên bản điều chỉnh kho trên hệ thống."
            ),
            (
                "SOP-03",
                "Quy trình xử lý thuốc cận hạn, hết hạn và thuốc thu hồi",
                "disposal",
                "Mục đích: Ngăn chặn hoàn toàn việc bán thuốc hết hạn hoặc kém chất lượng cho người bệnh; tuân thủ đúng quy chế dược.\n"
                "Các bước thực hiện:\n"
                "1. Rà soát cận hạn:\n"
                "   - Trước 60 - 90 ngày: Liên hệ nhà cung cấp làm thủ tục đổi hàng luân chuyển theo cam kết hợp đồng.\n"
                "   - Trước 30 ngày: Nếu nhà cung cấp không nhận đổi trả, cân nhắc áp dụng chương trình chiết khấu/bán ưu tiên đối với các bệnh mạn tính dùng ngay (phải có tư vấn kỹ của Dược sĩ), hoặc niêm phong chuẩn bị hủy.\n"
                "2. Biệt trữ thuốc hết hạn/thu hồi:\n"
                "   - Lập tức chuyển thuốc hết hạn sang 'Khu vực chờ xử lý' hoặc tủ biệt trữ có khóa riêng, dán nhãn màu đỏ cảnh báo rõ: 'THUỐC CHỜ HỦY - KHÔNG ĐƯỢC BÁN'.\n"
                "   - Cập nhật trạng thái lô trên phần mềm từ 'active' sang 'expired' hoặc 'disposed' để khóa chức năng xuất bán.\n"
                "3. Thành lập hội đồng hủy thuốc:\n"
                "   - Hội đồng gồm: Dược sĩ phụ trách chuyên môn, Quản lý kho, Kế toán.\n"
                "   - Lập biên bản kiểm kê thuốc cần tiêu hủy (tên thuốc, hàm lượng, số lô, hạn dùng, số lượng, lý do hủy).\n"
                "4. Ký hợp đồng với đơn vị có chức năng xử lý chất thải y tế nguy hại để tiêu hủy đúng quy định môi trường. Nghiêm cấm vứt thuốc bừa bãi vào rác thải sinh hoạt."
            ),
            (
                "SOP-04",
                "Quy trình bán thuốc và tư vấn sử dụng thuốc theo GPP",
                "dispensing",
                "Mục đích: Đảm bảo người bệnh nhận đúng thuốc, đúng liều lượng, hướng dẫn sử dụng an toàn, tuân thủ nguyên tắc FEFO/FIFO.\n"
                "Các bước thực hiện:\n"
                "1. Tiếp nhận và phân loại nhu cầu:\n"
                "   - Thuốc không kê đơn (OTC): Hỏi thăm triệu chứng, tiền sử dị ứng, đối tượng đặc biệt (trẻ em, phụ nữ có thai/cho con bú, người già, suy gan/thận).\n"
                "   - Thuốc kê đơn (Rx): Kiểm tra tính hợp lệ của đơn thuốc (chữ ký bác sĩ, dấu cơ sở y tế, ngày kê đơn trong vòng 5 ngày, thông tin bệnh nhân). Tuyệt đối không tự ý bán thuốc Rx khi không có đơn.\n"
                "2. Lấy thuốc theo nguyên tắc FEFO (First Expired, First Out):\n"
                "   - Luôn ưu tiên xuất lô có hạn dùng gần nhất trước, tuyệt đối không xuất lô đã hết hạn.\n"
                "3. Kiểm tra 5 đúng trước khi giao: Đúng người bệnh, đúng thuốc, đúng liều lượng, đúng đường dùng, đúng thời gian dùng.\n"
                "4. Tư vấn sử dụng cho khách hàng: Liều dùng, thời điểm uống (trước/sau ăn), tác dụng phụ cần lưu ý, kiêng kỵ khi uống thuốc.\n"
                "5. Lập hóa đơn xuất bán trên hệ thống, in hóa đơn/phiếu hướng dẫn liều dùng cho bệnh nhân."
            )
        ]
        cursor.executemany("""
        INSERT INTO sop_documents (code, title, category, content)
        VALUES (?, ?, ?, ?)
        """, sops)

    # 8. Mẫu hóa đơn mẫu để có số liệu thống kê doanh thu
    cursor.execute("SELECT COUNT(*) FROM invoices")
    if cursor.fetchone()[0] == 0:
        # Hóa đơn 1
        cursor.execute("""
        INSERT INTO invoices (invoice_number, cashier_id, customer_name, customer_phone, total_amount, discount, final_amount, payment_method, note, created_at)
        VALUES ('HD20241028001', 3, 'Nguyễn Văn Minh', '0912345678', 40000, 0, 40000, 'cash', 'Khách mua thuốc cảm sốt thông thường', datetime('now', '-3 days'))
        """)
        inv_id1 = cursor.lastrowid
        cursor.execute("INSERT INTO invoice_items (invoice_id, batch_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, 1, 1, 20, 2000, 40000)", (inv_id1,))

        # Hóa đơn 2
        cursor.execute("""
        INSERT INTO invoices (invoice_number, cashier_id, customer_name, customer_phone, total_amount, discount, final_amount, payment_method, note, created_at)
        VALUES ('HD20241029002', 2, 'Lê Thị Hồng', '0987654321', 60000, 0, 60000, 'transfer', 'Đơn thuốc viêm phế quản', datetime('now', '-2 days'))
        """)
        inv_id2 = cursor.lastrowid
        cursor.execute("INSERT INTO invoice_items (invoice_id, batch_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, 3, 2, 20, 3000, 60000)", (inv_id2,))

        # Hóa đơn 3
        cursor.execute("""
        INSERT INTO invoices (invoice_number, cashier_id, customer_name, customer_phone, total_amount, discount, final_amount, payment_method, note, created_at)
        VALUES ('HD20241030003', 3, 'Trần Đức Anh', '0903112233', 75000, 5000, 70000, 'cash', 'Mua bổ sung vitamin C & hạ sốt', datetime('now', '-1 days'))
        """)
        inv_id3 = cursor.lastrowid
        cursor.execute("INSERT INTO invoice_items (invoice_id, batch_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, 5, 3, 10, 2800, 28000)", (inv_id3,))
        cursor.execute("INSERT INTO invoice_items (invoice_id, batch_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, 10, 7, 18, 2500, 45000)", (inv_id3,))

    conn.commit()

if __name__ == "__main__":
    init_db()
    print("Khởi tạo cơ sở dữ liệu Pharmacy DB thành công!")
