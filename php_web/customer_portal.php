<?php
include_once 'app.php';

$message = '';
$customer = null;

// Xử lý đăng xuất khách hàng
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_name']);
    unset($_SESSION['customer_phone']);
    header('Location: customer_portal.php');
    exit;
}

// Xử lý đăng nhập khách hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_customer'])) {
    $phone = trim($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!$phone || !$password) {
        $message = 'Vui lòng nhập số điện thoại và mật khẩu.';
    } else {
        $cust = pms_fetch_one("SELECT * FROM customers WHERE phone = ? LIMIT 1", 's', [$phone]);
        if ($cust && !empty($cust['password']) && password_verify($password, $cust['password'])) {
            $_SESSION['customer_id'] = $cust['customer_id'];
            $_SESSION['customer_name'] = $cust['customer_name'];
            $_SESSION['customer_phone'] = $cust['phone'];
            header('Location: customer_portal.php');
            exit;
        } else {
            $message = 'Số điện thoại hoặc mật khẩu không chính xác.';
        }
    }
}

// Nếu đã đăng nhập, lấy dữ liệu khách hàng
if (isset($_SESSION['customer_id'])) {
    $customer = pms_fetch_one("SELECT * FROM customers WHERE customer_id = ? LIMIT 1", 'i', [$_SESSION['customer_id']]);
    // Lấy lịch sử hóa đơn mua hàng
    $invoices = pms_fetch_all("
        SELECT i.*, p.total_ammount 
        FROM invoice_header i 
        LEFT JOIN payment_details p ON p.invoice_no = i.invoice_no 
        WHERE i.customer_name = ? OR i.customer_name = ? 
        ORDER BY i.date DESC LIMIT 10
    ", 'ss', [$customer['customer_name'], $customer['phone']]);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cổng thông tin & Hỗ trợ sử dụng thuốc cho Khách hàng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand {
            font-size: 18px;
            font-weight: 800;
            color: #0f766e;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .container {
            max-width: 1000px;
            margin: 32px auto;
            padding: 0 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            padding: 28px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
            margin-bottom: 24px;
        }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .field input, .field textarea {
            width: 100%; padding: 12px 18px; border-radius: 9999px; border: 1px solid #cbd5e1;
            font-size: 14px; font-family: inherit; outline: none; transition: border-color 0.2s;
        }
        .field textarea { border-radius: 18px; }
        .field input:focus, .field textarea:focus { border-color: #0d9488; }
        .btn {
            display: inline-block; padding: 12px 24px; border-radius: 9999px; border: none;
            font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; text-align: center;
            transition: all 0.2s ease;
        }
        .btn-primary { background: #0d9488; color: #ffffff; }
        .btn-primary:hover { background: #0f766e; }
        .btn-secondary { background: #f1f5f9; color: #334155; }
        .btn-secondary:hover { background: #e2e8f0; }
        .badge {
            display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700;
        }
        .badge-vip { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-standard { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .stat-box {
            padding: 20px; border-radius: 20px; background: #f8fafc; border: 1px solid #e2e8f0;
        }
        .stat-val { font-size: 28px; font-weight: 800; color: #0f766e; }
        .stat-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-top: 4px; }
        .disclaimer-box {
            background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 12px;
            font-size: 13px; color: #92400e; line-height: 1.6; margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <header class="navbar">
        <a href="customer_portal.php" class="navbar-brand">
            <span>NHÀ THUỐC</span>
            <span style="font-size: 12px; background: #f0fdf4; color: #166534; padding: 3px 8px; border-radius: 9999px;">Cổng Thành Viên</span>
        </a>
        <div style="display: flex; gap: 12px; align-items: center;">
            <?php if ($customer): ?>
                <span style="font-size: 13.5px; font-weight: 600;">Xin chào, <?= pms_h($customer['customer_name']) ?></span>
                <a href="customer_portal.php?action=logout" class="btn btn-secondary" style="padding: 6px 16px; font-size: 12.5px;">Đăng xuất</a>
            <?php else: ?>
                <a href="index.php" class="btn btn-secondary" style="padding: 6px 16px; font-size: 12.5px;">Nhân viên đăng nhập</a>
                <a href="register_customer.php" class="btn btn-primary" style="padding: 6px 16px; font-size: 12.5px;">Đăng ký thành viên</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="container">
        <?php if (!$customer): ?>
            <!-- Form Đăng nhập Khách hàng -->
            <div style="max-width: 440px; margin: 40px auto;">
                <div class="card">
                    <div style="text-align: center; margin-bottom: 24px;">
                        <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 6px;">Đăng nhập khách hàng</h2>
                        <p style="font-size: 13.5px; color: #64748b;">Tra cứu điểm tích lũy & Hướng dẫn sử dụng thuốc</p>
                    </div>

                    <?php if ($message): ?>
                        <div style="background: #fef2f2; color: #991b1b; padding: 12px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 16px; border: 1px solid #fecaca;">
                            <?= pms_h($message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="field">
                            <label>Số điện thoại</label>
                            <input type="tel" name="phone" required placeholder="09xxxxxxxx" autocomplete="username">
                        </div>
                        <div class="field">
                            <label>Mật khẩu</label>
                            <input type="password" name="password" required placeholder="Nhập mật khẩu" autocomplete="current-password">
                        </div>
                        <button type="submit" name="login_customer" class="btn btn-primary" style="width: 100%;">Đăng nhập</button>
                    </form>

                    <div style="text-align: center; margin-top: 20px; font-size: 13px; color: #64748b;">
                        Chưa có tài khoản? <a href="register_customer.php" style="color: #0d9488; font-weight: 700; text-decoration: none;">Đăng ký thành viên mới</a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Dashboard Khách hàng sau khi đăng nhập -->
            <?php
            $pts = (int)$customer['loyalty_points'];
            $tier = $pts >= 100 ? 'VIP Kim Cương' : ($pts >= 50 ? 'Thành Viên Vàng' : ($pts >= 20 ? 'Thành Viên Bạc' : 'Thành Viên Mới'));
            $discount = $pts >= 100 ? '10%' : ($pts >= 50 ? '5%' : ($pts >= 20 ? '2%' : '0%'));
            $badgeClass = $pts >= 50 ? 'badge-vip' : 'badge-standard';
            ?>

            <div class="card" style="background: linear-gradient(to right, #f0fdfa, #ffffff); border-color: #ccfbf1;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <span class="badge <?= $badgeClass ?>"><?= $tier ?></span>
                        <h1 style="font-size: 24px; font-weight: 800; margin: 8px 0 4px;"><?= pms_h($customer['customer_name']) ?></h1>
                        <p style="font-size: 13.5px; color: #64748b; margin: 0;">SĐT: <?= pms_h($customer['phone']) ?> · Chiết khấu thành viên: <strong><?= $discount ?></strong></p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary" onclick="askAiPolicy()">Xem chi tiết ưu đãi tích điểm</button>
                    </div>
                </div>
            </div>

            <!-- Thống kê điểm -->
            <div class="grid-3" style="margin-bottom: 24px;">
                <div class="stat-box">
                    <div class="stat-val"><?= number_format($pts) ?></div>
                    <div class="stat-label">Điểm tích lũy hiện có</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 6px;">Tương đương <?= number_format($pts * 100) ?> đ giảm trừ</div>
                </div>
                <div class="stat-box">
                    <div class="stat-val"><?= $discount ?></div>
                    <div class="stat-label">Mức giảm giá trực tiếp</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 6px;">Áp dụng cho mọi hóa đơn tại quầy</div>
                </div>
                <div class="stat-box">
                    <div class="stat-val"><?= count($invoices) ?></div>
                    <div class="stat-label">Lần mua thuốc gần đây</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 6px;">Được ghi nhận trên hệ thống</div>
                </div>
            </div>

            <!-- Trợ lý Hướng dẫn sử dụng thuốc & Dược lý tham khảo -->
            <div class="card">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800;">Tra cứu Hướng dẫn sử dụng thuốc</h2>
                        <p style="font-size: 13px; color: #64748b;">Hệ thống hỗ trợ tra cứu cách uống, thời điểm dùng thuốc và các lưu ý kiêng khem</p>
                    </div>
                </div>

                <div class="disclaimer-box">
                    <strong>Lưu ý chuyên môn quan trọng:</strong> Nội dung hướng dẫn được sinh tự động nhằm mục đích hỗ trợ tham khảo cách dùng thuốc an toàn (thời điểm trước/sau ăn, lưu ý uống nhiều nước). Bệnh nhân cần tuân thủ tuyệt đối theo đơn thuốc của Bác sĩ chuyên khoa.
                </div>

                <div class="field">
                    <label>Nhập tên thuốc hoặc câu hỏi bạn cần hướng dẫn cách dùng:</label>
                    <textarea id="custQuestion" rows="3" placeholder="Ví dụ: Tôi mua Amoxicillin 500mg và Paracetamol, nên uống lúc nào trước hay sau ăn?"></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-primary" onclick="askCustAi()">Gửi câu hỏi tra cứu cách dùng</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('custQuestion').value=''; document.getElementById('aiAnsBox').style.display='none';">Xóa</button>
                </div>

                <div id="aiLoading" style="display: none; padding: 20px; text-align: center; font-size: 13.5px; color: #0f766e;">
                    Đang đối soát thông tin dược thư và hướng dẫn sử dụng an toàn...
                </div>

                <div id="aiAnsBox" style="display: none; margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 18px; border: 1px solid #e2e8f0;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 10px; color: #0f172a;">Hướng dẫn từ Dược thư nhà thuốc:</div>
                    <div id="aiAnsText" style="font-size: 13.5px; line-height: 1.7; color: #1e293b; white-space: pre-wrap;"></div>
                </div>
            </div>

            <script>
            async function askCustAi() {
                const q = document.getElementById('custQuestion').value.trim();
                if (!q) { alert('Vui lòng nhập tên thuốc hoặc câu hỏi của bạn.'); return; }

                const loading = document.getElementById('aiLoading');
                const box = document.getElementById('aiAnsBox');
                const text = document.getElementById('aiAnsText');

                loading.style.display = 'block';
                box.style.display = 'none';

                try {
                    const resp = await fetch('api_ai_customer.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'consult_customer',
                            customer_name: <?= json_encode($customer['customer_name']) ?>,
                            phone: <?= json_encode($customer['phone']) ?>,
                            points: <?= $pts ?>,
                            medicines: q,
                            question: q
                        })
                    });
                    const d = await resp.json();
                    loading.style.display = 'none';
                    if (d.ok) {
                        text.innerText = d.text;
                        box.style.display = 'block';
                    } else {
                        alert(d.message || 'Lỗi xử lý yêu cầu.');
                    }
                } catch (e) {
                    loading.style.display = 'none';
                    alert('Lỗi kết nối: ' + e.message);
                }
            }

            async function askAiPolicy() {
                const loading = document.getElementById('aiLoading');
                const box = document.getElementById('aiAnsBox');
                const text = document.getElementById('aiAnsText');

                loading.style.display = 'block';
                box.style.display = 'none';

                try {
                    const resp = await fetch('api_ai_customer.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'explain_policy',
                            customer_name: <?= json_encode($customer['customer_name']) ?>,
                            phone: <?= json_encode($customer['phone']) ?>,
                            points: <?= $pts ?>
                        })
                    });
                    const d = await resp.json();
                    loading.style.display = 'none';
                    if (d.ok) {
                        text.innerText = d.text;
                        box.style.display = 'block';
                    }
                } catch(e) {
                    loading.style.display = 'none';
                }
            }
            </script>
        <?php endif; ?>
    </main>
</body>
</html>
