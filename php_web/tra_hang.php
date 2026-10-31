<?php
include_once 'app.php';
pms_auth('admin,manager,pharmacist,cashier');
$role = pms_current_role();
$invoiceCode = trim($_POST['invoice_code'] ?? $_GET['invoice_code'] ?? '');
$header = $invoiceCode ? pms_fetch_one("SELECT * FROM invoice_header WHERE invoice_code=?", 's', [$invoiceCode]) : null;
$items = $header ? pms_fetch_all("SELECT * FROM invoice_item WHERE invoice_no=?", 'i', [$header['invoice_no']]) : [];
$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['process_return'])) {
    pms_csrf_verify('tra_hang.php');
    $reason = trim($_POST['reason'] ?? '');
    if ($header && time() - strtotime($header['created_at']) > 24 * 3600) {
        $errors[] = 'Không thể trả hàng do hóa đơn đã lập quá 24 giờ.';
    } elseif ($reason === '') {
        $errors[] = 'Vui lòng nhập lý do trả hàng.';
    } else {
        $result = pms_process_return($invoiceCode, (int)$_POST['item_id'], (int)$_POST['quantity'], $reason);
        if ($result['ok']) { pms_flash($result['message']); pms_redirect('tra_hang.php'); }
        else $errors[] = $result['message'];
    }
}
$recentReturns = pms_fetch_all("SELECT r.*, DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') formatted_date FROM return_header r ORDER BY return_id DESC LIMIT 10");
pms_render_header('Quản lý trả hàng / khách hoàn trả', $role, 'tra_hang.php', [
    'Phiếu trả gần đây' => count($recentReturns),
]); ?>

<div class="th-layout">
    <!-- Form tra cứu + kết quả -->
    <div>
        <!-- Tra cứu hóa đơn -->
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>🔍 Tra cứu hóa đơn cần trả</h2>
                    <div class="panel-subtitle">Nhập mã hóa đơn để tìm danh sách thuốc đã bán và lập phiếu trả hàng.</div>
                </div>
            </div>
            <?php if($errors): ?><div class="alert error"><?= pms_h(implode(' ', $errors)) ?></div><?php endif; ?>
            <form method="get" class="th-search">
                <div class="th-search__input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="text" name="invoice_code" placeholder="Nhập mã hóa đơn (VD: INV-20260401-001)" value="<?= pms_h($invoiceCode) ?>">
                </div>
                <button type="submit">Tra cứu</button>
            </form>
        </section>

        <?php if ($header): ?>
        <!-- Thông tin hóa đơn -->
        <section class="panel th-invoice-info">
            <div class="panel-head">
                <div>
                    <h2>📋 Hóa đơn: <?= pms_h($header['invoice_code']) ?></h2>
                    <div class="panel-subtitle">Khách hàng: <strong><?= pms_h($header['customer_name']) ?></strong></div>
                </div>
                <div class="th-invoice-total">
                    <span>Tổng hóa đơn</span>
                    <strong><?= pms_currency((float)$header['grand_total']) ?></strong>
                </div>
            </div>
            <div class="th-invoice-meta">
                <div><span>Mã HĐ</span><strong><?= pms_h($header['invoice_code']) ?></strong></div>
                <div><span>Khách hàng</span><strong><?= pms_h($header['customer_name']) ?></strong></div>
                <div><span>Thời gian</span><strong><?= pms_h($header['created_at']) ?></strong></div>
                <div><span>Tổng tiền</span><strong style="color: var(--blue-dark)"><?= pms_currency((float)$header['grand_total']) ?></strong></div>
            </div>
        </section>

        <?php
        $is_returnable = false;
        if ($header) {
            if (time() - strtotime($header['created_at']) <= 24 * 3600) {
                $is_returnable = true;
            }
        }
        ?>
        <?php if ($header && !$is_returnable): ?>
        <section class="panel">
            <div class="empty">
                <div style="font-size: 2rem; margin-bottom: 8px;">⏳</div>
                Không thể trả hàng cho hóa đơn này vì đã quá 24 giờ kể từ lúc mua (<?= pms_h($header['created_at']) ?>).
            </div>
        </section>
        <?php elseif ($header && $is_returnable): ?>
        <!-- Chọn thuốc trả lại -->
        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>↩️ Chọn thuốc trả lại</h2>
                    <div class="panel-subtitle">Chọn thuốc, nhập số lượng và lý do. Hệ thống tự cộng lại tồn kho đúng lô.</div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Thuốc</th>
                            <th>Đã bán</th>
                            <th>Đơn giá</th>
                            <th>Thành tiền</th>
                            <th>Hoàn trả</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($items as $item): ?>
                        <tr>
                            <td><strong><?= pms_h($item['drug_name']) ?></strong></td>
                            <td><span class="badge info"><?= (int)$item['quantity'] ?></span></td>
                            <td><?= pms_currency((float)$item['unit_price']) ?></td>
                            <td><?= pms_currency((float)$item['unit_price'] * (int)$item['quantity']) ?></td>
                            <td>
                                <form method="post" class="th-return-form">
                                    <?= pms_csrf_field() ?>
                                    <input type="hidden" name="invoice_code" value="<?= pms_h($invoiceCode) ?>">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                                    <input type="number" name="quantity" min="1" max="<?= (int)$item['quantity'] ?>" value="1" class="th-qty-input">
                                    <input type="text" name="reason" placeholder="Lý do trả" class="th-reason-input">
                                    <button type="submit" name="process_return" class="btn danger sm">Hoàn tiền</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(!$items): ?>
                        <tr><td colspan="5"><div class="empty">Không có dòng thuốc nào trong hóa đơn.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
        <?php elseif ($invoiceCode): ?>
        <section class="panel">
            <div class="empty">
                <div style="font-size: 2rem; margin-bottom: 8px;">❌</div>
                Không tìm thấy hóa đơn với mã <strong><?= pms_h($invoiceCode) ?></strong>. Vui lòng kiểm tra lại.
            </div>
        </section>
        <?php else: ?>
        <section class="panel">
            <div class="empty">
                <div style="font-size: 2rem; margin-bottom: 8px;">📋</div>
                Nhập mã hóa đơn ở trên để bắt đầu quy trình trả hàng.
            </div>
        </section>
        <?php endif; ?>
    </div>

    <!-- Sidebar: Lịch sử trả hàng gần đây -->
    <aside class="panel th-history">
        <div class="panel-head">
            <div>
                <h2>📜 Lịch sử trả hàng</h2>
                <div class="panel-subtitle">10 phiếu gần nhất</div>
            </div>
        </div>
        <div class="th-history__list">
            <?php foreach($recentReturns as $ret): ?>
            <div class="th-history__item">
                <div class="th-history__item-head">
                    <strong><?= pms_h($ret['invoice_code']) ?></strong>
                    <span class="badge danger">-<?= pms_currency((float)$ret['refund_total']) ?></span>
                </div>
                <div class="muted"><?= pms_h($ret['customer_name']) ?> · <?= pms_h($ret['formatted_date']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if(!$recentReturns): ?>
                <div class="empty compact">Chưa có phiếu trả hàng nào.</div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<style>
.th-layout { display: grid; grid-template-columns: 1fr 340px; gap: 16px; align-items: start; }

.th-search { display: flex; gap: 10px; }
.th-search__input-wrap {
    flex: 1; display: flex; align-items: center; gap: 8px;
    padding: 0 12px; border-radius: var(--radius);
    border: 1.5px solid var(--gray-300); background: var(--white);
    transition: border-color .18s, box-shadow .18s;
}
.th-search__input-wrap:focus-within { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
.th-search__input-wrap svg { color: var(--gray-400); flex-shrink: 0; }
.th-search__input-wrap input { border: none; background: transparent; padding: 10px 4px; font-size: 13.5px; outline: none; flex: 1; }

.th-invoice-meta {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
    border-radius: var(--radius); overflow: hidden;
    border: 1px solid var(--gray-200); background: var(--gray-50);
}
.th-invoice-meta > div {
    padding: 12px 16px; text-align: center;
    border-right: 1px solid var(--gray-200);
}
.th-invoice-meta > div:last-child { border-right: none; }
.th-invoice-meta span { display: block; font-size: 11px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; margin-bottom: 4px; }
.th-invoice-meta strong { font-size: 13.5px; font-weight: 700; color: var(--gray-800); }

.th-invoice-total { text-align: right; }
.th-invoice-total span { display: block; font-size: 11px; font-weight: 600; color: var(--gray-500); }
.th-invoice-total strong { font-size: 1.2rem; font-weight: 800; color: var(--blue-dark); }

.th-return-form { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; }
.th-qty-input { width: 60px !important; padding: 6px 8px !important; text-align: center; }
.th-reason-input { width: 120px !important; padding: 6px 8px !important; font-size: 12px !important; }

.th-history { position: sticky; top: 80px; }
.th-history__list { display: grid; gap: 8px; }
.th-history__item {
    padding: 12px 14px; border-radius: var(--radius);
    background: var(--gray-50); border: 1px solid var(--gray-200);
    transition: background .15s;
}
.th-history__item:hover { background: var(--white); }
.th-history__item-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.th-history__item-head strong { font-size: 13px; color: var(--gray-800); }

@media (max-width: 1180px) {
    .th-layout { grid-template-columns: 1fr; }
    .th-history { position: static; }
}
@media (max-width: 760px) {
    .th-invoice-meta { grid-template-columns: 1fr 1fr; }
    .th-invoice-meta > div { border-bottom: 1px solid var(--gray-200); }
    .th-return-form { flex-wrap: wrap; }
}
</style>
<?php pms_render_footer(); ?>