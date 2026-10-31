<?php
include_once 'app.php';
pms_auth('pharmacist');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $drug_id = (int)($_POST['drug_id'] ?? 0);
    $drug_name = trim($_POST['drug_name'] ?? '');
    $strength = trim($_POST['strength'] ?? '');
    $dose = trim($_POST['dose'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    if (!$drug_id || !$drug_name || !$strength || !$dose || $quantity <= 0) $errors[] = 'Vui lòng nhập đầy đủ thông tin đơn thuốc.';
    if (!$errors) {
        pms_exec("INSERT INTO prescription (drug_id,drug_name,strength,dose,quantity) VALUES (?,?,?,?,?)", 'isssi', [$drug_id, $drug_name, $strength, $dose, $quantity]);
        pms_flash('Đã lưu đơn thuốc mới.');
        pms_redirect('prescription.php');
    }
}
$list = pms_fetch_all("SELECT * FROM prescription ORDER BY prescription_id DESC");
pms_render_header('Quản lý đơn thuốc', 'pharmacist', 'prescription.php', ['Số đơn hiện có' => count($list)]);
?>
<section class="panel"><div class="panel-head"><h2>Thêm đơn thuốc</h2><div class="panel-subtitle">Lưu thông tin để tra cứu lại khi cấp phát thuốc.</div></div>
<?php if ($errors): ?><div class="alert error"><?= pms_h(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="form-grid two">
<label>Mã thuốc<input type="number" name="drug_id" min="1" required></label>
<label>Tên thuốc<input type="text" name="drug_name" required></label>
<label>Hàm lượng<input type="text" name="strength" placeholder="VD: 500 mg" required></label>
<label>Liều dùng<input type="text" name="dose" placeholder="VD: 1 viên x 2 lần/ngày" required></label>
<label>Số lượng<input type="number" name="quantity" min="1" required></label>
<div class="actions" style="grid-column:1/-1;"><button type="submit">Lưu đơn thuốc</button></div>
</form></section>
<section class="panel"><div class="panel-head"><h2>Danh sách đơn thuốc</h2></div><div class="table-wrap"><table><thead><tr><th>#</th><th>Thuốc</th><th>Hàm lượng</th><th>Liều dùng</th><th>Số lượng</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($list as $row): ?>
<tr><td><?= (int)$row['prescription_id'] ?></td><td><strong><?= pms_h($row['drug_name']) ?></strong><br><span class="muted">Mã thuốc: <?= (int)$row['drug_id'] ?></span></td><td><?= pms_h($row['strength']) ?></td><td><?= pms_h($row['dose']) ?></td><td><?= number_format((int)$row['quantity']) ?></td><td><a class="btn danger" href="delete_prescription.php?prescription_id=<?= (int)$row['prescription_id'] ?>" onclick="return confirm('Xóa đơn thuốc này?')">Xóa</a></td></tr>
<?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="6"><div class="empty">Chưa có đơn thuốc nào.</div></td></tr><?php endif; ?>
</tbody></table></div></section>
<?php pms_render_footer(); ?>