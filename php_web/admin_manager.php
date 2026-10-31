<?php
include_once 'app.php';
pms_auth('admin');

pms_log('Hệ thống', 'Truy cập tính năng cũ: admin_manager.php');
pms_flash('Tính năng quản trị riêng lẻ đã được gộp. Đang chuyển hướng...', 'info');
pms_redirect('accounts.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $staff = trim($_POST['staff_id'] ?? '');
    $address = trim($_POST['postal_address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!$first || !$last || !$staff || !$address || !$phone || !$email || !$username) $errors[] = 'Vui lòng điền đầy đủ các trường bắt buộc.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Vui lòng nhập email hợp lệ.';

    if (!$errors) {
        $existing = pms_fetch_one("SELECT manager_id FROM manager WHERE username = ? AND manager_id <> ? LIMIT 1", 'si', [$username, $id]);
        if ($existing) $errors[] = 'Tên đăng nhập đã tồn tại.';
    }

    if (!$errors) {
        if ($id > 0) {
            $current = pms_fetch_one("SELECT password FROM manager WHERE manager_id = ?", 'i', [$id]);
            $finalPassword = $current['password'] ?? '';
            if ($password !== '') $finalPassword = password_hash($password, PASSWORD_DEFAULT);
            pms_exec("UPDATE manager SET first_name=?, last_name=?, staff_id=?, postal_address=?, phone=?, email=?, username=?, password=? WHERE manager_id=?", 'ssssssssi', [$first, $last, $staff, $address, $phone, $email, $username, $finalPassword, $id]);
            pms_flash('Đã cập nhật tài khoản quản lý.');
        } else {
            if ($password === '') $errors[] = 'Mật khẩu là bắt buộc khi tạo tài khoản mới.';
            else {
                pms_exec("INSERT INTO manager (first_name,last_name,staff_id,postal_address,phone,email,username,password,date) VALUES (?,?,?,?,?,?,?,?,NOW())", 'ssssssss', [$first, $last, $staff, $address, $phone, $email, $username, password_hash($password, PASSWORD_DEFAULT)]);
                pms_flash('Đã tạo tài khoản quản lý mới.');
            }
        }
    }

    if (!$errors) pms_redirect('admin_manager.php');
    $editing = $id > 0;
}

$record = ['manager_id' => '', 'first_name' => '', 'last_name' => '', 'staff_id' => '', 'postal_address' => '', 'phone' => '', 'email' => '', 'username' => ''];
if ($editing) $record = pms_fetch_one("SELECT * FROM manager WHERE manager_id = ?", 'i', [$id]) ?: $record;
$list = pms_fetch_all("SELECT * FROM manager ORDER BY date DESC, manager_id DESC");
pms_render_header('Admin • Quản lý', 'admin', 'admin_manager.php', ['Tổng quản lý' => count($list)]);
?>
<section class="panel">
    <div class="panel-head"><h2><?= $editing ? 'Chỉnh sửa quản lý' : 'Tạo tài khoản quản lý mới' ?></h2></div>
    <?php if ($errors): ?><div class="alert error"><?= pms_h(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post" class="form-grid two">
        <input type="hidden" name="id" value="<?= (int)($record['manager_id'] ?? 0) ?>">
        <label>Tên<input type="text" name="first_name" value="<?= pms_h($record['first_name'] ?? ($_POST['first_name'] ?? '')) ?>" required></label>
        <label>Họ<input type="text" name="last_name" value="<?= pms_h($record['last_name'] ?? ($_POST['last_name'] ?? '')) ?>" required></label>
        <label>Mã nhân viên<input type="text" name="staff_id" value="<?= pms_h($record['staff_id'] ?? ($_POST['staff_id'] ?? '')) ?>" required></label>
        <label>Điện thoại<input type="text" name="phone" value="<?= pms_h($record['phone'] ?? ($_POST['phone'] ?? '')) ?>" required></label>
        <label>Email<input type="email" name="email" value="<?= pms_h($record['email'] ?? ($_POST['email'] ?? '')) ?>" required></label>
        <label>Tên đăng nhập<input type="text" name="username" value="<?= pms_h($record['username'] ?? ($_POST['username'] ?? '')) ?>" required></label>
        <label style="grid-column:1/-1;">Địa chỉ<input type="text" name="postal_address" value="<?= pms_h($record['postal_address'] ?? ($_POST['postal_address'] ?? '')) ?>" required></label>
        <label style="grid-column:1/-1;">Mật khẩu <?= $editing ? '<span class="muted">(để trống nếu muốn giữ nguyên)</span>' : '' ?><input type="password" name="password" <?= $editing ? '' : 'required' ?>></label>
        <div class="actions" style="grid-column:1/-1;">
            <button type="submit"><?= $editing ? 'Lưu thay đổi' : 'Tạo tài khoản' ?></button>
            <?php if ($editing): ?><a class="btn secondary" href="admin_manager.php">Hủy chỉnh sửa</a><?php endif; ?>
        </div>
    </form>
</section>
<section class="panel">
    <div class="panel-head"><h2>Danh sách quản lý</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Họ tên</th><th>Mã NV</th><th>Điện thoại</th><th>Email</th><th>Tên đăng nhập</th><th>Thao tác</th></tr></thead>
            <tbody>
                <?php foreach ($list as $row): ?>
                    <tr>
                        <td><strong><?= pms_h($row['first_name'] . ' ' . $row['last_name']) ?></strong><br><span class="muted"><?= pms_h($row['postal_address']) ?></span></td>
                        <td><?= pms_h($row['staff_id']) ?></td>
                        <td><?= pms_h($row['phone']) ?></td>
                        <td><?= pms_h($row['email']) ?></td>
                        <td><?= pms_h($row['username']) ?></td>
                        <td><div class="actions"><a class="btn secondary" href="admin_manager.php?edit=<?= (int)$row['manager_id'] ?>">Sửa</a><a class="btn danger" href="delete_manager.php?manager_id=<?= (int)$row['manager_id'] ?>" onclick="return confirm('Xóa quản lý này?')">Xóa</a></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$list): ?><tr><td colspan="6"><div class="empty">Chưa có tài khoản quản lý nào.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php pms_render_footer(); ?>