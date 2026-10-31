<?php
include_once 'app.php';
pms_auth('admin');
$statsRow = pms_fetch_one("SELECT (SELECT COUNT(*) FROM admin) admins, (SELECT COUNT(*) FROM pharmacist) pharmacists, (SELECT COUNT(*) FROM manager) managers, (SELECT COUNT(*) FROM cashier) cashiers") ?: [];
$total = (int)($statsRow['admins'] ?? 0) + (int)($statsRow['pharmacists'] ?? 0) + (int)($statsRow['managers'] ?? 0) + (int)($statsRow['cashiers'] ?? 0);
$stats = ['Dược sĩ / Nhân viên bán hàng' => ((int)($statsRow['pharmacists'] ?? 0) + (int)($statsRow['cashiers'] ?? 0)), 'Quản lý' => ((int)($statsRow['managers'] ?? 0) + (int)($statsRow['admins'] ?? 0))];
pms_render_header('Quản trị tài khoản & phân quyền', 'admin', 'admin.php', $stats);
?>
<section class="ds-panel">
    <div class="admin-hero">
        <div class="admin-hero__content">
            <h2>Quản lý tài khoản nội bộ</h2>
            <p style="color:var(--ds-text-muted);font-size:14px;line-height:1.7;margin-bottom:16px">Phân quyền đúng vai trò: Quản lý điều hành tổng thể, Dược sĩ tư vấn và tra cứu, Nhân viên bán hàng thao tác nhanh tại quầy.</p>
            <div style="display:flex;gap:12px;margin-top:16px">
                <a class="ds-btn ds-btn-secondary" href="accounts.php">+ Dược sĩ/Bán hàng</a>
                <a class="ds-btn ds-btn-secondary" href="accounts.php">+ Quản lý</a>
                <a class="ds-btn ds-btn-secondary" href="he_thong.php">Hệ thống</a>
            </div>
        </div>
        <div class="ds-stats-row" style="display: flex; gap: 24px; padding: 0; width: 100%;">
            <div class="ds-stat-card" style="flex: 1;">
                <div class="ds-stat-icon manager">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <span>Tài khoản hoạt động</span>
                <strong><?= $total ?></strong>
            </div>
            <div class="ds-stat-card" style="flex: 1;">
                <div class="ds-stat-icon cashier">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
                </div>
                <span>Nhóm quyền</span>
                <strong>3</strong>
            </div>
        </div>
    </div>
</section>

<div class="ds-dashboard-grid" style="grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 32px;">
    <a class="ds-info-card" href="accounts.php" style="text-decoration:none">
        <div class="ds-info-card__body">
            <strong style="font-size:16px;color:#1e293b;display:block;margin-bottom:10px">Tài khoản dược sĩ / bán hàng</strong>
            <div class="ds-muted" style="margin-bottom:20px; font-size: 14px; line-height: 1.6;">Bán hàng POS, tra cứu thuốc, quản lý đơn và tư vấn tại quầy.</div>
            <span class="ds-badge ds-badge-primary" style="padding: 6px 14px; border-radius: 10px;">Nghiệp vụ · <?= (int)($statsRow['pharmacists'] ?? 0) + (int)($statsRow['cashiers'] ?? 0) ?> tài khoản</span>
        </div>
    </a>
    <a class="ds-info-card" href="accounts.php" style="text-decoration:none">
        <div class="ds-info-card__body">
            <strong style="font-size:16px;color:#1e293b;display:block;margin-bottom:10px">Tài khoản quản lý</strong>
            <div class="ds-muted" style="margin-bottom:20px; font-size: 14px; line-height: 1.6;">Toàn quyền nhập kho, báo cáo, doanh thu và xử lý cảnh báo.</div>
            <span class="ds-badge ds-badge-primary" style="padding: 6px 14px; border-radius: 10px;">Quản lý · <?= (int)($statsRow['managers'] ?? 0) ?> tài khoản</span>
        </div>
    </a>
</div>
<?php pms_chart('adminRoleChart', 'Cơ cấu người dùng trong hệ thống', ['Dược sĩ / Nhân viên bán hàng','Quản lý'], [(int)($statsRow['pharmacists'] ?? 0) + (int)($statsRow['cashiers'] ?? 0),(int)($statsRow['managers'] ?? 0)]); ?>
<?php pms_render_footer(); ?>
