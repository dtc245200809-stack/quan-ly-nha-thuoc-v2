<?php
include_once 'app.php';
pms_auth('admin');
$id = (int)($_GET['pharmacist_id'] ?? 0);
if ($id > 0) {
    pms_log('Hệ thống', 'Truy cập tính năng cũ: delete_pharmacist.php');
    pms_flash('Tính năng xóa riêng lẻ đã được gộp. Đang chuyển hướng...', 'info');
}
pms_redirect('accounts.php');
