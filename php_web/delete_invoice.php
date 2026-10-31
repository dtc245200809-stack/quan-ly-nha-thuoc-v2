<?php
/**
 * HỦY HÓA ĐƠN CÓ KIỂM SOÁT
 * 
 * Luồng: POST only -> xác thực quyền -> load hóa đơn -> transaction:
 *   1. Hoàn tồn kho theo đúng lô (sale_batch_usage)
 *   2. Hoàn điểm loyalty nếu có
 *   3. Đánh dấu invoice_header.status = 'cancelled'
 *   4. Xóa payment_details tương ứng
 *   5. Ghi system_logs
 * 
 * KHÔNG cho phép GET. Chỉ cho phép admin/manager.
 */
include_once 'app.php';
pms_auth('admin,manager');

// ── Chặn GET: Không bao giờ cho xóa/hủy qua URL trực tiếp ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pms_log('Bảo mật', 'Cố truy cập hủy hóa đơn bằng GET (đã chặn)');
    pms_flash('Không được phép truy cập trực tiếp. Vui lòng thao tác từ giao diện Hóa đơn.', 'error');
    pms_redirect('invoice.php');
}

pms_csrf_verify('invoice.php');

$invoiceNo = (int)($_POST['invoice_no'] ?? 0);
$reason    = trim($_POST['cancel_reason'] ?? '');

if ($invoiceNo <= 0 || $reason === '') {
    pms_flash('Vui lòng nhập lý do hủy hóa đơn.', 'error');
    pms_redirect('invoice.php');
}

// ── Load hóa đơn ──
$header = pms_fetch_one("SELECT * FROM invoice_header WHERE invoice_no=?", 'i', [$invoiceNo]);
if (!$header) {
    pms_flash('Không tìm thấy hóa đơn.', 'error');
    pms_redirect('invoice.php');
}

// ── Kiểm tra đã hủy chưa ──
if (($header['status'] ?? '') === 'cancelled') {
    pms_flash('Hóa đơn ' . $header['invoice_code'] . ' đã bị hủy trước đó.', 'error');
    pms_redirect('invoice.php');
}

// ── Bắt đầu Transaction ──
mysqli_begin_transaction(pms_db());
try {
    $invoiceCode = $header['invoice_code'];
    
    // 1. Hoàn tồn kho theo đúng lô đã xuất (sale_batch_usage)
    $usages = pms_fetch_all(
        "SELECT * FROM sale_batch_usage WHERE invoice_no=?", 'i', [$invoiceNo]
    );
    foreach ($usages as $usage) {
        $qtyUsed = (int)$usage['quantity_used'];
        if ($qtyUsed > 0) {
            pms_exec(
                "UPDATE stock SET quantity = quantity + ? WHERE stock_id=?",
                'ii', [$qtyUsed, $usage['stock_id']]
            );
        }
    }
    // Xóa bản ghi batch usage (đã hoàn kho xong)
    pms_exec("DELETE FROM sale_batch_usage WHERE invoice_no=?", 'i', [$invoiceNo]);
    
    // 2. Hoàn điểm loyalty nếu có
    $customerId = (int)($header['customer_id'] ?? 0);
    $usedPoints = (int)($header['used_points'] ?? 0);
    if ($customerId > 0) {
        // Hoàn lại điểm đã dùng
        if ($usedPoints > 0) {
            pms_exec(
                "UPDATE customers SET loyalty_points = loyalty_points + ? WHERE customer_id=?",
                'ii', [$usedPoints, $customerId]
            );
        }
        // Thu hồi điểm đã tích từ hóa đơn này
        $earnedLogs = pms_fetch_all(
            "SELECT * FROM loyalty_logs WHERE customer_id=? AND invoice_no=? AND points_delta > 0",
            'ii', [$customerId, $invoiceNo]
        );
        foreach ($earnedLogs as $log) {
            pms_exec(
                "UPDATE customers SET loyalty_points = GREATEST(loyalty_points - ?, 0) WHERE customer_id=?",
                'ii', [(int)$log['points_delta'], $customerId]
            );
        }
        // Ghi log loyalty hoàn
        pms_exec(
            "INSERT INTO loyalty_logs (customer_id, invoice_no, points_delta, note) VALUES (?,?,?,?)",
            'iiis', [$customerId, $invoiceNo, 0, 'HỦY HĐ ' . $invoiceCode . ': Đã hoàn điểm. Lý do: ' . $reason]
        );
    }
    
    // 3. Đánh dấu hóa đơn đã hủy (không xóa hàng, chỉ set trạng thái)
    pms_exec(
        "UPDATE invoice_header SET status='cancelled', note=CONCAT(COALESCE(note,''), ' [HỦY: ', ?, ']') WHERE invoice_no=?",
        'si', [$reason, $invoiceNo]
    );
    
    // 4. Xóa bản ghi payment_details
    pms_exec("DELETE FROM payment_details WHERE invoice_no=?", 'i', [$invoiceNo]);
    
    // (BỎ GỠ): 5. KHÔNG XÓA invoice_item để giữ lại chứng từ kế toán.
    // Lệnh Update Status ='cancelled' của Header là đủ để huỷ hóa đơn này.
    
    // 6. Ghi nhật ký hệ thống
    pms_log('Hủy hóa đơn', "Hủy hóa đơn $invoiceCode (ID: $invoiceNo). Lý do: $reason. Đã hoàn kho " . count($usages) . " lô, hoàn $usedPoints điểm.");
    
    mysqli_commit(pms_db());
    pms_flash('Đã hủy hóa đơn ' . $invoiceCode . ' thành công. Tồn kho và điểm thưởng đã được hoàn lại.');
} catch (Throwable $e) {
    mysqli_rollback(pms_db());
    pms_log('Lỗi hệ thống', "Hủy hóa đơn thất bại: " . $e->getMessage());
    pms_flash('Hủy hóa đơn thất bại: ' . $e->getMessage(), 'error');
}
pms_redirect('invoice.php');
