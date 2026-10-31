<?php
include_once 'app.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');
    $role = trim($_POST['role'] ?? 'pharmacist'); // pharmacist hoặc cashier

    if (!$username || !$password || !$first_name || !$last_name) {
        $error = 'Vui lòng điền đầy đủ các thông tin bắt buộc (*).';
    } elseif ($password !== $password_confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } else {
        $table = in_array($role, ['pharmacist', 'cashier']) ? $role : 'pharmacist';
        $db = pms_db();

        // Kiểm tra username đã tồn tại chưa
        $existing = pms_fetch_one("
            SELECT username FROM admin WHERE username = ?
            UNION SELECT username FROM manager WHERE username = ?
            UNION SELECT username FROM pharmacist WHERE username = ?
            UNION SELECT username FROM cashier WHERE username = ?
            LIMIT 1
        ", 'ssss', [$username, $username, $username, $username]);

        if ($existing) {
            $error = 'Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $staff_id = ($role === 'pharmacist' ? 'DS-' : 'TN-') . strtoupper(substr(uniqid(), -5));
            $now = date('Y-m-d H:i:s');

            $stmt = mysqli_prepare($db, "
                INSERT INTO {$table} (first_name, last_name, staff_id, postal_address, phone, email, username, password, is_locked, date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
            ");
            $address = 'Chi nhánh nhà thuốc';
            mysqli_stmt_bind_param($stmt, 'sssssssss', $first_name, $last_name, $staff_id, $address, $phone, $email, $username, $hash, $now);

            if (mysqli_stmt_execute($stmt)) {
                $success = 'Đăng ký tài khoản nhân viên thành công! Bạn có thể đăng nhập ngay.';
            } else {
                $error = 'Lỗi hệ thống: ' . mysqli_error($db);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản nhân viên | Quản lý nhà thuốc</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --blue-deep: #0d1b3e; --blue-mid: #1a3a6e; --blue-brand: #1d6fff;
            --teal: #0dcfbb; --gray-100: #eef1f7; --gray-500: #7a8aab;
        }
        body {
            min-height: 100vh; font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--blue-deep) 0%, var(--blue-mid) 60%, #1a4fa0 100%);
            display: flex; align-items: center; justify-content: center; padding: 24px;
        }
        .reg-card {
            background: #ffffff; border-radius: 24px; width: 100%; max-width: 520px;
            padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .reg-header { text-align: center; margin-bottom: 24px; }
        .reg-header h2 { font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        .reg-header p { font-size: 13.5px; color: var(--gray-500); }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 5px; }
        .field input, .field select {
            width: 100%; padding: 10px 14px; border-radius: 12px; border: 1px solid #cbd5e1;
            font-size: 13.5px; font-family: inherit; transition: all 0.2s;
        }
        .field input:focus, .field select:focus {
            border-color: #0284c7; outline: none; box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn-reg {
            width: 100%; padding: 12px; background: #0284c7; color: #ffffff; border: none;
            border-radius: 9999px; font-size: 14px; font-weight: 600; cursor: pointer;
            margin-top: 10px; transition: background 0.2s;
        }
        .btn-reg:hover { background: #0369a1; }
        .alert { padding: 12px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 16px; font-weight: 500; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .footer-link { text-align: center; margin-top: 18px; font-size: 13px; color: #64748b; }
        .footer-link a { color: #0284c7; text-decoration: none; font-weight: 600; }
        .footer-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="reg-card">
        <div class="reg-header">
            <h2>Đăng ký tài khoản nhân viên</h2>
            <p>Hệ thống quản lý nhà thuốc chuẩn GPP</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= pms_h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= pms_h($success) ?>
                <div style="margin-top:8px;">
                    <a href="index.php" style="color:#15803d;font-weight:700;text-decoration:underline;">&rarr; Bấm vào đây để đăng nhập</a>
                </div>
            </div>
        <?php else: ?>

        <form method="post">
            <div class="row-2">
                <div class="field">
                    <label>Họ đệm *</label>
                    <input type="text" name="first_name" required placeholder="Nguyễn Văn" value="<?= pms_h($_POST['first_name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Tên *</label>
                    <input type="text" name="last_name" required placeholder="An" value="<?= pms_h($_POST['last_name'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label>Vị trí chuyên môn *</label>
                <select name="role">
                    <option value="pharmacist" <?= (($_POST['role'] ?? '') === 'pharmacist') ? 'selected' : '' ?>>Dược sĩ tư vấn quầy</option>
                    <option value="cashier" <?= (($_POST['role'] ?? '') === 'cashier') ? 'selected' : '' ?>>Nhân viên thu ngân / Bán hàng</option>
                </select>
            </div>

            <div class="field">
                <label>Tên đăng nhập *</label>
                <input type="text" name="username" required placeholder="VD: an.nguyen" autocomplete="username" value="<?= pms_h($_POST['username'] ?? '') ?>">
            </div>

            <div class="row-2">
                <div class="field">
                    <label>Số điện thoại</label>
                    <input type="text" name="phone" placeholder="09xxxxxxxx" value="<?= pms_h($_POST['phone'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="an@nhathuoc.vn" value="<?= pms_h($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="row-2">
                <div class="field">
                    <label>Mật khẩu *</label>
                    <input type="password" name="password" required placeholder="Tối thiểu 6 ký tự" autocomplete="new-password">
                </div>
                <div class="field">
                    <label>Xác nhận mật khẩu *</label>
                    <input type="password" name="password_confirm" required placeholder="Nhập lại mật khẩu" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" name="register" class="btn-reg">Hoàn tất đăng ký</button>
        </form>

        <?php endif; ?>

        <div class="footer-link">
            Đã có tài khoản? <a href="index.php">Quay lại đăng nhập</a>
        </div>
    </div>
</body>
</html>
