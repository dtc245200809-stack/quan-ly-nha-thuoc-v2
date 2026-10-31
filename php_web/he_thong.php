<?php
include_once 'app.php';
pms_auth('admin,manager');
$role = isset($_SESSION['admin_id']) ? 'admin' : 'manager';

$errors = [];

// ── Xử lý POST: Tạo backup ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pms_csrf_verify('he_thong.php');

    if (isset($_POST['backup'])) {
        $res = pms_backup_create(trim($_POST['note'] ?? ''));
        if ($res['ok']) {
            pms_flash($res['message'] . ' Tệp: ' . $res['file']);
        } else {
            $errors[] = $res['message'];
        }
        pms_redirect('he_thong.php');
    }

    if (isset($_POST['delete_backup']) && $role === 'admin') {
        $fileToDelete = basename($_POST['file_name'] ?? '');
        $filePath = __DIR__ . '/backups/' . $fileToDelete;
        if ($fileToDelete && file_exists($filePath)) {
            unlink($filePath);
            pms_exec("DELETE FROM backup_log WHERE file_name=?", 's', [$fileToDelete]);
            pms_log('Sao lưu', "Xóa file backup: $fileToDelete");
            pms_flash("Đã xóa file backup: $fileToDelete");
        } else {
            pms_flash('File backup không tồn tại.', 'error');
        }
        pms_redirect('he_thong.php');
    }
}

// ── Xử lý GET: Tải file backup ──
if (isset($_GET['download']) && $role === 'admin') {
    $file = basename($_GET['download']);
    $path = __DIR__ . '/backups/' . $file;
    if ($file && file_exists($path) && str_ends_with(strtolower($file), '.sql')) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// ── Lấy danh sách backup từ DB + kiểm tra file thực tế ──
$logs = pms_fetch_all("SELECT * FROM backup_log ORDER BY backup_id DESC LIMIT 20");
$backupDir = __DIR__ . '/backups';
foreach ($logs as &$log) {
    $fp = $backupDir . '/' . $log['file_name'];
    $log['file_exists'] = file_exists($fp);
    $log['file_size'] = $log['file_exists'] ? round(filesize($fp) / 1024, 1) : 0;
}
unset($log);

pms_render_header('Hệ thống · Sao lưu dữ liệu', $role, 'he_thong.php', [
    'Tổng bản sao lưu' => count($logs),
]);
?>

<section class="dashboard-grid">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>💾 Sao lưu cơ sở dữ liệu</h2>

            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert error"><?= pms_h(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <?= pms_csrf_field() ?>
            <label style="grid-column: 1 / -1;">Ghi chú sao lưu
                <input type="text" name="note" placeholder="VD: Sao lưu trước khi cập nhật hệ thống" maxlength="200">
            </label>
            <div class="actions" style="grid-column: 1 / -1;">
                <button type="submit" name="backup">💾 Tạo bản sao lưu ngay</button>
            </div>
        </form>
    </section>
</section>

<!-- Lịch sử sao lưu -->
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>📋 Lịch sử sao lưu</h2>
            <div class="panel-subtitle">Danh sách các bản backup đã tạo. Nhấn "Tải về" để download file .sql.</div>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tệp backup</th>
                    <th>Dung lượng</th>
                    <th>Ghi chú</th>
                    <th>Thời gian tạo</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $row): ?>
                    <tr>
                        <td><strong><?= pms_h($row['file_name']) ?></strong></td>
                        <td>
                            <?php if ($row['file_exists']): ?>
                                <span class="badge ok"><?= $row['file_size'] ?> KB</span>
                            <?php else: ?>
                                <span class="badge danger">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= pms_h($row['note'] ?? '') ?></td>
                        <td><?= pms_h(date('d/m/Y H:i:s', strtotime($row['created_at']))) ?></td>
                        <td>
                            <?php if ($row['file_exists']): ?>
                                <span class="badge ok">Có file</span>
                            <?php else: ?>
                                <span class="badge danger">File đã xóa</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if ($row['file_exists'] && $role === 'admin'): ?>
                                    <a class="btn sm secondary" href="?download=<?= urlencode($row['file_name']) ?>">⬇ Tải về</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Xóa file backup này?');">
                                        <?= pms_csrf_field() ?>
                                        <input type="hidden" name="file_name" value="<?= pms_h($row['file_name']) ?>">
                                        <button type="submit" name="delete_backup" class="btn sm danger">🗑 Xóa</button>
                                    </form>
                                <?php elseif (!$row['file_exists']): ?>
                                    <span class="muted">Không khả dụng</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty">Chưa có lịch sử sao lưu. Nhấn "Tạo bản sao lưu" để bắt đầu.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php pms_render_footer(); ?>