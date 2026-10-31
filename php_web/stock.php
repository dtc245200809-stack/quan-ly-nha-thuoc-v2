<?php
include_once 'app.php';
pms_auth('admin,manager');
$role = pms_current_role();

$errors = [];
$suppliers_db = pms_fetch_all("SELECT id, name FROM suppliers ORDER BY name ASC");
$drugs_db = pms_fetch_all("SELECT id, name, active_ingredient, unit, retail_unit, conversion_factor, sale_price, purchase_price, company, category FROM drugs WHERE is_deleted=0 ORDER BY name ASC");

// ── Tạo mã phiếu nhập tự động ──
function pms_next_purchase_code(): string {
    $row = pms_fetch_one("SELECT purchase_id FROM purchase_header ORDER BY purchase_id DESC LIMIT 1");
    $next = ((int)($row['purchase_id'] ?? 0)) + 1;
    return 'PN-' . date('Ymd') . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

// ── Xử lý POST: Lưu phiếu nhập kho ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    pms_csrf_verify('stock.php');

    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $document_no   = trim($_POST['document_no'] ?? '');
    $note          = trim($_POST['note'] ?? '');
    $drug_ids      = $_POST['items_drug_id'] ?? [];
    $batch_nos     = $_POST['items_batch_no'] ?? [];
    $mfg_dates     = $_POST['items_mfg_date'] ?? [];
    $expiry_dates  = $_POST['items_expiry_date'] ?? [];
    $quantities    = $_POST['items_quantity'] ?? [];
    $prices_in     = $_POST['items_purchase_price'] ?? [];
    $prices_out    = $_POST['items_sale_price'] ?? [];

    if (!$supplier_name) $errors[] = 'Vui lòng chọn hoặc nhập nhà cung cấp.';
    if (!$drug_ids || !is_array($drug_ids) || count($drug_ids) === 0) $errors[] = 'Phiếu nhập phải có ít nhất 1 dòng thuốc.';

    // Validate từng dòng
    $validItems = [];
    if (!$errors) {
        foreach ($drug_ids as $i => $did) {
            $did = (int)$did;
            $batch = trim($batch_nos[$i] ?? '');
            $mfg = trim($mfg_dates[$i] ?? '');
            $exp = trim($expiry_dates[$i] ?? '');
            $qty = (int)($quantities[$i] ?? 0);
            $pIn = (float)($prices_in[$i] ?? 0);
            $pOut = (float)($prices_out[$i] ?? 0);

            if ($did <= 0) { $errors[] = "Dòng " . ($i+1) . ": Vui lòng chọn thuốc."; continue; }
            if (!$batch) { $errors[] = "Dòng " . ($i+1) . ": Mã lô không được trống."; continue; }
            if (!$exp) { $errors[] = "Dòng " . ($i+1) . ": Hạn sử dụng bắt buộc."; continue; }
            if ($qty <= 0) { $errors[] = "Dòng " . ($i+1) . ": Số lượng phải > 0."; continue; }
            if ($pIn < 0) { $errors[] = "Dòng " . ($i+1) . ": Giá nhập không được âm."; continue; }
            if ($pOut <= 0) { $errors[] = "Dòng " . ($i+1) . ": Giá bán phải > 0."; continue; }

            // Validate HSD > NSX
            if ($mfg && $exp && strtotime($exp) <= strtotime($mfg)) {
                $errors[] = "Dòng " . ($i+1) . ": Hạn sử dụng phải sau ngày sản xuất.";
                continue;
            }
            // Validate HSD > hôm nay
            if (strtotime($exp) <= strtotime(date('Y-m-d'))) {
                $errors[] = "Dòng " . ($i+1) . ": Không được nhập lô đã hết hạn (HSD: $exp).";
                continue;
            }

            $drug = pms_fetch_one("SELECT * FROM drugs WHERE id=?", 'i', [$did]);
            if (!$drug) { $errors[] = "Dòng " . ($i+1) . ": Thuốc không tồn tại."; continue; }

            $validItems[] = [
                'drug_id' => $did,
                'drug' => $drug,
                'batch_no' => $batch,
                'mfg_date' => $mfg ?: null,
                'expiry_date' => $exp,
                'quantity' => $qty,
                'purchase_price' => $pIn,
                'sale_price' => $pOut,
                'line_total' => $qty * $pIn,
            ];
        }
    }

    if (!$errors && $validItems) {
        $db = pms_db();
        mysqli_begin_transaction($db);
        try {
            $purchaseCode = pms_next_purchase_code();
            $totalAmount = array_sum(array_column($validItems, 'line_total'));
            $createdBy = $_SESSION['username'] ?? 'system';

            pms_exec("INSERT INTO purchase_header (purchase_code, supplier_name, document_no, note, total_amount, created_by) VALUES (?,?,?,?,?,?)",
                'ssssds', [$purchaseCode, $supplier_name, $document_no, $note, $totalAmount, $createdBy]);
            $purchaseId = pms_last_id();

            foreach ($validItems as $item) {
                $drug = $item['drug'];
                // Lưu chi tiết phiếu nhập
                pms_exec("INSERT INTO purchase_item (purchase_id, drug_id, drug_name, batch_no, mfg_date, expiry_date, quantity, purchase_price, sale_price, line_total) VALUES (?,?,?,?,?,?,?,?,?,?)",
                    'iissssiddd', [$purchaseId, $item['drug_id'], $drug['name'], $item['batch_no'], $item['mfg_date'], $item['expiry_date'], $item['quantity'], $item['purchase_price'], $item['sale_price'], $item['line_total']]);

                // Cập nhật hoặc tạo lô trong stock
                $existing = pms_fetch_one("SELECT stock_id, quantity FROM stock WHERE drug_id=? AND batch_no=? LIMIT 1", 'is', [$item['drug_id'], $item['batch_no']]);
                if ($existing) {
                    pms_exec("UPDATE stock SET quantity = quantity + ?, purchase_price=?, sale_price=?, expiry_date=?, mfg_date=?, supplier_name=? WHERE stock_id=?",
                        'iddsssi', [$item['quantity'], $item['purchase_price'], $item['sale_price'], $item['expiry_date'], $item['mfg_date'], $supplier_name, $existing['stock_id']]);
                } else {
                    pms_exec("INSERT INTO stock (drug_id, drug_name, active_ingredient, category, company, supplier_name, unit, retail_unit, conversion_factor, batch_no, mfg_date, expiry_date, quantity, min_quantity, purchase_price, sale_price, date_supplied) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        'isssssssisssiidds',
                        [$item['drug_id'], $drug['name'], $drug['active_ingredient'] ?? '', $drug['category'] ?? '', $drug['company'] ?? '', $supplier_name, $drug['unit'] ?? 'Hộp', $drug['retail_unit'] ?? 'Viên', (int)($drug['conversion_factor'] ?? 1), $item['batch_no'], $item['mfg_date'], $item['expiry_date'], $item['quantity'], (int)($drug['min_stock'] ?? 20), $item['purchase_price'], $item['sale_price'], date('Y-m-d H:i:s')]);
                }
            }

            pms_log('Nhập kho', "Tạo phiếu nhập $purchaseCode — NCC: $supplier_name — " . count($validItems) . " dòng, tổng: " . number_format($totalAmount) . "đ");
            mysqli_commit($db);
            pms_flash("Đã lưu phiếu nhập $purchaseCode thành công (" . count($validItems) . " dòng thuốc).");
            pms_redirect('stock.php');
        } catch (Throwable $e) {
            mysqli_rollback($db);
            $errors[] = 'Lỗi khi lưu phiếu nhập: ' . $e->getMessage();
        }
    }
}

// ── Dữ liệu hiển thị ──
$tab = $_GET['tab'] ?? 'nhap';
$search_drug = trim($_GET['q'] ?? '');
$list = $search_drug
    ? pms_fetch_all("SELECT drug_id, drug_name, category, retail_unit, SUM(quantity) total_qty, MIN(expiry_date) nearest_expiry FROM stock WHERE drug_name LIKE ? OR active_ingredient LIKE ? OR batch_no LIKE ? GROUP BY drug_id, drug_name, category, retail_unit ORDER BY drug_name ASC", 'sss', ["%$search_drug%","%$search_drug%","%$search_drug%"])
    : pms_fetch_all("SELECT drug_id, drug_name, category, retail_unit, SUM(quantity) total_qty, MIN(expiry_date) nearest_expiry FROM stock GROUP BY drug_id, drug_name, category, retail_unit ORDER BY drug_name ASC");
$batches = pms_fetch_all("SELECT * FROM stock ORDER BY drug_name ASC, expiry_date ASC");
$byDrug = [];
foreach ($batches as $b) { $byDrug[$b['drug_id']][] = $b; }

// Lịch sử phiếu nhập
$purchases = pms_fetch_all("SELECT * FROM purchase_header ORDER BY purchase_id DESC LIMIT 50");

pms_render_header('Nhập kho theo lô và hạn dùng', $role, 'stock.php', pms_stock_stats());
?>

<!-- Tabs -->
<div style="display:flex;gap:6px;margin-bottom:16px;">
    <a class="btn <?= $tab === 'nhap' ? 'primary' : 'secondary' ?> sm" href="?tab=nhap">📥 Lập phiếu nhập kho</a>
    <a class="btn <?= $tab === 'ton' ? 'primary' : 'secondary' ?> sm" href="?tab=ton">📦 Tồn kho & lô thuốc</a>
    <a class="btn <?= $tab === 'history' ? 'primary' : 'secondary' ?> sm" href="?tab=history">📋 Lịch sử phiếu nhập</a>
</div>

<?php if ($tab === 'nhap'): ?>
<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- TAB 1: Lập phiếu nhập kho (header + multi-line detail)               -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="panel">
    <div class="panel-head">
        <div>
            <h2>📥 Lập phiếu nhập kho</h2>
            <div class="panel-subtitle">Phiếu nhập dạng header + nhiều dòng thuốc. Hệ thống tự cập nhật tồn kho theo lô.</div>
        </div>
    </div>
    <?php if ($errors): ?><div class="alert error"><?= pms_h(implode('<br>', $errors)) ?></div><?php endif; ?>
    <form method="post" id="purchaseForm">
        <?= pms_csrf_field() ?>
        <input type="hidden" name="save_purchase" value="1">

        <!-- Header phiếu nhập -->
        <div class="form-grid two" style="margin-bottom:20px;">
            <label>Nhà cung cấp *
                <input type="text" name="supplier_name" list="suplist" required placeholder="Chọn hoặc nhập tên NCC">
                <datalist id="suplist">
                    <?php foreach($suppliers_db as $s): ?><option value="<?= pms_h($s['name']) ?>"></option><?php endforeach; ?>
                </datalist>
            </label>
            <label>Số chứng từ / hóa đơn nguồn
                <input type="text" name="document_no" placeholder="VD: HĐ-2026-001">
            </label>
            <label style="grid-column:1/-1">Ghi chú phiếu nhập
                <input type="text" name="note" placeholder="VD: Nhập bổ sung theo đơn đặt hàng tháng 4">
            </label>
        </div>

        <!-- Chi tiết dòng thuốc -->
        <h3 style="margin:0 0 12px;font-size:14px;font-weight:700;color:var(--gray-700);">Chi tiết thuốc nhập (thêm/xóa dòng)</h3>
        <div class="table-wrap" style="overflow-x:auto;">
            <table id="purchaseLines" style="min-width:1120px; width:100%; table-layout:fixed;">
                <thead>
                    <tr>
                        <th style="width:240px">Thuốc *</th>
                        <th style="width:100px">Mã lô *</th>
                        <th style="width:130px">Ngày SX</th>
                        <th style="width:130px">Hạn SD *</th>
                        <th style="width:100px">SL *</th>
                        <th style="width:130px">Giá nhập</th>
                        <th style="width:130px">Giá bán *</th>
                        <th style="width:110px">Thành tiền</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="purchaseBody">
                    <!-- Dòng đầu tiên (template) -->
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7" style="text-align:right;font-weight:700;">Tổng tiền phiếu nhập:</td>
                        <td><strong id="grandTotal">0 đ</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px;">
            <button type="button" class="btn secondary sm" onclick="addLine()">➕ Thêm dòng thuốc</button>
        </div>
        <div class="actions" style="margin-top:20px;">
            <button type="submit" onclick="return validatePurchase()">💾 Lưu phiếu nhập kho</button>
            <button type="reset" class="btn secondary" onclick="resetPurchase()">Xóa trắng</button>
        </div>
    </form>
</div>

<script>
const drugsData = <?= json_encode($drugs_db, JSON_UNESCAPED_UNICODE) ?>;
let lineCount = 0;

function addLine() {
    lineCount++;
    const tbody = document.getElementById('purchaseBody');
    const tr = document.createElement('tr');
    tr.id = 'line-' + lineCount;
    let opts = '<option value="">-- Chọn thuốc --</option>';
    drugsData.forEach(d => { opts += `<option value="${d.id}" data-price="${d.sale_price}" data-pprice="${d.purchase_price || 0}">${d.name}</option>`; });
    tr.innerHTML = `
        <td><select name="items_drug_id[]" class="drug-select" onchange="drugChanged(this)" style="width:100%;padding:7px;">${opts}</select></td>
        <td><input type="text" name="items_batch_no[]" placeholder="LOT..." style="width:100%;padding:7px;"></td>
        <td><input type="date" name="items_mfg_date[]" style="width:100%;padding:7px;"></td>
        <td><input type="date" name="items_expiry_date[]" required style="width:100%;padding:7px;"></td>
        <td><input type="number" name="items_quantity[]" min="1" value="1" class="qty-in" oninput="calcLine(this)" style="width:100%;padding:7px;text-align:center;"></td>
        <td><input type="number" name="items_purchase_price[]" min="0" step="500" value="0" class="pin-in" oninput="calcLine(this)" style="width:100%;padding:7px;"></td>
        <td><input type="number" name="items_sale_price[]" min="0" step="500" value="0" class="pout-in" style="width:100%;padding:7px;"></td>
        <td><span class="line-total" style="font-weight:600;">0 đ</span></td>
        <td><button type="button" class="btn danger sm" onclick="removeLine('line-${lineCount}')" title="Xóa dòng">✕</button></td>
    `;
    tbody.appendChild(tr);
}
function removeLine(id) {
    const row = document.getElementById(id);
    if (row) row.remove();
    calcGrand();
}
function drugChanged(sel) {
    const opt = sel.selectedOptions[0];
    const tr = sel.closest('tr');
    if (opt && opt.dataset.pprice) tr.querySelector('.pin-in').value = opt.dataset.pprice;
    if (opt && opt.dataset.price) tr.querySelector('.pout-in').value = opt.dataset.price;
    calcLine(sel);
}
function calcLine(el) {
    const tr = el.closest('tr');
    const qty = parseInt(tr.querySelector('.qty-in').value) || 0;
    const pin = parseFloat(tr.querySelector('.pin-in').value) || 0;
    const total = qty * pin;
    tr.querySelector('.line-total').innerText = total.toLocaleString('vi-VN') + ' đ';
    calcGrand();
}
function calcGrand() {
    let sum = 0;
    document.querySelectorAll('#purchaseBody tr').forEach(tr => {
        const qty = parseInt(tr.querySelector('.qty-in')?.value) || 0;
        const pin = parseFloat(tr.querySelector('.pin-in')?.value) || 0;
        sum += qty * pin;
    });
    document.getElementById('grandTotal').innerText = sum.toLocaleString('vi-VN') + ' đ';
}
function validatePurchase() {
    const rows = document.querySelectorAll('#purchaseBody tr');
    if (rows.length === 0) { alert('Vui lòng thêm ít nhất 1 dòng thuốc.'); return false; }
    let ok = true;
    rows.forEach((tr, i) => {
        const drug = tr.querySelector('.drug-select').value;
        const batch = tr.querySelector('input[name="items_batch_no[]"]').value.trim();
        const exp = tr.querySelector('input[name="items_expiry_date[]"]').value;
        const mfg = tr.querySelector('input[name="items_mfg_date[]"]').value;
        const qty = parseInt(tr.querySelector('.qty-in').value) || 0;
        if (!drug || !batch || !exp || qty <= 0) { alert('Dòng ' + (i+1) + ': Vui lòng điền đủ Thuốc, Mã lô, HSD, Số lượng.'); ok = false; }
        if (mfg && exp && new Date(exp) <= new Date(mfg)) { alert('Dòng ' + (i+1) + ': Hạn sử dụng phải sau ngày sản xuất.'); ok = false; }
        if (exp && new Date(exp) <= new Date()) { alert('Dòng ' + (i+1) + ': Không được nhập lô đã hết hạn.'); ok = false; }
    });
    return ok;
}
function resetPurchase() { document.getElementById('purchaseBody').innerHTML = ''; lineCount = 0; calcGrand(); }
// Thêm sẵn 1 dòng khi load
document.addEventListener('DOMContentLoaded', () => addLine());
</script>

<?php elseif ($tab === 'ton'): ?>
<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- TAB 2: Tồn kho & lô thuốc                                            -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="panel" id="lots">
    <div class="panel-head">
        <div>
            <h2>📦 Danh sách tồn kho</h2>
            <div class="panel-subtitle">Hiển thị theo thuốc — bấm để xem chi tiết từng lô.</div>
        </div>
        <form method="get" class="filter-row" style="margin:0; display:flex; gap:8px; align-items:center; flex-wrap: nowrap;">
            <input type="hidden" name="tab" value="ton">
            <input type="text" name="q" value="<?= pms_h($search_drug) ?>" placeholder="Tìm tên thuốc / hoạt chất / lô..." style="flex:1;">
            <button type="submit" class="btn sm">Tìm</button>
            <?php if ($search_drug): ?><a class="btn secondary sm" href="stock.php?tab=ton">Xóa lọc</a><?php endif; ?>
        </form>
    </div>
    <?php if (!$list): ?>
        <div class="empty">Không tìm thấy thuốc nào<?= $search_drug ? " với từ khóa \"$search_drug\"" : '' ?>.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tên thuốc</th>
                    <th>Nhóm</th>
                    <th>Tổng tồn</th>
                    <th>Hạn gần nhất</th>
                    <th>Chi tiết lô</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $row):
                $lots = $byDrug[$row['drug_id']] ?? [];
                $nearestDays = $row['nearest_expiry'] ? pms_days_until($row['nearest_expiry']) : null;
                $statusClass = $nearestDays === null ? 'gray' : ($nearestDays < 0 ? 'danger' : ($nearestDays <= 90 ? 'warn' : 'ok'));
                $rowId = 'lot-' . md5($row['drug_name'] . $row['drug_id']);
            ?>
                <tr>
                    <td><strong><?= pms_h($row['drug_name']) ?></strong></td>
                    <td><?= pms_h($row['category']) ?></td>
                    <td><strong><?= number_format((int)$row['total_qty']) ?></strong> <?= pms_h($row['retail_unit']) ?></td>
                    <td>
                        <?= $row['nearest_expiry'] ? pms_h($row['nearest_expiry']) : '—' ?>
                        <?php if ($nearestDays !== null): ?>
                            <span class="badge <?= $statusClass ?>">
                                <?= $nearestDays < 0 ? 'Hết hạn' : ($nearestDays <= 90 ? "$nearestDays ngày" : 'Còn hạn') ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn secondary sm" onclick="var t=document.getElementById('<?= $rowId ?>');t.style.display=t.style.display==='none'?'table-row':'none'">
                            <?= count($lots) ?> lô
                        </button>
                    </td>
                </tr>
                <tr id="<?= $rowId ?>" style="display:none">
                    <td colspan="5" style="padding:0;background:var(--gray-50)">
                        <table style="width:100%;margin:0;border-radius:0">
                            <thead>
                                <tr><th>Mã lô</th><th>Số lượng</th><th>NSX</th><th>HSD</th><th>Giá nhập</th><th>Giá bán</th><th>NCC</th><th>Trạng thái</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($lots as $lot):
                                $d = pms_days_until($lot['expiry_date']);
                                $sc = $d === null ? 'gray' : ($d < 0 ? 'danger' : ($d <= 30 ? 'danger' : ($d <= 90 ? 'warn' : 'ok')));
                            ?>
                                <tr>
                                    <td><?= pms_h($lot['batch_no']) ?></td>
                                    <td><?= number_format((int)$lot['quantity']) ?> <?= pms_h($lot['retail_unit'] ?? '') ?></td>
                                    <td><?= pms_h($lot['mfg_date'] ?? '—') ?></td>
                                    <td><?= pms_h($lot['expiry_date'] ?? '—') ?></td>
                                    <td><?= pms_currency((float)$lot['purchase_price']) ?></td>
                                    <td><?= pms_currency((float)$lot['sale_price']) ?></td>
                                    <td><?= pms_h($lot['supplier_name'] ?? '—') ?></td>
                                    <td><span class="badge <?= $sc ?>"><?= $d === null ? '—' : ($d < 0 ? 'Hết hạn' : ($d <= 30 ? "⚠ $d ngày" : ($d <= 90 ? "$d ngày" : 'An toàn'))) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- TAB 3: Lịch sử phiếu nhập                                            -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="panel">
    <div class="panel-head">
        <div>
            <h2>📋 Lịch sử phiếu nhập kho</h2>
            <div class="panel-subtitle">50 phiếu nhập gần nhất. Nhấn "Xem" để xem chi tiết hoặc in phiếu.</div>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Mã phiếu</th>
                    <th>Nhà cung cấp</th>
                    <th>Số chứng từ</th>
                    <th>Tổng tiền</th>
                    <th>Người tạo</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($purchases as $p): ?>
                <tr>
                    <td><strong style="color:var(--blue-brand)"><?= pms_h($p['purchase_code']) ?></strong></td>
                    <td><?= pms_h($p['supplier_name']) ?></td>
                    <td><?= pms_h($p['document_no'] ?? '—') ?></td>
                    <td><span class="badge ok"><?= pms_currency((float)$p['total_amount']) ?></span></td>
                    <td><span class="badge gray"><?= pms_h($p['created_by'] ?? '') ?></span></td>
                    <td><?= pms_h(date('d/m/Y H:i', strtotime($p['created_at']))) ?></td>
                    <td>
                        <div class="actions">
                            <a class="btn sm secondary" href="print_purchase.php?id=<?= (int)$p['purchase_id'] ?>" target="_blank">🖨 In / Xem</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$purchases): ?>
                <tr><td colspan="7"><div class="empty">Chưa có phiếu nhập nào.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php pms_render_footer(); ?>
