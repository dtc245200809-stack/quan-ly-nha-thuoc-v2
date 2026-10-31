<?php
include_once 'app.php';
$role = pms_current_role();
if (!$role) pms_redirect('index.php');

$user_id_key = [
    'admin' => 'admin_id',
    'manager' => 'manager_id',
    'pharmacist_cashier' => isset($_SESSION['pharmacist_id']) ? 'pharmacist_id' : 'cashier_id'
][$role];

$table = [
    'admin' => 'admin',
    'manager' => 'manager',
    'pharmacist_cashier' => isset($_SESSION['pharmacist_id']) ? 'pharmacist' : 'cashier'
][$role];

$user_id = $_SESSION[$user_id_key];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_profile'])) {
    pms_csrf_verify('profile.php');
    $avatar = $_POST['avatar_file'] ?? ($_SESSION['avatar'] ?? '01.jpg');
    $notes = $_POST['notes'] ?? '';
    
    // Security check for avatar
    $avatar = basename($avatar);
    if (!file_exists(__DIR__ . '/avatar/' . $avatar)) $avatar = '01.jpg';

    pms_exec("UPDATE `$table` SET avatar = ?, notes = ? WHERE `$user_id_key` = ?", "ssi", [$avatar, $notes, $user_id]);
    $_SESSION['avatar'] = $avatar;
    $_SESSION['notes'] = $notes;
    pms_flash("Đã cập nhật hồ sơ thành công!");
    pms_redirect('profile.php');
}

$avatars = glob(__DIR__ . '/avatar/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
$avatars = array_map('basename', $avatars);

pms_render_header('Hồ sơ cá nhân', $role, 'profile.php');
?>

<div class="profile-layout">
    <div class="ds-panel">
        <div class="ds-panel__head">
            <h2>Cài đặt tài khoản</h2>
            <div class="ds-panel__subtitle">Tùy chỉnh thông tin cá nhân và ảnh đại diện của bạn.</div>
        </div>
        
        <div class="profile-content">
            <!-- Current Profile Summary -->
            <div class="profile-summary">
                <div class="profile-main-avatar">
                    <?php if (isset($_SESSION['avatar']) && $_SESSION['avatar']): ?>
                        <img src="avatar/<?= pms_h($_SESSION['avatar']) ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="avatar-placeholder"><?= strtoupper(substr($_SESSION['username'], 0, 2)) ?></div>
                    <?php endif; ?>
                </div>
                <div class="profile-details">
                    <h3><?= pms_h($_SESSION['username']) ?></h3>
                    <p class="role-badge"><?= pms_h(pms_role_name()) ?></p>
                    <div class="info-grid">
                        <?php if (isset($_SESSION['first_name'])): ?>
                            <div class="info-item">
                                <span>Họ tên:</span>
                                <strong><?= pms_h($_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? '')) ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['staff_id'])): ?>
                            <div class="info-item">
                                <span>Mã nhân viên:</span>
                                <strong><?= pms_h($_SESSION['staff_id']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-notes-display">
                        <span>Ghi chú:</span>
                        <p><?= nl2br(pms_h($_SESSION['notes'] ?? 'Chưa có ghi chú nào.')) ?></p>
                    </div>
                </div>
            </div>

            <!-- Avatar Selection -->
            <div class="avatar-selection-section">

                
                <form method="POST" id="profileForm">
                    <?= pms_csrf_field() ?>
                    <input type="hidden" name="set_profile" value="1">
                    <input type="hidden" name="avatar_file" id="selectedAvatar" value="<?= pms_h($_SESSION['avatar'] ?? '') ?>">
                    
                    <div class="notes-field" style="margin-bottom: 24px;">
                        <label for="notesInput" style="display:block; margin-bottom:8px; font-weight:700; color:#1e293b;">Ghi chú cá nhân</label>
                        <textarea id="notesInput" name="notes" placeholder="Nhập ghi chú hoặc nhắc nhở công việc tại đây..." style="width:100%; min-height:100px; padding:12px; border-radius:12px; border:1px solid #e2e8f0; background:#f8fafc; font-family:inherit; font-size:14px; outline:none; transition:border-color 0.2s;"><?= pms_h($_SESSION['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="avatar-grid">
                        <?php foreach ($avatars as $av): 
                            $isSelected = (isset($_SESSION['avatar']) && $_SESSION['avatar'] === $av);
                        ?>
                            <div class="avatar-option <?= $isSelected ? 'selected' : '' ?>" 
                                 onclick="selectAvatar('<?= pms_h($av) ?>', this)">
                                <img src="avatar/<?= pms_h($av) ?>" alt="Avatar option">
                                <div class="check-overlay">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save-profile">Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.profile-layout {
    max-width: 1000px;
    margin: 0 auto;
}
.profile-content {
    display: flex;
    flex-direction: column;
    gap: 40px;
    padding: 20px 0;
}

/* Summary Section */
.profile-summary {
    display: flex;
    gap: 30px;
    align-items: center;
    background: #f8fafc;
    padding: 30px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
}
.profile-main-avatar {
    width: 120px;
    height: 120px;
    border-radius: 30px;
    overflow: hidden;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
}
.profile-main-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.avatar-placeholder {
    font-size: 40px;
    font-weight: 800;
    color: #fff;
}
.profile-details h3 {
    margin: 0 0 8px;
    font-size: 24px;
    font-weight: 800;
    color: #1e293b;
}
.role-badge {
    display: inline-block;
    background: #dbeafe;
    color: #1d4ed8;
    padding: 4px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 16px;
}
.info-grid {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 10px 20px;
}
.info-item span {
    font-size: 14px;
    color: #64748b;
}
.info-item strong {
    font-size: 14px;
    color: #1e293b;
}
.profile-notes-display {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px dashed #cbd5e1;
}
.profile-notes-display span {
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    display: block;
    margin-bottom: 4px;
}
.profile-notes-display p {
    margin: 0;
    font-size: 14px;
    color: #475569;
    font-style: italic;
    line-height: 1.5;
}

/* Avatar Grid */
.avatar-selection-section {
    border-top: 1px solid #f1f5f9;
    padding-top: 30px;
}
.section-title {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}
.section-desc {
    margin: 0 0 24px;
    color: #64748b;
    font-size: 14px;
}
.avatar-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}
.avatar-option {
    aspect-ratio: 1/1;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    border: 3px solid transparent;
    transition: all 0.2s;
    background: #f1f5f9;
}
.avatar-option:hover {
    transform: scale(1.05);
    border-color: #cbd5e1;
}
.avatar-option.selected {
    border-color: #3b82f6;
    transform: scale(1.05);
}
.avatar-option img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.check-overlay {
    position: absolute;
    inset: 0;
    background: rgba(37, 99, 235, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s;
}
.avatar-option.selected .check-overlay {
    opacity: 1;
}
.check-overlay svg {
    width: 32px;
    height: 32px;
    color: #fff;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid #f1f5f9;
    padding-top: 24px;
}
.btn-save-profile {
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 12px 32px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    transition: all 0.2s;
}
.btn-save-profile:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
}

@media (max-width: 768px) {
    .profile-summary {
        flex-direction: column;
        text-align: center;
    }
    .avatar-grid {
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    }
}
</style>

<script>
function selectAvatar(file, el) {
    document.querySelectorAll('.avatar-option').forEach(opt => opt.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedAvatar').value = file;
}
</script>

<?php pms_render_footer(); ?>
