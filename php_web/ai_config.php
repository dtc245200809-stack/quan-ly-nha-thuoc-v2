<?php
include_once __DIR__ . '/app.php';
include_once __DIR__ . '/ai_service.php';

$auth = pms_auth('admin');
$page_title = 'Cấu hình Gemini AI Đa Key & Xoay Key Tự Động';

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = trim($_POST['provider'] ?? 'gemini');
    $model = trim($_POST['model'] ?? 'gemini-1.5-flash');
    $keys_raw = trim($_POST['api_keys_text'] ?? '');
    
    // Tách các key theo dòng hoặc dấu phẩy
    $keyList = preg_split('/[\r\n,]+/', $keys_raw);
    $cleanedKeys = [];
    foreach ($keyList as $k) {
        $k = trim($k);
        if (!empty($k) && strlen($k) > 10 && !in_array($k, $cleanedKeys)) {
            $cleanedKeys[] = $k;
        }
    }

    $currentConfig = pms_get_ai_config();
    $currentConfig['provider'] = $provider;
    $currentConfig['model'] = $model;
    $currentConfig['api_keys'] = $cleanedKeys;
    $currentConfig['api_key'] = implode("\n", $cleanedKeys);
    $currentConfig['current_key_index'] = 0;

    if (pms_save_ai_config($currentConfig)) {
        $msg = "Đã lưu thành công cấu hình AI với " . count($cleanedKeys) . " Gemini API Key! Cơ chế tự động xoay key khi hết Token (429) đã được kích hoạt.";
    } else {
        $msg = "Lỗi khi lưu tệp ai_config.json. Vui lòng kiểm tra quyền ghi tệp.";
        $msg_type = 'danger';
    }
}

$cfg = pms_get_ai_config();
$keys = $cfg['api_keys'] ?? [];
if (empty($keys) && !empty($cfg['api_key'])) {
    $keys = preg_split('/[\r\n,]+/', trim($cfg['api_key']));
    $keys = array_values(array_filter(array_map('trim', $keys)));
}

pms_render_header($page_title, pms_current_role(), "ai_config.php");
?>

<div style="max-width:900px;margin:0 auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <h2 style="font-size:20px;font-weight:700;color:var(--ds-text);">⚙️ Cấu Hình Gemini AI & Quản Lý Đa Key (Xoay Key Tự Động)</h2>
        <a href="ai_assistant.php" class="btn btn-secondary" style="font-size:13px;">&larr; Quay lại AI Trợ lý</a>
    </div>

    <?php if (!empty($msg)): ?>
        <div style="padding:14px;border-radius:10px;margin-bottom:20px;font-size:14px;font-weight:600;<?= $msg_type === 'success' ? 'background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;' : 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Highlight Box Auto-Rotation -->
    <div class="ds-card" style="margin-bottom:20px;border-left:4px solid #10b981;background:#f0fdf4;">
        <h4 style="margin:0 0 6px 0;font-size:15px;color:#065f46;font-weight:700;">🔄 Nguyên lý Xoay Key Tự Động (Auto-Rotation):</h4>
        <p style="margin:0;font-size:13px;color:#047857;line-height:1.6;">
            Khi nhân viên nhà thuốc sử dụng tính năng AI (tóm tắt thuốc, phân tích hạn dùng, hỏi đáp SOP), nếu key đang sử dụng trả về lỗi <b>HTTP 429 (ResourceExhausted / Hết Token/Hết hạn mức Quota)</b>, 
            hệ thống sẽ <b>ngay lập tức tự động đổi sang key kế tiếp</b> trong danh sách bên dưới mà không làm gián đoạn yêu cầu. 
            Nếu toàn bộ các key đều hết hạn ngạch, hệ thống tự động chuyển sang <b>Local Smart Engine</b> để nhà thuốc luôn hoạt động thông suốt.
        </p>
    </div>

    <form method="POST" class="ds-card" style="box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);border-radius:12px;margin-bottom:24px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:var(--ds-text);margin-bottom:6px;">Nhà cung cấp AI (Provider):</label>
                <select name="provider" class="form-control" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                    <option value="gemini" <?= ($cfg['provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Google Gemini (Hỗ trợ đa key xoay vòng)</option>
                    <option value="openai" <?= ($cfg['provider'] ?? '') === 'openai' ? 'selected' : '' ?>>OpenAI (ChatGPT)</option>
                    <option value="local" <?= ($cfg['provider'] ?? '') === 'local' ? 'selected' : '' ?>>Chỉ sử dụng Local Knowledge Engine (Không cần Internet)</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:var(--ds-text);margin-bottom:6px;">Mô hình AI (Model):</label>
                <select name="model" class="form-control" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                    <option value="gemini-1.5-flash" <?= ($cfg['model'] ?? '') === 'gemini-1.5-flash' ? 'selected' : '' ?>>Gemini 1.5 Flash (Tốc độ cao, tối ưu quota)</option>
                    <option value="gemini-2.0-flash" <?= ($cfg['model'] ?? '') === 'gemini-2.0-flash' ? 'selected' : '' ?>>Gemini 2.0 Flash (Thế hệ mới nhất)</option>
                    <option value="gemini-1.5-pro" <?= ($cfg['model'] ?? '') === 'gemini-1.5-pro' ? 'selected' : '' ?>>Gemini 1.5 Pro (Suy luận sâu)</option>
                    <option value="gpt-4o-mini" <?= ($cfg['model'] ?? '') === 'gpt-4o-mini' ? 'selected' : '' ?>>OpenAI gpt-4o-mini</option>
                </select>
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:13px;font-weight:600;color:var(--ds-text);margin-bottom:6px;">
                Danh sách API Keys (Nhập mỗi dòng 1 Key để kích hoạt xoay vòng):
                <span style="font-weight:normal;color:#6b7280;font-size:12px;">(Lấy miễn phí tại <a href="https://aistudio.google.com/" target="_blank" style="color:#0284c7;text-decoration:underline;">Google AI Studio</a>)</span>
            </label>
            <textarea name="api_keys_text" rows="5" class="form-control" style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font-family:monospace;font-size:13px;" placeholder="AIzaSyA... (Key 1)&#10;AIzaSyB... (Key 2)&#10;AIzaSyC... (Key 3)"><?= htmlspecialchars(implode("\n", $keys)) ?></textarea>
        </div>

        <div style="display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary" style="padding:10px 24px;font-size:14px;font-weight:700;background:#059669;color:#fff;border:none;border-radius:8px;cursor:pointer;">
                💾 Lưu Cấu Hình & Bể Key
            </button>
        </div>
    </form>

    <!-- Key Pool Table -->
    <div class="ds-card" style="box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);border-radius:12px;">
        <h3 style="font-size:16px;font-weight:700;color:var(--ds-text);margin-bottom:14px;">📋 Bể Key Đang Được Hệ Thống Giám Sát (<?= count($keys) ?> Keys)</h3>
        <table class="table" style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead>
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;text-align:left;">
                    <th style="padding:10px;">Thứ Tự</th>
                    <th style="padding:10px;">Mã Key (Che bảo mật)</th>
                    <th style="padding:10px;text-align:center;">Trạng Thái Vận Hành</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($keys)): ?>
                    <tr><td colspan="3" style="padding:20px;text-align:center;color:#9ca3af;">Chưa có Key nào trong bể. Hãy nạp key vào ô phía trên.</td></tr>
                <?php else: ?>
                    <?php foreach ($keys as $idx => $k): ?>
                        <?php 
                            $isActive = ($idx === ($cfg['current_key_index'] ?? 0));
                            $masked = substr($k, 0, 4) . '****' . substr($k, -4);
                        ?>
                        <tr style="border-bottom:1px solid #f3f4f6;<?= $isActive ? 'background:#ecfdf5;' : '' ?>">
                            <td style="padding:10px;font-weight:700;font-family:monospace;">Key #<?= $idx + 1 ?></td>
                            <td style="padding:10px;font-family:monospace;font-weight:600;color:#1f2937;"><?= htmlspecialchars($masked) ?></td>
                            <td style="padding:10px;text-align:center;">
                                <?php if ($isActive): ?>
                                    <span style="padding:4px 10px;background:#10b981;color:#fff;border-radius:20px;font-size:11px;font-weight:700;">🟢 Đang Active</span>
                                <?php else: ?>
                                    <span style="padding:4px 10px;background:#e5e7eb;color:#374151;border-radius:20px;font-size:11px;font-weight:600;">⚪ Sẵn sàng dự phòng</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php pms_render_footer(); ?>
