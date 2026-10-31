<?php
include_once __DIR__ . '/security_helper.php';
session_start();
include_once __DIR__ . '/connect_db.php';

function pms_db(): mysqli { return $GLOBALS['___pms_db']; }
function pms_query(string $sql, string $types = '', array $params = []): mysqli_stmt {
    $stmt = mysqli_prepare(pms_db(), $sql);
    if (!$stmt) throw new Exception("Prepare failed: " . mysqli_error(pms_db()) . " SQL: $sql");
    if ($types !== '' && $params) {
        $bound = mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (!$bound) throw new Exception("Binding parameters failed: " . mysqli_stmt_error($stmt));
    }
    if (!mysqli_stmt_execute($stmt)) throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
    return $stmt;
}
function pms_fetch_all(string $sql, string $types = '', array $params = []): array {
    try {
        $stmt = pms_query($sql, $types, $params);
        $result = mysqli_stmt_get_result($stmt);
        $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}
function pms_fetch_one(string $sql, string $types = '', array $params = []): ?array {
    $rows = pms_fetch_all($sql, $types, $params); return $rows[0] ?? null;
}
function pms_exec(string $sql, string $types = '', array $params = []): bool {
    $stmt = pms_query($sql, $types, $params);
    $ok = mysqli_stmt_errno($stmt) === 0;
    mysqli_stmt_close($stmt);
    return $ok;
}
function pms_log(string $action, string $details = ''): void {
    $username = 'guest';
    if (isset($_SESSION['admin_id'])) $username = 'admin';
    elseif (isset($_SESSION['manager_id'])) $username = 'manager';
    elseif (isset($_SESSION['pharmacist_id'])) $username = 'pharmacist';
    elseif (isset($_SESSION['cashier_id'])) $username = 'cashier';
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    pms_exec("INSERT INTO system_logs (username, action, details, ip_address) VALUES (?, ?, ?, ?)", "ssss", [$username, $action, $details, $ip]);
}
function pms_last_id(): int { return (int)mysqli_insert_id(pms_db()); }
function pms_h($value = null): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function pms_redirect(string $url): never { header('Location: ' . $url); exit; }
function pms_flash(?string $message = null, string $type = 'success'): ?array {
    if ($message !== null) { $_SESSION['flash'] = ['message' => $message, 'type' => $type]; return null; }
    if (!isset($_SESSION['flash'])) return null; $flash = $_SESSION['flash']; unset($_SESSION['flash']); return $flash;
}
function pms_auth(string $roles): array {
    $map = ['admin' => 'admin_id', 'manager' => 'manager_id', 'cashier' => 'cashier_id', 'pharmacist' => 'pharmacist_id'];
    $allowed = array_map('trim', explode(',', $roles));
    
    // Gộp quyền pharmacist và cashier: Nếu yêu cầu 1 trong 2 thì cho phép cả 2
    if (in_array('pharmacist', $allowed) || in_array('cashier', $allowed) || in_array('pharmacist_cashier', $allowed)) {
        $allowed[] = 'pharmacist';
        $allowed[] = 'cashier';
    }
    
    foreach (array_unique($allowed) as $r) {
        if (isset($_SESSION[$map[$r] ?? ''])) return $_SESSION;
    }
    pms_flash("Bạn không có quyền truy cập chức năng này.", "error");
    pms_redirect('index.php');
}
function pms_current_role(): string {
    if (isset($_SESSION['admin_id'])) return 'admin';
    if (isset($_SESSION['manager_id'])) return 'manager';
    if (isset($_SESSION['pharmacist_id']) || isset($_SESSION['cashier_id'])) return 'pharmacist_cashier';
    return '';
}
function pms_is_manager_role(): bool { return in_array(pms_current_role(), ['admin', 'manager']); }
function pms_is_staff_role(): bool { return pms_current_role() === 'pharmacist_cashier'; }
function pms_role_name(): string {
    if (isset($_SESSION['admin_id'])) return 'Quản trị hệ thống';
    if (isset($_SESSION['manager_id'])) return 'Quản lý nhà thuốc';
    if (isset($_SESSION['cashier_id']) || isset($_SESSION['pharmacist_id'])) return 'Dược sĩ / Nhân viên bán hàng';
    return 'Khách';
}
function pms_nav_for(string $role): array {
    if ($role === 'cashier' || $role === 'pharmacist') $role = 'pharmacist_cashier';
    $common_nav = [
        ['group' => 'Bán hàng', 'items' => [
            ['href' => 'ban_hang.php', 'label' => 'Bán hàng POS', 'icon' => '🛒'],
            ['href' => 'invoice.php', 'label' => 'Hóa đơn bán hàng', 'icon' => '🧾'],
            ['href' => 'tra_hang.php', 'label' => 'Trả hàng', 'icon' => '↩️'],
            ['href' => 'combo.php', 'label' => 'Cắt liều / Combo', 'icon' => '🧩'],
        ]],
        ['group' => 'Kho & lô thuốc', 'items' => [
            ['href' => 'stock.php', 'label' => 'Quản lý kho & lô', 'icon' => '📦'],
            ['href' => 'canh_bao.php', 'label' => 'Cảnh báo hạn dùng & tồn', 'icon' => '⏰'],
        ]],
        ['group' => 'Danh mục', 'items' => [
            ['href' => 'catalog.php', 'label' => 'Danh mục thuốc', 'icon' => '💊'],
            ['href' => 'suppliers.php', 'label' => 'Nhà cung cấp', 'icon' => '🏭'],
            ['href' => 'khach_hang.php', 'label' => 'Khách hàng', 'icon' => '👥'],
        ]],
        ['group' => 'Báo cáo & thống kê', 'items' => [
            ['href' => 'bao_cao.php', 'label' => 'Doanh thu', 'icon' => '📊'],
            ['href' => 'xnt.php', 'label' => 'Xuất - nhập - tồn', 'icon' => '📈'],
        ]],
                ['group' => 'Trợ lý nghiệp vụ', 'items' => [
            ['href' => 'ai_assistant.php', 'label' => 'Tra cứu & Hỗ trợ nghiệp vụ', 'icon' => '📚'],
            ['href' => 'ai_config.php', 'label' => 'Cấu hình API Engine', 'icon' => '⚙️'],
        ]],
        ['group' => 'Hệ thống', 'items' => [
            ['href' => 'accounts.php', 'label' => 'Tài khoản & phân quyền', 'icon' => '🛡️'],
            ['href' => 'he_thong.php', 'label' => 'Sao lưu dữ liệu', 'icon' => '🗄️'],
            ['href' => 'restore.php', 'label' => 'Phục hồi dữ liệu', 'icon' => '🔄'],
            ['href' => 'system_logs.php', 'label' => 'Nhật ký hệ thống', 'icon' => '📋'],
        ]],
        ['group' => 'Cá nhân', 'items' => [
            ['href' => 'profile.php', 'label' => 'Hồ sơ', 'icon' => '👤'],
        ]],
    ];
    return match ($role) {
        'admin' => [
            ['group' => 'Tổng quan', 'items' => [['href' => 'admin.php', 'label' => 'Tổng quan quản trị', 'icon' => '🏠']]],
            ...$common_nav
        ],
        'manager' => [
            ['group' => 'Tổng quan', 'items' => [['href' => 'manager.php', 'label' => 'Tổng quan', 'icon' => '🏠']]],
            ...$common_nav
        ],
        'pharmacist_cashier' => [
            ['group' => 'Tổng quan', 'items' => [['href' => 'pharmacist.php', 'label' => 'Tổng quan', 'icon' => '🏠']]],
            ['group' => 'Nghiệp vụ & Bán hàng', 'items' => [
                ['href' => 'ban_hang.php', 'label' => 'Bán hàng POS', 'icon' => '🛒'],
                ['href' => 'invoice.php', 'label' => 'Quản lý hóa đơn', 'icon' => '🧾'],
                ['href' => 'tra_hang.php', 'label' => 'Trả hàng', 'icon' => '↩️'],
                ['href' => 'combo.php', 'label' => 'Combo / Cắt liều', 'icon' => '🧩'],
            ]],
            ['group' => 'Tra cứu & Tồn kho', 'items' => [
                ['href' => 'prescription.php', 'label' => 'Đơn thuốc', 'icon' => '📋'],
                ['href' => 'stock_pharmacist.php', 'label' => 'Tra cứu tồn kho', 'icon' => '📦'],
                ['href' => 'canh_bao.php', 'label' => 'Hạn sử dụng', 'icon' => '⏰'],
            ]],
            ['group' => 'Danh mục', 'items' => [
                ['href' => 'khach_hang.php', 'label' => 'Khách hàng', 'icon' => '👥'],
            ]],
        ],
        default => [],
    };
}
function pms_currency(float|int|string $value): string { return number_format((float)$value, 0, ',', '.') . ' đ'; }
function pms_datetime_label(): string { return date('d/m/Y'); }
function pms_page_context(string $role): array {
    if ($role === 'cashier' || $role === 'pharmacist') $role = 'pharmacist_cashier';
    return match ($role) {
        'admin' => ['eyebrow' => 'QUẢN TRỊ · PHÂN QUYỀN · SAO LƯU DỮ LIỆU', 'title' => 'Điều hành tài khoản và an toàn hệ thống'],
        'manager' => ['eyebrow' => 'TỒN KHO · POS · LÔ THUỐC · HẠN DÙNG', 'title' => 'Điều hành toàn bộ nhà thuốc trên một giao diện hiện đại'],
        'pharmacist_cashier' => ['eyebrow' => 'NGHIỆP VỤ · POS · TRA CỨU TỒN KHO', 'title' => 'Hỗ trợ nghiệp vụ tư vấn và bán hàng'],
        default => ['eyebrow' => 'HỆ THỐNG QUẢN LÝ THUỐC CHO NHÀ THUỐC', 'title' => 'Pharmacy Management System'],
    };
}
function pms_global_counts(): array {
    $alerts = pms_alert_data();
    return ['alerts' => count($alerts['low']) + count($alerts['expiring'])];
}
function pms_global_search(string $keyword): array {
    $keyword = trim($keyword); if ($keyword === '') return ['stock' => [], 'invoice' => [], 'customers' => []];
    $like = '%' . $keyword . '%';
    return [
        'stock' => pms_fetch_all("SELECT drug_name, active_ingredient, batch_no, quantity FROM stock WHERE drug_name LIKE ? OR active_ingredient LIKE ? OR barcode LIKE ? ORDER BY drug_name ASC LIMIT 6", 'sss', [$like,$like,$like]),
        'invoice' => pms_fetch_all("SELECT invoice_code, customer_name, grand_total, created_at FROM invoice_header WHERE invoice_code LIKE ? OR customer_name LIKE ? ORDER BY invoice_no DESC LIMIT 6", 'ss', [$like,$like]),
        'customers' => pms_fetch_all("SELECT customer_name, phone, loyalty_points FROM customers WHERE customer_name LIKE ? OR phone LIKE ? LIMIT 6", 'ss', [$like,$like]),
    ];
}

function pms_item_svg(string $href): string {
    $map = [
        'admin.php'              => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
        'manager.php'            => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
        'cashier.php'            => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
        'pharmacist.php'         => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
        'ban_hang.php'           => '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>',
        'invoice.php'            => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>',
        'stock.php'              => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
        'stock.php#lots'         => '<polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline>',
        'stock_pharmacist.php'   => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
        'canh_bao.php'           => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'canh_bao.php#low-stock' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
        'prescription.php'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>',
        'khach_hang.php'         => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'payment.php'            => '<rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line>',
        'bao_cao.php'            => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
        'combo.php'              => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><path d="M13 8h4"></path><path d="M13 12h4"></path>',
        'tra_hang.php'           => '<polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path>',
        'admin_manager.php'      => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'admin_pharmacist.php'   => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><path d="M13 8h4"></path><path d="M13 12h4"></path>',
        'admin_cashier.php'      => '<rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line>',
        'he_thong.php'           => '<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>',
        'stock.php#catalog'      => '<rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line>',
        'profile.php'            => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
    ];
    $d = $map[$href] ?? '<circle cx="12" cy="12" r="10"></circle>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">' . $d . '</svg>';
}
function pms_group_svg(string $group): string {
    $map = [
        'Tổng quan'             => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
        'Bán hàng'              => '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>',
        'Kho & thuốc'           => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
        'Kho & lô thuốc'        => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
        'Danh mục & đối tác'   => '<rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line>',
        'Danh mục'              => '<rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line>',
        'Tài chính & báo cáo'  => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
        'Báo cáo & thống kê'    => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
        'Quản trị'              => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
        'Hệ thống'              => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
        'Khách hàng'            => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'Nghiệp vụ & Bán hàng'  => '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>',
        'Tra cứu & Tồn kho'     => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
        'Nghiệp vụ dược'        => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><path d="M13 8h4"></path><path d="M13 12h4"></path>',
    ];
    $d = $map[$group] ?? '<circle cx="12" cy="12" r="10"></circle>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">' . $d . '</svg>';
}
function pms_render_header(string $title, string $role, string $active, array $stats = []): void {
    $nav = pms_nav_for($role); $user = $_SESSION['username'] ?? 'guest'; $flash = pms_flash(); $context = pms_page_context($role); $search = trim($_GET['global_q'] ?? ''); $global = pms_global_counts(); $searchData = $search !== '' ? pms_global_search($search) : ['stock'=>[], 'invoice'=>[], 'customers'=>[]];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= pms_h($title) ?> | Hệ thống quản lý thuốc</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style/modern.css?v=<?= filemtime(__DIR__ . '/style/modern.css') ?>">
    <link rel="stylesheet" href="style/dashboard-modern.css?v=<?= time() ?>">
    <link rel="stylesheet" href="style/premium-dashboard.css?v=<?= filemtime(__DIR__ . '/style/premium-dashboard.css') ?>">
</head>
<body>
<div class="ds-shell">
    <aside class="ds-sidebar" id="appSidebar">
        <!-- Brand -->
        <div class="ds-sidebar__brand">
            <div class="ds-sidebar__logo"></div>
            <div class="ds-sidebar__text">
                <div class="ds-sidebar__name">Nhà Thuốc</div>
            </div>
            <button type="button" class="sb-toggle-btn" aria-label="Thu gọn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="16" height="16"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
        <!-- Navigation -->
        <nav class="ds-sidebar__nav">
            <?php foreach ($nav as $section):
                $groupName = $section['group'];
                $items     = $section['items'];
                $groupId   = 'grp-' . substr(md5($groupName), 0, 8);
                // check if any child is active
                $groupHasActive = false;
                foreach ($items as $_it) {
                    $_bh = strtok($_it['href'], '#');
                    if ($active === $_bh || $active === $_it['href']) { $groupHasActive = true; break; }
                }
            ?>
            <?php if (count($items) === 1):
                $item     = $items[0];
                $href     = $item['href'];
                $baseHref = strtok($href, '#');
                $isActive = $active === $baseHref || $active === $href;
            ?>
                <div class="ds-sidebar__group"><?= pms_h($groupName) ?></div>
                <div class="nav-item">
                    <a class="ds-sidebar__link <?= $isActive ? 'active' : '' ?>" href="<?= pms_h($href) ?>">
                        <span class="ds-sidebar__icon"><?= pms_item_svg($href) ?></span>
                        <span class="nav-label"><?= pms_h($item['label']) ?></span>
                    </a>
                </div>
            <?php else: ?>
                <div class="ds-sidebar__group"><?= pms_h($groupName) ?></div>
                <div class="nav-item <?= $groupHasActive ? 'open' : '' ?>" id="<?= pms_h($groupId) ?>">
                    <div class="ds-sidebar__link" onclick="pmsToggle('<?= pms_h($groupId) ?>')" style="cursor:pointer">
                        <span class="ds-sidebar__icon"><?= pms_group_svg($groupName) ?></span>
                        <span class="nav-label"><?= pms_h($groupName) ?></span>
                        <svg class="nav-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left:auto"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                    <div class="sub-menu">
                        <?php foreach ($items as $item):
                            $href     = $item['href'];
                            $baseHref = strtok($href, '#');
                            $isActive = $active === $baseHref || $active === $href;
                        ?>
                        <a class="ds-sidebar__link <?= $isActive ? 'active' : '' ?>" href="<?= pms_h($href) ?>" style="padding-left:36px;font-size:13px;opacity:0.9">
                            <?= pms_h($item['label']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php endforeach; ?>

        </nav>
        <!-- Footer user -->
        <div class="ds-sidebar__footer">
            <div class="ds-sidebar__user">
                <a href="profile.php" class="ds-sidebar__avatar" style="text-decoration:none;">
                    <?php if (isset($_SESSION['avatar']) && $_SESSION['avatar']): ?>
                        <img src="avatar/<?= pms_h($_SESSION['avatar']) ?>" alt="Avatar" style="width:100%; height:100%; border-radius:inherit; object-fit:cover;">
                    <?php else: ?>
                        <?= strtoupper(substr($user, 0, 2)) ?>
                    <?php endif; ?>
                </a>
                <div class="ds-sidebar__user-info">
                    <div class="ds-sidebar__user-name"><?= pms_h($user) ?></div>
                    <div class="ds-sidebar__user-role">
                        <a href="profile.php" style="color:inherit; text-decoration:none;"><?= pms_h(pms_role_name()) ?> ⚙️</a>
                    </div>
                </div>
                <a class="ds-sidebar__logout" href="logout.php" title="Đăng xuất">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>
    </aside>
    <main class="ds-main">
        <header class="ds-topbar">
            <div class="topbar-left">
                <button type="button" class="mobile-menu-btn" onclick="document.body.classList.toggle('sidebar-open')" aria-label="Menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="18" height="18"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="ds-topbar__title">
                    <h1 style="margin:0;font-size:1.1rem;font-weight:700"><?= pms_h($title) ?></h1>
                </div>
            </div>
            <div class="topbar-center">
                <form class="global-search" method="get">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="global_q" value="<?= pms_h($search) ?>" placeholder="Tìm thuốc, hoạt chất, mã hóa đơn...">
                    <button type="submit">Tìm</button>
                </form>
            </div>
            <div class="ds-topbar__actions">
                <div class="ds-topbar__date">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?= pms_datetime_label() ?>
                </div>
            </div>
        </header>
        <?php if ($search !== ''): ?>
            <div class="page-body">
            <div class="ds-page-body">
            <section class="ds-panel">
                <div class="ds-panel__head"><h2>Kết quả tìm kiếm nhanh</h2><div class="ds-panel__subtitle">Tra cứu toàn cục đúng tinh thần thao tác nhanh trong nhà thuốc.</div></div>
                <div class="ds-dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                    <article class="ds-info-card"><div class="ds-info-card__title">Thuốc / lô thuốc</div><?php if ($searchData['stock']): ?><div class="ds-info-list"><?php foreach ($searchData['stock'] as $row): ?><div class="ds-list-item"><div><strong><?= pms_h($row['drug_name']) ?></strong><br><span class="ds-muted"><?= pms_h($row['active_ingredient']) ?> · Lô <?= pms_h($row['batch_no']) ?></span></div><span class="ds-badge ds-badge--info">Tồn <?= (int)$row['quantity'] ?></span></div><?php endforeach; ?></div><?php else: ?><div class="ds-empty ds-empty--compact">Không có thuốc phù hợp.</div><?php endif; ?></article>
                    <article class="ds-info-card"><div class="ds-info-card__title">Hóa đơn</div><?php if ($searchData['invoice']): ?><div class="ds-info-list"><?php foreach ($searchData['invoice'] as $row): ?><div class="ds-list-item"><div><strong><?= pms_h($row['invoice_code']) ?></strong><br><span class="ds-muted"><?= pms_h($row['customer_name']) ?> · <?= pms_h($row['created_at']) ?></span></div><span class="ds-badge ds-badge--ok"><?= pms_currency((float)$row['grand_total']) ?></span></div><?php endforeach; ?></div><?php else: ?><div class="ds-empty ds-empty--compact">Không có hóa đơn phù hợp.</div><?php endif; ?></article>
                    <article class="ds-info-card"><div class="ds-info-card__title">Khách hàng</div><?php if ($searchData['customers']): ?><div class="ds-info-list"><?php foreach ($searchData['customers'] as $row): ?><div class="ds-list-item"><div><strong><?= pms_h($row['customer_name']) ?></strong><br><span class="ds-muted">SĐT: <?= pms_h($row['phone']) ?></span></div><span class="ds-badge ds-badge--info"><?= (int)$row['loyalty_points'] ?> điểm</span></div><?php endforeach; ?></div><?php else: ?><div class="ds-empty ds-empty--compact">Không có khách hàng phù hợp.</div><?php endif; ?></article>
                </div>
            </section>
            </div>
        <?php endif; ?>
        <?php if ($stats): ?>
            <div class="ds-stats-row">
                <?php $i=0; foreach ($stats as $label => $value): $i++; ?>
                    <article class="ds-stat-card">
                        <div class="ds-stat-icon <?= $i===2?'manager':($i===3?'cashier':'') ?>">
                            <?php if($i===1): ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <?php elseif($i===2): ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20v-6M6 20V10M18 20V4"></path></svg>
                            <?php else: ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                            <?php endif; ?>
                        </div>
                        <span><?= pms_h($label) ?></span>
                        <strong><?= pms_h((string)$value) ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="ds-page-body">
        <?php if ($flash): ?>
            <div class="ds-alert <?= pms_h($flash['type']) ?>" id="pmsFlash">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span><?= pms_h($flash['message']) ?></span>
                    <button type="button" onclick="document.getElementById('pmsFlash').remove();" style="background:none; border:none; color:inherit; cursor:pointer; padding:0 4px; font-size:16px; opacity:0.6; line-height:1;">&times;</button>
                </div>
            </div>
            <script>setTimeout(function(){ var el = document.getElementById('pmsFlash'); if(el) el.style.opacity='0', el.style.transition='opacity 0.6s', setTimeout(function(){el.remove()}, 600); }, 5000);</script>
        <?php endif; ?>
<?php }
function pms_render_footer(): void {
    echo <<<'HTML'
<script>
(function(){
  /* ── Accordion toggle ── */
  function pmsToggle(id){
    var el=document.getElementById(id);
    var wasOpen=el.classList.contains('open');
    document.querySelectorAll('.nav-item.open').forEach(function(e){e.classList.remove('open');});
    if(!wasOpen) el.classList.add('open');
  }
  window.pmsToggle=pmsToggle;

  /* ── Sidebar collapse (desktop) – pure CSS class, no inline styles ── */
  var colBtn=document.querySelector('.sb-toggle-btn');
  if(colBtn){
    // Restore persisted state
    if(localStorage.getItem('pms_sb_collapsed')==='1'){
      document.body.classList.add('sidebar-collapsed');
    }
    colBtn.addEventListener('click',function(){
      var isNowCollapsed=document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('pms_sb_collapsed', isNowCollapsed?'1':'0');
    });
  }

  /* ── Mobile overlay close ── */
  document.addEventListener('click',function(e){
    if(window.innerWidth>1180) return;
    var mb=e.target.closest('.mobile-menu-btn');
    var sb=e.target.closest('.sidebar');
    if(!mb && !sb) document.body.classList.remove('sidebar-open');
  });

  /* ── Keyboard shortcut: [ to toggle sidebar ── */
  document.addEventListener('keydown',function(e){
    if((e.key==='[') && !e.ctrlKey && !e.metaKey && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)){
      if(colBtn) colBtn.click();
    }
  });
})();
</script>
</div></main></div></body></html>
HTML;
}
function pms_password_matches(string $plain, string $stored): bool { return password_verify($plain, $stored) || $plain === $stored; }
function pms_login_user(string $username, string $password): bool|string {
    $config = ['Admin' => ['table' => 'admin', 'id' => 'admin_id', 'redirect' => 'admin.php'], 'Pharmacist' => ['table' => 'pharmacist', 'id' => 'pharmacist_id', 'redirect' => 'pharmacist.php'], 'Cashier' => ['table' => 'cashier', 'id' => 'cashier_id', 'redirect' => 'pharmacist.php'], 'Manager' => ['table' => 'manager', 'id' => 'manager_id', 'redirect' => 'manager.php']];
    foreach ($config as $position => $cfg) {
        $row = pms_fetch_one("SELECT * FROM {$cfg['table']} WHERE username = ? LIMIT 1", 's', [$username]);
        if ($row && pms_password_matches($password, (string)$row['password'])) {
            if ((int)($row['is_locked'] ?? 0) === 1) return 'locked';
            session_regenerate_id(true);
            $_SESSION = []; $_SESSION[$cfg['id']] = $row[$cfg['id']]; $_SESSION['username'] = $row['username'];
            foreach (['first_name','last_name','staff_id','avatar','notes'] as $field) if (isset($row[$field])) $_SESSION[$field] = $row[$field];
            pms_log('Đăng nhập', "Đăng nhập hệ thống phân hệ $position");
            pms_redirect($cfg['redirect']);
        }
    }
    return false;
}
function pms_stock_stats(): array {
    $row = pms_fetch_one("SELECT COUNT(*) total_items, COALESCE(SUM(quantity),0) total_units, SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 ELSE 0 END) expiring_soon FROM stock");
    return ['Số lô đang quản lý' => $row['total_items'] ?? 0, 'Tổng số lượng tồn' => number_format((int)($row['total_units'] ?? 0)), 'Lô cần ưu tiên 60 ngày' => $row['expiring_soon'] ?? 0];
}
function pms_days_until(?string $date): ?int { if (!$date) return null; $ts = strtotime($date); if (!$ts) return null; return (int)floor(($ts - strtotime(date('Y-m-d'))) / 86400); }
function pms_low_stock_threshold(): int { return 20; }
function pms_sale_candidates(string $drugName): array {
    return pms_fetch_all("SELECT * FROM stock WHERE drug_name = ? AND quantity > 0 AND (expiry_date IS NULL OR expiry_date > CURDATE()) ORDER BY expiry_date IS NULL, expiry_date ASC, stock_id ASC", 's', [$drugName]);
}

/**
 * FEFO candidates VỚI row-level lock (SELECT ... FOR UPDATE).
 * CHỈ được gọi bên trong một transaction đang mở.
 * Lock theo thứ tự stock_id ASC để tránh deadlock.
 */
function pms_sale_candidates_for_update(int $drugId): array {
    return pms_fetch_all(
        "SELECT * FROM stock
         WHERE drug_id = ? AND quantity > 0
           AND (expiry_date IS NULL OR expiry_date >= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
         ORDER BY expiry_date IS NULL, expiry_date ASC, stock_id ASC
         FOR UPDATE",
        'i', [$drugId]
    );
}

function pms_drug_options(): array {
    return pms_fetch_all("
        SELECT 
            d.id, d.drug_code, d.name AS drug_name, d.active_ingredient, d.unit, d.sale_price,
            COALESCE(SUM(s.quantity), 0) AS total_quantity,
            MIN(s.expiry_date) AS nearest_expiry,
            (SELECT COALESCE(SUM(quantity), 0) FROM invoice_item ii WHERE ii.drug_id = d.id) as total_sold
        FROM drugs d
        LEFT JOIN stock s ON d.id = s.drug_id
        WHERE d.is_deleted = 0
        GROUP BY d.id, d.drug_code, d.name, d.active_ingredient, d.unit, d.sale_price
        ORDER BY d.name ASC
    ");
}
function pms_sale_preview(?string $drugName, int $quantity = 1): array {
    if (!$drugName || $quantity <= 0) return []; $candidates = pms_sale_candidates($drugName); $remaining = $quantity; $preview = [];
    foreach ($candidates as $row) { if ($remaining <= 0) break; $take = min($remaining, (int)$row['quantity']); if ($take <= 0) continue; $preview[] = ['stock_id'=>(int)$row['stock_id'],'batch_no' => $row['batch_no'], 'expiry_date' => $row['expiry_date'], 'take' => $take, 'quantity' => $row['quantity'], 'sale_price'=>(float)$row['sale_price']]; $remaining -= $take; }
    return $preview;
}
function pms_next_invoice_code(): string {
    $row = pms_fetch_one("SELECT invoice_no FROM invoice_header ORDER BY invoice_no DESC LIMIT 1");
    $next = ((int)($row['invoice_no'] ?? 0)) + 1; return 'HD-' . date('Ymd') . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}
function pms_customer_options(): array { return pms_fetch_all("SELECT * FROM customers ORDER BY customer_name ASC"); }
function pms_customer_by_id(int $id): ?array { return pms_fetch_one("SELECT * FROM customers WHERE customer_id=?", 'i', [$id]); }
function pms_customer_by_name(string $name): ?array { return pms_fetch_one("SELECT * FROM customers WHERE customer_name=?", 's', [$name]); }
function pms_upsert_customer(string $name, string $phone=''): int {
    $name = trim($name); if ($name === '' || $name === 'Khách lẻ') return 0;
    $row = pms_customer_by_name($name); if ($row) return (int)$row['customer_id'];
    pms_exec("INSERT INTO customers (customer_name, phone, loyalty_points) VALUES (?,?,0)", 'ss', [$name, $phone]);
    return pms_last_id();
}
function pms_combo_options(): array { return pms_fetch_all("SELECT combo_id, combo_name, sale_price, target_days, note FROM combos ORDER BY combo_name ASC"); }
function pms_combo_detail(int $comboId): array {
    return pms_fetch_all("SELECT ci.*, s.drug_id, s.drug_name, s.active_ingredient, s.sale_price, MAX(s.is_prescription) is_prescription FROM combo_items ci JOIN stock s ON s.stock_id = ci.stock_id WHERE ci.combo_id=? GROUP BY ci.combo_item_id, ci.combo_id, ci.stock_id, ci.quantity, ci.unit_note, s.drug_id, s.drug_name, s.active_ingredient, s.sale_price ORDER BY ci.combo_item_id ASC", 'i', [$comboId]);
}
function pms_sale_total(array $items): float { $t = 0; foreach ($items as $i) $t += (float)$i['line_total']; return $t; }
function pms_build_combo_preview(int $comboId): array {
    $rows = pms_combo_detail($comboId); $preview = []; foreach ($rows as $row) { $alloc = pms_sale_preview($row['drug_name'], (int)$row['quantity']); $preview[] = ['drug_name'=>$row['drug_name'],'qty'=>(int)$row['quantity'],'alloc'=>$alloc,'sale_price'=>(float)$row['sale_price'],'line_total'=>(float)$row['sale_price']*(int)$row['quantity'],'is_prescription'=>(int)$row['is_prescription']]; } return $preview;
}

/* =========================================================================
 * BÁN HÀNG ĐƠN LẺ (POS) - CÓ CHỐNG ÂM KHO & RACE CONDITION
 * =========================================================================
 * Chiến lược:
 *   1. Kiểm tra thuốc kê đơn TRƯỚC transaction (chỉ đọc metadata, không ảnh hưởng)
 *   2. BEGIN TRANSACTION
 *   3. SELECT ... FOR UPDATE → khóa tất cả lô FEFO của thuốc cần bán
 *   4. Kiểm tra tổng tồn >= số lượng cần bán (sau khi đã lock)
 *   5. UPDATE stock SET quantity = quantity - ? (trừ tương đối, không set tuyệt đối)
 *   6. Sau mỗi UPDATE → SELECT lại quantity, nếu < 0 → ROLLBACK ngay
 *   7. Insert invoice, batch_usage, payment, loyalty
 *   8. COMMIT nếu tất cả thành công
 *   9. Nếu deadlock → retry tối đa 3 lần
 * ========================================================================= */
function pms_create_sale(string $customer, string $drugName, int $quantity, float $tax, string $paymentType, float $discount = 0, int $customerId = 0, int $usePoints = 0, bool $rxConfirmed = false): array {

    // ── Bước 0: Kiểm tra thuốc kê đơn (chỉ đọc metadata, không cần lock) ──
    $option = pms_fetch_one("SELECT MAX(is_prescription) is_prescription FROM stock WHERE drug_name=?", 's', [$drugName]);
    if ((int)($option['is_prescription'] ?? 0) === 1 && !$rxConfirmed) {
        return ['ok' => false, 'message' => 'Thuốc kê đơn: vui lòng xác nhận đã kiểm tra đơn bác sĩ trước khi thanh toán.'];
    }

    // ── Deadlock retry: tối đa 3 lần ──
    $maxRetries = 3;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            return pms_create_sale_inner($customer, $drugName, $quantity, $tax, $paymentType, $discount, $customerId, $usePoints);
        } catch (Throwable $e) {
            // Kiểm tra nếu là deadlock (MySQL error 1213)
            $isDeadlock = (str_contains($e->getMessage(), '1213') || str_contains(strtolower($e->getMessage()), 'deadlock'));
            if ($isDeadlock && $attempt < $maxRetries) {
                // Chờ ngẫu nhiên 50-200ms rồi retry
                usleep(random_int(50000, 200000));
                continue;
            }
            // Không phải deadlock hoặc hết số lần retry → trả lỗi
            return ['ok' => false, 'message' => 'Không thể hoàn tất giao dịch (lần thử ' . $attempt . '): ' . $e->getMessage()];
        }
    }
    return ['ok' => false, 'message' => 'Giao dịch thất bại sau ' . $maxRetries . ' lần thử. Vui lòng thử lại.'];
}

/**
 * Logic bán hàng thực sự, được tách ra để hỗ trợ deadlock retry.
 * Hàm này sẽ throw exception nếu có lỗi → caller sẽ bắt và retry nếu cần.
 */
function pms_create_sale_inner(string $customer, string $drugName, int $quantity, float $tax, string $paymentType, float $discount, int $customerId, int $usePoints): array {
    $db = pms_db();
    mysqli_begin_transaction($db);
    try {
        // ── Bước 1: Lock tất cả lô FEFO bằng SELECT ... FOR UPDATE ──
        // Thứ tự lock: expiry_date ASC, stock_id ASC → nhất quán, tránh deadlock
        $candidates = pms_sale_candidates_for_update($drugName);

        // ── Bước 2: Kiểm tra tổng tồn TRONG transaction (sau khi đã lock) ──
        if (!$candidates) {
            mysqli_rollback($db);
            return ['ok' => false, 'message' => 'Thuốc "' . $drugName . '" không có sẵn hoặc tất cả các lô đã hết hạn.'];
        }
        $available = array_sum(array_map(fn($r) => (int)$r['quantity'], $candidates));
        if ($available < $quantity) {
            mysqli_rollback($db);
            return ['ok' => false, 'message' => 'Không đủ tồn kho cho "' . $drugName . '". Cần: ' . $quantity . ', Còn: ' . $available . '.'];
        }

        // ── Bước 3: Trừ kho FEFO - lô gần hết hạn trước ──
        $remaining = $quantity;
        $subtotal = 0.0;
        $warnExpiring = false;
        $firstStockId = (int)$candidates[0]['stock_id'];
        $unitPrice = (float)$candidates[0]['sale_price'];
        $usageRows = [];

        foreach ($candidates as $row) {
            if ($remaining <= 0) break;
            $take = min($remaining, (int)$row['quantity']);
            if ($take <= 0) continue;

            // UPDATE tương đối: quantity = quantity - take (không set giá trị tuyệt đối)
            pms_exec(
                "UPDATE stock SET quantity = quantity - ? WHERE stock_id = ?",
                'ii', [$take, (int)$row['stock_id']]
            );

            // ── BẮT BUỘC: Kiểm tra lại quantity sau UPDATE ──
            // Nếu quantity < 0 → có race condition hoặc lỗi logic → ROLLBACK ngay
            $verify = pms_fetch_one(
                "SELECT quantity FROM stock WHERE stock_id = ?",
                'i', [(int)$row['stock_id']]
            );
            if ($verify === null || (int)$verify['quantity'] < 0) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => 'Lỗi tồn kho: lô ' . $row['batch_no'] . ' bị âm sau khi trừ. Giao dịch đã hủy, vui lòng thử lại.'];
            }

            $subtotal += $take * (float)$row['sale_price'];
            $usageRows[] = [
                'stock_id' => (int)$row['stock_id'],
                'batch_no' => $row['batch_no'],
                'qty'      => $take
            ];
            $days = pms_days_until($row['expiry_date'] ?? null);
            if ($days !== null && $days <= 60) $warnExpiring = true;
            $remaining -= $take;
        }

        // An toàn: nếu vẫn còn remaining > 0 (lý do: quantity thay đổi giữa check và loop)
        if ($remaining > 0) {
            mysqli_rollback($db);
            return ['ok' => false, 'message' => 'Không đủ hàng sau khi phân bổ FEFO. Thiếu ' . $remaining . ' đơn vị. Vui lòng thử lại.'];
        }

        // ── Bước 4: Tạo hóa đơn ──
        $discount = max(0.0, $discount + ($usePoints * 100));
        $grandTotal = max(0, $subtotal + $tax - $discount);
        $invoiceCode = pms_next_invoice_code();

        if (!pms_exec(
            "INSERT INTO invoice_header (invoice_code, customer_name, payment_type, subtotal, tax, discount, grand_total, customer_id, used_points) VALUES (?,?,?,?,?,?,?,?,?)",
            'sssddddii', [$invoiceCode, $customer, $paymentType, $subtotal, $tax, $discount, $grandTotal, $customerId ?: null, $usePoints]
        )) {
            mysqli_rollback($db);
            return ['ok' => false, 'message' => 'Lỗi khi tạo hóa đơn. Giao dịch đã hủy.'];
        }
        $invoiceNo = pms_last_id();

        // ── Bước 5: Ghi batch usage (lô nào bán bao nhiêu) ──
        foreach ($usageRows as $usage) {
            if (!pms_exec(
                "INSERT INTO sale_batch_usage (invoice_no, stock_id, batch_no, quantity_used) VALUES (?,?,?,?)",
                'iisi', [$invoiceNo, $usage['stock_id'], $usage['batch_no'], $usage['qty']]
            )) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => 'Lỗi khi ghi sale_batch_usage. Giao dịch đã hủy.'];
            }
        }

        // ── Bước 6: Ghi chi tiết hóa đơn ──
        if (!pms_exec(
            "INSERT INTO invoice_item (invoice_no, drug_id, drug_name, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)",
            'iisidd', [$invoiceNo, $firstStockId, $drugName, $quantity, $unitPrice, $subtotal]
        )) {
            mysqli_rollback($db);
            return ['ok' => false, 'message' => 'Lỗi khi ghi invoice_item. Giao dịch đã hủy.'];
        }

        // ── Bước 7: Ghi thanh toán ──
        if (!pms_exec(
            "INSERT INTO payment_details (invoice_no, customer_name, payment_type, total_ammount) VALUES (?,?,?,?)",
            'issd', [$invoiceNo, $customer, $paymentType, $grandTotal]
        )) {
            mysqli_rollback($db);
            return ['ok' => false, 'message' => 'Lỗi khi ghi payment_details. Giao dịch đã hủy.'];
        }

        // ── Bước 8: Xử lý điểm khách hàng ──
        if ($customerId > 0) {
            if ($usePoints > 0) {
                pms_exec("UPDATE customers SET loyalty_points = GREATEST(loyalty_points - ?, 0) WHERE customer_id=?", 'ii', [$usePoints, $customerId]);
            }
            $earn = (int)floor($grandTotal / 10000);
            if ($earn > 0) {
                pms_exec("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE customer_id=?", 'ii', [$earn, $customerId]);
                pms_exec("INSERT INTO loyalty_logs (customer_id, invoice_no, points_delta, note) VALUES (?,?,?,?)", 'iiis', [$customerId, $invoiceNo, $earn, 'Tích điểm từ hóa đơn ' . $invoiceCode]);
            }
        }

        // ── Bước 9: Ghi log và COMMIT ──
        pms_log('Bán hàng', "Tạo hóa đơn $invoiceCode cho $customer — trừ kho FEFO " . count($usageRows) . " lô");
        mysqli_commit($db);

        $msg = 'Đã tạo hóa đơn ' . $invoiceCode . ' và tự động trừ kho theo FEFO.';
        if ($warnExpiring) $msg .= ' Có lô cận date đã được ưu tiên xuất trước.';
        if ($customerId > 0) $msg .= ' Điểm khách hàng đã được cập nhật.';
        return ['ok' => true, 'message' => $msg, 'invoice_no' => $invoiceNo, 'invoice_code' => $invoiceCode];

    } catch (Throwable $e) {
        mysqli_rollback($db);
        throw $e; // Ném lại để caller (pms_create_sale) xử lý retry
    }
}

/* =========================================================================
 * BÁN HÀNG ĐA MẶT HÀNG (GIỎ HÀNG) - CÓ CHỐNG ÂM KHO & DEADLOCK PREVENTION
 * =========================================================================
 * Chiến lược:
 *   1. Nhận danh sách items [{name: 'Drug A', qty: 2}, ...]
 *   2. SẮP XẾP theo drug_id ASC để lock nhất quán
 *   3. Transaction + SELECT FOR UPDATE cho từng loại thuốc
 *   4. Ghi invoice_header, invoice_item và sale_batch_usage
 * ========================================================================= */
function pms_create_multi_sale(string $customer, array $items, string $paymentType, float $discount = 0.0, int $customerId = 0, int $usePoints = 0): array {
    if (!$items) return ['ok' => false, 'message' => 'Giỏ hàng trống.'];
    $maxRetries = 3;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            return pms_create_multi_sale_inner($customer, $items, $paymentType, $discount, $customerId, $usePoints);
        } catch (Throwable $e) {
            $isDeadlock = (str_contains($e->getMessage(), '1213') || str_contains(strtolower($e->getMessage()), 'deadlock'));
            if ($isDeadlock && $attempt < $maxRetries) {
                usleep(random_int(50000, 200000));
                continue;
            }
            return ['ok' => false, 'message' => 'Không thể hoàn tất bán hàng (lần thử ' . $attempt . '): ' . $e->getMessage()];
        }
    }
    return ['ok' => false, 'message' => 'Giao dịch thất bại sau ' . $maxRetries . ' lần thử. Vui lòng thử lại.'];
}

function pms_create_multi_sale_inner(string $customer, array $items, string $paymentType, float $discount, int $customerId, int $usePoints): array {
    $db = pms_db();
    mysqli_begin_transaction($db);
    try {
        if ($usePoints > 0) {
            if ($customerId <= 0) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => 'Khách lẻ không thể sử dụng điểm tích luỹ.'];
            }
            $cData = pms_fetch_one("SELECT loyalty_points FROM customers WHERE customer_id=?", 'i', [$customerId]);
            if (!$cData || (int)$cData['loyalty_points'] < $usePoints) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => 'Khách hàng không đủ điểm tích luỹ (' . ((int)$cData['loyalty_points']) . ' điểm).'];
            }
        }

        // ── Bước 1: Sắp xếp items theo drug_id ASC để lock nhất quán ──
        usort($items, fn($a, $b) => (int)$a['drug_id'] <=> (int)$b['drug_id']);

        $processedItems = [];
        $allUsages = [];
        $totalSubtotal = 0.0;
        $warnExpiring = false;

        foreach ($items as $item) {
            $drugId = (int)$item['drug_id'];
            $needed = (int)$item['quantity'];
            if ($needed <= 0) continue;

            // Lấy thông tin giá gốc từ bảng drugs (Security)
            $drug = pms_fetch_one("SELECT name, sale_price FROM drugs WHERE id=?", "i", [$drugId]);
            if (!$drug) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => "Không tìm thấy thuốc có ID $drugId trong danh mục."];
            }
            $drugName = $drug['name'];
            $unitPrice = (float)$drug['sale_price'];

            $candidates = pms_sale_candidates_for_update($drugId);
            if (!$candidates) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => 'Thuốc "' . $drugName . '" không có sẵn hoặc sắp hết hạn (< 7 ngày).'];
            }

            $available = array_sum(array_map(fn($r) => (int)$r['quantity'], $candidates));
            if ($available < $needed) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => 'Không đủ tồn kho cho "' . $drugName . '". Cần: ' . $needed . ', Còn: ' . $available . '.'];
            }

            $remaining = $needed;
            $itemSubtotal = 0.0;

            foreach ($candidates as $row) {
                if ($remaining <= 0) break;
                $take = min($remaining, (int)$row['quantity']);
                if ($take <= 0) continue;

                pms_exec("UPDATE stock SET quantity = quantity - ? WHERE stock_id = ?", 'ii', [$take, (int)$row['stock_id']]);
                
                // Kiểm tra lại tồn kho sau UPDATE
                $verify = pms_fetch_one("SELECT quantity FROM stock WHERE stock_id = ?", 'i', [(int)$row['stock_id']]);
                if ($verify === null || (int)$verify['quantity'] < 0) {
                    mysqli_rollback($db);
                    return ['ok' => false, 'message' => 'Lỗi tồn kho thuốc ' . $drugName . ' bị âm. Giao dịch đã hủy.'];
                }

                $allUsages[] = ['stock_id' => (int)$row['stock_id'], 'batch_no' => $row['batch_no'], 'qty' => $take];
                $remaining -= $take;
                if (pms_days_until($row['expiry_date']) <= 30) $warnExpiring = true;
            }

            $itemLineTotal = $needed * $unitPrice;
            $totalSubtotal += $itemLineTotal;

            $processedItems[] = [
                'drug_id' => $drugId,
                'drug_name' => $drugName,
                'quantity' => $needed,
                'unit_price' => $unitPrice,
                'line_total' => $itemLineTotal
            ];
        }

        // ── Tạo hóa đơn ──
        $discountAmount = $discount + ($usePoints * 100);
        $grand = max(0, $totalSubtotal - $discountAmount);
        $invoiceCode = pms_next_invoice_code();

        pms_exec(
            "INSERT INTO invoice_header (invoice_code, customer_name, payment_type, subtotal, tax, discount, grand_total, customer_id, used_points) VALUES (?,?,?,?,?,?,?,?,?)",
            'sssddddii', [$invoiceCode, $customer, $paymentType, $totalSubtotal, 0, $discountAmount, $grand, $customerId ?: null, $usePoints]
        );
        $invoiceNo = pms_last_id();

        // ── Ghi chi tiết và batch usage ──
        foreach ($processedItems as $pi) {
            pms_exec("INSERT INTO invoice_item (invoice_no, drug_id, drug_name, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)", 'iisidd', [$invoiceNo, $pi['drug_id'], $pi['drug_name'], $pi['quantity'], $pi['unit_price'], $pi['line_total']]);
        }
        foreach ($allUsages as $usage) {
            pms_exec("INSERT INTO sale_batch_usage (invoice_no, stock_id, batch_no, quantity_used) VALUES (?,?,?,?)", 'iisi', [$invoiceNo, $usage['stock_id'], $usage['batch_no'], $usage['qty']]);
        }

        // ── Thanh toán và Điểm ──
        pms_exec("INSERT INTO payment_details (invoice_no, customer_name, payment_type, total_ammount) VALUES (?,?,?,?)", 'issd', [$invoiceNo, $customer, $paymentType, $grand]);
        if ($customerId > 0) {
            if ($usePoints > 0) pms_exec("UPDATE customers SET loyalty_points = GREATEST(loyalty_points - ?, 0) WHERE customer_id=?", 'ii', [$usePoints, $customerId]);
            $earn = (int)floor($grand / 10000);
            if ($earn > 0) pms_exec("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE customer_id=?", 'ii', [$earn, $customerId]);
        }

        pms_log('Bán hàng POS', "Tạo HĐ $invoiceCode cho $customer ($totalSubtotal đ)");
        mysqli_commit($db);
        return ['ok' => true, 'message' => 'Thanh toán thành công hóa đơn ' . $invoiceCode, 'invoice_no' => $invoiceNo];
    } catch (Throwable $e) {
        mysqli_rollback($db);
        throw $e;
    }
}

/* =========================================================================
 * BÁN COMBO - CÓ CHỐNG ÂM KHO & DEADLOCK PREVENTION
 * =========================================================================
 * Chiến lược chống deadlock cho combo (nhiều thuốc):
 *   1. Lấy danh sách thuốc trong combo
 *   2. SẮP XẾP theo drug_name ASC (thứ tự cố định)
 *   3. Trong transaction: lock từng thuốc theo đúng thứ tự đã sort
 *   4. Với mỗi thuốc: SELECT FOR UPDATE → trừ quantity tương đối → verify >= 0
 *   5. Nếu bất kỳ thuốc nào không đủ → ROLLBACK toàn bộ
 *   6. Deadlock retry tối đa 3 lần
 * ========================================================================= */
function pms_create_combo_sale(string $customer, int $comboId, string $paymentType, int $customerId = 0, int $usePoints = 0): array {
    // Kiểm tra sơ bộ combo có tồn tại không (không cần lock)
    $comboRows = pms_combo_detail($comboId);
    if (!$comboRows) return ['ok' => false, 'message' => 'Combo không có dữ liệu.'];

    // ── Deadlock retry: tối đa 3 lần ──
    $maxRetries = 3;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            return pms_create_combo_sale_inner($customer, $comboId, $comboRows, $paymentType, $customerId, $usePoints);
        } catch (Throwable $e) {
            $isDeadlock = (str_contains($e->getMessage(), '1213') || str_contains(strtolower($e->getMessage()), 'deadlock'));
            if ($isDeadlock && $attempt < $maxRetries) {
                usleep(random_int(50000, 200000));
                continue;
            }
            return ['ok' => false, 'message' => 'Không thể hoàn tất bán combo (lần thử ' . $attempt . '): ' . $e->getMessage()];
        }
    }
    return ['ok' => false, 'message' => 'Giao dịch combo thất bại sau ' . $maxRetries . ' lần thử. Vui lòng thử lại.'];
}

/**
 * Logic bán combo thực sự, được tách ra để hỗ trợ deadlock retry.
 */
function pms_create_combo_sale_inner(string $customer, int $comboId, array $comboRows, string $paymentType, int $customerId, int $usePoints): array {
    $db = pms_db();
    mysqli_begin_transaction($db);
    try {
        if ($usePoints > 0) {
            if ($customerId <= 0) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => 'Khách lẻ không thể sử dụng điểm tích luỹ.'];
            }
            $cData = pms_fetch_one("SELECT loyalty_points FROM customers WHERE customer_id=?", 'i', [$customerId]);
            if (!$cData || (int)$cData['loyalty_points'] < $usePoints) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => 'Khách hàng không đủ điểm tích luỹ (' . ((int)$cData['loyalty_points']) . ' điểm).'];
            }
        }

        $combo = pms_fetch_one("SELECT * FROM combos WHERE combo_id=?", 'i', [$comboId]);
        if (!$combo) {
            mysqli_rollback($db);
            return ['ok' => false, 'message' => 'Combo không tồn tại.'];
        }

        // ── Bước 1: Sắp xếp comboRows theo drug_id ASC để lock nhất quán ──
        usort($comboRows, fn($a, $b) => (int)$a['drug_id'] <=> (int)$b['drug_id']);

        $processedItems = [];
        $allUsages = [];
        $totalSubtotal = 0.0;

        foreach ($comboRows as $row) {
            $drugId = (int)$row['drug_id'];
            $needed = (int)$row['quantity'];
            if ($needed <= 0) continue;

            // Lấy giá từ bảng drugs (Security)
            $drug = pms_fetch_one("SELECT name, sale_price FROM drugs WHERE id=?", "i", [$drugId]);
            if (!$drug) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => "Không tìm thấy thuốc ID $drugId trong danh mục."];
            }
            $drugName = $drug['name'];
            $unitPrice = (float)$drug['sale_price'];

            $candidates = pms_sale_candidates_for_update($drugId);
            if (!$candidates) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => "Hết hàng hoặc thuốc sắp hết hạn: $drugName"];
            }

            $available = array_sum(array_map(fn($r) => (int)$r['quantity'], $candidates));
            if ($available < $needed) {
                mysqli_rollback($db);
                return ['ok' => false, 'message' => "Combo thất bại: $drugName không đủ kho ($available / $needed)"];
            }

            $remaining = $needed;
            foreach ($candidates as $c) {
                if ($remaining <= 0) break;
                $take = min($remaining, (int)$c['quantity']);
                pms_exec("UPDATE stock SET quantity = quantity - ? WHERE stock_id = ?", 'ii', [$take, (int)$c['stock_id']]);
                
                $verify = pms_fetch_one("SELECT quantity FROM stock WHERE stock_id = ?", 'i', [(int)$c['stock_id']]);
                if ($verify === null || (int)$verify['quantity'] < 0) {
                    mysqli_rollback($db);
                    return ['ok' => false, 'message' => "Lỗi kho âm khi bán combo: $drugName"];
                }

                $allUsages[] = ['stock_id' => (int)$c['stock_id'], 'batch_no' => $c['batch_no'], 'qty' => $take];
                $remaining -= $take;
            }

            $itemTotal = $needed * $unitPrice;
            $totalSubtotal += $itemTotal;
            $processedItems[] = [
                'drug_id' => $drugId,
                'drug_name' => $drugName,
                'quantity' => $needed,
                'unit_price' => $unitPrice,
                'line_total' => $itemTotal
            ];
        }

        $grand = $combo['combo_price'] ?? $totalSubtotal;
        $grand = max(0, $grand - ($usePoints * 100));

        // ── Bước 2: Ghi invoice_header ──
        $invoiceCode = pms_next_invoice_code();
        pms_exec(
            "INSERT INTO invoice_header (invoice_code, customer_name, payment_type, subtotal, discount, grand_total, customer_id, used_points, status, note) VALUES (?,?,?,?,?,?,?,?,?,?)",
            'sssdddiiss', [$invoiceCode, $customer, $paymentType, $totalSubtotal, 0.0, $grand, $customerId ?: null, $usePoints, 'completed', 'Bán theo combo: ' . ($combo['combo_name'] ?? '')]
        );
        $invoiceNo = pms_last_id();

        // ── Bước 3: Ghi invoice_item ──
        foreach ($processedItems as $pi) {
            pms_exec("INSERT INTO invoice_item (invoice_no, drug_id, drug_name, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)", 'iisidd', [$invoiceNo, $pi['drug_id'], $pi['drug_name'], $pi['quantity'], $pi['unit_price'], $pi['line_total']]);
        }

        // ── Bước 4: Ghi sale_batch_usage ──
        foreach ($allUsages as $usage) {
            pms_exec("INSERT INTO sale_batch_usage (invoice_no, stock_id, batch_no, quantity_used) VALUES (?,?,?,?)", 'iisi', [$invoiceNo, $usage['stock_id'], $usage['batch_no'], $usage['qty']]);
        }

        // ── Bước 5: Ghi thanh toán ──
        pms_exec("INSERT INTO payment_details (invoice_no, customer_name, payment_type, total_ammount) VALUES (?,?,?,?)", 'issd', [$invoiceNo, $customer, $paymentType, $grand]);

        // ── Bước 6: Xử lý điểm khách hàng ──
        if ($customerId > 0) {
            if ($usePoints > 0) {
                pms_exec("UPDATE customers SET loyalty_points = GREATEST(loyalty_points - ?, 0) WHERE customer_id=?", 'ii', [$usePoints, $customerId]);
            }
            $earn = (int)floor($grand / 10000);
            if ($earn > 0) {
                pms_exec("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE customer_id=?", 'ii', [$earn, $customerId]);
                pms_exec("INSERT INTO loyalty_logs (customer_id, invoice_no, points_delta, note) VALUES (?,?,?,?)", 'iiis', [$customerId, $invoiceNo, $earn, 'Tích điểm từ combo ' . ($combo['combo_name'] ?? '')]);
            }
        }

        // ── Bước 7: Log và COMMIT ──
        pms_log('Bán combo', "Bán combo " . ($combo['combo_name'] ?? '') . " — HĐ $invoiceCode cho $customer — " . count($allUsages) . " lô");
        mysqli_commit($db);

        return ['ok' => true, 'message' => 'Đã bán combo ' . ($combo['combo_name'] ?? '') . ' và trừ kho tự động theo FEFO.', 'invoice_no' => $invoiceNo];

    } catch (Throwable $e) {
        mysqli_rollback($db);
        throw $e;
    }
}
function pms_alert_data(): array {
    $low = pms_fetch_all("SELECT drug_name, category, MIN(min_quantity) min_quantity, SUM(quantity) total_quantity, MIN(expiry_date) nearest_expiry FROM stock GROUP BY drug_name, category HAVING SUM(quantity) <= MIN(min_quantity) ORDER BY total_quantity ASC, drug_name ASC");
    $expiring = pms_fetch_all("SELECT stock_id, drug_name, batch_no, expiry_date, quantity, category, company, supplier_name FROM stock WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) ORDER BY expiry_date ASC, drug_name ASC");
    return ['low' => $low, 'expiring' => $expiring];
}
function pms_process_return(string $invoiceCode, int $itemId, int $qty, string $reason): array {
    $header = pms_fetch_one("SELECT * FROM invoice_header WHERE invoice_code=?", 's', [$invoiceCode]);
    if (!$header) return ['ok'=>false, 'message'=>'Không tìm thấy hóa đơn.'];
    $item = pms_fetch_one("SELECT * FROM invoice_item WHERE item_id=? AND invoice_no=?", 'ii', [$itemId, $header['invoice_no']]);
    if (!$item) return ['ok'=>false, 'message'=>'Dòng thuốc cần trả không hợp lệ.'];
    if ($qty <= 0 || $qty > (int)$item['quantity']) return ['ok'=>false, 'message'=>'Số lượng trả không hợp lệ.'];
    mysqli_begin_transaction(pms_db());
    try {
        $refund = $qty * (float)$item['unit_price'];
        pms_exec("INSERT INTO return_header (invoice_no, invoice_code, customer_name, reason, refund_total) VALUES (?,?,?,?,?)", 'isssd', [$header['invoice_no'], $invoiceCode, $header['customer_name'], $reason, $refund]);
        $returnId = pms_last_id();
        pms_exec("INSERT INTO return_item (return_id, item_id, drug_name, quantity, refund_amount) VALUES (?,?,?,?,?)", 'iisid', [$returnId, $itemId, $item['drug_name'], $qty, $refund]);
        
        // Hoàn kho chính xác theo lô đã xuất cho hóa đơn này
        $usages = pms_fetch_all("SELECT * FROM sale_batch_usage WHERE invoice_no=? AND stock_id IN (SELECT stock_id FROM stock WHERE drug_id=?) ORDER BY usage_id ASC", 'ii', [$header['invoice_no'], (int)$item['drug_id']]);
        $remaining = $qty;
        foreach ($usages as $usage) {
            if ($remaining <= 0) break;
            $take = min($remaining, (int)$usage['quantity_used']);
            pms_exec("UPDATE stock SET quantity = quantity + ? WHERE stock_id=?", 'ii', [$take, $usage['stock_id']]);
            // Giảm số lượng đã dùng trong bản ghi usage để tránh lỗi hoàn trả 2 lần
            pms_exec("UPDATE sale_batch_usage SET quantity_used = GREATEST(quantity_used - ?, 0) WHERE usage_id=?", 'ii', [$take, $usage['usage_id']]);
            $remaining -= $take;
        }
        
        pms_exec("UPDATE invoice_header SET grand_total = GREATEST(grand_total - ?, 0) WHERE invoice_no=?", 'di', [$refund, $header['invoice_no']]);
        pms_exec("UPDATE payment_details SET total_ammount = GREATEST(total_ammount - ?, 0) WHERE invoice_no=?", 'di', [$refund, $header['invoice_no']]);
        
        // Kiểm tra nếu hóa đơn đã trả hết sạch tiền -> Đổi trạng thái sang cancelled
        $newTotal = pms_fetch_one("SELECT grand_total FROM invoice_header WHERE invoice_no=?", 'i', [$header['invoice_no']]);
        if ($newTotal && (float)$newTotal['grand_total'] <= 0) {
            pms_exec("UPDATE invoice_header SET status = 'cancelled' WHERE invoice_no=?", 'i', [$header['invoice_no']]);
        }

        pms_log('Trả hàng', "Khách hoàn trả $qty " . $item['drug_name'] . " (Hóa đơn $invoiceCode)");
        mysqli_commit(pms_db());
        return ['ok'=>true,'message'=>'Đã hoàn trả ' . $qty . ' ' . $item['drug_name'] . ' vào đúng lô gốc.'];
    } catch (Throwable $e) { mysqli_rollback(pms_db()); return ['ok'=>false,'message'=>'Lỗi trả hàng: ' . $e->getMessage()]; }
}
function pms_backup_create(string $note = ''): array {
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            return ['ok' => false, 'message' => 'Không thể tạo thư mục backups.'];
        }
    }
    $fileName = 'backup-' . date('Ymd-His') . '.sql';
    $filePath = $backupDir . '/' . $fileName;

    // ── Thử dùng mysqldump trước (nhanh, đầy đủ) ──
    $mysqldumpPaths = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
    ];
    $mysqldump = null;
    foreach ($mysqldumpPaths as $path) {
        if (file_exists($path)) { $mysqldump = $path; break; }
    }

    if ($mysqldump) {
        $cmd = sprintf(
            '%s --host=%s --user=%s %s --default-character-set=utf8mb4 --single-transaction --routines --triggers %s > %s 2>&1',
            escapeshellarg($mysqldump),
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '',
            escapeshellarg(DB_NAME),
            escapeshellarg($filePath)
        );
        exec($cmd, $output, $exitCode);
        if ($exitCode === 0 && file_exists($filePath) && filesize($filePath) > 0) {
            $sizeKB = round(filesize($filePath) / 1024, 1);
            pms_exec("INSERT INTO backup_log (file_name, note) VALUES (?,?)", 'ss', [$fileName, $note ?: 'Backup bằng mysqldump']);
            pms_log('Sao lưu', "Tạo backup thành công: $fileName ({$sizeKB}KB) bằng mysqldump");
            return ['ok' => true, 'file' => $fileName, 'message' => "Sao lưu thành công ({$sizeKB}KB — mysqldump)."];
        }
        // mysqldump thất bại → fallback sang PHP
        @unlink($filePath);
    }

    // ── Fallback: Pure PHP dump ──────────────────────────────────────────
    $db = pms_db();
    $sql = "-- Nhà Thuốc — Backup tự động\n";
    $sql .= "-- Ngày: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . DB_NAME . "\n\n";
    $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql .= "SET time_zone = \"+07:00\";\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Lấy danh sách tất cả bảng
    $tablesResult = mysqli_query($db, "SHOW TABLES");
    if (!$tablesResult) {
        return ['ok' => false, 'message' => 'Không thể đọc danh sách bảng: ' . mysqli_error($db)];
    }
    $tables = [];
    while ($row = mysqli_fetch_row($tablesResult)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        $escapedTable = '`' . str_replace('`', '``', $table) . '`';

        // DROP TABLE
        $sql .= "-- ──────────────────────────────────────\n";
        $sql .= "-- Bảng: $table\n";
        $sql .= "-- ──────────────────────────────────────\n";
        $sql .= "DROP TABLE IF EXISTS $escapedTable;\n";

        // CREATE TABLE
        $createResult = mysqli_query($db, "SHOW CREATE TABLE $escapedTable");
        if ($createResult) {
            $createRow = mysqli_fetch_row($createResult);
            $sql .= $createRow[1] . ";\n\n";
        }

        // INSERT DATA
        $dataResult = mysqli_query($db, "SELECT * FROM $escapedTable");
        if ($dataResult && mysqli_num_rows($dataResult) > 0) {
            // Lấy tên cột
            $fields = mysqli_fetch_fields($dataResult);
            $colNames = [];
            foreach ($fields as $f) {
                $colNames[] = '`' . $f->name . '`';
            }

            while ($dataRow = mysqli_fetch_row($dataResult)) {
                $values = [];
                foreach ($dataRow as $i => $val) {
                    if ($val === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . mysqli_real_escape_string($db, $val) . "'";
                    }
                }
                $sql .= "INSERT INTO $escapedTable (" . implode(',', $colNames) . ") VALUES (" . implode(',', $values) . ");\n";
            }
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    // Ghi file
    $written = file_put_contents($filePath, $sql);
    if ($written === false) {
        return ['ok' => false, 'message' => 'Không thể ghi file backup. Kiểm tra quyền thư mục backups/.'];
    }
    $sizeKB = round($written / 1024, 1);
    pms_exec("INSERT INTO backup_log (file_name, note) VALUES (?,?)", 'ss', [$fileName, $note ?: 'Backup bằng PHP']);
    pms_log('Sao lưu', "Tạo backup thành công: $fileName ({$sizeKB}KB) bằng PHP");
    return ['ok' => true, 'file' => $fileName, 'message' => "Sao lưu thành công ({$sizeKB}KB — PHP dump)."];
}

/**
 * Phục hồi database từ file .sql backup.
 * Giải pháp: đọc file, tách thành từng câu SQL rồi thực thi tuần tự.
 */
function pms_backup_restore(string $filePath): array {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return ['ok' => false, 'message' => 'File backup không tồn tại hoặc không đọc được.'];
    }

    $sql = file_get_contents($filePath);
    if ($sql === false || trim($sql) === '') {
        return ['ok' => false, 'message' => 'File backup rỗng hoặc không đọc được.'];
    }

    $db = pms_db();

    // Tắt foreign key check để DROP/CREATE thoải mái
    mysqli_query($db, "SET FOREIGN_KEY_CHECKS = 0");
    mysqli_query($db, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    mysqli_query($db, "SET NAMES utf8mb4");

    // Tách câu SQL bằng delimiter phổ biến (;)
    // Loại bỏ comment và dòng trống
    $statements = [];
    $currentStmt = '';
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Bỏ comment
        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
            continue;
        }
        $currentStmt .= $line . "\n";
        // Nếu dòng kết thúc bằng ; → đây là 1 câu SQL hoàn chỉnh
        if (str_ends_with($trimmed, ';')) {
            $statements[] = trim($currentStmt);
            $currentStmt = '';
        }
    }
    // Câu cuối nếu không có ;
    if (trim($currentStmt) !== '') {
        $statements[] = trim($currentStmt);
    }

    $success = 0;
    $failed  = 0;
    $errors  = [];

    foreach ($statements as $stmt) {
        if (trim($stmt) === '' || trim($stmt) === ';') continue;
        $result = mysqli_query($db, $stmt);
        if ($result) {
            $success++;
        } else {
            $failed++;
            $err = mysqli_error($db);
            // Bỏ qua lỗi nhỏ (duplicate key, table exists, etc.)
            if (!str_contains($err, 'already exists') && !str_contains($err, 'Duplicate')) {
                $errors[] = mb_substr($err, 0, 120);
            }
        }
    }

    mysqli_query($db, "SET FOREIGN_KEY_CHECKS = 1");

    if ($failed > 0 && count($errors) > 0) {
        return [
            'ok'      => true,
            'message' => "Phục hồi hoàn tất: $success câu lệnh thành công, $failed lỗi nhỏ. " . implode(' | ', array_slice($errors, 0, 3)),
            'success' => $success,
            'failed'  => $failed,
        ];
    }

    return [
        'ok'      => true,
        'message' => "Phục hồi thành công: $success câu lệnh đã thực thi.",
        'success' => $success,
        'failed'  => $failed,
    ];
}
function pms_chart(string $id, string $title, array $labels, array $values): void {
?>
<section class="panel chart-panel">
    <div class="panel-head"><h2><?= pms_h($title) ?></h2></div>
    <div class="chart-wrap"><canvas id="<?= pms_h($id) ?>"></canvas></div>
</section>
<script>
(() => {
    const ctx = document.getElementById('<?= pms_h($id) ?>'); if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: { labels: <?= json_encode(array_values($labels), JSON_UNESCAPED_UNICODE) ?>, datasets: [{ data: <?= json_encode(array_map('intval', $values), JSON_UNESCAPED_UNICODE) ?>, borderRadius: 18, maxBarThickness: 72, backgroundColor: ['#2563eb','#14b8a6','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#10b981','#f97316'] }]},
        options: { maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: { y: {beginAtZero: true, grid: {color: 'rgba(148,163,184,.16)'}, ticks: {color: '#475569'}}, x: {grid: {display: false}, ticks: {color: '#475569'}} } }
    });
})();
</script>
<?php }
