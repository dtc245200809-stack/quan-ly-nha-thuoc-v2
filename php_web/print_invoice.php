<?php
include_once 'app.php';
pms_auth('admin,manager,pharmacist,cashier');

$invoiceId = (int)($_GET['id'] ?? 0);
$header = pms_fetch_one("SELECT * FROM invoice_header WHERE invoice_no = ?", 'i', [$invoiceId]);
if (!$header) die('Không tìm thấy hóa đơn.');

$items = pms_fetch_all("SELECT * FROM invoice_item WHERE invoice_no = ?", 'i', [$invoiceId]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>In hóa đơn - <?= pms_h($header['invoice_code']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: #000;
            background: #fff;
            width: 80mm; /* K80 Paper Width */
            margin: 0 auto;
            padding: 5mm;
        }
        .header { text-align: center; margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 8px; }
        .header h1 { font-size: 17px; text-transform: uppercase; margin-bottom: 4px; }
        .header p { font-size: 12px; margin-bottom: 2px; }
        
        .info { margin-bottom: 10px; line-height: 1.5; font-size: 12px; border-bottom: 1px dashed #000; padding-bottom: 6px; }
        .info div { display: flex; justify-content: space-between; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th { text-align: left; border-bottom: 1px solid #000; padding: 4px 0; font-size: 12px; }
        td { padding: 6px 0; vertical-align: top; font-size: 12px; }
        .item-name { font-weight: bold; display: block; }
        .item-meta { font-size: 11px; color: #333; }
        
        .totals { border-top: 1px solid #000; padding-top: 6px; line-height: 1.8; }
        .totals div { display: flex; justify-content: space-between; }
        .grand-total { font-size: 16px; font-weight: 800; margin-top: 6px; padding-top: 4px; border-top: 1px dashed #000; }

        .footer { text-align: center; margin-top: 20px; font-style: italic; font-size: 11px; }
        .barcode { text-align: center; margin-top: 15px; font-family: 'IDAutomationHC39M', 'Libre Barcode 39', cursive; font-size: 24px; }

        @media print {
            @page { margin: 0; }
            body { margin: 0; padding: 5mm; width: 80mm; }
            .no-print { display: none; }
        }
        .no-print {
            background: #2563eb; color: #fff; border: none; padding: 10px 20px;
            border-radius: 6px; font-weight: 600; cursor: pointer;
            margin-bottom: 20px; width: 100%; display: block; text-align: center;
            text-decoration: none; font-family: sans-serif;
        }
    </style>
</head>
<body onload="window.print()">
    <button class="no-print" onclick="window.print()">🖨 BẮT ĐẦU IN (K80)</button>
    <a href="invoice.php" class="no-print" style="background:#64748b">← Quay lại danh sách</a>

    <div class="header">
        <h1>NHÀ THUỐC ĐẠT CHUẨN GPP</h1>
        <p>Địa chỉ: 123 Đường Demo, TP. Hồ Chí Minh</p>
        <p>SĐT: 0900.xxx.xxx - Hotline: 1800.xxxx</p>
    </div>

    <div class="info">
        <div><span>Mã HĐ:</span> <strong><?= pms_h($header['invoice_code']) ?></strong></div>
        <div><span>Ngày:</span> <span><?= date('d/m/Y H:i', strtotime($header['created_at'])) ?></span></div>
        <div><span>Khách hàng:</span> <span><?= pms_h($header['customer_name'] ?: 'Khách lẻ') ?></span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Sản phẩm</th>
                <th style="text-align: center; width: 40px;">SL</th>
                <th style="text-align: right; width: 80px;">T.Tiền</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td>
                    <span class="item-name"><?= pms_h($it['drug_name']) ?></span>
                    <span class="item-meta"><?= pms_currency((float)$it['unit_price']) ?></span>
                </td>
                <td style="text-align: center;"><?= (int)$it['quantity'] ?></td>
                <td style="text-align: right;"><?= pms_currency((float)$it['line_total']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div><span>Cộng tiền hàng:</span> <span><?= pms_currency((float)$header['subtotal']) ?></span></div>
        <?php if ($header['discount'] > 0): ?>
        <div><span>Giảm giá / Điểm:</span> <span>-<?= pms_currency((float)$header['discount']) ?></span></div>
        <?php endif; ?>
        <div class="grand-total">
            <span>TỔNG CỘNG:</span>
            <span><?= pms_currency((float)$header['grand_total']) ?></span>
        </div>
        <div style="font-size: 11px; margin-top: 4px;">
            <span>HT Thanh toán:</span>
            <span><?= pms_h($header['payment_type'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản') ?></span>
        </div>
    </div>

    <div class="footer">
        <p>Cảm ơn quý khách đã tin tưởng!</p>
        <p>Hàng mua rồi miễn đổi trả.</p>
        <p>Chúc quý khách nhiều sức khỏe.</p>
    </div>
</body>
</html>
