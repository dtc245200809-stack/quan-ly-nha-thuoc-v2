<?php
include_once 'app.php';
pms_auth('admin,manager');
$role = isset($_SESSION['admin_id']) ? 'admin' : 'manager';

$error = null;
$restoreResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pms_csrf_verify('restore.php');

    // ── Phương thức 1: Restore từ file upload ──
    if (isset($_POST['restore_upload'])) {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $filename = $_FILES['backup_file']['name'];
            if (!str_ends_with(strtolower($filename), '.sql')) {
                $error = 'Chỉ chấp nhận tệp định dạng .sql';
            } else {
                $tmpPath = $_FILES['backup_file']['tmp_name'];
                $fileSize = round($_FILES['backup_file']['size'] / 1024, 1);

                // Tạo bản sao lưu tự động trước khi restore
                $autoBackup = pms_backup_create('Auto-backup trước khi phục hồi từ: ' . $filename);

                $result = pms_backup_restore($tmpPath);
                if ($result['ok']) {
                    pms_log('Phục hồi dữ liệu', "Phục hồi thành công từ file upload: $filename ({$fileSize}KB). {$result['success']} câu lệnh thực thi, {$result['failed']} lỗi nhỏ.");
                    pms_flash("✅ " . $result['message'] . ($autoBackup['ok'] ? " (Bản sao lưu tự động: {$autoBackup['file']})" : ''));
                    pms_redirect('restore.php');
                } else {
                    $error = $result['message'];
                    pms_log('Phục hồi dữ liệu', "Phục hồi thất bại từ file upload: $filename. Lỗi: {$result['message']}");
                }
            }
        } else {
            $error = 'Vui lòng chọn tệp .sql hợp lệ';
        }
    }

    // ── Phương thức 2: Restore từ file backup trong thư mục ──
    if (isset($_POST['restore_existing'])) {
        $file = basename($_POST['file_name'] ?? '');
        $filePath = __DIR__ . '/backups/' . $file;

        if (!$file || !file_exists($filePath)) {
            $error = 'File backup không tồn tại hoặc đã bị xóa.';
        } elseif (!str_ends_with(strtolower($file), '.sql')) {
            $error = 'File không hợp lệ.';
        } else {
            $fileSize = round(filesize($filePath) / 1024, 1);

            // Tạo bản sao lưu tự động trước khi restore
            $autoBackup = pms_backup_create('Auto-backup trước khi phục hồi từ: ' . $file);

            $result = pms_backup_restore($filePath);
            if ($result['ok']) {
                pms_log('Phục hồi dữ liệu', "Phục hồi thành công từ: $file ({$fileSize}KB). {$result['success']} câu lệnh, {$result['failed']} lỗi nhỏ.");
                pms_flash("✅ " . $result['message'] . ($autoBackup['ok'] ? " (Bản sao lưu tự động: {$autoBackup['file']})" : ''));
                pms_redirect('restore.php');
            } else {
                $error = $result['message'];
                pms_log('Phục hồi dữ liệu', "Phục hồi thất bại từ: $file. Lỗi: {$result['message']}");
            }
        }
    }
}

// ── Lấy danh sách file backup có sẵn ──
$backupDir = __DIR__ . '/backups';
$existingBackups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.sql');
    if ($files) {
        // Sắp xếp mới nhất trước
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        foreach ($files as $f) {
            $existingBackups[] = [
                'name' => basename($f),
                'size' => round(filesize($f) / 1024, 1),
                'date' => date('d/m/Y H:i:s', filemtime($f)),
            ];
        }
    }
}

pms_render_header('Hệ thống · Phục hồi dữ liệu', $role, 'restore.php');
?>

<section class="dashboard-grid">
    <!-- Upload file restore -->
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>📤 Phục hồi từ file tải lên</h2>
                <div class="panel-subtitle">Upload file .sql để khôi phục toàn bộ cơ sở dữ liệu. Hệ thống sẽ tự động tạo bản sao lưu trước khi phục hồi.</div>
            </div>
        </div>

        <div class="alert warn">
            ⚠️ <strong>LƯU Ý DÀNH CHO QUẢN TRỊ:</strong> Phục hồi dữ liệu sẽ ghi đè lên toàn bộ cơ sở dữ liệu hiện tại.
            Hệ thống sẽ tự động tạo bản backup trước khi thực hiện. Vui lòng đảm bảo tệp .sql hoàn toàn đáng tin cậy.
        </div>

        <?php if ($flash = pms_flash()): ?>
            <div class="alert <?= $flash['type'] ?>"><?= pms_h($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= pms_h($error) ?></div>
        <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="form-grid">
                <?= pms_csrf_field() ?>
                <label style="grid-column: 1 / -1;">Tệp dữ liệu (.sql)
                    <input type="file" name="backup_file" accept=".sql" required>
                </label>
                <div class="actions" style="grid-column: 1 / -1;">
                    <button type="submit" name="restore_upload" class="danger"
                            onclick="return confirm('CẢNH BÁO: Bạn có chắc chắn muốn phục hồi dữ liệu từ file này?\n\nHệ thống sẽ tự sao lưu trước khi thực hiện.');">
                        ⚡ Bắt đầu phục hồi
                    </button>
                </div>
            </form>
    </section>
</section>

<?php if ($existingBackups): ?>
<!-- Restore từ file backup có sẵn -->
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>📂 Phục hồi từ backup có sẵn</h2>
            <div class="panel-subtitle">Chọn một file backup trong hệ thống để khôi phục nhanh. Không cần tải lên.</div>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tệp backup</th>
                    <th>Dung lượng</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($existingBackups as $bk): ?>
                    <tr>
                        <td><strong><?= pms_h($bk['name']) ?></strong></td>
                        <td><span class="badge info"><?= $bk['size'] ?> KB</span></td>
                        <td><?= pms_h($bk['date']) ?></td>
                        <td>
                            <form method="post" style="display:inline;"
                                  onsubmit="return confirm('CẢNH BÁO: Phục hồi từ <?= pms_h($bk['name']) ?>?\n\nHệ thống sẽ tự sao lưu trước khi thực hiện.');">
                                <?= pms_csrf_field() ?>
                                <input type="hidden" name="file_name" value="<?= pms_h($bk['name']) ?>">
                                <button type="submit" name="restore_existing" class="btn sm danger">🔄 Phục hồi</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php pms_render_footer(); ?>
