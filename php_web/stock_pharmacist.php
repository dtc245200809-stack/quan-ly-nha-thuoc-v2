<?php
include_once 'app.php';
pms_auth('pharmacist_cashier');
$role = pms_current_role();
$list = pms_fetch_all("SELECT *, CASE WHEN expiry_date IS NULL THEN 0 WHEN expiry_date <= CURDATE() THEN 2 WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 ELSE 0 END AS expiry_state FROM stock ORDER BY expiry_date IS NULL, expiry_date ASC, stock_id DESC");
pms_render_header('Tra cứu kho thuốc', $role, 'stock_pharmacist.php', pms_stock_stats());
?>
<section class="panel">
    <div class="panel-head"><h2>Thuốc hiện có</h2><div class="panel-subtitle">Dành cho dược sĩ tra cứu nhanh khi tư vấn hoặc cấp phát.</div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Thuốc</th><th>Lô</th><th>Hạn dùng</th><th>Số lượng</th><th>Đơn giá</th></tr></thead>
            <tbody>
                <?php foreach ($list as $row): ?>
                    <?php $badgeClass = $row['expiry_state'] == 2 ? 'danger' : ($row['expiry_state'] == 1 ? 'warn' : 'ok'); ?>
                    <tr>
                        <td><strong><?= pms_h($row['drug_name']) ?></strong><br><span class="muted"><?= pms_h($row['category']) ?> • <?= pms_h($row['company']) ?></span></td>
                        <td><?= pms_h($row['batch_no'] ?: '—') ?></td>
                        <td><?= pms_h($row['expiry_date'] ?: '—') ?><br><span class="badge <?= $badgeClass ?>"><?= $row['expiry_state'] == 2 ? 'Đã hết hạn' : ($row['expiry_state'] == 1 ? 'Sắp hết hạn' : 'Ổn định') ?></span></td>
                        <td><?= number_format((int)$row['quantity']) ?></td>
                        <td><?= pms_currency((float)$row['sale_price']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$list): ?><tr><td colspan="5"><div class="empty">Không có dữ liệu kho thuốc.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php pms_render_footer(); ?>