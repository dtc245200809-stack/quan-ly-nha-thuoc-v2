<?php
/**
 * ============================================================================
 *  SECURITY HELPER — Dùng chung cho toàn bộ hệ thống nhà thuốc
 * ============================================================================
 *
 *  1. Cấu hình session cookie an toàn (httponly, samesite)
 *  2. CSRF token: tạo, render, xác thực
 *  3. Validation helpers
 *
 *  Include file này TRƯỚC session_start().
 * ============================================================================
 */

// ── Cấu hình session cookie an toàn ──────────────────────────────────────────
// Chỉ set nếu session chưa active (tránh warning khi include nhiều lần)
if (session_status() === PHP_SESSION_NONE) {
    // PHP 7.3+ hỗ trợ SameSite qua session.cookie_samesite
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    // Nếu chạy HTTPS thì bật secure flag
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.use_strict_mode', '1');
}

// ── CSRF Token ───────────────────────────────────────────────────────────────

/**
 * Tạo hoặc lấy CSRF token hiện tại từ session.
 * Token sống xuyên suốt session, đổi lại khi session_regenerate_id.
 */
function pms_csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Render hidden input chứa CSRF token.
 * Sử dụng trong mọi form POST:
 *   <?= pms_csrf_field() ?>
 */
function pms_csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(pms_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Kiểm tra CSRF token từ POST data.
 * Gọi ở đầu mỗi POST handler.
 * Nếu token sai → log + flash + redirect.
 *
 * @param string $redirect URL redirect khi token sai (default: trang hiện tại)
 */
function pms_csrf_verify(string $redirect = ''): void {
    if ($redirect === '') {
        $redirect = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    }
    $submitted = $_POST['_csrf_token'] ?? '';
    $expected  = $_SESSION['_csrf_token'] ?? '';

    if ($expected === '' || !hash_equals($expected, (string)$submitted)) {
        // Ghi log nếu hàm pms_log tồn tại (file này có thể include trước app.php)
        if (function_exists('pms_log')) {
            pms_log('Bảo mật', 'CSRF token không hợp lệ — chặn request POST tới ' . ($_SERVER['REQUEST_URI'] ?? ''));
        }
        if (function_exists('pms_flash')) {
            pms_flash('Phiên làm việc hết hạn hoặc yêu cầu không hợp lệ. Vui lòng thử lại.', 'error');
        }
        header('Location: ' . $redirect);
        exit;
    }
}

// ── Validation Helpers ──────────────────────────────────────────────────────

/**
 * Kiểm tra giá trị có nằm trong danh sách cho phép (whitelist).
 */
function pms_validate_in(string $value, array $allowed): bool {
    return in_array($value, $allowed, true);
}

/**
 * Kiểm tra chuỗi chỉ chứa số, dấu cách, dấu + (phone format đơn giản).
 */
function pms_validate_phone(string $phone): bool {
    if ($phone === '') return true; // phone không bắt buộc
    return (bool)preg_match('/^[0-9\s\+\-\.]+$/', $phone);
}
