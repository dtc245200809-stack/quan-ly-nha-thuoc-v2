<?php
include_once 'app.php';
pms_auth('admin,manager');
$role = pms_current_role();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pms_csrf_verify('catalog.php');
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $code = trim($_POST['drug_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $active = trim($_POST['active_ingredient'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $dosage_form = trim($_POST['dosage_form'] ?? '');
        $unit = trim($_POST['unit'] ?? 'Hộp');
        $retail_unit = trim($_POST['retail_unit'] ?? 'Viên');
        $conv = max(1, (int)($_POST['conversion_factor'] ?? 1));
        $salePrice = (float)($_POST['sale_price'] ?? 0);
        $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
        $company = trim($_POST['company'] ?? '');
        $strength = trim($_POST['strength'] ?? '');
        $minStock = max(0, (int)($_POST['min_stock'] ?? 20));
        $description = trim($_POST['description'] ?? '');
        
        // Cập nhật: Số lượng và Hạn dùng
        $newTotal = (int)($_POST['initial_stock'] ?? 0);
        $oldTotal = (int)($_POST['old_total_stock'] ?? 0);
        $initBatch = trim($_POST['initial_batch'] ?? 'DAUKY');
        $initExp = trim($_POST['initial_expiry'] ?? '');
        $oldExp = trim($_POST['old_expiry'] ?? '');
        if (!$initExp) $initExp = date('Y-m-d', strtotime('+2 years'));
        
        if (!$code || !$name) {
            $error = 'Mã thuốc và Tên thuốc không được để trống';
        } else {
            // [NGHIỆP VỤ] - KHÔNG CHO PHÉP DÙNG LẠI MÃ THUỐC ĐÃ SOFT-DELETE
            // Tránh loạn dữ liệu: Nếu thuốc cũ (P-01) đã soft-delete, P-01 vẫn nằm trong lịch sử Hóa đơn.
            // Nếu cho phép tái sử dụng P-01 cho thuốc mới, kế toán xem lại báo cáo sẽ bị nhầm lẫn giá vốn/thuốc.
            $exists = pms_fetch_one("SELECT id FROM drugs WHERE drug_code=?", "s", [$code]);
            if ($exists && $exists['id'] != $id) {
                $error = 'Mã thuốc này đã tồn tại (hoặc đã từng tồn tại và bị ngừng kinh doanh), vui lòng chọn mã khác!';
            } else {
                if ($id > 0) {
                    pms_exec("UPDATE drugs SET drug_code=?, name=?, active_ingredient=?, category=?, dosage_form=?, unit=?, retail_unit=?, conversion_factor=?, sale_price=?, purchase_price=?, company=?, strength=?, min_stock=?, description=? WHERE id=?",
                        "sssssssiddssisi",
                        [$code, $name, $active, $category, $dosage_form, $unit, $retail_unit, $conv, $salePrice, $purchasePrice, $company, $strength, $minStock, $description, $id]);
                    
                    // 1. Đồng bộ mức tồn tối thiểu
                    pms_exec("UPDATE stock SET min_quantity=? WHERE drug_id=?", "ii", [$minStock, $id]);

                    // 2. Đồng bộ Hạn sử dụng (Nếu người dùng đổi ngày ở trang danh mục -> Cập nhật cho tất cả lô đang còn)
                    if ($initExp !== $oldExp) {
                        pms_exec("UPDATE stock SET expiry_date=? WHERE drug_id=? AND quantity > 0", "si", [$initExp, $id]);
                        pms_log('Cập nhật hạn dùng', "Đổi HSD của $name sang $initExp");
                    }

                    // 3. Đồng bộ Số lượng (Tính chênh lệch để nhập/xuất điều chỉnh)
                    if ($newTotal != $oldTotal) {
                        $diff = $newTotal - $oldTotal;
                        pms_exec("INSERT INTO stock (drug_id, drug_name, active_ingredient, category, company, supplier_name, unit, retail_unit, conversion_factor, batch_no, expiry_date, quantity, min_quantity, purchase_price, sale_price, date_supplied) 
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                                 "isssssssisssiidd",
                                 [$id, $name, $active, $category, $company, $diff > 0 ? 'Điều chỉnh tăng' : 'Điều chỉnh giảm', $unit, $retail_unit, $conv, $initBatch, $initExp, $diff, $minStock, $purchasePrice, $salePrice, date('Y-m-d')]);
                        pms_log('Điều chỉnh tồn kho', "Điều chỉnh " . ($diff > 0 ? "+" : "") . "$diff $retail_unit cho $name");
                    }

                    pms_log('Cập nhật danh mục thuốc', "Sửa thuốc: $name ($code)");
                    pms_flash('Đã cập nhật dữ liệu' . ($newTotal != $oldTotal ? " và điều chỉnh tồn kho thành $newTotal." : ""));
                } else {
                    pms_exec("INSERT INTO drugs (drug_code, name, active_ingredient, category, dosage_form, unit, retail_unit, conversion_factor, sale_price, purchase_price, company, strength, min_stock, description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        "sssssssiddssis",
                        [$code, $name, $active, $category, $dosage_form, $unit, $retail_unit, $conv, $salePrice, $purchasePrice, $company, $strength, $minStock, $description]);
                    
                    $newId = pms_last_id();
                    
                    // Nếu có nhập số lượng đầu kỳ -> Tạo ngay 1 lô trong stock
                    if ($initQty > 0) {
                        pms_exec("INSERT INTO stock (drug_id, drug_name, active_ingredient, category, company, supplier_name, unit, retail_unit, conversion_factor, batch_no, expiry_date, quantity, min_quantity, purchase_price, sale_price, date_supplied) 
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                                 "isssssssisssiidd",
                                 [$newId, $name, $active, $category, $company, 'Nhập đầu kỳ', $unit, $retail_unit, $conv, $initBatch, $initExp, $initQty, $minStock, $purchasePrice, $salePrice, date('Y-m-d')]);
                        pms_log('Nhập kho đầu kỳ', "Tạo lô đầu kỳ cho $name: $initQty $retail_unit");
                    }
                    
                    pms_log('Thêm danh mục thuốc', "Thêm mới: $name ($code)");
                    pms_flash('Đã thêm thuốc vào danh mục' . ($initQty > 0 ? " và khởi tạo $initQty $retail_unit tồn kho." : ""));
                }
                pms_redirect('catalog.php');
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $item = pms_fetch_one("SELECT name, drug_code FROM drugs WHERE id=?", "i", [$id]);
        if ($item) {
            // Kiểm tra xem thuốc đã phát sinh giao dịch chưa (Trong kho hoặc hóa đơn)
            $inStock = pms_fetch_one("SELECT stock_id FROM stock WHERE drug_id=? LIMIT 1", 'i', [$id]);
            $inInvoice = pms_fetch_one("SELECT item_id FROM invoice_item WHERE drug_id=? LIMIT 1", 'i', [$id]);
            $inPurchase = pms_fetch_one("SELECT p_item_id FROM purchase_item WHERE drug_id=? LIMIT 1", 'i', [$id]);
            
            if ($inStock || $inInvoice || $inPurchase) {
                pms_flash("Không thể xóa thuốc này vì đã phát sinh giao dịch (nhập kho hoặc bán hàng). Vui lòng cấu hình Ngừng Kinh Doanh nếu cần.", "error");
            } else {
                pms_exec("UPDATE drugs SET is_deleted=1 WHERE id=?", "i", [$id]);
                pms_log('Xóa danh mục thuốc', "Xóa thuốc: " . trim($item['name']) . " (" . trim($item['drug_code']) . ")");
                pms_flash('Đã xóa thuốc khỏi danh mục');
            }
        }
        pms_redirect('catalog.php');
    }
}

$search = trim($_GET['q'] ?? '');
$catSearch = trim($_GET['cat'] ?? '');

$where = "d.is_deleted=0";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (d.name LIKE ? OR d.drug_code LIKE ? OR d.active_ingredient LIKE ? OR d.category LIKE ?)";
    $params = array_merge($params, array_fill(0, 4, "%$search%"));
    $types .= "ssss";
}
if ($catSearch !== '') {
    $where .= " AND d.category = ?";
    $params[] = $catSearch;
    $types .= "s";
}

if (!empty($params)) {
    $drugs = pms_fetch_all("SELECT d.*, IFNULL(SUM(s.quantity), 0) as current_stock, MIN(s.expiry_date) as earliest_expiry FROM drugs d LEFT JOIN stock s ON s.drug_id = d.id AND s.quantity > 0 WHERE $where GROUP BY d.id ORDER BY d.name ASC", $types, $params);
} else {
    $drugs = pms_fetch_all("SELECT d.*, IFNULL(SUM(s.quantity), 0) as current_stock, MIN(s.expiry_date) as earliest_expiry FROM drugs d LEFT JOIN stock s ON s.drug_id = d.id AND s.quantity > 0 WHERE $where GROUP BY d.id ORDER BY d.name ASC");
}

$allCats = pms_fetch_all("SELECT DISTINCT category FROM drugs WHERE is_deleted=0 AND category != '' ORDER BY category ASC");

pms_render_header('Quản lý danh mục thuốc', $role, 'catalog.php', ['Tổng thuốc' => count($drugs)]);
?>
<section class="dashboard-grid">
    <section class="panel">
        <div class="panel-head"><h2>📝 Quản lý danh mục thuốc</h2></div>
        <?php if ($error): ?><div class="alert error"><?= pms_h($error) ?></div><?php endif; ?>
        <form method="post" class="form-grid two" id="drugForm">
            <?= pms_csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="d_id" value="0">
            <input type="hidden" name="old_total_stock" id="d_old_qty" value="0">
            <input type="hidden" name="old_expiry" id="d_old_exp" value="">
            <label>Mã thuốc * <input type="text" name="drug_code" id="d_code" required placeholder="VD: PARA01"></label>
            <label>Tên thuốc * <input type="text" name="name" id="d_name" required placeholder="VD: Paracetamol 500mg"></label>
            <label>Hoạt chất <input type="text" name="active_ingredient" id="d_active" placeholder="VD: Paracetamol"></label>
            <label>Nhóm thuốc <input type="text" name="category" id="d_category" placeholder="VD: Giảm đau - Hạ sốt" list="catList">
                <datalist id="catList">
                    <option value="Giảm đau - hạ sốt"><option value="Kháng sinh"><option value="Vitamin">
                    <option value="Dị ứng"><option value="Dạ dày"><option value="Kháng viêm">
                    <option value="Điện giải"><option value="Rửa mũi - nhỏ mắt"><option value="Tim mạch">
                    <option value="Hô hấp"><option value="Da liễu"><option value="Thực phẩm chức năng">
                </datalist>
            </label>
            <label>Hàm lượng <input type="text" name="strength" id="d_strength" placeholder="VD: 500mg"></label>
            <label>Dạng bào chế <input type="text" name="dosage_form" id="d_dosage" placeholder="VD: Viên nén" list="dosageList">
                <datalist id="dosageList">
                    <option value="Viên nén"><option value="Viên nang"><option value="Dung dịch">
                    <option value="Siro"><option value="Kem"><option value="Gel">
                    <option value="Bột pha"><option value="Thuốc nhỏ"><option value="Thuốc xịt">
                </datalist>
            </label>
            <label>Nhà sản xuất <input type="text" name="company" id="d_company" placeholder="VD: Dược Hậu Giang"></label>
            <label>Đơn vị nhập (cơ bản) <input type="text" name="unit" id="d_unit" value="Hộp" placeholder="Hộp / Chai / Tuýp"></label>
            <label>Đơn vị bán lẻ <input type="text" name="retail_unit" id="d_retail" value="Viên" placeholder="Viên / ml / gói"></label>
            <label>Hệ số quy đổi <input type="number" name="conversion_factor" id="d_conv" min="1" value="1"><span class="hint">VD: 1 Hộp = 10 Viên → nhập 10</span></label>
            <label>Giá bán <input type="number" name="sale_price" id="d_price" step="100" value="0"></label>
            <label>Giá nhập đề xuất <input type="number" name="purchase_price" id="d_pprice" step="100" value="0"></label>
            <label>Mức tồn tối thiểu <input type="number" name="min_stock" id="d_minstock" min="0" value="20"><span class="hint">Cảnh báo khi tồn dưới mức này</span></label>
            <div id="initial_stock_section" style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; background: var(--blue-50); padding: 12px; border-radius: var(--radius); margin: 8px 0; border: 1px dashed var(--blue-200);">
                <label style="color:var(--blue-700);font-weight:600;">Tổng tồn kho hiện tại <input type="number" name="initial_stock" id="d_init_qty" min="0" value="0"></label>
                <label>Số lô <input type="text" name="initial_batch" id="d_init_batch" value="DAUKY"></label>
                <label>Hạn sử dụng <input type="date" name="initial_expiry" id="d_init_expiry"></label>
            </div>
            <label style="grid-column:1/-1">Mô tả / Ghi chú <textarea name="description" id="d_desc" rows="2" placeholder="Mô tả ngắn về thuốc (tùy chọn)"></textarea></label>
            <div class="actions" style="grid-column: 1 / -1;">
                <button type="submit" id="btn_save">Thêm mới</button>
                <button type="button" class="btn secondary" onclick="resetForm()">Hủy</button>
            </div>
        </form>
    </section>
</section>

<section class="panel">
    <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;">
        <h2>Danh mục thuốc (<?= count($drugs) ?>)</h2>
        <form method="get" style="display:flex;gap:6px;">
            <select name="cat" style="padding:7px 12px;border:1px solid var(--gray-300);border-radius:var(--radius);background:#fff;max-width:200px;">
                <option value="">Tất cả nhóm</option>
                <?php foreach ($allCats as $c): ?>
                    <option value="<?= pms_h($c['category']) ?>" <?= $catSearch === $c['category'] ? 'selected' : '' ?>><?= pms_h($c['category']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" value="<?= pms_h($search) ?>" placeholder="Tìm mã / tên / hoạt chất..." style="padding:7px 12px;border:1px solid var(--gray-300);border-radius:var(--radius);width:250px;">
            <button type="submit" class="btn sm">Tìm</button>
            <?php if ($search || $catSearch): ?><a class="btn secondary sm" href="catalog.php">Xóa lọc</a><?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Mã</th><th>Tên thuốc</th><th>Hoạt chất</th><th>Nhóm</th><th>Hàm lượng</th><th>ĐVT</th><th>Giá bán</th><th>NSX</th><th>Tồn TT</th><th>Hành động</th>
            </tr></thead>
            <tbody>
                <?php foreach ($drugs as $d): ?>
                    <tr>
                        <td><span class="badge gray"><?= pms_h($d['drug_code']) ?></span></td>
                        <td><strong><?= pms_h($d['name']) ?></strong></td>
                        <td><?= pms_h($d['active_ingredient']) ?></td>
                        <td><?= pms_h($d['category'] ?? '') ?></td>
                        <td><?= pms_h($d['strength'] ?? '') ?></td>
                        <td><?= pms_h($d['unit']) ?></td>
                        <td><?= pms_currency($d['sale_price']) ?></td>
                        <td><?= pms_h($d['company']) ?></td>
                        <td><strong><?= number_format((int)$d['current_stock']) ?></strong></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn sm secondary" onclick="editRow(<?= $d['id'] ?>, <?= pms_h(json_encode($d, JSON_UNESCAPED_UNICODE)) ?>)">Sửa</button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Xóa mã thuốc này?');">
                                    <?= pms_csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <button type="submit" class="btn sm danger">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(!$drugs): ?><tr><td colspan="10"><div class="empty">Chưa có thuốc nào trong Danh mục.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
function editRow(id, data) {
    document.getElementById('d_id').value = id;
    document.getElementById('d_code').value = data.drug_code || '';
    document.getElementById('d_name').value = data.name || '';
    document.getElementById('d_active').value = data.active_ingredient || '';
    document.getElementById('d_category').value = data.category || '';
    document.getElementById('d_dosage').value = data.dosage_form || '';
    document.getElementById('d_unit').value = data.unit || 'Hộp';
    document.getElementById('d_retail').value = data.retail_unit || 'Viên';
    document.getElementById('d_conv').value = data.conversion_factor || 1;
    document.getElementById('d_price').value = data.sale_price || 0;
    document.getElementById('d_pprice').value = data.purchase_price || 0;
    document.getElementById('d_company').value = data.company || '';
    document.getElementById('d_strength').value = data.strength || '';
    document.getElementById('d_minstock').value = data.min_stock || 20;
    document.getElementById('d_desc').value = data.description || '';
    document.getElementById('btn_save').innerText = 'Cập nhật';
    
    // Đổ dữ liệu tồn kho hiện tại
    document.getElementById('d_old_qty').value = data.current_stock || 0;
    document.getElementById('d_init_qty').value = data.current_stock || 0;
    
    // Đổ dữ liệu hạn dùng hiện tại
    document.getElementById('d_old_exp').value = data.earliest_expiry || '';
    document.getElementById('d_init_expiry').value = data.earliest_expiry || '';
    
    document.getElementById('initial_stock_section').style.display = 'grid';
    document.getElementById('d_code').focus();
    window.scrollTo({top:0, behavior:'smooth'});
}
function resetForm() {
    document.getElementById('drugForm').reset();
    document.getElementById('d_id').value = '0';
    document.getElementById('btn_save').innerText = 'Thêm mới';
    document.getElementById('initial_stock_section').style.display = 'grid';
}
</script>
<?php pms_render_footer(); ?>
