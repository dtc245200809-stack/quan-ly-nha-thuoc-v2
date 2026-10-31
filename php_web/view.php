<?php
include_once 'app.php';
pms_auth('admin,manager');
pms_log('Hệ thống', 'Truy cập tính năng cũ: view.php');
pms_flash('Trang tra cứu nhân sự đã được chuyển vào phần Quản trị Tài Khoản.', 'info');
pms_redirect('accounts.php');
$group = $_GET['group'] ?? 'pharmacist';
$allowed = ['pharmacist' => 'Dược sĩ', 'cashier' => 'Thu ngân', 'manager' => 'Quản lý'];
if (!isset($allowed[$group])) $group = 'pharmacist';
$list = pms_fetch_all("SELECT first_name,last_name,staff_id,phone,email,username,postal_address FROM {$group} ORDER BY first_name, last_name");
pms_render_header('Danh bạ nhân sự', 'manager', 'view.php', ['Đang xem' => $allowed[$group], 'Tổng số' => count($list)]);
?>
<section class="panel">
    <div class="segmented">
        <?php foreach ($allowed as $key => $label): ?><a class="<?= $group === $key ? 'active' : '' ?>" href="view.php?group=<?= pms_h($key) ?>"><?= pms_h($label) ?></a><?php endforeach; ?>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Họ tên</th><th>Mã nhân viên</th><th>Điện thoại</th><th>Email</th><th>Tên đăng nhập</th></tr></thead><tbody>
    <?php foreach ($list as $row): ?>
        <tr><td><strong><?= pms_h($row['first_name'] . ' ' . $row['last_name']) ?></strong><br><span class="muted"><?= pms_h($row['postal_address']) ?></span></td><td><?= pms_h($row['staff_id']) ?></td><td><?= pms_h($row['phone']) ?></td><td><?= pms_h($row['email']) ?></td><td><?= pms_h($row['username']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5"><div class="empty">Không có nhân sự nào trong nhóm này.</div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php pms_render_footer(); ?>