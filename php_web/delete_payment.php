<?php
include_once 'app.php';
pms_auth('admin,manager,cashier');
$id = (int)($_GET['payment_id'] ?? 0);
if ($id > 0) {
    pms_log('Bảo mật', "Cảnh báo: Cố tình xóa giao dịch thanh toán ID=$id");
    pms_flash('Tính năng này đã bị khóa. Không được xóa thủ công khoản thu chi.', 'error');
}
pms_redirect('payment.php');
