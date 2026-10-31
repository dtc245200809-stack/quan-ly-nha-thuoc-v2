<?php
include_once 'app.php';
pms_auth('admin,manager');
$id = (int)($_GET['stock_id'] ?? 0);
if ($id > 0) {
    pms_log('Hệ thống', 'Truy cập tính năng cũ: delete_stock.php');
    pms_flash('Tính năng xóa riêng lẻ này không tương thích với cấu trúc quản lý lô thuốc mới. Hiện tại đã bị khóa.', 'error');
}
pms_redirect('stock.php');
