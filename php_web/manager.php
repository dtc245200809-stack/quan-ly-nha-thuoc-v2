<?php
include_once 'app.php';
pms_auth('admin,manager');
$alerts = pms_alert_data();
$today = date('Y-m-d');
$statsRow = pms_fetch_one("SELECT (SELECT COUNT(DISTINCT drug_name) FROM stock) medicines, (SELECT COUNT(*) FROM stock) batches, (SELECT COUNT(*) FROM invoice_header WHERE status<>'cancelled') invoices, (SELECT COALESCE(SUM(grand_total),0) FROM invoice_header WHERE status<>'cancelled' AND DATE(created_at)=?) revenue_today", "s", [$today]) ?: [];
$topDrugs = pms_fetch_all("SELECT i.drug_name, SUM(i.quantity) total_qty FROM invoice_item i JOIN invoice_header h ON i.invoice_no=h.invoice_no WHERE h.status<>'cancelled' GROUP BY i.drug_name ORDER BY total_qty DESC LIMIT 6");
$recentReturns = pms_fetch_all("SELECT invoice_code, customer_name, refund_total, created_at FROM return_header ORDER BY return_id DESC LIMIT 5");
pms_render_header('Dashboard quản lý nhà thuốc', 'manager', 'manager.php', [
    'Doanh thu hôm nay' => pms_currency((float)($statsRow['revenue_today'] ?? 0)),
    'Tổng đơn hàng' => $statsRow['invoices'] ?? 0,
    'Mặt hàng thuốc' => $statsRow['medicines'] ?? 0,
    'Lô cần xử lý' => count($alerts['expiring']),
]);
?>

<div class="ds-dashboard-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 24px;">
    <a class="ds-info-card" href="ban_hang.php" style="text-decoration:none">
        <strong style="font-size:14px;color:var(--ds-text-main);display:block;margin-bottom:6px">Bán hàng POS</strong>
        <div class="ds-muted" style="margin-bottom:12px;line-height:1.5">Màn hình quầy bán full chiều ngang, tối ưu FEFO, thuốc kê đơn và điểm khách hàng.</div>
        <span class="ds-badge ds-badge-primary">Mở POS</span>
    </a>
    <a class="ds-info-card" href="stock.php" style="text-decoration:none">
        <strong style="font-size:14px;color:var(--ds-text-main);display:block;margin-bottom:6px">Nhập kho & lô thuốc</strong>
        <div class="ds-muted" style="margin-bottom:12px;line-height:1.5">Quản lý một thuốc nhiều lô, hạn dùng, quy đổi đơn vị và trạng thái an toàn.</div>
        <span class="ds-badge ds-badge-primary">Kho</span>
    </a>
    <a class="ds-info-card" href="combo.php" style="text-decoration:none">
        <strong style="font-size:14px;color:var(--ds-text-main);display:block;margin-bottom:6px">Bán cắt liều</strong>
        <div class="ds-muted" style="margin-bottom:12px;line-height:1.5">Tạo sẵn combo như liều cảm cúm, đau dạ dày, giúp nhân viên thao tác nhanh.</div>
        <span class="ds-badge ds-badge-primary">Combo đơn mẫu</span>
    </a>
    <a class="ds-info-card" href="tra_hang.php" style="text-decoration:none">
        <strong style="font-size:14px;color:var(--ds-text-main);display:block;margin-bottom:6px">Trả hàng</strong>
        <div class="ds-muted" style="margin-bottom:12px;line-height:1.5">Lập phiếu hoàn trả từ hóa đơn đã bán và cộng lại tồn kho đúng lô.</div>
        <span class="ds-badge ds-badge-primary">Hoàn tiền</span>
    </a>
</div>
<?php pms_chart('topDrugChart', 'Top thuốc bán chạy gần đây', array_map(fn($x)=>$x['drug_name'], $topDrugs), array_map(fn($x)=>(int)$x['total_qty'], $topDrugs)); ?>
<div class="ds-dashboard-grid">
    <section class="ds-panel">
        <div class="ds-panel__head"><h2>Cảnh báo cấp bách</h2><div class="ds-panel__subtitle">Bảng thu gọn các lô &lt; 90 ngày và đã hết hạn.</div></div>
        <div class="ds-info-list">
            <?php foreach (array_slice($alerts['expiring'],0,6) as $row): $days = pms_days_until($row['expiry_date']); ?>
                <div class="ds-list-item">
                    <div><strong><?= pms_h($row['drug_name']) ?></strong><br><span class="ds-muted">Lô <?= pms_h($row['batch_no']) ?> · <?= pms_h($row['expiry_date']) ?></span></div>
                    <span class="ds-badge <?= $days < 0 ? 'ds-badge--danger' : 'ds-badge--warning' ?>"><?= $days < 0 ? 'Quá hạn' : $days . ' ngày' ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$alerts['expiring']): ?><div class="ds-empty">Hiện chưa có lô cận date trong 90 ngày.</div><?php endif; ?>
        </div>
    </section>
    <section class="ds-panel">
        <div class="ds-panel__head"><h2>Khách hàng thân thiết</h2><div class="ds-panel__subtitle">Tự động cộng điểm theo hóa đơn và dùng điểm giảm giá.</div></div>
        <?php $customers = pms_fetch_all("SELECT customer_name, loyalty_points, phone FROM customers ORDER BY loyalty_points DESC LIMIT 5"); ?>
        <div class="ds-info-list">
            <?php foreach ($customers as $customer): ?>
                <div class="ds-list-item"><div><strong><?= pms_h($customer['customer_name']) ?></strong><br><span class="ds-muted"><?= pms_h($customer['phone']) ?></span></div><span class="ds-badge ds-badge--info"><?= (int)$customer['loyalty_points'] ?> điểm</span></div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<section class="ds-panel">
    <div class="ds-panel__head"><h2>Hoạt động gần đây</h2><div class="ds-panel__subtitle">Theo dõi nhanh các phiếu trả hàng mới tạo.</div></div>
    <div class="table-wrap"><table><thead><tr><th>Mã hóa đơn</th><th>Khách hàng</th><th>Số tiền hoàn</th><th>Thời gian</th></tr></thead><tbody>
        <?php foreach ($recentReturns as $row): ?><tr><td><?= pms_h($row['invoice_code']) ?></td><td><?= pms_h($row['customer_name']) ?></td><td><?= pms_currency((float)$row['refund_total']) ?></td><td><?= pms_h($row['created_at']) ?></td></tr><?php endforeach; ?>
        <?php if (!$recentReturns): ?><tr><td colspan="4"><div class="ds-empty">Chưa có phiếu trả hàng nào.</div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php pms_render_footer(); ?>