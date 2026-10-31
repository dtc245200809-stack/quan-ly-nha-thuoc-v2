<?php
include_once 'app.php';
pms_auth('admin,manager,pharmacist,cashier');
$role = pms_current_role();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if (!$name)
        $errors[] = 'Vui lòng nhập tên khách hàng.';
    if (!$errors) {
        pms_upsert_customer($name, $phone);
        pms_flash('Đã lưu khách hàng mới.');
        pms_redirect('khach_hang.php');
    }
}
$customers = pms_fetch_all("SELECT c.*, COALESCE(SUM(l.points_delta),0) log_points FROM customers c LEFT JOIN loyalty_logs l ON l.customer_id=c.customer_id GROUP BY c.customer_id ORDER BY c.loyalty_points DESC, c.customer_name ASC");
$totalPoints = 0;
foreach ($customers as $c)
    $totalPoints += (int) $c['loyalty_points'];
pms_render_header('Khách hàng thân thiết & tích điểm', $role, 'khach_hang.php', [
    'Khách hàng' => count($customers),
    'Tổng điểm hệ thống' => number_format($totalPoints),
]); ?>

<!-- Form + Quy tắc -->
<section class="kh-top-grid">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>➕ Thêm khách hàng</h2>
                <div class="panel-subtitle">Đăng ký khách hàng mới vào hệ thống tích điểm.</div>
            </div>
        </div>
        <?php if ($errors): ?>
            <div class="alert error">
                <?= pms_h(implode(' ', $errors)) ?>
            </div>
        <?php endif; ?>
        <form method="post" class="kh-form">
            <label>
                <span class="form-label">Tên khách hàng</span>
                <input type="text" name="customer_name" required placeholder="Nguyễn Văn A">
            </label>
            <label>
                <span class="form-label">Số điện thoại</span>
                <input type="text" name="phone" placeholder="0901234567">
            </label>
            <button type="submit">Lưu khách hàng</button>
        </form>
    </div>

    <div class="panel kh-rules-panel">
        <div class="panel-head">
            <h2> Quy tắc tích điểm</h2>
        </div>
        <div class="kh-rules">
            <div class="kh-rule">
                <div class="kh-rule__icon" style="background: var(--green-bg); color: var(--green);"></div>
                <div>
                    <strong>Tích lũy</strong>
                    <span>10.000 VNĐ = 1 điểm</span>
                </div>
            </div>
            <div class="kh-rule">
                <div class="kh-rule__icon" style="background: var(--blue-light); color: var(--blue);"></div>
                <div>
                    <strong>Sử dụng</strong>
                    <span>1 điểm = 100 VNĐ giảm giá</span>
                </div>
            </div>
            <div class="kh-rule">
                <div class="kh-rule__icon" style="background: var(--orange-bg); color: var(--orange);"></div>
                <div>
                    <strong>Mục tiêu</strong>
                    <span>Giữ chân khách hàng thân thiết, tăng doanh số</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Danh sách khách hàng -->
<section class="panel">
    <div class="panel-head" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h2>Danh sách khách hàng (<span id="khCount">
                    <?= count($customers) ?>
                </span>)</h2>
            <div class="panel-subtitle">Xếp theo điểm tích lũy giảm dần.</div>
        </div>
        <div class="panel-search">
            <input type="text" id="khSearch" placeholder="Tìm tên hoặc SĐT..."
                style="padding:8px 12px; border-radius:8px; border:1px solid var(--gray-200); width:250px;">
        </div>
    </div>
    <?php if (!$customers): ?>
        <div class="empty">Chưa có khách hàng nào trong hệ thống.</div>
    <?php else: ?>
        <div class="kh-grid">
            <?php foreach ($customers as $i => $c):
                $rank = $i + 1;
                $pts = (int) $c['loyalty_points'];
                $tier = $pts >= 100 ? 'gold' : ($pts >= 30 ? 'silver' : 'bronze');
                $tierLabel = $pts >= 100 ? '🥇 VIP' : ($pts >= 30 ? '🥈 Thân thiết' : '🥉 Mới');
                ?>
                <article class="kh-card kh-card--<?= $tier ?>">
                    <div class="kh-card__header">
                        <div class="kh-card__avatar">
                            <?= mb_substr($c['customer_name'], 0, 1) ?>
                        </div>
                        <span
                            class="kh-card__tier badge <?= $tier === 'gold' ? 'warn' : ($tier === 'silver' ? 'info' : 'gray') ?>">
                            <?= $tierLabel ?>
                        </span>
                    </div>
                    <div class="kh-card__name">
                        <?= pms_h($c['customer_name']) ?>
                    </div>
                    <div class="kh-card__phone">
                        <?= pms_h($c['phone'] ?: 'Chưa có SĐT') ?>
                    </div>
                    <div class="kh-card__points">
                        <div class="kh-card__pts-value">
                            <?= number_format($pts) ?>
                        </div>
                        <div class="kh-card__pts-label">điểm tích lũy</div>
                    </div>
                    <div class="kh-card__rank">#
                        <?= $rank ?>
                    </div>

                    <button type="button" class="btn-ai-consult" onclick="openCustomerAIModal('<?= pms_h($c['customer_name']) ?>', '<?= pms_h($c['phone']) ?>', <?= $pts ?>)" style="margin-top:12px;width:100%;padding:8px 12px;background:#f0fdf4;border:1px solid #86efac;color:#166534;font-size:12px;font-weight:600;border-radius:9999px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:all 0.2s ease;">
                        <span>Tư vấn & Quyền lợi</span>
                    </button>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<style>
    .kh-top-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    .kh-form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        align-items: end;
    }

    .kh-form label {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .kh-form button {
        grid-column: 1 / -1;
    }

    .form-label {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--gray-600);
    }

    .kh-rules {
        display: grid;
        gap: 10px;
    }

    .kh-rule {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        border-radius: var(--radius);
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        transition: transform .18s, box-shadow .18s;
    }

    .kh-rule:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .06);
    }

    .kh-rule__icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .kh-rule strong {
        display: block;
        font-size: 13.5px;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: 2px;
    }

    .kh-rule span {
        font-size: 12.5px;
        color: var(--gray-500);
    }

    .kh-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 14px;
    }

    .kh-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 24px 16px 18px;
        border-radius: var(--radius-lg);
        background: var(--white);
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow);
        transition: transform .18s, box-shadow .18s;
        position: relative;
    }

    .kh-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }

    .kh-card--gold {
        border-color: #fde68a;
        background: linear-gradient(160deg, #fffbeb, var(--white) 40%);
    }

    .kh-card--silver {
        border-color: var(--blue-mid);
        background: linear-gradient(160deg, #eff6ff, var(--white) 40%);
    }

    .kh-card__header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-bottom: 12px;
        width: 100%;
    }

    .kh-card__avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--blue), #6366f1);
        color: #fff;
        font-weight: 700;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(37, 99, 235, .2);
    }

    .kh-card--gold .kh-card__avatar {
        background: linear-gradient(135deg, #f59e0b, #ef4444);
        box-shadow: 0 4px 12px rgba(245, 158, 11, .25);
    }

    .kh-card--silver .kh-card__avatar {
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    }

    .kh-card__tier {
        font-size: 10.5px;
        position: absolute;
        top: 10px;
        right: 10px;
    }

    .kh-card__name {
        font-size: 15px;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: 2px;
        word-break: break-word;
        line-height: 1.3;
    }

    .kh-card__phone {
        font-size: 12.5px;
        color: var(--gray-500);
        margin-bottom: 14px;
    }

    .kh-card__points {
        padding: 10px 20px;
        border-radius: var(--radius);
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        width: 100%;
    }

    .kh-card__pts-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--blue-dark);
        line-height: 1;
    }

    .kh-card__pts-label {
        font-size: 10.5px;
        font-weight: 600;
        color: var(--gray-500);
        text-transform: uppercase;
        margin-top: 2px;
    }

    .kh-card--gold .kh-card__pts-value {
        color: #d97706;
    }

    .kh-card--gold .kh-card__points {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .kh-card__rank {
        position: absolute;
        top: 10px;
        left: 12px;
        font-size: 11px;
        font-weight: 800;
        color: var(--gray-400);
    }

    @media (max-width: 1180px) {
        .kh-top-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .kh-grid {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        }

        .kh-form {
            grid-template-columns: 1fr;
        }
    }
</style>
<script>
    document.getElementById('khSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        let visible = 0;
        document.querySelectorAll('.kh-card').forEach(card => {
            const name = card.querySelector('.kh-card__name').innerText.toLowerCase();
            const phone = card.querySelector('.kh-card__phone').innerText.toLowerCase();
            if (name.includes(q) || phone.includes(q)) {
                card.style.display = 'flex';
                visible++;
            } else {
                card.style.display = 'none';
            }
        });
        document.getElementById('khCount').innerText = visible;
    });
</script>

<!-- Modal AI Trợ Lý Tư Vấn Dược Lý & Quyền Lợi Khách Hàng -->
<div id="aiCustomerModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#ffffff;border-radius:20px;max-width:650px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);border:1px solid #cbd5e1;">
        <div style="padding:18px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;border-top-left-radius:20px;border-top-right-radius:20px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:9999px;background:#f1f5f9;color:#0f172a;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:13px;border:1px solid #cbd5e1;">RX</div>
                <div>
                    <h3 style="margin:0;font-size:16px;font-weight:700;color:#0f172a;" id="aiModalCustTitle">Trợ lý Nghiệp vụ & Dặn dò Khách hàng</h3>
                    <div style="font-size:12px;color:#64748b;" id="aiModalCustSubtitle">Hệ thống phân tích dược lý và quyền lợi thành viên</div>
                </div>
            </div>
            <button type="button" onclick="closeCustomerAIModal()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;line-height:1;">&times;</button>
        </div>

        <div style="padding:20px 24px;">
            <!-- Thông tin khách hàng tóm tắt -->
            <div style="display:flex;gap:12px;margin-bottom:16px;padding:12px;background:#f1f5f9;border-radius:14px;font-size:13px;">
                <div style="flex:1;"><strong>Khách hàng:</strong> <span id="aiModalCustName">...</span></div>
                <div style="flex:1;"><strong>Số điện thoại:</strong> <span id="aiModalCustPhone">...</span></div>
                <div style="flex:1;"><strong>Điểm tích lũy:</strong> <span id="aiModalCustPoints" style="color:#0284c7;font-weight:700;">0</span> pts</div>
            </div>

            <!-- Form tùy chọn tư vấn -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#334155;">Đơn thuốc cần dặn dò hoặc câu hỏi của khách:</label>
                <textarea id="aiModalMedicineInput" rows="3" placeholder="Ví dụ: Khách mua Amoxicillin 500mg và Paracetamol 500mg, muốn hỏi uống thế nào và kiêng gì..." style="width:100%;padding:10px 14px;border-radius:14px;border:1px solid #cbd5e1;font-size:13px;box-sizing:border-box;font-family:inherit;"></textarea>
            </div>

            <div style="display:flex;gap:10px;margin-bottom:20px;">
                <button type="button" onclick="callCustomerAI('consult_customer')" id="btnAiConsult" style="flex:1;padding:10px 16px;background:#0d9488;color:#fff;border:none;border-radius:9999px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <span>Lập hướng dẫn sử dụng thuốc</span>
                </button>
                <button type="button" onclick="callCustomerAI('explain_policy')" id="btnAiPolicy" style="flex:1;padding:10px 16px;background:#0284c7;color:#fff;border:none;border-radius:9999px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <span>Quy chế điểm thưởng & Ưu đãi</span>
                </button>
            </div>

            <!-- Khung kết quả hiển thị -->
            <div id="aiModalLoading" style="display:none;padding:24px;text-align:center;color:#64748b;font-size:13px;">
                <div style="margin-bottom:8px;"> Đang đối soát dược thư & quy chế nhà thuốc...</div>
            </div>

            <div id="aiModalResultBox" style="display:none;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;border-bottom:1px dashed #cbd5e1;padding-bottom:8px;">
                    <span style="font-weight:700;font-size:13px;color:#0f172a;" id="aiModalResultHeader">Nội dung hướng dẫn:</span>
                    <button type="button" onclick="copyAiResult()" style="padding:4px 10px;background:#ffffff;border:1px solid #cbd5e1;border-radius:9999px;font-size:11px;cursor:pointer;color:#475569;">📋 Sao chép</button>
                </div>
                <div id="aiModalResultContent" style="font-size:13.5px;line-height:1.7;color:#1e293b;white-space:pre-wrap;"></div>
            </div>
        </div>

        <div style="padding:14px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;border-bottom-left-radius:20px;border-bottom-right-radius:20px;">
            <button type="button" onclick="closeCustomerAIModal()" style="padding:8px 20px;background:#e2e8f0;color:#334155;border:none;border-radius:9999px;font-size:13px;font-weight:600;cursor:pointer;">Đóng</button>
        </div>
    </div>
</div>

<script>
let currentCustData = { name: '', phone: '', points: 0 };

function openCustomerAIModal(name, phone, points) {
    currentCustData = { name, phone, points };
    document.getElementById('aiModalCustName').innerText = name;
    document.getElementById('aiModalCustPhone').innerText = phone || 'Chưa có SĐT';
    document.getElementById('aiModalCustPoints').innerText = Number(points).toLocaleString();
    document.getElementById('aiModalCustTitle').innerText = 'Dặn dò & Chăm sóc: ' + name;
    document.getElementById('aiModalMedicineInput').value = '';
    document.getElementById('aiModalResultBox').style.display = 'none';
    document.getElementById('aiCustomerModal').style.display = 'flex';
}

function closeCustomerAIModal() {
    document.getElementById('aiCustomerModal').style.display = 'none';
}

async function callCustomerAI(action) {
    const inputMed = document.getElementById('aiModalMedicineInput').value.trim();
    const loading = document.getElementById('aiModalLoading');
    const resultBox = document.getElementById('aiModalResultBox');
    const resultContent = document.getElementById('aiModalResultContent');

    loading.style.display = 'block';
    resultBox.style.display = 'none';

    try {
        const resp = await fetch('api_ai_customer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: action,
                customer_name: currentCustData.name,
                phone: currentCustData.phone,
                points: currentCustData.points,
                medicines: inputMed,
                question: inputMed
            })
        });
        const data = await resp.json();
        loading.style.display = 'none';
        if (data.ok) {
            resultContent.innerText = data.text;
            resultBox.style.display = 'block';
        } else {
            alert(data.message || 'Có lỗi xảy ra khi gọi trợ lý AI.');
        }
    } catch (err) {
        loading.style.display = 'none';
        alert('Lỗi kết nối API trợ lý: ' + err.message);
    }
}

function copyAiResult() {
    const text = document.getElementById('aiModalResultContent').innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('Đã sao chép nội dung dặn dò khách hàng!');
    });
}
</script>

<?php pms_render_footer(); ?>