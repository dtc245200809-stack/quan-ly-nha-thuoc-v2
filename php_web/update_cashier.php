<?php
include_once 'app.php';
pms_auth('admin');
pms_log('Hệ thống', 'Truy cập tính năng cũ: update_cashier.php');
pms_flash('Tính năng này đã được gộp. Đang chuyển hướng...', 'info');
pms_redirect('accounts.php');
