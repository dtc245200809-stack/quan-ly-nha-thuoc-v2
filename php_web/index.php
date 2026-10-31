<?php
include_once 'app.php';

$message = '';
if (isset($_POST['submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    
    if (!$username || !$password) {
        $message = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        $result = pms_login_user($username, $password);
        if ($result === 'locked') {
            $message = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên để được hỗ trợ.';
        } elseif ($result === false) {
            $message = 'Sai tên đăng nhập hoặc mật khẩu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập hệ thống | Quản lý nhà thuốc</title>
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
            --slate-700: #334155;
            --slate-500: #64748b;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
        }

        body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background: #f8fafc;
            color: var(--slate-900);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 960px;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 10px 40px -10px rgba(15, 23, 42, 0.08);
            border: 1px solid var(--slate-200);
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
        }

        /* Cột trái: Giới thiệu nghiệp vụ thanh lịch chuẩn Dược */
        .auth-intro {
            background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
            color: #ffffff;
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .intro-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.12);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.02em;
            margin-bottom: 24px;
            width: fit-content;
            backdrop-filter: blur(8px);
        }

        .intro-title {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 14px;
            letter-spacing: -0.02em;
        }

        .intro-desc {
            font-size: 14px;
            line-height: 1.7;
            color: #ccfbf1;
            margin-bottom: 32px;
        }

        .intro-features {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .intro-feat-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13.5px;
            color: #f0fdfa;
        }

        .intro-feat-item strong {
            display: block;
            color: #ffffff;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .intro-portal-box {
            margin-top: 32px;
            padding: 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 13px;
        }

        .intro-portal-box a {
            color: #5eead4;
            font-weight: 700;
            text-decoration: underline;
        }

        /* Cột phải: Form đăng nhập */
        .auth-form-panel {
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 24px;
        }

        .form-header h2 {
            font-size: 24px;
            font-weight: 800;
            color: var(--slate-900);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 13.5px;
            color: var(--slate-500);
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--slate-700);
            margin-bottom: 6px;
        }

        .field input {
            width: 100%;
            padding: 12px 18px;
            border-radius: 9999px;
            border: 1px solid var(--slate-200);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
            background: #ffffff;
        }

        .field input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.12);
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
        }

        .flash-error {
            padding: 12px 16px;
            border-radius: 14px;
            background: #fef2f2;
            color: #991b1b;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #fecaca;
            margin-bottom: 20px;
        }

        /* Hộp tài khoản thử nghiệm điền nhanh */
        .quick-accounts {
            margin-top: 24px;
            padding: 14px;
            background: var(--slate-100);
            border-radius: 18px;
            border: 1px solid var(--slate-200);
            font-size: 12px;
        }

        .quick-accounts-title {
            font-weight: 700;
            color: var(--slate-900);
            margin-bottom: 8px;
            font-size: 12px;
        }

        .quick-accounts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .quick-acc-btn {
            padding: 8px 10px;
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: 12px;
            cursor: pointer;
            text-align: left;
            font-size: 11.5px;
            transition: all 0.15s ease;
        }

        .quick-acc-btn:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .form-footer {
            margin-top: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 13px;
            color: var(--slate-500);
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 820px) {
            .auth-wrapper {
                grid-template-columns: 1fr;
                max-width: 460px;
            }
            .auth-intro {
                display: none;
            }
            .auth-form-panel {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <!-- Cột trái giới thiệu -->
        <div class="auth-intro">
            <div>
                <div class="intro-brand">
                    <span>HỆ THỐNG QUẢN LÝ DƯỢC PHẨM</span>
                </div>
                <h1 class="intro-title">Vận hành nhà thuốc chuyên nghiệp & chuẩn mực</h1>
                <p class="intro-desc">Quản lý kho dược chuẩn GPP/GSP, xuất kho thông minh theo hạn dùng (FEFO), hỗ trợ tra cứu dược lý và tích điểm thành viên.</p>

                <div class="intro-features">
                    <div class="intro-feat-item">
                        <div>
                            <strong>Quản lý lô & Hạn sử dụng (FEFO)</strong>
                            <span>Tự động ưu tiên xuất lô cận date, chặn xuất thuốc quá hạn.</span>
                        </div>
                    </div>
                    <div class="intro-feat-item">
                        <div>
                            <strong>Bán hàng POS & Mã hóa đơn</strong>
                            <span>Thanh toán nhanh chóng, tự động trừ tồn kho theo lô.</span>
                        </div>
                    </div>
                    <div class="intro-feat-item">
                        <div>
                            <strong>Chăm sóc khách hàng & Tích điểm</strong>
                            <span>Hỗ trợ tra cứu cách dùng thuốc và chính sách ưu đãi.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="intro-portal-box">
                Bạn là khách hàng mua thuốc? <br>
                Truy cập <a href="customer_portal.php">Cổng thông tin & Tích điểm Khách hàng</a>
            </div>
        </div>

        <!-- Cột phải form login -->
        <div class="auth-form-panel">
            <div class="form-header">
                <h2>Đăng nhập hệ thống</h2>
                <p>Tự động nhận diện phân quyền: Admin, Dược sĩ, Thu ngân, Quản lý & Khách hàng</p>
            </div>

            <?php if ($message): ?>
                <div class="flash-error"><?= pms_h($message) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="field">
                    <label for="username">Tên đăng nhập hoặc Số điện thoại</label>
                    <input type="text" id="username" name="username" placeholder="Tên đăng nhập hoặc SĐT khách hàng" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="password">Mật khẩu</label>
                    <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
                </div>
                <button type="submit" name="submit" class="btn-submit">Đăng nhập hệ thống</button>
            </form>

            <!-- Bảng tài khoản thử nghiệm -->
            <div class="quick-accounts">
                <div class="quick-accounts-title">Tài khoản đăng nhập hệ thống (Bấm để chọn nhanh):</div>
                <div class="quick-accounts-grid" style="grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));">
                    <button type="button" class="quick-acc-btn" onclick="fillAccount('admin', 'admin123')">
                        <strong style="color: #0f766e;">Admin:</strong><br><span style="color: #64748b;">admin / admin123</span>
                    </button>
                    <button type="button" class="quick-acc-btn" onclick="fillAccount('duocsi', 'duocsi123')">
                        <strong style="color: #0284c7;">Dược sĩ:</strong><br><span style="color: #64748b;">duocsi / duocsi123</span>
                    </button>
                    <button type="button" class="quick-acc-btn" onclick="fillAccount('phuong', 'thungan123')">
                        <strong style="color: #b45309;">Thu ngân:</strong><br><span style="color: #64748b;">phuong / thungan123</span>
                    </button>
                    <button type="button" class="quick-acc-btn" onclick="fillAccount('chien', 'quanly123')">
                        <strong style="color: #6d28d9;">Quản lý:</strong><br><span style="color: #64748b;">chien / quanly123</span>
                    </button>
                    <button type="button" class="quick-acc-btn" onclick="fillAccount('0911111111', 'khach123')">
                        <strong style="color: #059669;">Khách hàng:</strong><br><span style="color: #64748b;">0911111111 / khach123</span>
                    </button>
                </div>
            </div>

            <div class="form-footer">
                <a href="register_customer.php">Đăng ký khách hàng</a>
                <span style="color: #cbd5e1;">•</span>
                <a href="customer_portal.php">Cổng khách hàng</a>
            </div>
        </div>
    </div>

    <script>
        function fillAccount(u, p) {
            document.getElementById("username").value = u;
            document.getElementById("password").value = p;
        }
    </script>
</body>
</html>
