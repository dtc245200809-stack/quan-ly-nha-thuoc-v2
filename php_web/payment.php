<?php
include_once 'app.php';
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['manager_id']) && !isset($_SESSION['cashier_id'])) pms_redirect('index.php');
$role = isset($_SESSION['admin_id']) ? 'admin' : (isset($_SESSION['manager_id']) ? 'manager' : 'cashier');
$summary = pms_fetch_one("SELECT COUNT(*) total, COALESCE(SUM(total_ammount),0) amount FROM payment_details") ?: ['total'=>0,'amount'=>0];
$list = pms_fetch_all("SELECT pd.*, ih.invoice_code FROM payment_details pd LEFT JOIN invoice_header ih ON pd.invoice_no = ih.invoice_no ORDER BY pd.payment_id DESC LIMIT 200");
pms_render_header('Quản lý thu chi', $role, 'payment.php', ['Số giao dịch' => $summary['total'], 'Tổng đã thu' => pms_currency((float)$summary['amount'])]);
?>
<section class="panel">
    <div class="panel-head"><h2>Ghi nhận thanh toán</h2></div>
    <div class="empty" style="padding:24px;line-height:1.7;">
        <div style="font-size:2rem;margin-bottom:8px;">🔒</div>
        Để tuân thủ nghiệp vụ chuẩn và quy tắc xuất kho (FEFO), hệ thống <strong>không cho phép</strong> chèn hay xóa khoản thanh toán trực tiếp tại đây.<br>
        Mọi khoản thu chi phải được phát sinh tự động từ màn hình <a href="ban_hang.php" style="color:var(--blue-brand);font-weight:600;">Bán hàng POS</a> hoặc <a href="combo.php" style="color:var(--blue-brand);font-weight:600;">Bán Combo</a>.<br>
        Để hoàn tiền, vui lòng sử dụng <a href="tra_hang.php" style="color:var(--blue-brand);font-weight:600;">Trả hàng</a>.
    </div>
</section>
<section class="panel">
    <div class="panel-head"><h2>Lịch sử thanh toán</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Mã HĐ</th><th>Khách hàng</th><th>Phương thức</th><th>Số tiền</th></tr></thead>
            <tbody>
            <?php foreach ($list as $row): ?>
                <tr>
                    <td><?= (int)$row['payment_id'] ?></td>
                    <td><?= pms_h($row['invoice_code'] ?? ('HĐ-' . $row['invoice_no'])) ?></td>
                    <td><?= pms_h($row['customer_name']) ?></td>
                    <td><span class="badge info"><?= pms_h($row['payment_type'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản') ?></span></td>
                    <td><?= pms_currency((float)$row['total_ammount']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="5"><div class="empty">Chưa có giao dịch thanh toán nào.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php pms_render_footer(); ?>