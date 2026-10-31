<?php
include_once 'app.php';
pms_auth('admin,manager,pharmacist,cashier');
$role = pms_current_role();

$view_id = (int)($_GET['view'] ?? 0);

if ($view_id > 0) {
    $header = pms_fetch_one("SELECT * FROM invoice_header WHERE invoice_no=?", 'i', [$view_id]);
    if (!$header) pms_redirect('invoice.php');
    $items = pms_fetch_all("SELECT * FROM invoice_item WHERE invoice_no=?", 'i', [$view_id]);
    pms_render_header('Chi tiết ' . $header['invoice_code'], $role, 'invoice.php');
    ?>
    <section class="panel print-no">
        <div class="panel-head">
            <div>
                <h2>Chi tiết hóa đơn: <?= pms_h($header['invoice_code']) ?>
                    <?php if (($header['status'] ?? 'active') === 'cancelled'): ?>
                        <span class="badge danger" style="font-size:12px;vertical-align:middle;margin-left:8px;">ĐÃ HỦY</span>
                    <?php endif; ?>
                </h2>
                <div class="panel-subtitle">Ngày tạo: <?= pms_h(date('d/m/Y H:i:s', strtotime($header['created_at']))) ?></div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <a class="btn secondary" href="invoice.php">← Danh sách HĐ</a>
                <button class="btn secondary" onclick="window.open('print_invoice.php?id=<?= $header['invoice_no'] ?>', '_blank', 'width=400,height=600')">🖨 In hóa đơn (K80)</button>
                <?php if (($header['status'] ?? 'active') !== 'cancelled'): ?>
                    <a class="btn primary sm" href="tra_hang.php?invoice_code=<?= urlencode($header['invoice_code']) ?>">↩ Trả hàng</a>
                    <?php if ($role === 'admin' || $role === 'manager'): ?>
                        <button class="btn danger sm" onclick="document.getElementById('cancelModal').style.display='flex'">✕ Hủy HĐ</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php if (($header['status'] ?? 'active') !== 'cancelled' && ($role === 'admin' || $role === 'manager')): ?>
    <div id="cancelModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
        <div style="background:var(--white);border-radius:var(--radius);padding:28px 32px;max-width:440px;width:90%;box-shadow:0 12px 40px rgba(0,0,0,.18);">
            <h3 style="margin-bottom:12px;color:var(--red);">⚠️ Hủy hóa đơn <?= pms_h($header['invoice_code']) ?></h3>
            <p style="font-size:13.5px;color:var(--gray-600);line-height:1.6;margin-bottom:16px;">
                Thao tác này sẽ:<br>
                • Hoàn lại <strong>toàn bộ tồn kho</strong> theo đúng lô đã xuất<br>
                • Hoàn lại <strong>điểm thưởng</strong> nếu có<br>
                • Xóa bản ghi <strong>thanh toán</strong> và chi tiết hóa đơn<br>
                • Đánh dấu hóa đơn là <strong>ĐÃ HỦY</strong>
            </p>
            <form method="post" action="delete_invoice.php">
                <?= pms_csrf_field() ?>
                <input type="hidden" name="invoice_no" value="<?= (int)$header['invoice_no'] ?>">
                <label style="display:block;margin-bottom:14px;">
                    <span style="font-weight:600;font-size:13px;">Lý do hủy <span style="color:var(--red);">*</span></span>
                    <textarea name="cancel_reason" required rows="3" style="width:100%;margin-top:6px;padding:10px;border:1.5px solid var(--gray-300);border-radius:var(--radius);font-size:13px;resize:vertical;" placeholder="VD: Khách đổi ý, nhập sai thuốc, lỗi hệ thống..."></textarea>
                </label>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" class="btn secondary" onclick="document.getElementById('cancelModal').style.display='none'">Đóng</button>
                    <button type="submit" class="btn danger">Xác nhận hủy hóa đơn</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <section class="panel" style="max-width: 600px; margin: 0 auto; padding: 30px; font-family: monospace; color: #000;" id="printable-invoice">
        <div style="text-align: center; margin-bottom: 20px; border-bottom: 1px dashed #000; padding-bottom: 10px;">
            <h2 style="margin-bottom: 4px;">NHÀ THUỐC ĐẠT CHUẨN GPP</h2>
            <p>Địa chỉ: Đường Demo, TP. Demo</p>
            <p>Điện thoại: 0900.000.000</p>
            <br>
            <h3 style="font-size: 20px;">HÓA ĐƠN BÁN LẺ</h3>
            <p>Mã: <strong><?= pms_h($header['invoice_code']) ?></strong> - Lập lúc: <?= pms_h(date('d/m/Y H:i', strtotime($header['created_at']))) ?></p>
        </div>
        
        <div style="margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 10px;">
            <p>KH: <?= pms_h($header['customer_name'] ?: 'Khách lẻ') ?></p>
            <p>HTTT: <?= pms_h($header['payment_type'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản (QR)') ?></p>
            <?php if ($header['used_points'] > 0): ?>
            <p>Trừ điểm: <?= (int)$header['used_points'] ?> điểm (Quy đổi: -<?= number_format($header['used_points'] * 100) ?>đ)</p>
            <?php endif; ?>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 14px;">
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th style="padding: 4px 0; text-align: left;">Sản phẩm</th>
                    <th style="padding: 4px 0; text-align: center;">SL</th>
                    <th style="padding: 4px 0; text-align: right;">Đơn giá</th>
                    <th style="padding: 4px 0; text-align: right;">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td style="padding: 6px 0; border-bottom: 1px dashed #ccc;"><?= pms_h($it['drug_name']) ?></td>
                    <td style="padding: 6px 0; border-bottom: 1px dashed #ccc; text-align: center;"><?= (int)$it['quantity'] ?></td>
                    <td style="padding: 6px 0; border-bottom: 1px dashed #ccc; text-align: right;"><?= number_format((float)$it['unit_price']) ?></td>
                    <td style="padding: 6px 0; border-bottom: 1px dashed #ccc; text-align: right;"><?= number_format((float)$it['line_total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="text-align: right; line-height: 1.8; border-bottom: 1px dashed #000; padding-bottom: 10px;">
            <div>Cộng tiền hàng: <strong><?= number_format((float)$header['subtotal']) ?></strong></div>
            <?php if ($header['discount'] > 0): ?>
            <div>Giảm giá/Tích điểm: <strong>-<?= number_format((float)$header['discount']) ?></strong></div>
            <?php endif; ?>
            <div style="font-size: 18px; margin-top: 8px;">TỔNG THANH TOÁN: <strong><?= number_format((float)$header['grand_total']) ?> đ</strong></div>
        </div>
        
        <div style="text-align: center; margin-top: 20px; font-style: italic; font-size: 13px;">
            <p>Cảm ơn quý khách và hẹn gặp lại!</p>
            <p>Xin trân trọng báo: Hàng xuất miễn đổi trả lại ngoại trừ sai sót hệ thống.</p>
        </div>
    </section>
    <style type="text/css" media="print">
        body { background: white; }
        .print-no, .sidebar, .topbar { display: none !important; }
        .main-panel { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
        .panel { box-shadow: none !important; border: none !important; background: white !important; margin:0 !important; padding: 0 !important; }
        table { font-size: 12px; }
    </style>
    <?php
    pms_render_footer();
    exit;
}

$filter_q = trim($_GET['q'] ?? '');
$from_date = trim($_GET['from'] ?? date('Y-m-01'));
$to_date = trim($_GET['to'] ?? date('Y-m-t'));

$summary = pms_fetch_one("SELECT COUNT(*) total_invoices, COALESCE(SUM(grand_total),0) total_revenue FROM invoice_header WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?", "ss", [$from_date, $to_date]) ?: ['total_invoices' => 0, 'total_revenue' => 0];

$where = "DATE(created_at) >= ? AND DATE(created_at) <= ?";
$params = [$from_date, $to_date];
$types = "ss";
if ($filter_q) {
    $where .= " AND (invoice_code LIKE ? OR customer_name LIKE ?)";
    $qParam = "%$filter_q%";
    $params[] = $qParam;
    $params[] = $qParam;
    $types .= "ss";
}

$list = pms_fetch_all("SELECT * FROM invoice_header WHERE $where ORDER BY invoice_no DESC LIMIT 200", $types, $params);

pms_render_header('Hóa đơn bán hàng', $role, 'invoice.php', [
    'Hóa đơn kỳ này'   => $summary['total_invoices'],
    'Tổng thu kỳ này'  => pms_currency((float)$summary['total_revenue']),
]);
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Tra cứu hóa đơn bán hàng</h2>
            <div class="panel-subtitle">Quản lý lịch sử hóa đơn POS và Cắt liều.</div>
        </div>
        <a class="btn primary sm" href="ban_hang.php">🛒 Mở POS Bán hàng ngay</a>
    </div>
    
    <form method="get" class="form-grid" style="grid-template-columns: 1fr 1fr 2fr auto; margin-bottom: 20px; align-items: end;">
        <label>Từ ngày
            <input type="date" name="from" value="<?= pms_h($from_date) ?>" required>
        </label>
        <label>Đến ngày
            <input type="date" name="to" value="<?= pms_h($to_date) ?>" required>
        </label>
        <label>Khách hàng / Mã HĐ
            <input type="text" name="q" value="<?= pms_h($filter_q) ?>" placeholder="Nguyễn Văn A hoặc HD-...">
        </label>
        <button type="submit" class="btn primary" style="height: 42px;">Tìm kiếm</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Mã hóa đơn</th>
                    <th>Ngày tạo</th>
                    <th>Khách hàng</th>
                    <th>Thanh toán</th>
                    <th>Giảm giá</th>
                    <th>Tổng thanh toán</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $row): ?>
                <?php $isCancelled = ($row['status'] ?? 'active') === 'cancelled'; ?>
                <tr<?= $isCancelled ? ' style="opacity:.55;text-decoration:line-through;"' : '' ?>>
                    <td><a href="?view=<?= $row['invoice_no'] ?>" style="color:var(--blue-brand); font-weight: 600; text-decoration: underline;"><?= pms_h($row['invoice_code']) ?></a></td>
                    <td><?= pms_h(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                    <td><?= pms_h($row['customer_name'] ?: 'Khách lẻ') ?></td>
                    <td><span class="badge gray"><?= $row['payment_type'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản (QR)' ?></span></td>
                    <td><?= pms_currency((float)$row['discount']) ?></td>
                    <td><strong><span class="badge <?= $isCancelled ? 'danger' : 'ok' ?>"><?= pms_currency((float)$row['grand_total']) ?></span></strong></td>
                    <td>
                        <?php if ($isCancelled): ?>
                            <span class="badge danger">Đã hủy</span>
                        <?php else: ?>
                            <span class="badge ok">Hoạt động</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a class="btn sm secondary" href="?view=<?= $row['invoice_no'] ?>">Xem & In</a>
                            <?php if (!$isCancelled): ?>
                                <a class="btn sm primary" href="tra_hang.php?invoice_code=<?= urlencode($row['invoice_code']) ?>">↩ Trả hàng</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?>
                <tr><td colspan="8"><div class="empty">Không tìm thấy thông tin nào khả dụng.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php pms_render_footer(); ?>