<?php
include_once 'app.php';
pms_auth('admin,manager');
pms_log('Hệ thống', 'Truy cập tính năng cũ: update_stock.php');
pms_flash('Tính năng này đã được vô hiệu hóa để bảo vệ tính đồng nhất của Kho. Vui lòng dùng mục Quản lý lô.', 'error');
pms_redirect('stock.php');
