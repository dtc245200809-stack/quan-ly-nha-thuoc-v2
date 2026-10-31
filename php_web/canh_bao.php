<?php
include_once 'app.php';
pms_auth('admin,manager,pharmacist');
$role = pms_current_role();

// ── Xử lý POST: Đánh dấu đã xử lý cảnh báo ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_alert'])) {
    pms_csrf_verify('canh_bao.php');
    $stockId = (int)$_POST['stock_id'];
    $alertType = $_POST['alert_type'] ?? '';
    $drugName = $_POST['drug_name'] ?? '';
    $batchNo = $_POST['batch_no'] ?? '';
    $user = $_SESSION['username'] ?? 'system';
    
    pms_exec("INSERT INTO canh_bao (alert_type, drug_name, batch_no, stock_id, message, status, resolved_by, resolved_at) VALUES (?,?,?,?,?,?,?,NOW())",
        'sssisss', [$alertType, $drugName, $batchNo, $stockId, "Đã xử lý bởi $user", 'resolved', $user]);
    pms_log('Cảnh báo', "Đánh dấu đã xử lý: $drugName lô $batchNo ($alertType)");
    pms_flash("Đã ghi nhận xử lý cảnh báo cho $drugName.");
    pms_redirect('canh_bao.php');
}

// ── Xuất CSV ──
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $alerts = pms_alert_data();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="canh_bao_' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    // BOM cho Excel nhận UTF-8
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($type === 'low_stock') {
        fputcsv($out, ['Tên thuốc', 'Nhóm', 'Tồn hiện tại', 'Định mức tối thiểu', 'Thiếu']);
        foreach ($alerts['low'] as $row) {
            fputcsv($out, [$row['drug_name'], $row['category'], $row['total_quantity'], $row['min_quantity'], (int)$row['min_quantity'] - (int)$row['total_quantity']]);
        }
    } else {
        fputcsv($out, ['Tên thuốc', 'Mã lô', 'Hạn sử dụng', 'Số tồn', 'Số ngày còn lại', 'Trạng thái']);
        foreach ($alerts['expiring'] as $row) {
            $days = pms_days_until($row['expiry_date']);
            $status = $days < 0 ? 'Đã hết hạn' : ($days <= 30 ? 'Nguy hiểm' : ($days <= 60 ? 'Cần chú ý' : 'Theo dõi'));
            fputcsv($out, [$row['drug_name'], $row['batch_no'], $row['expiry_date'], $row['quantity'], $days, $status]);
        }
    }
    fclose($out);
    exit;
}

$alerts = pms_alert_data();
$totalLow = count($alerts['low']);
$totalExp = count($alerts['expiring']);
$expired = array_filter($alerts['expiring'], fn($r) => pms_days_until($r['expiry_date']) < 0);
$under30 = array_filter($alerts['expiring'], fn($r) => pms_days_until($r['expiry_date']) >= 0 && pms_days_until($r['expiry_date']) <= 30);
$under60 = array_filter($alerts['expiring'], fn($r) => pms_days_until($r['expiry_date']) > 30 && pms_days_until($r['expiry_date']) <= 60);
$under90 = array_filter($alerts['expiring'], fn($r) => pms_days_until($r['expiry_date']) > 60 && pms_days_until($r['expiry_date']) <= 90);

// Lịch sử cảnh báo đã xử lý
$resolvedAlerts = pms_fetch_all("SELECT * FROM canh_bao WHERE status='resolved' ORDER BY resolved_at DESC LIMIT 20");

$totalDrugs = pms_fetch_one("SELECT COUNT(*) as cnt FROM drugs WHERE is_deleted=0")['cnt'] ?? 0;

pms_render_header('Cảnh báo hạn dùng & tồn kho thấp', $role, 'canh_bao.php', [
    'Tổng thuốc' => $totalDrugs,
    'Cận / quá hạn' => $totalExp,
    'Đã hết hạn' => count($expired),
]);
?>

<!-- Summary cards -->
<div class="ds-stats-row" style="grid-template-columns: repeat(5, 1fr) !important; margin-bottom: 24px;">
    <div class="ds-stat-card" style="padding: 16px 18px !important; min-height: 90px; grid-template-columns: 46px minmax(0, 1fr); cursor: pointer;" onclick="filterAlerts('expired')">
        <div class="ds-stat-icon danger" style="width: 46px; height: 46px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
        <span>Đã hết hạn</span>
        <strong><?= count($expired) ?></strong>
    </div>
    <div class="ds-stat-card" style="padding: 16px 18px !important; min-height: 90px; grid-template-columns: 46px minmax(0, 1fr); cursor: pointer;" onclick="filterAlerts('under30')">
        <div class="ds-stat-icon" style="width: 46px; height: 46px; background: linear-gradient(135deg, #DD6B20, #ED8936); box-shadow: 0 8px 16px rgba(221, 107, 32, 0.25);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
        <span>Dưới 30 ngày</span>
        <strong><?= count($under30) ?></strong>
    </div>
    <div class="ds-stat-card" style="padding: 16px 18px !important; min-height: 90px; grid-template-columns: 46px minmax(0, 1fr); cursor: pointer;" onclick="filterAlerts('under60')">
        <div class="ds-stat-icon" style="width: 46px; height: 46px; background: linear-gradient(135deg, #ECC94B, #D69E2E); box-shadow: 0 8px 16px rgba(214, 158, 46, 0.25);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
        <span>30–60 ngày</span>
        <strong><?= count($under60) ?></strong>
    </div>
    <div class="ds-stat-card" style="padding: 16px 18px !important; min-height: 90px; grid-template-columns: 46px minmax(0, 1fr); cursor: pointer;" onclick="filterAlerts('under90')">
        <div class="ds-stat-icon manager" style="width: 46px; height: 46px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
        </div>
        <span>60–90 ngày</span>
        <strong><?= count($under90) ?></strong>
    </div>
    <div class="ds-stat-card" style="padding: 16px 18px !important; min-height: 90px; grid-template-columns: 46px minmax(0, 1fr); cursor: pointer;" onclick="filterAlerts('low_stock')">
        <div class="ds-stat-icon" style="width: 46px; height: 46px; background: linear-gradient(135deg, #E53E3E, #C53030); box-shadow: 0 8px 16px rgba(229, 62, 62, 0.25);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
        </div>
        <span>Tồn thấp</span>
        <strong><?= $totalLow ?></strong>
    </div>
</div>

<div class="cb-grid">
    <!-- Tồn kho thấp -->
    <div class="panel" id="low-stock">
        <div class="panel-head">
            <div>
                <h2 style="color:var(--orange)">⚠ Tồn kho thấp</h2>
                <div class="panel-subtitle">Thuốc có tồn dưới mức tối thiểu cần bổ sung.</div>
            </div>
            <div class="actions">
                <a class="btn sm secondary" href="?export=low_stock">📥 Xuất CSV</a>
                <span class="badge warn"><?= $totalLow ?> mục</span>
            </div>
        </div>
        <?php if (!$alerts['low']): ?>
            <div class="empty">
                <div style="font-size: 2rem; margin-bottom: 8px;">✅</div>
                Không có thuốc nào dưới mức tồn tối thiểu.
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tên thuốc</th><th>Nhóm</th><th>Tồn hiện tại</th>
                        <th>Định mức</th><th>Thiếu</th><th>Mức độ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($alerts['low'] as $row):
                    $deficit = (int)$row['min_quantity'] - (int)$row['total_quantity'];
                    $severity = $deficit > 50 ? 'danger' : 'warn';
                ?>
                    <tr>
                        <td><strong><?= pms_h($row['drug_name']) ?></strong></td>
                        <td><?= pms_h($row['category']) ?></td>
                        <td><span class="badge warn"><?= number_format((int)$row['total_quantity']) ?></span></td>
                        <td><?= number_format((int)$row['min_quantity']) ?></td>
                        <td><span class="badge danger">-<?= number_format($deficit) ?></span></td>
                        <td>
                            <div class="cb-severity cb-severity--<?= $severity ?>">
                                <div class="cb-severity__bar" style="width: <?= min(100, ($deficit / max(1, (int)$row['min_quantity'])) * 100) ?>%"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Cận date / hết hạn -->
    <div class="panel" id="expiry">
        <div class="panel-head">
            <div>
                <h2 style="color:var(--red)">🚨 Hạn sử dụng</h2>
                <div class="panel-subtitle">Lô thuốc sắp hết hạn hoặc đã quá hạn trong 90 ngày tới.</div>
            </div>
            <div class="actions">
                <a class="btn sm secondary" href="?export=expiry">📥 Xuất CSV</a>
                <span class="badge danger"><?= $totalExp ?> lô</span>
            </div>
        </div>
        <?php if (!$alerts['expiring']): ?>
            <div class="empty">
                <div style="font-size: 2rem; margin-bottom: 8px;">✅</div>
                Không có lô nào cận date trong 90 ngày tới.
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tên thuốc</th><th>Mã lô</th><th>Số tồn</th>
                        <th>Hạn dùng</th><th>Trạng thái</th><th>Tiến trình</th><th>Xử lý</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($alerts['expiring'] as $row): $days = pms_days_until($row['expiry_date']); $cat = $days < 0 ? 'expired' : ($days <= 30 ? 'under30' : ($days <= 60 ? 'under60' : 'under90')); ?>
                    <tr <?= $days < 0 ? 'class="cb-row-danger"' : ($days <= 30 ? 'class="cb-row-warn"' : '') ?> data-cat="<?= $cat ?>">
                        <td><strong><?= pms_h($row['drug_name']) ?></strong></td>
                        <td><span class="badge gray"><?= pms_h($row['batch_no']) ?></span></td>
                        <td><?= number_format((int)$row['quantity']) ?></td>
                        <td><?= pms_h($row['expiry_date']) ?></td>
                        <td>
                            <span class="badge <?= $days < 0 ? 'danger' : ($days <= 30 ? 'danger' : ($days <= 60 ? 'warn' : 'info')) ?>">
                                <?= $days < 0 ? 'Đã hết hạn ' . abs($days) . ' ngày' : "Còn $days ngày" ?>
                            </span>
                        </td>
                        <td>
                            <div class="cb-countdown <?= $days < 0 ? 'expired' : ($days <= 30 ? 'critical' : 'warning') ?>">
                                <div class="cb-countdown__fill" style="width: <?= $days < 0 ? 100 : max(5, 100 - ($days / 90 * 100)) ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <form method="post" style="display:inline">
                                <?= pms_csrf_field() ?>
                                <input type="hidden" name="resolve_alert" value="1">
                                <input type="hidden" name="stock_id" value="<?= (int)$row['stock_id'] ?>">
                                <input type="hidden" name="alert_type" value="<?= $days < 0 ? 'expired' : 'near_expiry' ?>">
                                <input type="hidden" name="drug_name" value="<?= pms_h($row['drug_name']) ?>">
                                <input type="hidden" name="batch_no" value="<?= pms_h($row['batch_no']) ?>">
                                <button type="submit" class="btn sm secondary" onclick="return confirm('Đánh dấu đã xử lý?')">✓ Xử lý</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($resolvedAlerts): ?>
<section class="panel" style="margin-top:16px;">
    <div class="panel-head">
        <h2>📋 Lịch sử cảnh báo đã xử lý (20 gần nhất)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Loại</th><th>Thuốc</th><th>Lô</th><th>Người xử lý</th><th>Thời gian</th></tr></thead>
            <tbody>
            <?php foreach ($resolvedAlerts as $a): ?>
                <tr>
                    <td><span class="badge <?= $a['alert_type'] === 'expired' ? 'danger' : ($a['alert_type'] === 'near_expiry' ? 'warn' : 'info') ?>">
                        <?= $a['alert_type'] === 'expired' ? 'Hết hạn' : ($a['alert_type'] === 'near_expiry' ? 'Cận date' : 'Tồn thấp') ?>
                    </span></td>
                    <td><?= pms_h($a['drug_name']) ?></td>
                    <td><?= pms_h($a['batch_no'] ?? '—') ?></td>
                    <td><span class="badge gray"><?= pms_h($a['resolved_by']) ?></span></td>
                    <td><?= pms_h($a['resolved_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<style>
.cb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.cb-grid > .panel { margin-bottom: 0; box-shadow: var(--pm-shadow-soft); border: 1px solid var(--pm-card-border); }
.cb-row-danger td { background: #FFF5F5 !important; border-bottom: 1px solid #FED7D7 !important; }
.cb-row-warn td { background: #FFFDF0 !important; border-bottom: 1px solid #FEFCBF !important; }
.ds-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
.ds-stat-card.active { border: 2px solid var(--primary-500); }
.cb-severity, .cb-countdown { height: 8px; border-radius: 4px; background: #EDF2F7; overflow: hidden; min-width: 60px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.06); }
.cb-severity__bar { height: 100%; border-radius: 4px; background: #ED8936; transition: width .3s; }
.cb-severity--danger .cb-severity__bar { background: #E53E3E; box-shadow: 0 0 8px rgba(229,62,62,0.4); }
.cb-countdown__fill { height: 100%; border-radius: 4px; transition: width .3s; }
.cb-countdown.warning .cb-countdown__fill { background: #ED8936; }
.cb-countdown.critical .cb-countdown__fill { background: #E53E3E; box-shadow: 0 0 8px rgba(229,62,62,0.4); }
.cb-countdown.expired .cb-countdown__fill { background: #C53030; animation: pulse-bar 1.5s ease-in-out infinite; }
@keyframes pulse-bar { 0%, 100% { opacity: 1; } 50% { opacity: .6; } }
@media (max-width: 1180px) { .cb-grid { grid-template-columns: 1fr; } }
</style>
<script>
function filterAlerts(type) {
    // Scroll to section
    if (type === 'low_stock') {
        document.getElementById('low-stock').scrollIntoView({ behavior: 'smooth' });
    } else {
        document.getElementById('expiry').scrollIntoView({ behavior: 'smooth' });
    }

    // Update active class on cards
    document.querySelectorAll('.ds-stats-row .ds-stat-card').forEach(el => el.classList.remove('active'));
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }

    // Filter expiry table
    if (type !== 'low_stock') {
        const rows = document.querySelectorAll('#expiry tbody tr');
        let hasVisible = false;
        rows.forEach(tr => {
            if (tr.dataset.cat === type || type === 'all') {
                tr.style.display = '';
                hasVisible = true;
            } else {
                tr.style.display = 'none';
            }
        });
        
        // Show/hide empty state
        let emptyDiv = document.getElementById('expiry-empty');
        if (!emptyDiv && document.querySelector('#expiry .table-wrap')) {
            emptyDiv = document.createElement('div');
            emptyDiv.id = 'expiry-empty';
            emptyDiv.className = 'empty';
            emptyDiv.innerHTML = '<div style="font-size: 2rem; margin-bottom: 8px;">✅</div>Không có lô nào thuộc mục này.';
            emptyDiv.style.display = 'none';
            document.querySelector('#expiry .table-wrap').after(emptyDiv);
        }
        if (emptyDiv) {
            emptyDiv.style.display = hasVisible ? 'none' : 'block';
            document.querySelector('#expiry .table-wrap').style.display = hasVisible ? 'block' : 'none';
        }
    }
}
</script>
<?php pms_render_footer(); ?>