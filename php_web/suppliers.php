<?php
include_once 'app.php';
pms_auth('admin,manager');
$role = pms_current_role();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pms_csrf_verify('suppliers.php');
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $note = trim($_POST['note'] ?? '');
        
        if (!$name) {
            $error = 'Tên nhà cung cấp không được để trống';
        } elseif (!pms_validate_phone($phone)) {
            $error = 'Số điện thoại chỉ được chứa số, dấu cách và dấu +';
        } else {
            if ($id > 0) {
                pms_exec("UPDATE suppliers SET name=?, phone=?, address=?, note=? WHERE id=?", "ssssi", [$name, $phone, $address, $note, $id]);
                pms_log('Cập nhật nhà cung cấp', "Sửa thông tin: $name");
                pms_flash('Đã cập nhật nhà cung cấp');
            } else {
                pms_exec("INSERT INTO suppliers (name, phone, address, note) VALUES (?, ?, ?, ?)", "ssss", [$name, $phone, $address, $note]);
                pms_log('Thêm nhà cung cấp', "Thêm mới: $name");
                pms_flash('Đã thêm nhà cung cấp');
            }
            pms_redirect('suppliers.php');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $sup = pms_fetch_one("SELECT name FROM suppliers WHERE id=?", "i", [$id]);
        if ($sup) {
            pms_exec("DELETE FROM suppliers WHERE id=?", "i", [$id]);
            pms_log('Xóa nhà cung cấp', "Xóa: " . $sup['name']);
            pms_flash('Đã xóa nhà cung cấp');
        }
        pms_redirect('suppliers.php');
    }
}

$suppliers = pms_fetch_all("SELECT * FROM suppliers ORDER BY id DESC");
pms_render_header('Danh mục · Nhà cung cấp', $role, 'suppliers.php');
?>
<section class="dashboard-grid">
    <section class="panel">
        <div class="panel-head"><h2>Nhà cung cấp</h2></div>
        <?php if ($flash = pms_flash()): ?><div class="alert <?= $flash['type'] ?>"><?= pms_h($flash['message']) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= pms_h($error) ?></div><?php endif; ?>
        <form method="post" class="form-grid">
            <?= pms_csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="s_id" value="0">
            <label>Tên nhà cung cấp <input type="text" name="name" id="s_name" required></label>
            <label>Điện thoại <input type="text" name="phone" id="s_phone"></label>
            <label style="grid-column: 1 / -1;">Địa chỉ <input type="text" name="address" id="s_address"></label>
            <label style="grid-column: 1 / -1;">Ghi chú <input type="text" name="note" id="s_note"></label>
            <div class="actions" style="grid-column: 1 / -1;">
                <button type="submit" id="btn_save">Thêm mới</button>
                <button type="button" class="btn secondary" onclick="resetForm()">Hủy</button>
            </div>
        </form>
    </section>
</section>

<section class="panel">
    <div class="panel-head"><h2>Danh sách</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Tên nhà cung cấp</th><th>Điện thoại</th><th>Địa chỉ</th><th>Ghi chú</th><th>Hành động</th></tr></thead>
            <tbody>
                <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td>#<?= $s['id'] ?></td>
                        <td><strong><?= pms_h($s['name']) ?></strong></td>
                        <td><?= pms_h($s['phone']) ?></td>
                        <td><?= pms_h($s['address']) ?></td>
                        <td><?= pms_h($s['note']) ?></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn sm secondary" onclick="editRow(<?= $s['id'] ?>, '<?= pms_escape($s['name']) ?>', '<?= pms_escape($s['phone']) ?>', '<?= pms_escape($s['address']) ?>', '<?= pms_escape($s['note']) ?>')">Sửa</button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Chắc chắn xóa?');">
                                    <?= pms_csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn sm danger">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(!$suppliers): ?><tr><td colspan="6"><div class="empty">Chưa có nhà cung cấp nào.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
function editRow(id, name, phone, address, note) {
    document.getElementById('s_id').value = id;
    document.getElementById('s_name').value = name;
    document.getElementById('s_phone').value = phone;
    document.getElementById('s_address').value = address;
    document.getElementById('s_note').value = note;
    document.getElementById('btn_save').innerText = 'Cập nhật';
    document.getElementById('s_name').focus();
    window.scrollTo({top:0, behavior:'smooth'});
}
function resetForm() {
    document.getElementById('s_id').value = '0';
    document.getElementById('s_name').value = '';
    document.getElementById('s_phone').value = '';
    document.getElementById('s_address').value = '';
    document.getElementById('s_note').value = '';
    document.getElementById('btn_save').innerText = 'Thêm mới';
}
</script>
<?php pms_render_footer(); ?>
