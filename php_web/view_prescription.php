<?php
include_once 'app.php';
pms_auth('admin,manager');
$list = pms_fetch_all("SELECT * FROM prescription ORDER BY prescription_id DESC");
pms_render_header('Tra cứu đơn thuốc', 'manager', 'view_prescription.php', ['Tổng đơn thuốc' => count($list)]);
?>
<section class="panel">
    <div class="table-wrap"><table><thead><tr><th>#</th><th>Thuốc</th><th>Hàm lượng</th><th>Liều dùng</th><th>Số lượng</th></tr></thead><tbody>
    <?php foreach ($list as $row): ?>
        <tr><td><?= (int)$row['prescription_id'] ?></td><td><strong><?= pms_h($row['drug_name']) ?></strong><br><span class="muted">Mã thuốc: <?= (int)$row['drug_id'] ?></span></td><td><?= pms_h($row['strength']) ?></td><td><?= pms_h($row['dose']) ?></td><td><?= number_format((int)$row['quantity']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5"><div class="empty">Chưa có đơn thuốc nào.</div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php pms_render_footer(); ?>