<?php
include_once 'app.php';
pms_auth('admin,manager');
$role = isset($_SESSION['admin_id']) ? 'admin' : 'manager';

$from_date = trim($_GET['from'] ?? date('Y-m-01'));
$to_date = trim($_GET['to'] ?? date('Y-m-t'));
$filter_type = trim($_GET['filter_type'] ?? 'month');

// ── Xuất CSV ──
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bao_cao_' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($type === 'revenue') {
        $data = pms_fetch_all("SELECT h.invoice_code, h.customer_name, h.payment_type, h.subtotal, h.discount, h.grand_total, h.created_at FROM invoice_header h WHERE h.status<>'cancelled' AND DATE(h.created_at) >= ? AND DATE(h.created_at) <= ? ORDER BY h.created_at DESC", "ss", [$from_date, $to_date]);
        fputcsv($out, ['Mã HĐ', 'Khách hàng', 'Phương thức', 'Tổng hàng', 'Giảm giá', 'Thanh toán', 'Ngày']);
        foreach ($data as $row) {
            fputcsv($out, [$row['invoice_code'], $row['customer_name'], $row['payment_type'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản', $row['subtotal'], $row['discount'], $row['grand_total'], $row['created_at']]);
        }
    } elseif ($type === 'top_drugs') {
        $data = pms_fetch_all("SELECT i.drug_name, SUM(i.quantity) total_qty, SUM(i.line_total) total_revenue FROM invoice_item i JOIN invoice_header h ON i.invoice_no = h.invoice_no WHERE h.status<>'cancelled' AND DATE(h.created_at) >= ? AND DATE(h.created_at) <= ? GROUP BY i.drug_name ORDER BY total_qty DESC", "ss", [$from_date, $to_date]);
        fputcsv($out, ['Tên thuốc', 'Số lượng bán', 'Doanh thu']);
        foreach ($data as $row) { fputcsv($out, [$row['drug_name'], $row['total_qty'], $row['total_revenue']]); }
    }
    fclose($out);
    exit;
}

$summary = pms_fetch_one("SELECT COUNT(*) total_invoices, COALESCE(SUM(grand_total),0) total_revenue, COALESCE(AVG(grand_total),0) avg_revenue FROM invoice_header WHERE status<>'cancelled' AND DATE(created_at) >= ? AND DATE(created_at) <= ?", "ss", [$from_date, $to_date]) ?: [];

$refund_data = pms_fetch_one("SELECT COALESCE(SUM(refund_total),0) total_refund FROM return_header WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?", "ss", [$from_date, $to_date]) ?: [];
$total_refund = (float)($refund_data['total_refund'] ?? 0);
$gross_revenue = (float)($summary['total_revenue'] ?? 0);
$net_revenue = $gross_revenue - $total_refund;

// Phân tích theo phương thức thanh toán
$payment_split = pms_fetch_all("SELECT payment_type, COUNT(*) cnt, SUM(grand_total) total FROM invoice_header WHERE status<>'cancelled' AND DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY payment_type", "ss", [$from_date, $to_date]);
$cash_total = 0; $transfer_total = 0;
foreach ($payment_split as $ps) {
    if ($ps['payment_type'] === 'cash') $cash_total = (float)$ps['total'];
    else $transfer_total = (float)$ps['total'];
}

$top5 = pms_fetch_all("SELECT i.drug_name, SUM(i.quantity) total_qty, SUM(i.line_total) total_revenue FROM invoice_item i JOIN invoice_header h ON i.invoice_no = h.invoice_no WHERE h.status<>'cancelled' AND DATE(h.created_at) >= ? AND DATE(h.created_at) <= ? GROUP BY i.drug_name ORDER BY total_qty DESC LIMIT 5", "ss", [$from_date, $to_date]);

// Top 5 thuốc bán chậm nhất (có bán nhưng ít nhất)
$slow5 = pms_fetch_all("SELECT i.drug_name, SUM(i.quantity) total_qty, SUM(i.line_total) total_revenue FROM invoice_item i JOIN invoice_header h ON i.invoice_no = h.invoice_no WHERE h.status<>'cancelled' AND DATE(h.created_at) >= ? AND DATE(h.created_at) <= ? GROUP BY i.drug_name ORDER BY total_qty ASC LIMIT 5", "ss", [$from_date, $to_date]);

// Thuốc không bán gì trong kỳ
$unsold = pms_fetch_all("SELECT d.name AS drug_name FROM drugs d WHERE d.id NOT IN (SELECT DISTINCT ii.drug_id FROM invoice_item ii JOIN invoice_header ih ON ii.invoice_no = ih.invoice_no WHERE ih.status<>'cancelled' AND DATE(ih.created_at) >= ? AND DATE(ih.created_at) <= ?) ORDER BY d.name ASC LIMIT 10", "ss", [$from_date, $to_date]);

$daily_rev = pms_fetch_all("SELECT DATE(created_at) d, SUM(grand_total) total FROM invoice_header WHERE status<>'cancelled' AND DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY DATE(created_at) ORDER BY d ASC", "ss", [$from_date, $to_date]);

pms_render_header('Báo cáo doanh thu & quản trị', $role, 'bao_cao.php', []);
?>
<section class="panel print-no">
    <div class="panel-head">
        <div>
            <h2>📊 Báo cáo Doanh thu & Vận hành</h2>
            <div class="panel-subtitle">Phân tích hiệu suất kinh doanh, thuốc bán chạy/chậm, doanh thu theo phương thức thanh toán.</div>
        </div>
        <div class="actions">
            <a class="btn sm secondary" href="?from=<?= pms_h($from_date) ?>&to=<?= pms_h($to_date) ?>&export=revenue">📥 Xuất CSV doanh thu</a>
            <a class="btn sm secondary" href="?from=<?= pms_h($from_date) ?>&to=<?= pms_h($to_date) ?>&export=top_drugs">📥 Xuất CSV thuốc</a>
            <button class="btn secondary sm" onclick="window.print()">🖨️ In PDF</button>
        </div>
    </div>
    
    <script>
    function changeDateRange(val) {
        const fromEl = document.querySelector('input[name="from"]');
        const toEl = document.querySelector('input[name="to"]');
        const today = new Date();
        
        const formatDate = (date) => {
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        };

        if (val === 'day') {
            fromEl.value = formatDate(today);
            toEl.value = formatDate(today);
        } else if (val === 'month') {
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            fromEl.value = formatDate(firstDay);
            toEl.value = formatDate(lastDay);
        } else if (val === 'year') {
            const firstDay = new Date(today.getFullYear(), 0, 1);
            const lastDay = new Date(today.getFullYear(), 11, 31);
            fromEl.value = formatDate(firstDay);
            toEl.value = formatDate(lastDay);
        }
        
        if (val !== 'custom') {
            document.getElementById('reportFilterForm').submit();
        }
    }
    </script>
    
    <div style="background: #F8FAFC; border: 1px solid var(--pm-card-border); padding: 16px 20px; border-radius: 12px; margin-bottom: 24px;">
        <form id="reportFilterForm" method="get" class="form-grid" style="grid-template-columns: auto 1fr 1fr auto; max-width: 1000px; align-items: end; gap: 16px;">
            <label style="font-size: 13px; font-weight: 600; color: var(--gray-600);">Khoảng thời gian
                <select name="filter_type" onchange="changeDateRange(this.value);" style="padding: 11px 12px; width: 100%; border-radius: 8px; border: 1px solid var(--gray-300); background: #FFF; margin-top: 6px;">
                    <option value="day" <?= $filter_type === 'day' ? 'selected' : '' ?>>Hôm nay</option>
                    <option value="month" <?= $filter_type === 'month' ? 'selected' : '' ?>>Tháng này</option>
                    <option value="year" <?= $filter_type === 'year' ? 'selected' : '' ?>>Năm nay</option>
                    <option value="custom" <?= $filter_type === 'custom' ? 'selected' : '' ?>>Tùy chọn...</option>
                </select>
            </label>
            <label style="font-size: 13px; font-weight: 600; color: var(--gray-600);">Từ ngày 
                <input type="date" name="from" value="<?= pms_h($from_date) ?>" required onchange="document.querySelector('select[name=\'filter_type\']').value='custom';" style="padding: 10px 12px; border-radius: 8px; border: 1px solid var(--gray-300); margin-top: 6px;">
            </label>
            <label style="font-size: 13px; font-weight: 600; color: var(--gray-600);">Đến ngày 
                <input type="date" name="to" value="<?= pms_h($to_date) ?>" required onchange="document.querySelector('select[name=\'filter_type\']').value='custom';" style="padding: 10px 12px; border-radius: 8px; border: 1px solid var(--gray-300); margin-top: 6px;">
            </label>
            <button type="submit" class="btn primary" style="height: 44px; padding: 0 24px; border-radius: 8px; margin-bottom: 0;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="margin-right: 6px; vertical-align: middle;"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Lọc dữ liệu
            </button>
        </form>
    </div>

    <!-- Thống kê tổng hợp: Premium redesigned grid -->
    <div class="ds-stats-row" style="grid-template-columns: repeat(4, 1fr) !important; margin-top: 24px; padding: 0 !important; gap: 24px !important;">
        <!-- Số hóa đơn -->
        <div class="ds-stat-card" style="padding: 24px !important; min-height: 110px; border-radius: 20px !important; border: 1px solid #E2E8F0 !important; background: #fff !important; box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;">
            <div class="ds-stat-icon" style="background: linear-gradient(135deg, #6366F1, #4F46E5); box-shadow: 0 8px 16px rgba(79,70,229,0.2);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            </div>
            <span style="font-size: 11px !important; letter-spacing: 0.08em; font-weight: 700 !important; color: #64748B !important;">SỐ HÓA ĐƠN</span>
            <strong style="font-size: 26px !important; color: #1E293B !important;"><?= number_format($summary['total_invoices'] ?? 0) ?></strong>
        </div>
        
        <!-- Doanh thu gộp -->
        <div class="ds-stat-card" style="padding: 24px !important; min-height: 110px; border-radius: 20px !important; border: 1px solid #E2E8F0 !important; background: #fff !important; box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;">
            <div class="ds-stat-icon" style="background: linear-gradient(135deg, #EC4899, #DB2777); box-shadow: 0 8px 16px rgba(219,39,119,0.2);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line><path d="M16 15h2v2h-2zM12 15h2v2h-2zM8 15h2v2h-2z"></path></svg>
            </div>
            <span style="font-size: 11px !important; letter-spacing: 0.08em; font-weight: 700 !important; color: #64748B !important;">DOANH THU GỘP</span>
            <strong style="font-size: 26px !important; color: #1E293B !important;"><?= pms_currency($gross_revenue) ?></strong>
        </div>

        <!-- DT trung bình/HĐ -->
        <div class="ds-stat-card" style="padding: 24px !important; min-height: 110px; border-radius: 20px !important; border: 1px solid #E2E8F0 !important; background: #fff !important; box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;">
            <div class="ds-stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706); box-shadow: 0 8px 16px rgba(217,119,6,0.2);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline><path d="M12 12h.01"></path></svg>
            </div>
            <span style="font-size: 11px !important; letter-spacing: 0.08em; font-weight: 700 !important; color: #64748B !important;">DOANH THU TB / HĐ</span>
            <strong style="font-size: 26px !important; color: #1E293B !important;"><?= pms_currency((float)($summary['avg_revenue'] ?? 0)) ?></strong>
        </div>

        <!-- Doanh thu ròng -->
        <div class="ds-stat-card" style="padding: 24px !important; min-height: 110px; border-radius: 20px !important; background: linear-gradient(135deg, #F0F9FF, #E0F2FE) !important; border: 1px solid #BAE6FD !important; box-shadow: 0 8px 20px rgba(10, 110, 189, 0.08) !important;">
            <div class="ds-stat-icon" style="background: linear-gradient(135deg, #0EA5E9, #0284C7); box-shadow: 0 8px 16px rgba(2,132,199,0.24);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 6l-9.5 9.5-5-5L1 18"></path><polyline points="17 6 23 6 23 12"></polyline></svg>
            </div>
            <span style="font-size: 11px !important; letter-spacing: 0.08em; font-weight: 800 !important; color: #0369A1 !important;">DOANH THU RÒNG</span>
            <strong style="font-size: 28px !important; color: #0369A1 !important;"><?= pms_currency($net_revenue) ?></strong>
        </div>
    </div>
</section>

<div class="grid-2" style="margin-top: 24px; gap: 24px;">
    <?php
    pms_chart('revenueChart','Biểu đồ doanh thu theo ngày', array_map(fn($x)=>$x['d'],$daily_rev), array_map(fn($x)=>(int)$x['total'],$daily_rev));
    pms_chart('topDrugs','Top 5 thuốc bán chạy nhất', array_map(fn($x)=>$x['drug_name'],$top5), array_map(fn($x)=>(int)$x['total_qty'],$top5));
    ?>
</div>

<div class="grid-2" style="margin-top: 24px; gap: 24px;">
    <!-- Top 5 bán chạy -->
    <section class="panel">
        <div class="panel-head"><h2>🔥 Top 5 thuốc bán chạy</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Tên thuốc</th><th>SL bán</th><th>Doanh thu</th></tr></thead>
                <tbody>
                    <?php foreach ($top5 as $t): ?>
                        <tr>
                            <td><strong><?= pms_h($t['drug_name']) ?></strong></td>
                            <td><span class="badge ok">+ <?= number_format((int)$t['total_qty']) ?></span></td>
                            <td><?= pms_currency((float)$t['total_revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(!$top5): ?><tr><td colspan="3"><div class="empty">Chưa có dữ liệu.</div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Top 5 bán chậm -->
    <section class="panel">
        <div class="panel-head"><h2>🐌 Top 5 thuốc bán chậm</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Tên thuốc</th><th>SL bán</th><th>Doanh thu</th></tr></thead>
                <tbody>
                    <?php foreach ($slow5 as $t): ?>
                        <tr>
                            <td><strong><?= pms_h($t['drug_name']) ?></strong></td>
                            <td><span class="badge warn"><?= number_format((int)$t['total_qty']) ?></span></td>
                            <td><?= pms_currency((float)$t['total_revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(!$slow5): ?><tr><td colspan="3"><div class="empty">Chưa có dữ liệu.</div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($unsold): ?>
        <div style="padding:14px;">
            <h4 style="font-size:13px;color:var(--gray-600);margin-bottom:8px;">📦 Thuốc không bán gì trong kỳ:</h4>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach ($unsold as $u): ?>
                    <span class="badge gray"><?= pms_h($u['drug_name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>

<style type="text/css" media="print">
    .print-no, .sidebar, .topbar { display: none !important; }
    .main-panel { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
    .panel { border: none !important; box-shadow: none !important; }
</style>
<?php pms_render_footer(); ?>