<?php
/**
 * CỔNG THÔNG TIN KHÁCH HÀNG & TRA CỨU THUỐC
 * Hệ thống Nhà thuốc chuẩn GPP
 * Đầy đủ: Tra cứu thuốc, Lịch sử mua hàng, Tích điểm, Trợ lý Dược lý hỏi đáp
 */
include_once 'app.php';
include_once 'ai_service.php';

// Kiểm tra phiên đăng nhập khách hàng
if (!isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

$customerId = (int)$_SESSION['customer_id'];
$customer = pms_fetch_one("SELECT * FROM customers WHERE customer_id = ? LIMIT 1", 'i', [$customerId]);
if (!$customer) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Tính hạng thành viên & chiết khấu
$pts = (int)$customer['loyalty_points'];
$tier = $pts >= 100 ? 'VIP Kim Cương' : ($pts >= 50 ? 'Thành Viên Vàng' : ($pts >= 20 ? 'Thành Viên Bạc' : 'Thành Viên Mới'));
$discount = $pts >= 100 ? '10%' : ($pts >= 50 ? '5%' : ($pts >= 20 ? '2%' : '0%'));
$nextPoints = $pts >= 100 ? 0 : ($pts >= 50 ? 100 - $pts : ($pts >= 20 ? 50 - $pts : 20 - $pts));

// Xử lý tìm kiếm thuốc trong danh mục
$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

$sql = "
    SELECT d.id, d.drug_code, d.name, d.active_ingredient, d.category, d.unit, d.retail_unit, d.sale_price, d.company,
           COALESCE(SUM(s.quantity), 0) AS in_stock
    FROM drugs d
    LEFT JOIN stock s ON s.drug_id = d.id AND s.quantity > 0 AND (s.expiry_date IS NULL OR s.expiry_date > CURDATE())
    WHERE d.is_deleted = 0
";
$params = [];
$types = '';

if ($q !== '') {
    $sql .= " AND (d.name LIKE ? OR d.active_ingredient LIKE ? OR d.drug_code LIKE ?)";
    $qLike = "%{$q}%";
    $params[] = $qLike; $params[] = $qLike; $params[] = $qLike;
    $types .= 'sss';
}

if ($category !== '') {
    $sql .= " AND d.category = ?";
    $params[] = $category;
    $types .= 's';
}

$sql .= " GROUP BY d.id ORDER BY d.name ASC LIMIT 24";
$drugs = pms_fetch_all($sql, $types, $params);

// Lấy danh sách nhóm thuốc để lọc
$categories = pms_fetch_all("SELECT DISTINCT category FROM drugs WHERE category IS NOT NULL AND category != '' AND is_deleted = 0 ORDER BY category ASC");

// Lấy lịch sử hóa đơn mua thuốc của khách hàng
$invoices = pms_fetch_all("
    SELECT i.invoice_no, i.invoice_code, i.grand_total, i.payment_type, i.created_at,
           COUNT(it.item_id) AS total_items,
           GROUP_CONCAT(CONCAT(it.drug_name, ' (x', it.quantity, ')') SEPARATOR ', ') AS item_names
    FROM invoice_header i
    LEFT JOIN invoice_item it ON it.invoice_no = i.invoice_no
    WHERE i.customer_id = ? OR i.customer_name = ? OR i.customer_name = ?
    GROUP BY i.invoice_no
    ORDER BY i.created_at DESC
    LIMIT 10
", 'iss', [$customerId, $customer['customer_name'], $customer['phone']]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cổng Thành Viên & Tra Cứu Thuốc | Nhà Thuốc</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #0f766e;
            --primary-hover: #115e59;
            --primary-light: #f0fdfa;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-200: #e2e8f0;
            --slate-100: #f8fafc;
        }

        body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background: #f1f5f9;
            color: var(--slate-900);
        }

        /* Top Navbar */
        .portal-nav {
            background: #ffffff;
            border-bottom: 1px solid var(--slate-200);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .portal-logo {
            font-size: 18px;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .portal-user-nav {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-pill {
            display: inline-block;
            padding: 9px 20px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary { background: var(--primary); color: #ffffff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-outline { background: #ffffff; border: 1px solid var(--slate-200); color: var(--slate-600); }
        .btn-outline:hover { background: var(--slate-100); }

        .container {
            max-width: 1140px;
            margin: 28px auto;
            padding: 0 20px;
        }

        /* Banner thành viên */
        .member-banner {
            background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
            color: #ffffff;
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 30px -5px rgba(15, 118, 110, 0.25);
        }

        .tier-tag {
            display: inline-block;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            padding: 5px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.03em;
            margin-bottom: 8px;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .member-name { font-size: 26px; font-weight: 800; margin-bottom: 4px; }
        .member-sub { font-size: 13.5px; color: #ccfbf1; }

        .points-box {
            background: rgba(255, 255, 255, 0.12);
            padding: 18px 24px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: right;
        }

        .points-val { font-size: 32px; font-weight: 800; line-height: 1; }
        .points-lbl { font-size: 12px; color: #99f6e4; text-transform: uppercase; font-weight: 600; margin-top: 4px; }

        /* Tabs */
        .portal-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--slate-200);
            padding-bottom: 12px;
        }

        .tab-btn {
            padding: 10px 22px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: #ffffff;
            color: var(--slate-600);
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .tab-btn.active {
            background: var(--primary);
            color: #ffffff;
        }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* Card chung */
        .panel-card {
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid var(--slate-200);
            padding: 28px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
            margin-bottom: 24px;
        }

        .card-header {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-title { font-size: 18px; font-weight: 800; color: var(--slate-900); }
        .card-subtitle { font-size: 13px; color: var(--slate-500); margin-top: 2px; }

        /* Thanh tìm kiếm thuốc */
        .search-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 240px;
            padding: 12px 20px;
            border-radius: 9999px;
            border: 1px solid var(--slate-200);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-input:focus { border-color: var(--primary); }

        .search-select {
            padding: 12px 20px;
            border-radius: 9999px;
            border: 1px solid var(--slate-200);
            font-size: 13.5px;
            outline: none;
            background: #ffffff;
        }

        /* Lưới thuốc */
        .drug-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
        }

        .drug-card {
            background: var(--slate-100);
            border: 1px solid var(--slate-200);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .drug-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(15, 118, 110, 0.08);
            transform: translateY(-2px);
        }

        .drug-name { font-size: 15px; font-weight: 700; color: var(--slate-900); margin-bottom: 4px; }
        .drug-act { font-size: 12.5px; color: var(--primary); font-weight: 600; margin-bottom: 8px; }
        .drug-meta { font-size: 12px; color: var(--slate-500); margin-bottom: 12px; }
        .drug-price { font-size: 17px; font-weight: 800; color: #0f766e; }
        .drug-stock-tag {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 9999px;
            display: inline-block;
        }
        .stock-avail { background: #dcfce7; color: #166534; }
        .stock-out { background: #fee2e2; color: #991b1b; }

        /* Bảng lịch sử mua hàng */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        th { text-align: left; padding: 12px 16px; background: var(--slate-100); color: var(--slate-600); font-weight: 700; }
        td { padding: 14px 16px; border-bottom: 1px solid var(--slate-200); }

        /* Trợ lý Dược lý */
        .chat-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .chat-input-box textarea {
            width: 100%;
            padding: 14px 18px;
            border-radius: 18px;
            border: 1px solid var(--slate-200);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            resize: vertical;
        }

        .chat-input-box textarea:focus { border-color: var(--primary); }

        .quick-ask-chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .chip-btn {
            background: var(--slate-100);
            border: 1px solid var(--slate-200);
            border-radius: 9999px;
            padding: 6px 14px;
            font-size: 12px;
            color: var(--slate-600);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .chip-btn:hover { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }

        .disclaimer-alert {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 13px;
            color: #92400e;
            line-height: 1.6;
        }

        .response-box {
            padding: 20px;
            border-radius: 18px;
            background: var(--slate-100);
            border: 1px solid var(--slate-200);
            line-height: 1.7;
            font-size: 14px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <header class="portal-nav">
        <a href="customer_portal.php" class="portal-logo">
            <span>NHÀ THUỐC</span>
            <span style="font-size: 12px; background: #ecfdf5; color: #047857; padding: 4px 10px; border-radius: 9999px; font-weight: 700;">Cổng Thành Viên</span>
        </a>
        <div class="portal-user-nav">
            <span style="font-size: 13.5px; font-weight: 600; color: var(--slate-700);">Xin chào, <?= pms_h($customer['customer_name']) ?></span>
            <a href="index.php?logout=1" class="btn-pill btn-outline">Đăng xuất</a>
        </div>
    </header>

    <main class="container">
        <!-- Banner thành viên & Điểm tích lũy -->
        <div class="member-banner">
            <div>
                <span class="tier-tag"><?= $tier ?></span>
                <h1 class="member-name"><?= pms_h($customer['customer_name']) ?></h1>
                <p class="member-sub">
                    Số điện thoại: <strong><?= pms_h($customer['phone'] ?: 'Chưa cập nhật') ?></strong> · 
                    Ưu đãi chiết khấu trực tiếp: <strong><?= $discount ?></strong>
                </p>
                <?php if ($nextPoints > 0): ?>
                    <p style="font-size: 12.5px; color: #a7f3d0; margin-top: 6px;">
                        Tích lũy thêm <strong><?= $nextPoints ?> điểm</strong> nữa để nâng hạng thẻ ưu đãi cao hơn.
                    </p>
                <?php endif; ?>
            </div>
            <div class="points-box">
                <div class="points-val"><?= number_format($pts) ?></div>
                <div class="points-lbl">Điểm tích lũy</div>
                <div style="font-size: 12px; color: #ccfbf1; margin-top: 4px;">= <?= number_format($pts * 100) ?> đ giảm trừ</div>
            </div>
        </div>

        <!-- Điều hướng các Tab chức năng -->
        <div class="portal-tabs">
            <button type="button" class="tab-btn active" onclick="switchTab('tab-drugs', this)">Danh Mục & Tra Cứu Thuốc</button>
            <button type="button" class="tab-btn" onclick="switchTab('tab-ai', this)">Hỏi Đáp & Hướng Dẫn Sử Dụng</button>
            <button type="button" class="tab-btn" onclick="switchTab('tab-history', this)">Lịch Sử Mua Hàng (<?= count($invoices) ?>)</button>
        </div>

        <!-- TAB 1: TRA CỨU DANH MỤC THUỐC -->
        <div id="tab-drugs" class="tab-panel active">
            <div class="panel-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Tra cứu thuốc tại nhà thuốc</h2>
                        <p class="card-subtitle">Xem thông tin hoạt chất, quy cách đóng gói và giá bán niêm yết</p>
                    </div>
                </div>

                <form method="get" class="search-bar">
                    <input type="text" name="q" value="<?= pms_h($q) ?>" placeholder="Tìm kiếm theo tên thuốc, hoạt chất (Paracetamol, Amoxicillin...)" class="search-input">
                    <select name="category" class="search-select">
                        <option value="">-- Tất cả nhóm thuốc --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= pms_h($cat['category']) ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>>
                                <?= pms_h($cat['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-pill btn-primary">Tìm kiếm</button>
                    <?php if ($q || $category): ?>
                        <a href="customer_portal.php" class="btn-pill btn-outline">Đặt lại</a>
                    <?php endif; ?>
                </form>

                <?php if (!$drugs): ?>
                    <div style="text-align: center; padding: 40px; color: var(--slate-500); font-size: 14px;">
                        Không tìm thấy mặt hàng thuốc nào phù hợp với từ khóa "<strong><?= pms_h($q) ?></strong>".
                    </div>
                <?php else: ?>
                    <div class="drug-grid">
                        <?php foreach ($drugs as $d): ?>
                            <div class="drug-card">
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                        <div class="drug-name"><?= pms_h($d['name']) ?></div>
                                        <span class="drug-stock-tag <?= $d['in_stock'] > 0 ? 'stock-avail' : 'stock-out' ?>">
                                            <?= $d['in_stock'] > 0 ? 'Còn hàng' : 'Tạm hết' ?>
                                        </span>
                                    </div>
                                    <div class="drug-act"><?= pms_h($d['active_ingredient'] ?: 'Đang cập nhật') ?></div>
                                    <div class="drug-meta">
                                        Nhóm: <?= pms_h($d['category'] ?: 'Khác') ?><br>
                                        Đơn vị: <?= pms_h($d['unit']) ?> (<?= pms_h($d['retail_unit']) ?>)<br>
                                        Hãng SX: <?= pms_h($d['company'] ?: 'Nội địa') ?>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--slate-200); padding-top: 12px; margin-top: 12px;">
                                    <div class="drug-price"><?= number_format((float)$d['sale_price']) ?> đ</div>
                                    <button type="button" class="btn-pill btn-outline" style="padding: 5px 12px; font-size: 12px;" onclick="askAboutDrug('<?= pms_h($d['name']) ?>', '<?= pms_h($d['active_ingredient']) ?>')">
                                        Hỏi cách dùng
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 2: HỎI ĐÁP & HƯỚNG DẪN DÙNG THUỐC -->
        <div id="tab-ai" class="tab-panel">
            <div class="panel-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Hỏi đáp & Hướng dẫn sử dụng thuốc an toàn</h2>
                        <p class="card-subtitle">Tra cứu thời điểm uống (trước/sau ăn), lưu ý kiêng khem và quyền lợi tích điểm</p>
                    </div>
                </div>

                <div class="disclaimer-alert" style="margin-bottom: 20px;">
                    <strong>Lưu ý chuyên môn:</strong> Thông tin hướng dẫn nhằm mục đích hỗ trợ tham khảo cách dùng thuốc an toàn và kiến thức chăm sóc sức khỏe. Khách hàng bắt buộc phải tuân theo chỉ định của Bác sĩ điều trị. Khi có dấu hiệu dị ứng bất thường, hãy ngưng thuốc và đến cơ sở y tế gần nhất.
                </div>

                <div class="chat-container">
                    <div class="chat-input-box">
                        <textarea id="aiCustomerInput" rows="3" placeholder="Nhập tên thuốc hoặc câu hỏi (Ví dụ: Tôi mua Amoxicillin 500mg và Paracetamol, nên uống lúc nào trước hay sau ăn?)..."></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <button type="button" class="btn-pill btn-primary" onclick="sendCustomerQuestion('consult_customer')">Gửi câu hỏi tra cứu</button>
                        <button type="button" class="btn-pill btn-outline" onclick="sendCustomerQuestion('explain_policy')">Xem giải thích chính sách tích điểm</button>
                    </div>

                    <!-- Gợi ý câu hỏi nhanh -->
                    <div class="quick-ask-chips">
                        <span style="font-size: 12px; font-weight: 700; color: var(--slate-500); align-self: center;">Gợi ý câu hỏi:</span>
                        <button type="button" class="chip-btn" onclick="setQuickPrompt('Paracetamol 500mg uống cách nhau mấy tiếng và liều tối đa một ngày là bao nhiêu?')">Liều dùng Paracetamol?</button>
                        <button type="button" class="chip-btn" onclick="setQuickPrompt('Amoxicillin 500mg uống trước hay sau khi ăn và có cần uống hết đơn không?')">Cách uống kháng sinh Amoxicillin?</button>
                        <button type="button" class="chip-btn" onclick="setQuickPrompt('Quy tắc tích điểm và giảm giá của nhà thuốc áp dụng cho tài khẩu của tôi như thế nào?')">Chính sách điểm thưởng của tôi?</button>
                    </div>

                    <div id="aiLoadingBox" style="display: none; padding: 24px; text-align: center; color: var(--primary); font-size: 14px; font-weight: 600;">
                        Đang tra cứu cơ sở dữ liệu Dược thư và biên soạn hướng dẫn...
                    </div>

                    <div id="aiResponseBox" style="display: none;" class="response-box">
                        <div style="font-weight: 700; color: var(--slate-900); margin-bottom: 8px; border-bottom: 1px dashed var(--slate-200); padding-bottom: 8px;">
                            Nội dung hướng dẫn y tế:
                        </div>
                        <div id="aiResponseText"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: LỊCH SỬ MUA THUỐC -->
        <div id="tab-history" class="tab-panel">
            <div class="panel-card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Lịch sử hóa đơn mua hàng</h2>
                        <p class="card-subtitle">Danh sách các lần mua thuốc được ghi nhận gắn với số điện thoại của bạn</p>
                    </div>
                </div>

                <?php if (!$invoices): ?>
                    <div style="text-align: center; padding: 40px; color: var(--slate-500); font-size: 14px;">
                        Chưa có lịch sử giao dịch nào được lưu trữ cho tài khoản này.
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Mã hóa đơn</th>
                                    <th>Thời gian</th>
                                    <th>Danh sách thuốc đã mua</th>
                                    <th>Phương thức</th>
                                    <th style="text-align: right;">Tổng thanh toán</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: var(--primary);"><?= pms_h($inv['invoice_code'] ?: ('HD#' . $inv['invoice_no'])) ?></td>
                                        <td style="color: var(--slate-500); font-size: 13px;"><?= date('d/m/Y H:i', strtotime($inv['created_at'])) ?></td>
                                        <td style="line-height: 1.5;"><?= pms_h($inv['item_names'] ?: 'Chi tiết đơn thuốc') ?></td>
                                        <td>
                                            <span style="font-size: 12px; padding: 3px 8px; border-radius: 9999px; background: #f1f5f9; font-weight: 600;">
                                                <?= $inv['payment_type'] === 'cash' ? 'Tiền mặt' : ($inv['payment_type'] === 'transfer' ? 'Chuyển khoản' : 'Khác') ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right; font-weight: 800; color: var(--slate-900);">
                                            <?= number_format((float)$inv['grand_total']) ?> đ
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }

        function askAboutDrug(name, act) {
            switchTab('tab-ai', document.querySelectorAll('.tab-btn')[1]);
            document.getElementById('aiCustomerInput').value = 'Xin hướng dẫn tôi cách sử dụng an toàn thuốc ' + name + ' (hoạt chất ' + act + '), uống lúc nào và cần kiêng gì?';
            window.scrollTo({ top: 300, behavior: 'smooth' });
        }

        function setQuickPrompt(text) {
            document.getElementById('aiCustomerInput').value = text;
        }

        async function sendCustomerQuestion(action) {
            const input = document.getElementById('aiCustomerInput');
            const question = input.value.trim();
            if (action === 'consult_customer' && !question) {
                alert('Vui lòng nhập câu hỏi hoặc tên thuốc bạn cần tra cứu.');
                input.focus();
                return;
            }

            const loading = document.getElementById('aiLoadingBox');
            const respBox = document.getElementById('aiResponseBox');
            const respText = document.getElementById('aiResponseText');

            loading.style.display = 'block';
            respBox.style.display = 'none';

            try {
                const res = await fetch('api_ai_customer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: action,
                        customer_name: <?= json_encode($customer['customer_name']) ?>,
                        phone: <?= json_encode($customer['phone']) ?>,
                        points: <?= (int)$customer['loyalty_points'] ?>,
                        medicines: question,
                        question: question
                    })
                });
                const data = await res.json();
                loading.style.display = 'none';
                if (data.ok) {
                    respText.innerText = data.text;
                    respBox.style.display = 'block';
                } else {
                    alert(data.message || 'Lỗi khi kết nối hệ thống tra cứu.');
                }
            } catch (err) {
                loading.style.display = 'none';
                alert('Lỗi kết nối máy chủ: ' + err.message);
            }
        }
    </script>
</body>
</html>
