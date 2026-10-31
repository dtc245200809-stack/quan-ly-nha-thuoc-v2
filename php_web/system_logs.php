<?php
include_once 'app.php';
pms_auth('admin,manager');
$role = isset($_SESSION['admin_id']) ? 'admin' : 'manager';

$logs = pms_fetch_all("SELECT * FROM system_logs ORDER BY id DESC LIMIT 500");
pms_render_header('Hệ thống · Nhật ký hoạt động', $role, 'system_logs.php', ['Logs' => count($logs)]);
?>
<section class="panel">
    <div class="panel-head"><h2>Nhật ký hệ thống (500 lượt gần nhất)</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Thời gian</th><th>Tài khoản</th><th>IP Address</th><th>Hành động</th><th>Chi tiết</th></tr></thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td>#<?= $l['id'] ?></td>
                        <td><?= pms_h($l['created_at']) ?></td>
                        <td><span class="badge gray"><?= pms_h($l['username']) ?></span></td>
                        <td class="muted"><?= pms_h($l['ip_address']) ?></td>
                        <td><strong><?= pms_h($l['action']) ?></strong></td>
                        <td><small><?= pms_h($l['details']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                <?php if(!$logs): ?><tr><td colspan="6"><div class="empty">Chưa có hoạt động nào được ghi nhận.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php pms_render_footer(); ?>
