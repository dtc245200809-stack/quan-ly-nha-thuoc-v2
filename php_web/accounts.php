<?php
include_once 'app.php';
pms_auth('admin,manager');
$role = 'admin';

$error = null;
$allowed_roles = ['admin', 'manager', 'cashier', 'pharmacist'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    pms_csrf_verify('accounts.php');
    
    if ($_POST['action'] === 'save') {
        $acc_role = $_POST['acc_role'];
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!pms_validate_in($acc_role, $allowed_roles)) {
            $error = "Phân quyền không hợp lệ.";
        } elseif (!$username) {
            $error = "Tên đăng nhập không được để trống.";
        } elseif (mb_strlen($password) < 6) {
            $error = "Mật khẩu phải có ít nhất 6 ký tự.";
        } else {
            $is_duplicate = false;
            foreach ($allowed_roles as $tbl) {
                if (pms_fetch_one("SELECT username FROM $tbl WHERE username=?", 's', [$username])) {
                    $is_duplicate = true;
                    $error = "Tên đăng nhập ($username) đã được sử dụng ở nhóm $tbl. Vui lòng chọn tên khác.";
                    break;
                }
            }
            if ($is_duplicate) {
                // error is set, do nothing
            } else {
                $pw_hash = password_hash($password, PASSWORD_DEFAULT);
                if ($acc_role === 'admin') {
                    pms_exec("INSERT INTO admin (username, password, first_name, last_name) VALUES (?,?,?,?)", 'ssss', [$username, $pw_hash, $first_name, $last_name]);
                } else {
                    pms_exec("INSERT INTO $acc_role (username, password, first_name, last_name, phone, staff_id, postal_address, email) VALUES (?,?,?,?,?,?,?,?)", 'ssssssss', [$username, $pw_hash, $first_name, $last_name, $phone, 'SYS-G', '', '']);
                }
                pms_log('Hệ thống', "Thêm tài khoản $username ($acc_role)");
                pms_flash("Đã thêm tài khoản $username vào nhóm $acc_role");
                pms_redirect('accounts.php');
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $acc_role = $_POST['acc_role'];
        if (!pms_validate_in($acc_role, $allowed_roles)) {
            pms_flash('Phân quyền không hợp lệ.', 'error');
            pms_redirect('accounts.php');
        }
        $id = (int)$_POST['id'];
        $col = $acc_role . '_id';
        pms_exec("DELETE FROM $acc_role WHERE $col=?", 'i', [$id]);
        pms_log('Hệ thống', "Xóa tài khoản ID $id ($acc_role)");
        pms_flash("Đã xóa tài khoản.");
        pms_redirect('accounts.php');
    } elseif ($_POST['action'] === 'lock') {
        $acc_role = $_POST['acc_role'];
        if (!pms_validate_in($acc_role, $allowed_roles)) {
            pms_flash('Phân quyền không hợp lệ.', 'error');
            pms_redirect('accounts.php');
        }
        $id = (int)$_POST['id'];
        $col = $acc_role . '_id';
        pms_exec("UPDATE $acc_role SET is_locked=1 WHERE $col=?", 'i', [$id]);
        pms_log('Hệ thống', "Khóa tài khoản ID $id ($acc_role)");
        pms_flash("Đã khóa tài khoản.");
        pms_redirect('accounts.php');
    } elseif ($_POST['action'] === 'unlock') {
        $acc_role = $_POST['acc_role'];
        if (!pms_validate_in($acc_role, $allowed_roles)) {
            pms_flash('Phân quyền không hợp lệ.', 'error');
            pms_redirect('accounts.php');
        }
        $id = (int)$_POST['id'];
        $col = $acc_role . '_id';
        pms_exec("UPDATE $acc_role SET is_locked=0 WHERE $col=?", 'i', [$id]);
        pms_log('Hệ thống', "Mở khóa tài khoản ID $id ($acc_role)");
        pms_flash("Đã mở khóa tài khoản.");
        pms_redirect('accounts.php');
    } elseif ($_POST['action'] === 'reset_password') {
        $acc_role = $_POST['acc_role'];
        if (!pms_validate_in($acc_role, $allowed_roles)) {
            pms_flash('Phân quyền không hợp lệ.', 'error');
            pms_redirect('accounts.php');
        }
        $id = (int)$_POST['id'];
        $new_pw = trim($_POST['new_password'] ?? '');
        if (mb_strlen($new_pw) < 6) {
            pms_flash('Mật khẩu mới phải có ít nhất 6 ký tự.', 'error');
            pms_redirect('accounts.php');
        }
        $col = $acc_role . '_id';
        $pw_hash = password_hash($new_pw, PASSWORD_DEFAULT);
        pms_exec("UPDATE $acc_role SET password=? WHERE $col=?", 'si', [$pw_hash, $id]);
        pms_log('Hệ thống', "Reset mật khẩu tài khoản ID $id ($acc_role)");
        pms_flash("Đã đặt lại mật khẩu thành công.");
        pms_redirect('accounts.php');
    }
}

$admins = pms_fetch_all("SELECT admin_id as id, username, first_name, last_name, 'Quản trị viên' as type_name, 'admin' as type_key, is_locked FROM admin");
$managers = pms_fetch_all("SELECT manager_id as id, username, first_name, last_name, 'Quản lý' as type_name, 'manager' as type_key, is_locked FROM manager");
$cashiers = pms_fetch_all("SELECT cashier_id as id, username, first_name, last_name, 'Dược sĩ / NV Bán hàng (Thu ngân)' as type_name, 'cashier' as type_key, is_locked FROM cashier");
$pharmacists = pms_fetch_all("SELECT pharmacist_id as id, username, first_name, last_name, 'Dược sĩ / NV Bán hàng (Dược sĩ)' as type_name, 'pharmacist' as type_key, is_locked FROM pharmacist");
$all = array_merge($admins, $managers, $cashiers, $pharmacists);

$active_count = count(array_filter($all, fn($u) => !(int)($u['is_locked'] ?? 0)));
$locked_count = count($all) - $active_count;

pms_render_header('Hệ thống · Tài khoản & Phân quyền', $role, 'accounts.php', [
    'Tổng tài khoản' => count($all),
    'Đang hoạt động' => $active_count,
    'Bị khóa' => $locked_count,
]);
?>

<section class="dashboard-grid">
    <section class="panel">
        <div class="panel-head"><h2>👤 Cấp quyền tài khoản mới</h2></div>
        <?php if ($error): ?><div class="alert error" style="margin-bottom: 20px;"><?= pms_h($error) ?></div><?php endif; ?>
        <form method="post" class="form-grid two">
            <?= pms_csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <label>Phân quyền
                <select name="acc_role">
                    <optgroup label="Nhóm: Dược sĩ / Nhân viên bán hàng">
                        <option value="pharmacist">Dược sĩ (Nghiệp vụ, Tư vấn)</option>
                        <option value="cashier">Nhân viên bán hàng (Thu ngân)</option>
                    </optgroup>
                    <optgroup label="Nhóm: Quản lý hệ thống">
                        <option value="manager">Quản lý (Báo cáo tổng hợp)</option>
                        <option value="admin">Admin Hệ thống (Toàn quyền)</option>
                    </optgroup>
                </select>
            </label>
            <label>Tên đăng nhập <input type="text" name="username" required placeholder="VD: nguyenvana"></label>
            <label>Mật khẩu <input type="password" name="password" required minlength="6" placeholder="Tối thiểu 6 ký tự (sẽ được băm bảo mật)"></label>
            <label>Số điện thoại <input type="text" name="phone" placeholder="0901234567"></label>
            <label>Họ lót <input type="text" name="first_name" placeholder="Ví dụ: Nguyễn Văn"></label>
            <label>Tên <input type="text" name="last_name" placeholder="Ví dụ: An"></label>
            <div class="actions" style="grid-column: 1 / -1; margin-top: 10px;">
                <button type="submit">Cấp tài khoản</button>
            </div>
        </form>
    </section>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Danh sách người dùng (<?= count($all) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Phân quyền</th><th>Tài khoản</th><th>Họ tên</th><th>Trạng thái</th><th>Thao tác quản trị</th>
            </tr></thead>
            <tbody>
                <?php foreach ($all as $u): 
                    $isLocked = (int)($u['is_locked'] ?? 0);
                    $isSuperAdmin = $u['username'] === 'admin' && $u['type_key'] === 'admin';
                ?>
                    <tr <?= $isLocked ? 'style="opacity:0.6;background:#FFF5F5"' : '' ?>>
                        <td><span class="badge <?= $u['type_key'] === 'admin' ? 'danger' : ($u['type_key'] === 'manager' ? 'warn' : 'info') ?>"><?= pms_h($u['type_name']) ?></span></td>
                        <td><strong style="color:var(--pm-blue)"><?= pms_h($u['username']) ?></strong></td>
                        <td><span style="font-weight:500;"><?= pms_h(trim($u['first_name'] . ' ' . $u['last_name'])) ?></span></td>
                        <td>
                            <?php if ($isLocked): ?>
                                <span class="badge danger">🔒 Đã khóa</span>
                            <?php else: ?>
                                <span class="badge ok">✅ Hoạt động</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isSuperAdmin): ?>
                            <div class="actions" style="margin-top:0;">
                                <?php if ($isLocked): ?>
                                    <form method="post" style="display:inline">
                                        <?= pms_csrf_field() ?>
                                        <input type="hidden" name="action" value="unlock">
                                        <input type="hidden" name="acc_role" value="<?= $u['type_key'] ?>">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn sm" style="background:#48BB78;" title="Mở khóa tài khoản này" onclick="return confirm('Mở khóa tài khoản này?')">Mở khóa</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline">
                                        <?= pms_csrf_field() ?>
                                        <input type="hidden" name="action" value="lock">
                                        <input type="hidden" name="acc_role" value="<?= $u['type_key'] ?>">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn sm warn" style="background:#ED8936;color:#fff;" title="Khóa tài khoản này. Người dùng sẽ bị thoát ra." onclick="return confirm('Khóa tài khoản này? Người dùng sẽ không thể đăng nhập.')">Khóa</button>
                                    </form>
                                <?php endif; ?>

                                <button type="button" class="btn sm secondary" onclick="togglePwReset('pw-<?= $u['type_key'] . $u['id'] ?>')">Đổi MK</button>

                                <form method="post" style="display:inline" onsubmit="return confirm('Thu hồi và XÓA VĨNH VIỄN tài khoản này?');">
                                    <?= pms_csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="acc_role" value="<?= $u['type_key'] ?>">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn sm danger">Xóa</button>
                                </form>
                            </div>
                            <div class="pw-reset-box" id="pw-<?= $u['type_key'] . $u['id'] ?>" style="display:none; margin-top: 12px;">
                                <form method="post" class="form-grid" style="grid-template-columns: 1fr auto; gap: 8px; max-width: 320px;">
                                    <?= pms_csrf_field() ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="acc_role" value="<?= $u['type_key'] ?>">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <input type="password" name="new_password" placeholder="MK mới (≥6 ký tự)" required minlength="6">
                                    <button type="submit" class="btn primary" onclick="return confirm('Cập nhật mật khẩu?')">Lưu</button>
                                </form>
                            </div>
                            <?php else: ?>
                            <span class="badge" style="background:#E2E8F0;color:#4A5568;">Super Admin Không thể sửa</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
function togglePwReset(id) {
    const el = document.getElementById(id);
    if(el.style.display === 'none') {
        el.style.display = 'block';
        el.style.animation = 'pmSlideUp 0.3s ease';
        el.querySelector('input').focus();
    } else {
        el.style.display = 'none';
    }
}
</script>
<?php pms_render_footer(); ?>
