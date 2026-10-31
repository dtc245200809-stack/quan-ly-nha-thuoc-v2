<?php
include_once 'app.php';
pms_auth('admin,manager');

$purchaseId = (int)($_GET['id'] ?? 0);
$header = pms_fetch_one("SELECT * FROM purchase_header WHERE purchase_id=?", 'i', [$purchaseId]);
if (!$header) die('Không tìm thấy phiếu nhập.');

$items = pms_fetch_all("SELECT * FROM purchase_item WHERE purchase_id=?", 'i', [$purchaseId]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Phiếu nhập kho - <?= pms_h($header['purchase_code']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px; color: #000; background: #fff;
            max-width: 800px; margin: 0 auto; padding: 20px;
        }
        .header { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { font-size: 18px; text-transform: uppercase; margin-bottom: 4px; }
        .header p { font-size: 12px; margin-bottom: 2px; }

        .info { margin-bottom: 14px; line-height: 1.8; font-size: 12px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; }
        .info-grid span:first-child { font-weight: bold; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { padding: 6px 8px; border: 1px solid #000; font-size: 12px; }
        th { background: #f0f0f0; text-align: center; font-weight: bold; }
        td { vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .totals { text-align: right; font-size: 14px; font-weight: bold; margin-bottom: 20px; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; text-align: center; margin-top: 40px; }
        .signatures div { font-size: 12px; }
        .signatures strong { display: block; margin-bottom: 60px; }

        .no-print {
            background: #2563eb; color: #fff; border: none; padding: 10px 20px;
            border-radius: 6px; font-weight: 600; cursor: pointer;
            margin-bottom: 20px; width: 100%; display: block; text-align: center;
            text-decoration: none; font-family: sans-serif;
        }
        @media print {
            @page { margin: 10mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">🖨 IN PHIẾU NHẬP KHO</button>
    <a href="stock.php?tab=history" class="no-print" style="background:#64748b">← Quay lại</a>

    <div class="header">
        <h1>NHÀ THUỐC ĐẠT CHUẨN GPP</h1>
        <p>Địa chỉ: 123 Đường Demo, TP. Hồ Chí Minh</p>
        <p>SĐT: 0900.xxx.xxx</p>
        <br>
        <h2 style="font-size: 16px;">PHIẾU NHẬP KHO</h2>
        <p>Mã phiếu: <strong><?= pms_h($header['purchase_code']) ?></strong></p>
    </div>

    <div class="info">
        <div class="info-grid">
            <div><span>Nhà cung cấp:</span> <?= pms_h($header['supplier_name']) ?></div>
            <div><span>Ngày nhập:</span> <?= date('d/m/Y H:i', strtotime($header['created_at'])) ?></div>
            <div><span>Số chứng từ:</span> <?= pms_h($header['document_no'] ?: '—') ?></div>
            <div><span>Người lập:</span> <?= pms_h($header['created_by'] ?: '—') ?></div>
            <?php if ($header['note']): ?>
            <div style="grid-column:1/-1"><span>Ghi chú:</span> <?= pms_h($header['note']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>STT</th>
                <th>Tên thuốc</th>
                <th>Mã lô</th>
                <th>NSX</th>
                <th>HSD</th>
                <th>SL</th>
                <th>Đơn giá nhập</th>
                <th>Giá bán</th>
                <th>Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $it): ?>
            <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td><strong><?= pms_h($it['drug_name']) ?></strong></td>
                <td class="text-center"><?= pms_h($it['batch_no']) ?></td>
                <td class="text-center"><?= $it['mfg_date'] ? date('d/m/Y', strtotime($it['mfg_date'])) : '—' ?></td>
                <td class="text-center"><?= date('d/m/Y', strtotime($it['expiry_date'])) ?></td>
                <td class="text-center"><?= number_format((int)$it['quantity']) ?></td>
                <td class="text-right"><?= number_format((float)$it['purchase_price']) ?></td>
                <td class="text-right"><?= number_format((float)$it['sale_price']) ?></td>
                <td class="text-right"><?= number_format((float)$it['line_total']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        TỔNG CỘNG: <?= number_format((float)$header['total_amount']) ?> đ
    </div>

    <div class="signatures">
        <div>
            <strong>Người giao hàng</strong>
            (Ký, ghi rõ họ tên)
        </div>
        <div>
            <strong>Thủ kho</strong>
            (Ký, ghi rõ họ tên)
        </div>
        <div>
            <strong>Người lập phiếu</strong>
            <?= pms_h($header['created_by'] ?: '') ?>
        </div>
    </div>

</body>
</html>
