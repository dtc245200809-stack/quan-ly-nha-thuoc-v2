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
    <title>Hệ thống quản lý nhà thuốc</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --blue-deep: #0d1b3e;
            --blue-mid: #1a3a6e;
            --blue-brand: #1d6fff;
            --blue-light: #4f8cff;
            --teal: #0dcfbb;
            --white: #ffffff;
            --gray-50: #f8fafd;
            --gray-100: #eef1f7;
            --gray-300: #c8d0e0;
            --gray-500: #7a8aab;
            --gray-700: #3d4f70;
            --red-light: #fff0f0;
            --red: #e53935;
            --radius: 14px;
            --shadow-card: 0 20px 60px rgba(0, 0, 0, .18), 0 4px 16px rgba(0, 0, 0, .10);
        }

        body {
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--blue-deep) 0%, var(--blue-mid) 60%, #1a4fa0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        body.is-ready .page-wrapper {
            opacity: 1;
        }

        body.is-ready .left-panel {
            animation: heroRise 1s cubic-bezier(.18, .89, .32, 1.18) both;
        }

        body.is-ready .brand-pill {
            animation: badgeFloat 1.15s cubic-bezier(.22, 1, .36, 1) both .12s;
        }

        body.is-ready .left-panel h1 {
            animation: titleReveal .95s cubic-bezier(.2, .95, .22, 1) both .2s;
        }

        body.is-ready .left-panel .subtitle {
            animation: copyReveal .9s ease both .34s;
        }

        body.is-ready .feature-item {
            animation: featureCascade .8s cubic-bezier(.2, .8, .2, 1) both;
        }

        body.is-ready .feature-item:nth-child(1) {
            animation-delay: .42s;
        }

        body.is-ready .feature-item:nth-child(2) {
            animation-delay: .5s;
        }

        body.is-ready .feature-item:nth-child(3) {
            animation-delay: .58s;
        }

        body.is-ready .feature-item:nth-child(4) {
            animation-delay: .66s;
        }

        body.is-ready .login-card {
            animation: dropCard 1.05s cubic-bezier(.16, 1, .3, 1) both .14s, cardIdleFloat 5.5s ease-in-out infinite 1.3s;
        }

        body.is-ready .card-header {
            animation: contentFade .72s ease both .52s;
        }

        body.is-ready .field:nth-of-type(1) {
            animation: inputCascade .62s cubic-bezier(.2, .8, .2, 1) both .62s;
        }

        body.is-ready .field:nth-of-type(2) {
            animation: inputCascade .62s cubic-bezier(.2, .8, .2, 1) both .72s;
        }

        body.is-ready .field:nth-of-type(3) {
            animation: inputCascade .62s cubic-bezier(.2, .8, .2, 1) both .82s;
        }

        body.is-ready .btn-login {
            animation: inputCascade .62s cubic-bezier(.2, .8, .2, 1) both .92s, shimmerPulse 2.8s ease-in-out infinite 1.8s;
        }

        body.is-ready .card-footer {
            animation: contentFade .72s ease both 1.02s;
        }

        /* Decorative blobs */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            opacity: .13;
            pointer-events: none;
        }

        body::before {
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, var(--teal), transparent 70%);
            top: -180px;
            left: -180px;
            animation: blobMoveA 16s ease-in-out infinite alternate;
        }

        body::after {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, var(--blue-brand), transparent 70%);
            bottom: -160px;
            right: -120px;
            animation: blobMoveB 18s ease-in-out infinite alternate;
        }

        .page-wrapper {
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 960px;
            gap: 0;
            position: relative;
            z-index: 1;
            opacity: 0;
            transition: opacity .3s ease;
        }

        /* ─── Left panel ─── */
        .left-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 40px 48px 0;
            color: var(--white);
        }

        .brand-pill {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, .10);
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 999px;
            padding: 8px 18px 8px 10px;
            margin-bottom: 32px;
            width: fit-content;
            backdrop-filter: blur(10px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, .12);
        }

        .brand-pill .rx-badge {
            position: relative;
            width: 38px;
            height: 38px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, var(--blue-brand), #22d3ee 62%, #34d399);
            box-shadow: 0 10px 22px rgba(29, 111, 255, .28);
            overflow: hidden;
            flex-shrink: 0;
        }

        .brand-pill .rx-badge::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 10px;
            border-radius: 999px;
            background: white;
            transform: rotate(-38deg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
        }

        .brand-pill .rx-badge::after {
            content: '';
            position: absolute;
            right: 8px;
            top: 9px;
            width: 10px;
            height: 3px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .95);
            box-shadow: 0 0 0 999px transparent;
        }

        .brand-pill .rx-badge span,
        .brand-pill .rx-badge i {
            display: none;
        }

        .brand-pill span {
            font-size: 13px;
            font-weight: 600;
            opacity: .95;
        }

        .brand-pill small {
            display: block;
            font-size: 11px;
            color: rgba(255, 255, 255, .66);
            margin-top: 2px;
        }

        .left-panel h1 {
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 16px;
            letter-spacing: -.5px;
        }

        .left-panel h1 em {
            font-style: normal;
            background: linear-gradient(90deg, var(--teal), #7dd3fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .left-panel .subtitle {
            font-size: 15px;
            line-height: 1.8;
            color: rgba(255, 255, 255, .75);
            margin-bottom: 28px;
            max-width: 430px;
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            max-width: 520px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13.5px;
            color: rgba(255, 255, 255, .88);
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 16px;
            padding: 14px;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, .08);
            position: relative;
            overflow: hidden;
        }

        .feature-item::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 0%, rgba(255, 255, 255, .16) 45%, transparent 100%);
            transform: translateX(-140%);
            opacity: 0;
        }

        .feature-item:hover::after {
            opacity: 1;
            animation: sweepCard 1s ease;
        }

        .feature-dot {
            width: 10px;
            height: 10px;
            background: linear-gradient(135deg, var(--teal), #7dd3fc);
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 4px;
            box-shadow: 0 0 0 6px rgba(13, 207, 187, .12);
        }

        .feature-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 14px;
        }

        /* ─── Login card ─── */
        .login-card {
            background: var(--white);
            border-radius: 20px;
            padding: 34px 36px 18px;
            width: 420px;
            flex-shrink: 0;
            box-shadow: var(--shadow-card);
            position: relative;
            overflow: hidden;
            transform-origin: top center;
            will-change: transform, opacity;
        }

        .login-card::after {
            content: '';
            position: absolute;
            inset: -40% 45% auto -20%;
            height: 180px;
            background: radial-gradient(circle, rgba(79, 140, 255, .16), transparent 70%);
            pointer-events: none;
            animation: ambientGlow 6s ease-in-out infinite;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 40px;
            right: 40px;
            height: 3px;
            background: linear-gradient(90deg, var(--blue-brand), var(--teal));
            border-radius: 0 0 4px 4px;
        }

        .card-header {
            margin-bottom: 22px;
        }

        .card-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--blue-deep);
            margin-bottom: 6px;
        }

        .card-header p {
            font-size: 13.5px;
            color: var(--gray-500);
            line-height: 1.55;
        }

        /* Error */
        .flash-error {
            background: var(--red-light);
            border: 1px solid #ffcdd2;
            color: var(--red);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .flash-error::before {
            content: '⚠';
            font-size: 15px;
        }

        /* Form fields */
        .field {
            margin-bottom: 14px;
            opacity: 0;
            transform: translateY(-28px) scale(.98);
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 7px;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--gray-300);
            border-radius: 10px;
            font-size: 14.5px;
            font-family: inherit;
            color: var(--blue-deep);
            background: var(--gray-50);
            transition: border-color .2s, box-shadow .2s, background .2s;
            outline: none;
            appearance: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--blue-brand);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(29, 111, 255, .12);
        }

        .field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237a8aab' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
            cursor: pointer;
        }

        /* Demo hint */
        .demo-hint {
            background: linear-gradient(135deg, #eff6ff, #f0fffe);
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 11px 14px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #1e40af;
        }

        .demo-hint strong {
            display: block;
            margin-bottom: 3px;
            font-size: 12px;
            color: #3b82f6;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--blue-brand) 0%, #1458e8 100%);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            border: none;
            border-radius: 11px;
            cursor: pointer;
            letter-spacing: .02em;
            margin-top: 4px;
            transition: opacity .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 4px 18px rgba(29, 111, 255, .38);
            position: relative;
            overflow: hidden;
            isolation: isolate;
            opacity: 0;
            transform: translateY(-26px);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 20%, rgba(255, 255, 255, .35) 45%, transparent 70%);
            transform: translateX(-130%);
            z-index: -1;
        }

        .btn-login:hover::before {
            animation: shimmerRun .95s ease;
        }

        .btn-login:hover {
            opacity: .92;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(29, 111, 255, .45);
        }

        .btn-login:active {
            transform: translateY(0);
            opacity: 1;
        }

        .card-footer {
            margin-top: 10px;
            text-align: center;
            font-size: 12.5px;
            color: var(--gray-500);
            opacity: 0;
        }

        .card-footer a {
            color: var(--blue-brand);
            text-decoration: none;
            font-weight: 500;
        }

        .card-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @keyframes heroRise {
            0% {
                opacity: 0;
                transform: translateY(40px) scale(.98);
                filter: blur(8px);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0);
            }
        }

        @keyframes titleReveal {
            0% {
                opacity: 0;
                transform: translateY(34px);
                letter-spacing: .06em;
            }

            100% {
                opacity: 1;
                transform: translateY(0);
                letter-spacing: -.5px;
            }
        }

        @keyframes copyReveal {
            0% {
                opacity: 0;
                transform: translateY(22px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes featureCascade {
            0% {
                opacity: 0;
                transform: translateY(38px) scale(.94);
            }

            65% {
                opacity: 1;
                transform: translateY(-6px) scale(1.01);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes dropCard {
            0% {
                opacity: 0;
                transform: translateY(-140px) scale(.9) rotateX(16deg);
                filter: blur(12px);
            }

            55% {
                opacity: 1;
                transform: translateY(12px) scale(1.015) rotateX(0deg);
                filter: blur(0);
            }

            78% {
                transform: translateY(-6px) scale(.998);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes inputCascade {
            0% {
                opacity: 0;
                transform: translateY(-26px) scale(.98);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes contentFade {
            0% {
                opacity: 0;
                transform: translateY(16px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes badgeFloat {
            0% {
                opacity: 0;
                transform: translateY(24px) scale(.92);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes blobMoveA {
            0% {
                transform: translate3d(0, 0, 0) scale(1);
            }

            100% {
                transform: translate3d(80px, 40px, 0) scale(1.08);
            }
        }

        @keyframes blobMoveB {
            0% {
                transform: translate3d(0, 0, 0) scale(1);
            }

            100% {
                transform: translate3d(-70px, -40px, 0) scale(1.1);
            }
        }

        @keyframes cardIdleFloat {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        @keyframes shimmerRun {
            0% {
                transform: translateX(-130%);
            }

            100% {
                transform: translateX(130%);
            }
        }

        @keyframes shimmerPulse {

            0%,
            100% {
                box-shadow: 0 8px 26px rgba(29, 111, 255, .34);
            }

            50% {
                box-shadow: 0 16px 34px rgba(29, 111, 255, .52);
            }
        }

        @keyframes ambientGlow {

            0%,
            100% {
                transform: translate3d(0, 0, 0) scale(1);
                opacity: .8;
            }

            50% {
                transform: translate3d(18px, 14px, 0) scale(1.12);
                opacity: 1;
            }
        }

        @keyframes sweepCard {
            0% {
                transform: translateX(-140%);
            }

            100% {
                transform: translateX(140%);
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }

            .page-wrapper,
            .field,
            .btn-login,
            .card-footer {
                opacity: 1 !important;
                transform: none !important;
            }
        }

        @media (max-width: 900px) {
            .page-wrapper {
                max-width: 420px;
            }

            .left-panel {
                display: none;
            }

            .login-card {
                width: 100%;
                max-width: 420px;
            }
        }

        @media (max-width: 560px) {
            body {
                padding: 16px;
            }

            .login-card {
                padding: 24px 20px 16px;
                border-radius: 18px;
            }
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <!-- Left branding panel -->
        <div class="left-panel">
            <div class="brand-pill">
                <div class="rx-badge"></div>
                <div><span>Hệ thống quản lý nhà thuốc</span><small>Quản lý tồn kho, bán hàng và hạn dùng</small></div>
            </div>
            <h1>Hệ thống<br><em>quản lý nhà thuốc</em></h1>
            <p class="subtitle">Hệ thống quản lý thuốc được thiết kế với giao diện trực quan và thân thiện, tập trung
                vào các chức năng cốt lõi như quản lý tồn kho theo lô, bán hàng tại quầy (POS), theo dõi hạn sử dụng,
                cảnh báo tồn kho và hiển thị báo cáo doanh thu một cách rõ ràng, hỗ trợ nhà thuốc vận hành hiệu quả và
                chính xác hơn.</p>
            <div class="feature-list">
                <div class="feature-item">
                    <div class="feature-dot"></div>
                    <div><strong>Kho & lô thuốc</strong>Theo dõi số lượng, mã lô, ngày nhập và hạn sử dụng theo từng
                        đợt.</div>
                </div>
                <div class="feature-item">
                    <div class="feature-dot"></div>
                    <div><strong>Bán hàng POS</strong>Tạo hóa đơn nhanh, ưu tiên xuất lô gần hết hạn trước theo FEFO.
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-dot"></div>
                    <div><strong>Cảnh báo thông minh</strong>Nhắc thuốc cận date, sắp hết hàng và lô cần xử lý gấp.
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-dot"></div>
                    <div><strong>Báo cáo trực quan</strong>Tổng hợp doanh thu, nhập - xuất - tồn và hiệu suất kinh
                        doanh.</div>
                </div>
            </div>
        </div>

        <!-- Login card -->
        <div class="login-card">
            <div class="card-header">
                <h2>Đăng nhập hệ thống</h2>
                <p>Hệ thống tự động nhận diện phân quyền từ tên đăng nhập.</p>
            </div>

            <?php if ($message): ?>
                <div class="flash-error"><?= pms_h($message) ?></div>
            <?php endif; ?>


            <form method="post">
                <div class="field">
                    <label for="username">Tên đăng nhập</label>
                    <input type="text" id="username" name="username" placeholder="Nhập tên đăng nhập…"
                        autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="password">Mật khẩu</label>
                    <input type="password" id="password" name="password" placeholder="Nhập mật khẩu…"
                        autocomplete="current-password" required>
                </div>
                <button type="submit" name="submit" class="btn-login">Đăng nhập</button>
            </form>

            <div class="card-footer">
                <a href="about/about.html">Giới thiệu</a>

            </div>
        </div>
    </div>
    <script>
        window.addEventListener('load', function () {
            document.body.classList.add('is-ready');
        });
    </script>
</body>

</html>