<?php
include_once 'app.php';
pms_auth('admin,manager');
$role = isset($_SESSION['admin_id']) ? 'admin' : 'manager';

$drug_filter = trim($_GET['drug'] ?? '');
$from_date = trim($_GET['from'] ?? date('Y-m-01'));
$to_date = trim($_GET['to'] ?? date('Y-m-t'));

$inputs = [];
$outputs = [];
$stats = ['opening' => 0, 'in' => 0, 'out' => 0, 'closing' => 0];

if ($drug_filter) {
    pms_log("Xem thẻ kho (Xuất nhập tồn)", "Thuốc: $drug_filter từ $from_date đến $to_date");
    
    // 1. Nhập trong kỳ
    $inputs = pms_fetch_all("SELECT s.*, 
        (s.quantity + COALESCE((SELECT SUM(quantity_used) FROM sale_batch_usage WHERE stock_id = s.stock_id), 0)) as original_qty 
        FROM stock s 
        WHERE s.drug_name = ? AND DATE(s.date_supplied) >= ? AND DATE(s.date_supplied) <= ? 
        ORDER BY s.date_supplied DESC", "sss", [$drug_filter, $from_date, $to_date]);
    
    $stats['in'] = array_sum(array_column($inputs, 'original_qty'));

    // 2. Xuất trong kỳ
    $outputs = pms_fetch_all("SELECT su.*, i.created_at, i.invoice_code 
        FROM sale_batch_usage su 
        JOIN invoice_header i ON su.invoice_no = i.invoice_no 
        JOIN stock s ON su.stock_id = s.stock_id 
        WHERE s.drug_name = ? AND i.status <> 'cancelled' AND DATE(i.created_at) >= ? AND DATE(i.created_at) <= ? 
        ORDER BY i.created_at DESC", "sss", [$drug_filter, $from_date, $to_date]);
    
    $stats['out'] = array_sum(array_column($outputs, 'quantity_used'));

    // 3. Tồn hiện tại
    $current_total = pms_fetch_one("SELECT SUM(quantity) as s FROM stock WHERE drug_name = ?", "s", [$drug_filter])['s'] ?? 0;
    $stats['closing'] = $current_total;

    // 4. Tồn đầu kỳ = Tồn cuối + Xuất trong kỳ - Nhập trong kỳ
    $stats['opening'] = $stats['closing'] + $stats['out'] - $stats['in'];
}

// ── Xuất CSV ──
if (isset($_GET['export']) && $drug_filter) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="xnt_' . preg_replace('/[^a-zA-Z0-9]/', '_', $drug_filter) . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($out, ["BÁO CÁO XUẤT - NHẬP - TỒN"]);
    fputcsv($out, ["Thuốc: $drug_filter", "Từ: $from_date", "Đến: $to_date"]);
    fputcsv($out, []);
    fputcsv($out, ['Tồn đầu kỳ', 'Nhập trong kỳ', 'Xuất trong kỳ', 'Tồn cuối kỳ']);
    fputcsv($out, [$stats['opening'], $stats['in'], $stats['out'], $stats['closing']]);
    fputcsv($out, []);
    
    fputcsv($out, ['--- LỊCH SỬ NHẬP LÔ ---']);
    fputcsv($out, ['Ngày cung cấp', 'Số lô', 'Hạn dùng', 'SL nhập', 'SL tồn', 'Nhà cung cấp']);
    foreach ($inputs as $in) {
        fputcsv($out, [
            $in['date_supplied'], $in['batch_no'], $in['expiry_date'],
            $in['original_qty'], $in['quantity'], $in['supplier_name'] ?? ''
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['--- LỊCH SỬ XUẤT BÁN ---']);
    fputcsv($out, ['Ngày xuất', 'Mã HĐ', 'Lô xuất', 'SL xuất']);
    foreach ($outputs as $out_row) {
        fputcsv($out, [
            substr($out_row['created_at'],0,16), $out_row['invoice_code'],
            $out_row['batch_no'], $out_row['quantity_used']
        ]);
    }
    fclose($out);
    exit;
}

$all_drugs = pms_fetch_all("SELECT DISTINCT drug_name FROM stock ORDER BY drug_name ASC");

pms_render_header('Báo cáo · Xuất Nhập Tồn (Thẻ kho)', $role, 'xnt.php');
?>
<section class="panel print-no">
    <div class="panel-head">
        <div>
            <h2>📈 Báo cáo Xuất - Nhập - Tồn (Thẻ kho)</h2>
            <div class="panel-subtitle">Tra cứu chi tiết lịch sử nhập và xuất bán theo thời gian — Tồn đầu kỳ, Nhập, Xuất, Tồn cuối.</div>
        </div>
        <?php if ($drug_filter): ?>
            <a class="btn sm secondary" href="?drug=<?= urlencode($drug_filter) ?>&from=<?= pms_h($from_date) ?>&to=<?= pms_h($to_date) ?>&export=1">📥 Xuất CSV</a>
        <?php endif; ?>
    </div>
    <form method="get" class="form-grid" style="grid-template-columns: 2fr 1fr 1fr auto; max-width: 900px; margin-bottom: 20px; align-items: end;">
        <label>Thuốc cần tra cứu
            <select name="drug" required style="padding:10px;">
                <option value="">-- Chọn thuốc --</option>
                <?php foreach($all_drugs as $d): ?>
                    <option value="<?= pms_h($d['drug_name']) ?>" <?= $d['drug_name'] === $drug_filter ? 'selected' : '' ?>><?= pms_h($d['drug_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Từ ngày <input type="date" name="from" value="<?= pms_h($from_date) ?>" required></label>
        <label>Đến ngày <input type="date" name="to" value="<?= pms_h($to_date) ?>" required></label>
        <button type="submit" class="btn primary" style="height: 42px;">Lọc báo cáo</button>
    </form>
</section>

<?php if ($drug_filter): ?>
<section class="panel">
    <div class="summary-stats-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin-top:20px; margin-bottom:30px;">
        <div class="stat-card" style="background:#f0fdf4;border-color:#bbf7d0;">
            <span class="ds-muted" style="font-size:12px;color:#166534;">Tồn đầu kỳ</span>
            <div style="font-size:24px; font-weight:700; color:#166534;"><?= number_format($stats['opening']) ?></div>
        </div>
        <div class="stat-card">
            <span class="ds-muted" style="font-size:12px;">Nhập trong kỳ</span>
            <div style="font-size:24px; font-weight:700; color:#10b981;">+ <?= number_format($stats['in']) ?></div>
        </div>
        <div class="stat-card">
            <span class="ds-muted" style="font-size:12px;">Xuất (bán) trong kỳ</span>
            <div style="font-size:24px; font-weight:700; color:#ef4444;">- <?= number_format($stats['out']) ?></div>
        </div>
        <div class="stat-card" style="background:var(--blue-50); border-color:var(--blue-200);">
            <span class="ds-muted" style="font-size:12px; color:var(--blue-700);">Tồn cuối kỳ (thực tế)</span>
            <div style="font-size:24px; font-weight:800; color:var(--blue-800);"><?= number_format($stats['closing']) ?></div>
        </div>
    </div>

    <div class="grid-2">
        <div>
            <h3>Lịch sử nhập lô (<?= pms_h(date('d/m/Y', strtotime($from_date))) ?> — <?= pms_h(date('d/m/Y', strtotime($to_date))) ?>)</h3><br>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Ngày</th><th>Số lô</th><th>HSD</th><th>SL nhập</th><th>SL tồn</th><th>NCC</th></tr></thead>
                    <tbody>
                        <?php foreach($inputs as $in): ?>
                            <tr>
                                <td><?= pms_h(date('d/m/Y', strtotime($in['date_supplied']))) ?></td>
                                <td><strong><?= pms_h($in['batch_no']) ?></strong></td>
                                <td><?= pms_h(date('d/m/Y', strtotime($in['expiry_date']))) ?></td>
                                <td><span class="badge ok">+ <?= number_format($in['original_qty']) ?></span></td>
                                <td>Còn: <?= number_format($in['quantity']) ?> <?= pms_h($in['unit'] ?? '') ?></td>
                                <td><?= pms_h($in['supplier_name'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(!$inputs): ?><tr><td colspan="6"><div class="empty">Không có lịch sử nhập trong kỳ.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <h3>Lịch sử xuất bán</h3><br>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Ngày xuất</th><th>Mã HĐ</th><th>Lô xuất</th><th>SL xuất</th></tr></thead>
                    <tbody>
                        <?php foreach($outputs as $out): ?>
                            <tr>
                                <td><?= pms_h(substr($out['created_at'],0,16)) ?></td>
                                <td><a href="invoice.php?view=<?= pms_h($out['invoice_no'] ?? '') ?>" style="color:var(--blue);font-weight:600;"><?= pms_h($out['invoice_code']) ?></a></td>
                                <td><?= pms_h($out['batch_no']) ?></td>
                                <td><span class="badge danger">- <?= pms_h($out['quantity_used']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(!$outputs): ?><tr><td colspan="4" class="muted">Chưa có lịch sử xuất/bán trong kỳ.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
<style type="text/css" media="print">
    .print-no, .sidebar, .topbar { display: none !important; }
    .main-panel { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
    .panel { border: none !important; box-shadow: none !important; }
</style>
<?php pms_render_footer(); ?>
