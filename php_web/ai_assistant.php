<?php
include_once __DIR__ . '/app.php';
include_once __DIR__ . '/ai_service.php';

$auth = pms_auth('admin,manager,pharmacist,cashier');
$page_title = 'Trợ lý AI Nhà Thuốc';

// Thống kê nhanh lô cận hạn phục vụ AI Report
$nearExpiryStocks = pms_fetch_all("
    SELECT s.stock_id, s.drug_name, s.batch_no, s.expiry_date, s.quantity, DATEDIFF(s.expiry_date, CURDATE()) AS days_left 
    FROM stock s 
    WHERE s.quantity > 0 AND DATEDIFF(s.expiry_date, CURDATE()) <= 90 
    ORDER BY s.expiry_date ASC 
    LIMIT 15
");

// Danh sách thuốc cho phần tóm tắt
$sampleDrugs = pms_fetch_all("SELECT drug_id, drug_code, name, active_ingredient, unit, category FROM drugs LIMIT 20");

pms_render_header($page_title, pms_current_role(), "ai_assistant.php");
?>

<div class="ds-card" style="margin-bottom:24px;border-left:4px solid #10b981;background:linear-gradient(to right, #f0fdf4, #ffffff);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-size:20px;font-weight:700;color:#065f46;margin-bottom:6px;">🤖 Trợ lý AI Nhà Thuốc (Pharmacy AI Copilot)</h2>
            <p style="color:#047857;font-size:14px;margin:0;">Hỗ trợ tóm tắt thông tin thuốc, phân tích cảnh báo hạn sử dụng & hỏi đáp quy trình nội bộ.</p>
        </div>
        <div style="display:flex;gap:8px;">
            <?php if ($auth['role'] === 'admin'): ?>
                <a href="ai_config.php" class="btn btn-secondary" style="background:#fff;border:1px solid #10b981;color:#065f46;font-size:13px;">⚙️ Cấu hình API Key</a>
            <?php endif; ?>
        </div>
    </div>
    <div style="margin-top:14px;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;display:flex;align-items:center;gap:10px;">
        <span style="font-size:20px;">⚠️</span>
        <span style="color:#991b1b;font-size:13px;font-weight:600;">
            NGUYÊN TẮC AN TOÀN ĐẠO ĐỨC AI: AI chỉ cung cấp thông tin tham khảo nội bộ cho nhân viên. Tuyệt đối không thay thế Dược sĩ chuyên môn hoặc tư vấn chẩn đoán/kê đơn cho khách hàng.
        </span>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:20px;margin-bottom:24px;">
    <!-- Box 1: Tóm tắt thuốc -->
    <div class="ds-card" style="box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);border-radius:12px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <span style="font-size:24px;">💊</span>
            <h3 style="font-size:16px;font-weight:700;color:var(--ds-text);">AI Tóm Tắt Thuốc Tham Khảo</h3>
        </div>
        <p style="font-size:13px;color:var(--ds-text-muted);margin-bottom:12px;">Chọn thuốc có sẵn trong danh mục hoặc gõ thông tin để AI tóm tắt nhanh cho nhân viên bán quầy:</p>
        
        <div style="margin-bottom:12px;">
            <select id="select-drug" class="form-control" style="width:100%;margin-bottom:8px;" onchange="fillDrugInfo()">
                <option value="">-- Chọn thuốc từ hệ thống --</option>
                <?php foreach ($sampleDrugs as $d): ?>
                    <option value="<?= pms_h($d['name']) ?>" 
                            data-ing="<?= pms_h($d['active_ingredient']) ?>" 
                            data-cat="<?= pms_h($d['category'] ?? 'Thông thường') ?>" 
                            data-unit="<?= pms_h($d['unit']) ?>">
                        <?= pms_h($d['name']) ?> (<?= pms_h($d['active_ingredient']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <textarea id="drug-input" class="form-control" rows="3" placeholder="Nhập thông tin thuốc cần tóm tắt (hoặc chọn thuốc ở trên)..." style="width:100%;font-size:13px;"></textarea>
        </div>
        <button type="button" class="btn btn-primary" onclick="generateDrugSummary()" style="width:100%;">⚡ AI Tóm tắt ngay</button>
        
        <div id="drug-result" style="margin-top:14px;display:none;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;font-size:13px;white-space:pre-wrap;line-height:1.6;"></div>
    </div>

    <!-- Box 2: Báo cáo hạn dùng -->
    <div class="ds-card" style="box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);border-radius:12px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <span style="font-size:24px;">📊</span>
            <h3 style="font-size:16px;font-weight:700;color:var(--ds-text);">AI Báo Cáo Hạn Dùng & Đề Xuất</h3>
        </div>
        <p style="font-size:13px;color:var(--ds-text-muted);margin-bottom:12px;">Phân tích tự động các lô hàng cận hạn (<= 90 ngày) và đề xuất phương án giải phóng tồn kho (FEFO):</p>
        
        <div style="background:#fffbeb;border:1px solid #fef3c7;padding:10px;border-radius:8px;margin-bottom:12px;max-height:115px;overflow-y:auto;font-size:12px;">
            <strong>Lô cận hạn phát hiện trong kho:</strong>
            <?php if (empty($nearExpiryStocks)): ?>
                <div style="color:#059669;margin-top:4px;">✅ Tất cả lô thuốc đều có hạn dùng an toàn (> 90 ngày).</div>
            <?php else: ?>
                <ul style="margin:4px 0 0 16px;padding:0;color:#92400e;">
                    <?php foreach ($nearExpiryStocks as $st): ?>
                        <li><?= pms_h($st['drug_name']) ?> (Lô <?= pms_h($st['batch_no']) ?>): Còn <?= $st['days_left'] ?> ngày, SL <?= $st['quantity'] ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <button type="button" class="btn btn-primary" onclick="generateExpiryReport()" style="width:100%;background:#0284c7;border-color:#0284c7;">📑 AI Sinh Báo Cáo & Giải Pháp</button>
        
        <div id="expiry-result" style="margin-top:14px;display:none;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;font-size:13px;white-space:pre-wrap;line-height:1.6;"></div>
    </div>
</div>

<!-- Chatbot Section -->
<div class="ds-card" style="box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);border-radius:12px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e2e8f0;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:24px;">💬</span>
            <div>
                <h3 style="font-size:17px;font-weight:700;margin:0;">Chatbot Hỏi Đáp Quy Trình Nội Bộ (SOP)</h3>
                <span style="font-size:12px;color:var(--ds-text-muted);">Hỗ trợ nhân viên tra cứu: Nhập lô, kiểm kê định kỳ, tiêu hủy thuốc hết hạn...</span>
            </div>
        </div>
        <div style="display:flex;gap:6px;">
            <button class="btn btn-sm btn-outline-secondary" onclick="quickAsk('Quy trình nhập kho và kiểm tra số lô như thế nào?')">📦 Quy trình nhập lô</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="quickAsk('Quy trình tiêu hủy thuốc quá hạn sử dụng?')">🗑️ Tiêu hủy thuốc</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="quickAsk('Nguyên tắc xuất kho FEFO là gì?')">🔄 Nguyên tắc FEFO</button>
        </div>
    </div>

    <!-- Chat history box -->
    <div id="chat-box" style="height:350px;overflow-y:auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:14px;display:flex;flex-direction:column;gap:12px;">
        <div style="align-self:flex-start;max-width:85%;background:#fff;border:1px solid #e2e8f0;padding:12px 16px;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            <div style="font-weight:700;color:#10b981;font-size:12px;margin-bottom:4px;">🤖 AI ASSISTANT</div>
            <div style="font-size:14px;line-height:1.5;">Xin chào! Tôi là Trợ lý AI Nhà Thuốc. Tôi có thể hỗ trợ bạn tra cứu quy trình nội bộ, kiểm kê kho hoặc tóm tắt thuốc. Bạn cần tra cứu quy trình gì hôm nay?</div>
        </div>
    </div>

    <!-- Input bar -->
    <form id="chat-form" onsubmit="sendChatMessage(event)" style="display:flex;gap:10px;">
        <input type="text" id="chat-input" class="form-control" placeholder="Đặt câu hỏi về quy trình nhà thuốc (ví dụ: Quy trình nhập lô thuốc mới?)..." style="flex:1;" required autocomplete="off">
        <button type="submit" id="chat-submit-btn" class="btn btn-primary" style="padding:10px 24px;">Gửi 🚀</button>
    </form>
</div>

<script>
function fillDrugInfo() {
    const sel = document.getElementById('select-drug');
    const opt = sel.options[sel.selectedIndex];
    if (!sel.value) return;
    const txt = `Tên thuốc: ${opt.value}\nHoạt chất chính: ${opt.getAttribute('data-ing')}\nPhân loại: ${opt.getAttribute('data-cat')}\nĐơn vị tính: ${opt.getAttribute('data-unit')}`;
    document.getElementById('drug-input').value = txt;
}

async function callAiBackend(prompt, action = 'custom') {
    const res = await fetch('ajax_ai.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ prompt, action })
    });
    return await res.json();
}

async function generateDrugSummary() {
    const input = document.getElementById('drug-input').value.trim();
    if (!input) {
        alert('Vui lòng chọn hoặc nhập thông tin thuốc cần tóm tắt.');
        return;
    }
    const box = document.getElementById('drug-result');
    box.style.display = 'block';
    box.innerHTML = '⏳ AI đang phân tích dữ liệu và tóm tắt...';

    const p = `Dữ liệu thuốc: ${input}. Hãy tóm tắt thông tin để nhân viên tra cứu nhanh, kèm cảnh báo cần hỏi dược sĩ khi tư vấn khách.`;
    const resp = await callAiBackend(p, 'summary');
    box.innerHTML = resp.text || resp.error || 'Có lỗi xảy ra.';
}

async function generateExpiryReport() {
    const box = document.getElementById('expiry-result');
    box.style.display = 'block';
    box.innerHTML = '⏳ AI đang phân tích dữ liệu lô cận hạn và tạo đề xuất...';

    const prompt = 'Hãy sinh báo cáo phân tích các lô thuốc cận hạn hoặc có nguy cơ hết hạn trong nhà thuốc, đồng thời đề xuất giải pháp xử lý kho (FEFO, đổi trả nhà cung cấp, kiểm kê hủy hàng).';
    const resp = await callAiBackend(prompt, 'expiry_report');
    box.innerHTML = resp.text || resp.error || 'Có lỗi xảy ra.';
}

function quickAsk(text) {
    document.getElementById('chat-input').value = text;
    document.getElementById('chat-form').dispatchEvent(new Event('submit'));
}

async function sendChatMessage(e) {
    e.preventDefault();
    const inp = document.getElementById('chat-input');
    const text = inp.value.trim();
    if (!text) return;

    const chatBox = document.getElementById('chat-box');

    // Add user bubble
    const userDiv = document.createElement('div');
    userDiv.style.cssText = 'align-self:flex-end;max-width:85%;background:#10b981;color:#fff;padding:10px 14px;border-radius:12px;font-size:14px;line-height:1.5;';
    userDiv.innerText = text;
    chatBox.appendChild(userDiv);
    inp.value = '';
    chatBox.scrollTop = chatBox.scrollHeight;

    // Add loading bubble
    const aiDiv = document.createElement('div');
    aiDiv.style.cssText = 'align-self:flex-start;max-width:85%;background:#fff;border:1px solid #e2e8f0;padding:12px 16px;border-radius:12px;font-size:14px;line-height:1.5;box-shadow:0 1px 2px rgba(0,0,0,0.05);';
    aiDiv.innerHTML = '<div style="font-weight:700;color:#10b981;font-size:12px;margin-bottom:4px;">🤖 AI ASSISTANT</div><div class="loading-msg">Đang suy nghĩ... ⏳</div>';
    chatBox.appendChild(aiDiv);
    chatBox.scrollTop = chatBox.scrollHeight;

    const resp = await callAiBackend(text, 'chat');
    aiDiv.querySelector('.loading-msg').innerText = resp.text || resp.error || 'Không nhận được câu trả lời từ máy chủ.';
    chatBox.scrollTop = chatBox.scrollHeight;
}
</script>

<?php
pms_render_footer();
