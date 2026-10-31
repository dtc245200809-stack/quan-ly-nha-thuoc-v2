<?php
include_once 'app.php';
pms_auth('admin,manager,pharmacist,cashier');
$role = pms_current_role();
$errors=[]; $comboId=(int)($_POST['combo_id'] ?? $_GET['combo_id'] ?? 0); $customer=trim($_POST['customer_name'] ?? 'Khách lẻ'); $customerId=(int)($_POST['customer_id'] ?? 0); $usePoints=max(0,(int)($_POST['use_points'] ?? 0));
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['sell_combo'])) {
    pms_csrf_verify('combo.php');
    $payType = trim($_POST['payment_type'] ?? 'cash');
    if (!pms_validate_in($payType, ['cash', 'transfer'])) {
        $errors[] = 'Phương thức thanh toán không hợp lệ.';
    } else {
        $customerId = $customerId ?: pms_upsert_customer($customer, trim($_POST['customer_phone'] ?? ''));
        $result = pms_create_combo_sale($customer, $comboId, $payType, $customerId, $usePoints);
        if ($result['ok']) { 
            pms_flash($result['message']); 
            pms_redirect('combo.php?last_id=' . $result['invoice_no'] . '&combo_id=' . $comboId); 
        } else {
            $errors[]=$result['message'];
        }
    }
}
$combos = pms_combo_options(); $detail = $comboId ? pms_build_combo_preview($comboId) : []; $customers=pms_customer_options();
$comboTotal = 0; foreach ($detail as $d) $comboTotal += (float)$d['line_total'];
$selectedCombo = $comboId ? pms_fetch_one("SELECT * FROM combos WHERE combo_id=?", 'i', [$comboId]) : null;
pms_render_header('Bán cắt liều / Combo', $role, 'combo.php', [
    'Số combo mẫu' => count($combos),
    'Tổng tiền combo' => $comboId ? pms_currency($comboTotal) : '—',
]); ?>

<!-- Chọn combo -->
<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Combo đơn thuốc mẫu</h2>
            <div class="panel-subtitle">Thực tế nhà thuốc thường bán liều 3 ngày, 5 ngày. Nhân viên chỉ cần chọn combo để xuất nhanh nhiều thuốc cùng lúc.</div>
        </div>
        <a class="btn secondary sm" href="ban_hang.php">🛒 Về POS đơn lẻ</a>
    </div>

    <?php if (!$combos): ?>
        <div class="empty">Chưa có combo nào trong hệ thống. Vui lòng thêm combo qua CSDL.</div>
    <?php else: ?>
    <div class="combo-picker">
        <?php foreach($combos as $combo): 
            $isSelected = $comboId === (int)$combo['combo_id'];
            $cName = mb_strtolower($combo['combo_name']);
            $themeClass = '';
            if (str_contains($cName, 'dạ dày')) $themeClass = 'stomach';
            elseif (str_contains($cName, 'cảm cúm')) $themeClass = 'flu';
        ?>
        <a class="combo-card <?= $isSelected ? 'selected' : '' ?> <?= $themeClass ?>" href="combo.php?combo_id=<?= (int)$combo['combo_id'] ?>">
            <div class="combo-card__icon">
                <?php if ($isSelected): ?>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <?php else: ?>
                    <?php if($themeClass === 'flu'): ?>
                        <!-- Icon Cảm cúm: Bộ 2 viên thuốc -->
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.5 3.5a4.95 4.95 0 1 1 7 7l-7 7a4.95 4.95 0 1 1-7-7l7-7Z"/>
                            <path d="M8.5 8.5l7 7"/>
                            <circle cx="16" cy="16" r="3" fill="currentColor" fill-opacity="0.3"/>
                        </svg>
                    <?php elseif($themeClass === 'stomach'): ?>
                        <!-- Icon Dạ dày: Chai siro + Viên nén -->
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="10" width="8" height="10" rx="2"/>
                            <path d="M7 10V5h4v5"/>
                            <path d="M15 7h4v4h-4z" fill="currentColor" fill-opacity="0.3"/>
                            <circle cx="17" cy="15" r="3"/>
                        </svg>
                    <?php else: ?>
                        <!-- Mặc định -->
                        <svg width="24" height="24" viewBox="0 0 24 24"><g transform="rotate(-45 12 12)"><rect x="3" y="7" width="18" height="10" rx="5" fill="currentColor" fill-opacity="0.2" stroke="currentColor" stroke-width="2"/><path d="M12 7h4a5 5 0 0 1 5 5v0a5 5 0 0 1-5 5h-4z" fill="currentColor"/><path d="M12 7v10" stroke="currentColor" stroke-width="2"/></g></svg>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="combo-card__info">
                <strong><?= pms_h($combo['combo_name']) ?></strong>
                <span><?= (int)$combo['target_days'] ?> ngày · <?= pms_currency((float)$combo['sale_price']) ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php if ($errors): ?><div class="alert error"><?= pms_h(implode(' ', $errors)) ?></div><?php endif; ?>

<?php if (isset($_GET['last_id'])): ?>
<div class="pos-success-banner mb-3" style="display: flex; gap: 16px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px; border-radius: var(--radius); align-items: center; margin-bottom: 16px;">
    <div class="success-icon" style="width: 40px; height: 40px; background: #22c55e; color: #fff; border-radius: 50%; display: grid; place-items: center; font-size: 20px; font-weight: 800;">✓</div>
    <div class="success-content">
        <h3 style="margin: 0 0 4px; font-size: 16px; color: #166534; font-weight: 800;">Thanh toán hoàn tất!</h3>
        <p style="margin: 0 0 10px; font-size: 13px; color: #15803d;">Hóa đơn đã được lưu.</p>
        <div class="success-actions" style="display: flex; gap: 10px;">
            <button type="button" style="background: #16a34a; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer;" onclick="window.open('print_invoice.php?id=<?= (int)$_GET['last_id'] ?>', '_blank', 'width=400,height=600')">🖨 IN HÓA ĐƠN</button>
            <a href="combo.php" style="color: #16a34a; text-decoration: none; font-weight: 700; font-size: 13px; padding: 6px 0;">Tiếp tục</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chi tiết + Form bán -->
<div class="combo-layout">
    <!-- Bảng chi tiết combo -->
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2><?= $selectedCombo ? pms_h($selectedCombo['combo_name']) : 'Chi tiết combo' ?></h2>
                <div class="panel-subtitle">
                    <?= $selectedCombo 
                        ? 'Hệ thống tự lấy đúng thuốc và đúng lô gần hết hạn theo FEFO.' 
                        : 'Chọn một combo ở trên để xem chi tiết.' ?>
                </div>
            </div>
            <?php if ($selectedCombo && $selectedCombo['note']): ?>
                <span class="badge info"><?= pms_h($selectedCombo['note']) ?></span>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Thuốc</th>
                        <th>Số lượng</th>
                        <th>Lô sẽ xuất (FEFO)</th>
                        <th>Đơn giá</th>
                        <th>Thành tiền</th>
                        <th>Phân loại</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($detail as $row): ?>
                    <tr>
                        <td><strong><?= pms_h($row['drug_name']) ?></strong></td>
                        <td><?= (int)$row['qty'] ?></td>
                        <td>
                            <?php foreach($row['alloc'] as $alloc): ?>
                                <div class="lot-chip">
                                    <span class="badge gray"><?= pms_h($alloc['batch_no']) ?></span>
                                    <span class="muted">× <?= (int)$alloc['take'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td><?= pms_currency((float)$row['sale_price']) ?></td>
                        <td><strong><?= pms_currency((float)$row['line_total']) ?></strong></td>
                        <td><?= $row['is_prescription'] ? '<span class="badge danger">Kê đơn ⚕</span>' : '<span class="badge ok">OTC</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(!$detail): ?>
                    <tr><td colspan="6"><div class="empty">Chọn một combo để xem chi tiết thuốc sẽ xuất.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($detail): ?>
        <div class="combo-summary-bar">
            <div class="combo-summary-item">
                <span>Số thuốc</span>
                <strong><?= count($detail) ?> loại</strong>
            </div>
            <div class="combo-summary-item">
                <span>Tổng số lượng</span>
                <strong><?= array_sum(array_column($detail, 'qty')) ?> đơn vị</strong>
            </div>
            <div class="combo-summary-item highlight">
                <span>Tổng tiền combo</span>
                <strong><?= pms_currency($comboTotal) ?></strong>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- Form thanh toán -->
    <aside class="panel combo-checkout">
        <div class="panel-head">
            <div>
                <h2>💳 Thanh toán combo</h2>
                <div class="panel-subtitle">Hoàn tất thông tin khách hàng để bán</div>
            </div>
        </div>
        <form method="post" id="comboForm">
            <?= pms_csrf_field() ?>
            <input type="hidden" name="combo_id" value="<?= (int)$comboId ?>">
            <input type="hidden" name="sell_combo" value="1">

            <div class="checkout-form-grid">
                <label class="full-width">
                    <span class="form-label">Khách hàng</span>
                    <select name="customer_id">
                        <option value="0">Khách lẻ (không tích điểm)</option>
                        <?php foreach($customers as $c): ?>
                        <option value="<?= (int)$c['customer_id'] ?>"><?= pms_h($c['customer_name']) ?> — <?= (int)$c['loyalty_points'] ?> điểm</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="form-label">Tên khách (nếu thêm mới)</span>
                    <input type="text" name="customer_name" value="<?= pms_h($customer) ?>" placeholder="Nguyễn Văn A">
                </label>
                <label>
                    <span class="form-label">SĐT khách</span>
                    <input type="text" name="customer_phone" placeholder="0901234567">
                </label>
                <label>
                    <span class="form-label">Dùng điểm</span>
                    <input type="number" name="use_points" min="0" value="<?= (int)$usePoints ?>" placeholder="0">
                    <span class="hint">1 điểm = 100đ giảm giá</span>
                </label>
                <div class="full-width">
                    <span class="form-label mb-1">Phương thức thanh toán</span>
                    <div class="payment-selector">
                        <div class="payment-method-card active" data-value="cash" onclick="selectPayment('cash')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                            <span>Tiền mặt</span>
                        </div>
                        <div class="payment-method-card" data-value="transfer" onclick="selectPayment('transfer')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>
                            <span>Chuyển khoản</span>
                        </div>
                    </div>
                    <select name="payment_type" id="paymentType" style="display:none;">
                        <option value="cash">cash</option>
                        <option value="transfer">transfer</option>
                    </select>
                </div>
            </div>

            <?php if ($comboId && $detail): ?>
            <div class="checkout-total">
                <div class="checkout-total__line">
                    <span>Combo</span>
                    <span><?= pms_h($selectedCombo['combo_name'] ?? '') ?></span>
                </div>
                <div class="checkout-total__line">
                    <span>Số thuốc</span>
                    <span><?= count($detail) ?> loại · <?= array_sum(array_column($detail, 'qty')) ?> đơn vị</span>
                </div>
                <div class="checkout-total__line grand">
                    <span>Tổng thanh toán</span>
                    <strong><?= pms_currency($comboTotal) ?></strong>
                </div>
            </div>
            <?php endif; ?>

            <button type="button" onclick="handleComboSubmit()" class="checkout-btn">
                ✔ Xác nhận bán combo
            </button>

            <?php if (!$comboId): ?>
            <div class="checkout-hint">
                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                Chọn một combo ở trên để bắt đầu
            </div>
            <?php endif; ?>
        </form>
    </aside>
</div>

<style>
/* ── Combo Picker ── */
.combo-picker {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 12px;
}
.combo-card {
    display: flex; align-items: center; gap: 14px;
    padding: 16px; border-radius: 16px;
    background: #FFF; border: 1px solid var(--pm-card-border);
    transition: var(--pm-trans); cursor: pointer;
    box-shadow: var(--pm-shadow-soft);
}
.combo-card:hover {
    border-color: var(--pm-blue); background: #F8FAFC;
    transform: translateY(-3px); box-shadow: var(--pm-shadow-hover);
}
.combo-card:hover .combo-card__icon {
    background: var(--pm-blue);
    color: #FFF;
    transform: scale(1.1) rotate(-5deg);
    box-shadow: 0 4px 12px rgba(10, 110, 189, 0.3);
}
.combo-card.selected {
    border-color: var(--pm-blue); background: linear-gradient(135deg, #F0F9FF, #F8FAFC);
    box-shadow: 0 4px 12px rgba(37,99,235,0.15), inset 0 0 0 1px var(--pm-blue);
}
.combo-card__icon {
    width: 44px; height: 44px; flex-shrink: 0;
    display: grid; place-items: center;
    background: var(--pm-blue-light); border-radius: 12px;
    color: var(--pm-blue); box-shadow: inset 0 2px 4px rgba(255,255,255,0.4);
    transition: var(--pm-trans);
}
.combo-card.selected .combo-card__icon { 
    background: linear-gradient(135deg, var(--pm-blue), #2CCAED); 
    color: #FFF; 
    box-shadow: 0 8px 16px rgba(37,99,235,0.25);
}
/* Themes */
.combo-card.flu .combo-card__icon { background: #E0F2FE; color: #0369A1; }
.combo-card.stomach .combo-card__icon { background: #DCFCE7; color: #166534; }
.combo-card.flu:hover { border-color: #0369A1; }
.combo-card.stomach:hover { border-color: #166534; }
.combo-card__info { min-width: 0; flex: 1; }
.combo-card__info strong { display: block; font-size: 14.5px; font-weight: 800; color: var(--pm-navy); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.combo-card__info span { font-size: 12px; font-weight: 600; color: var(--pm-text-muted); }
.combo-card.selected .combo-card__info strong { color: var(--pm-blue); }

/* ── Combo Layout ── */
.combo-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 16px;
    align-items: start;
}

/* ── Lot chip ── */
.lot-chip { display: flex; align-items: center; gap: 6px; margin: 2px 0; }

/* ── Summary Bar ── */
.combo-summary-bar {
    display: flex; gap: 0; margin-top: 16px;
    border-radius: var(--radius); overflow: hidden;
    border: 1px solid var(--gray-200); background: var(--gray-50);
}
.combo-summary-item {
    flex: 1; padding: 14px 18px; text-align: center;
    border-right: 1px solid var(--gray-200);
}
.combo-summary-item:last-child { border-right: none; }
.combo-summary-item span { display: block; font-size: 11.5px; font-weight: 600; color: var(--gray-500); margin-bottom: 4px; text-transform: uppercase; letter-spacing: .03em; }
.combo-summary-item strong { font-size: 1.1rem; font-weight: 800; color: var(--gray-800); }
.combo-summary-item.highlight { background: var(--blue-light); }
.combo-summary-item.highlight strong { color: var(--blue-dark); font-size: 1.25rem; }

/* ── Checkout panel ── */
.combo-checkout { position: sticky; top: 80px; }
.combo-checkout .panel-head { border-bottom-color: var(--gray-200); }

.checkout-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.checkout-form-grid .full-width { grid-column: 1 / -1; }
.checkout-form-grid label { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12.5px; font-weight: 600; color: var(--gray-600); }

.checkout-total {
    margin-top: 18px; padding: 16px;
    background: var(--gray-50); border: 1px solid var(--gray-200);
    border-radius: var(--radius); display: grid; gap: 8px;
}
.checkout-total__line {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 13px; color: var(--gray-600);
}
.checkout-total__line.grand {
    padding-top: 10px; margin-top: 4px;
    border-top: 1px solid var(--gray-300);
    font-size: 15px; color: var(--gray-900);
}
.checkout-total__line.grand strong { font-size: 1.2rem; font-weight: 800; color: var(--blue-dark); }

.checkout-btn {
    width: 100%; margin-top: 16px; padding: 14px;
    font-size: 15px; font-weight: 700; border-radius: var(--radius);
    background: var(--blue); color: #fff; border: none; cursor: pointer;
    transition: background .2s, transform .15s, box-shadow .2s;
    box-shadow: 0 4px 14px rgba(37,99,235,.25);
}
.checkout-btn:hover:not(:disabled) { background: var(--blue-dark); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,.3); }
.checkout-btn:disabled { opacity: .45; cursor: not-allowed; box-shadow: none; }

.checkout-hint {
    display: flex; align-items: center; gap: 8px; justify-content: center;
    margin-top: 14px; padding: 12px;
    background: var(--orange-bg); border: 1px solid var(--orange-bd);
    border-radius: var(--radius); color: var(--orange);
    font-size: 13px; font-weight: 600;
}

@media (max-width: 1180px) {
    .combo-layout { grid-template-columns: 1fr; }
    .combo-checkout { position: static; }
}
@media (max-width: 760px) {
    .combo-picker { grid-template-columns: 1fr; }
    .checkout-form-grid { grid-template-columns: 1fr; }
    .combo-summary-bar { flex-direction: column; }
    .combo-summary-item { border-right: none; border-bottom: 1px solid var(--gray-200); }
    .combo-summary-item:last-child { border-bottom: none; }
}
/* Modal QR */
.pos-modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.pos-modal-content { background: #fff; width: 480px; border-radius: 16px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.pos-modal-content h3 { margin: 12px 0 8px; font-size: 20px; color: var(--gray-900); font-weight: 800; }
.pos-modal-content p { margin: 0 0 16px; color: var(--gray-500); font-size: 14px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
/* Payment Methods Card Selector */
.payment-selector { display: flex; gap: 8px; width: 100%; margin-top: 4px; }
.payment-method-card { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 12px 8px; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s; background: #fff; gap: 6px; color: #64748b; }
.payment-method-card:hover { border-color: var(--blue); background: #f8fafc; }
.payment-method-card.active { border-color: var(--blue); background: #f0f9ff; color: var(--blue); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1); }
.payment-method-card svg { width: 24px; height: 24px; stroke-width: 2.5; }
.payment-method-card span { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; }
</style>
<!-- Modal QR -->
<div id="qrModal" class="pos-modal" style="display:none;">
    <div class="pos-modal-content">
        <h3>Quét mã QR để thanh toán</h3>
        <p id="qrText">Đang tạo mã QR...</p>

        <div style="text-align:center;margin:16px 0;">
            <img id="qrImage" style="max-width:250px; display:none; margin: 0 auto; border-radius: 8px; border: 1px solid #e2e8f0;">
        </div>

        <div id="qrMeta" style="text-align:center; margin-top: 12px; font-size: 14px; line-height: 1.6; color: var(--gray-700);"></div>

        <div class="modal-actions">
            <button type="button" onclick="closeQr()" style="background: #f1f5f9; color: #475569; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer;">Hủy</button>
            <button type="button" onclick="submitAfterQR()" class="btn-checkout-confirm" style="background: #16a34a; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 800; cursor: pointer;">
                Xác nhận đã thanh toán
            </button>
        </div>
    </div>
</div>

<script>
let allowSubmit = false;

function selectPayment(val) {
    document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('active'));
    document.querySelector(`.payment-method-card[data-value="${val}"]`).classList.add('active');
    document.getElementById('paymentType').value = val;
}

function getPaymentType() {
    return document.getElementById('paymentType').value;
}

function getTotal() {
    return <?= (int)$comboTotal ?>;
}

function handleComboSubmit() {
    if (allowSubmit) {
        document.getElementById('comboForm').submit();
        return;
    }

    const payType = getPaymentType();

    if (payType === 'transfer') {
        generateQR();
    } else {
        document.getElementById('comboForm').submit();
    }
}

function generateQR() {
    const amount = getTotal();

    document.getElementById('qrModal').style.display = 'flex';
    document.getElementById('qrText').innerText = 'Đang tạo mã QR...';

    const fd = new FormData();
    fd.append('amount', amount);
    fd.append('invoice_code', 'COMBO-' + Date.now());

    fetch('ajax_create_qr.php', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (!data.ok) {
            document.getElementById('qrText').innerText = data.message;
            return;
        }

        document.getElementById('qrImage').src = data.qrDataURL;
        document.getElementById('qrImage').style.display = 'block';

        document.getElementById('qrText').innerText = 'Khách quét mã để thanh toán';
        document.getElementById('qrMeta').innerHTML =
            `<b>Số tiền:</b> ${data.amount.toLocaleString()} đ<br>
             <b>Nội dung:</b> ${data.content}`;
    });
}

function closeQr() {
    document.getElementById('qrModal').style.display = 'none';
}

function submitAfterQR() {
    allowSubmit = true;
    document.getElementById('comboForm').submit();
}
</script>
<?php pms_render_footer(); ?>