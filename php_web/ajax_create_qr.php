<?php
require_once 'app.php';
require_once 'vietqr_api.php';

$amount = isset($_POST['amount']) ? (int)$_POST['amount'] : (isset($_GET['amount']) ? (int)$_GET['amount'] : 0);
// Tạo mã đơn ngẫu nhiên hoặc truyền từ client
$invoiceCode = isset($_POST['invoice_code']) ? $_POST['invoice_code'] : (isset($_GET['code']) ? $_GET['code'] : 'POS' . time());

$res = pms_create_vietqr($amount, $invoiceCode);

header('Content-Type: application/json');
echo json_encode($res);
