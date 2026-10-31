<?php
include_once 'app.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_customer'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$customer_name || !$phone || !$password) {
        $error = 'Vui lòng điền đầy đủ họ tên, số điện thoại và mật khẩu.';
    } elseif ($password !== $password_confirm) {
        $error = 'Mật khẩu xác nhận không trùng khớp.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải từ 6 ký tự trở lên.';
    } else {
        $db = pms_db();
        // Kiểm tra xem số điện thoại đã tồn tại chưa
        $existing = pms_fetch_one("SELECT customer_id, customer_name, password FROM customers WHERE phone = ? LIMIT 1", 's', [$phone]);
        
        if ($existing && !empty($existing['password'])) {
            $error = 'Số điện thoại này đã được đăng ký tài khoản khách hàng thành viên.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($existing) {
                // Cập nhật tài khoản cho khách hàng đã có trong danh sách tích điểm trước đó
                $stmt = mysqli_prepare($db, "UPDATE customers SET customer_name = ?, password = ?, email = ?, address = ? WHERE customer_id = ?");
                mysqli_stmt_bind_param($stmt, 'ssssi', $customer_name, $hash, $email, $address, $existing['customer_id']);
                mysqli_stmt_execute($stmt);
            } else {
                // Thêm mới khách hàng với điểm thưởng ban đầu là 10 điểm chào mừng
                $stmt = mysqli_prepare($db, "INSERT INTO customers (customer_name, phone, password, email, address, loyalty_points, created_at) VALUES (?, ?, ?, ?, ?, 10, NOW())");
                mysqli_stmt_bind_param($stmt, 'sssss', $customer_name, $phone, $hash, $email, $address);
                mysqli_stmt_execute($stmt);
            }
            $success = 'Đăng ký tài khoản khách hàng thân thiết thành công! Bạn được tặng ngay 10 điểm thưởng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký thành viên nhà thuốc | Tra cứu điểm & Hướng dẫn sử dụng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .form-card {
            background: #ffffff;
            border-radius: 28px;
            width: 100%;
            max-width: 520px;
            padding: 40px;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
        }
        .header {
            text-align: center;
            margin-bottom: 28px;
        }
        .badge {
            display: inline-block;
            background: #ecfdf5;
            color: #059669;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 9999px;
            margin-bottom: 12px;
            border: 1px solid #a7f3d0;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .header p {
            font-size: 13.5px;
            color: #64748b;
        }
        .field {
            margin-bottom: 16px;
        }
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }
        .field input {
            width: 100%;
            padding: 12px 18px;
            border-radius: 9999px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
        }
        .field input:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.12);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: #0d9488;
            color: #ffffff;
            border: none;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: #0f766e;
        }
        .alert {
            padding: 14px 18px;
            border-radius: 16px;
            font-size: 13px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #64748b;
        }
        .footer a {
            color: #0d9488;
            text-decoration: none;
            font-weight: 700;
        }
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-card">
        <div class="header">
            <span class="badge">Khách hàng thành viên</span>
            <h1>Đăng ký tài khoản khách hàng</h1>
            <p>Tích điểm đổi quà, theo dõi lịch sử mua thuốc & nhận hướng dẫn sử dụng</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= pms_h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= pms_h($success) ?>
                <div style="margin-top: 10px;">
                    <a href="customer_portal.php" style="color: #15803d; font-weight: 700; text-decoration: underline;">&rarr; Đăng nhập cổng khách hàng ngay</a>
                </div>
            </div>
        <?php else: ?>

        <form method="post">
            <div class="field">
                <label>Họ và tên của bạn *</label>
                <input type="text" name="customer_name" required placeholder="Ví dụ: Nguyễn Văn An" value="<?= pms_h($_POST['customer_name'] ?? '') ?>">
            </div>

            <div class="grid-2">
                <div class="field">
                    <label>Số điện thoại * (dùng để đăng nhập)</label>
                    <input type="tel" name="phone" required placeholder="09xxxxxxxx" value="<?= pms_h($_POST['phone'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Email (nhận đơn thuốc)</label>
                    <input type="email" name="email" placeholder="an@gmail.com" value="<?= pms_h($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label>Địa chỉ nhận hàng</label>
                <input type="text" name="address" placeholder="Số nhà, đường, quận/huyện..." value="<?= pms_h($_POST['address'] ?? '') ?>">
            </div>

            <div class="grid-2">
                <div class="field">
                    <label>Mật khẩu *</label>
                    <input type="password" name="password" required placeholder="Tối thiểu 6 ký tự" autocomplete="new-password">
                </div>
                <div class="field">
                    <label>Xác nhận mật khẩu *</label>
                    <input type="password" name="password_confirm" required placeholder="Nhập lại mật khẩu" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" name="register_customer" class="btn-submit">Tạo tài khoản thành viên</button>
        </form>

        <?php endif; ?>

        <div class="footer">
            Đã có tài khoản khách hàng? <a href="customer_portal.php">Đăng nhập tại đây</a>
            <div style="margin-top: 8px;">
                <a href="index.php" style="color: #64748b; font-weight: 500; font-size: 12px;">&larr; Cổng đăng nhập nhân viên nhà thuốc</a>
            </div>
        </div>
    </div>
</body>
</html>
