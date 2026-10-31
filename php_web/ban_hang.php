<?php
include_once 'app.php';
pms_auth('admin,manager,pharmacist,cashier');
$role = pms_current_role();

$errors = [];
$success_id = null;

// Xử lý thanh toán giỏ hàng (Multi-item POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_json'])) {
    pms_csrf_verify('ban_hang.php');
    $cart = json_decode($_POST['cart_json'], true);
    $customerName = trim($_POST['customer_name'] ?? '');
    if ($customerName === '') $customerName = 'Khách lẻ';
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $paymentType = trim($_POST['payment_type'] ?? 'cash');
    $discount = (float)($_POST['discount'] ?? 0);
    $usePoints = (int)($_POST['use_points'] ?? 0);

    if (!$cart) {
        $errors[] = 'Giỏ hàng trống.';
    } else {
        // Nếu chọn khách hàng có sẵn -> Lấy tên từ DB để đồng bộ dữ liệu
        if ($customerId > 0) {
            $cus = pms_fetch_one("SELECT customer_name FROM customers WHERE customer_id=?", "i", [$customerId]);
            if ($cus) $customerName = $cus['customer_name'];
        } else {
            // Nếu là khách mới hoặc khách lẻ
            $customerId = pms_upsert_customer($customerName, trim($_POST['customer_phone'] ?? ''));
        }
        
        $result = pms_create_multi_sale($customerName, $cart, $paymentType, $discount, $customerId, $usePoints);
        if ($result['ok']) {
            pms_flash($result['message']);
            pms_redirect('ban_hang.php?last_id=' . $result['invoice_no']);
        } else {
            $errors[] = $result['message'];
        }
    }
}

$options = pms_drug_options();
$customers = pms_customer_options();
$today = date('Y-m-d');

// Tránh lỗi bảng invoice_header không có status
$todayStats = pms_fetch_one("SELECT COUNT(*) c, COALESCE(SUM(grand_total),0) s FROM invoice_header WHERE DATE(created_at)=?", "s", [$today]);

pms_render_header('Bán hàng POS', $role, 'ban_hang.php', [
    'Hóa đơn hôm nay' => (int)($todayStats['c'] ?? 0),
    'Doanh thu' => pms_currency((float)($todayStats['s'] ?? 0)),
]);
?>

<div class="pos-wrapper">
    <!-- A. CỘT TRÁI: TÌM KIẾM + DANH SÁCH THUỐC -->
    <aside class="pos-col pos-col-left">
        <div class="pos-search-box">
            <input type="text" id="posSearch" placeholder="Tìm tên thuốc... (F2)" autocomplete="off" autofocus>
            <div class="pos-search-shortcuts">
                <kbd>F2</kbd> Tìm <kbd>Enter</kbd> Thêm <kbd>F4</kbd> Thanh toán
            </div>
            <div class="pos-filters">
                <button type="button" class="filter-btn active" data-filter="all">Tất cả</button>
                <button type="button" class="filter-btn" data-filter="expiring">Sắp hết hạn</button>
                <button type="button" class="filter-btn" data-filter="bestseller">Bán chạy</button>
            </div>
        </div>
        
        <div class="pos-product-list" id="posProductList">
            <?php foreach ($options as $opt): 
                $id = (int)$opt['id'];
                $qty = (int)$opt['total_quantity'];
                $price = (float)$opt['sale_price'];
                $expiry = $opt['nearest_expiry'];
                
                $expiryStatus = 'normal';
                $badgeClass = 'badge-normal';
                $badgeText = 'Bình thường';
                $canSelect = true;

                if ($expiry) {
                    $days = (strtotime($expiry) - strtotime(date('Y-m-d'))) / 86400;
                    if ($days < 0) {
                        $expiryStatus = 'expired';
                        $badgeClass = 'badge-expired';
                        $badgeText = 'Hết hạn';
                        $canSelect = false;
                    } elseif ($days <= 15) {
                        $expiryStatus = 'expiring';
                        $badgeClass = 'badge-expiring';
                        $badgeText = 'Sắp hết hạn';
                    } elseif ($days <= 30) {
                        $expiryStatus = 'near-expiry';
                        $badgeClass = 'badge-near-expiry';
                        $badgeText = 'Cận date';
                    }
                }
            ?>
            <div class="product-card <?= $canSelect ? '' : 'disabled' ?>" 
                 data-id="<?= $id ?>"
                 data-name="<?= pms_h($opt['drug_name']) ?>"
                 data-active="<?= pms_h($opt['active_ingredient'] ?? '') ?>"
                 data-code="<?= pms_h($opt['drug_code'] ?? '') ?>"
                 data-price="<?= $price ?>"
                 data-stock="<?= $qty ?>"
                 data-sold="<?= (int)($opt['total_sold'] ?? 0) ?>"
                 data-expiry-status="<?= $expiryStatus ?>"
                 onclick="<?= $canSelect ? "addToCart($id, '" . pms_escape($opt['drug_name']) . "', $price, $qty, '" . ($expiry ? date('d/m/Y', strtotime($expiry)) : 'Không') . "')" : '' ?>">
                
                <div class="product-card__header">
                    <div class="product-card__name"><?= pms_h($opt['drug_name']) ?></div>
                    <span class="status-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                </div>
                
                <div class="product-card__body">
                    <div class="product-card__info">
                        <div class="info-line"><span>Hoạt chất:</span> <strong><?= pms_h($opt['active_ingredient'] ?? '—') ?></strong></div>
                        <div class="info-line"><span>Tồn kho:</span> <strong class="<?= $qty <= 10 ? 'text-red' : 'text-green' ?>"><?= $qty ?></strong></div>
                        <div class="info-line"><span>Lô/Hạn:</span> <strong>FEFO | <?= $expiry ? date('d/m/Y', strtotime($expiry)) : '—' ?></strong></div>
                    </div>
                    <div class="product-card__action">
                        <div class="product-card__price"><?= pms_currency($price) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- B. CỘT GIỮA: GIỎ HÀNG -->
    <main class="pos-col pos-col-main">
        <?php if (isset($_GET['last_id'])): ?>
        <div class="pos-success-banner mb-3">
            <div class="success-icon">✓</div>
            <div class="success-content">
                <h3>Thanh toán hoàn tất!</h3>
                <p>Hóa đơn đã được lưu.</p>
                <div class="success-actions">
                    <button type="button" onclick="window.open('print_invoice.php?id=<?= (int)$_GET['last_id'] ?>', '_blank', 'width=400,height=600')">🖨 IN HÓA ĐƠN</button>
                    <a href="ban_hang.php" class="btn-continue">Tiếp tục</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($errors): ?>
        <div class="pos-error-banner mb-3">
            <?= pms_h(implode(' · ', $errors)) ?>
        </div>
        <?php endif; ?>

        <div class="cart-container">
            <div class="cart-header">
                <h2 id="cartTitle">🛒 Giỏ hàng (0 sản phẩm)</h2>
                <button type="button" class="btn-clear-cart" onclick="clearCart()">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    Xóa giỏ hàng
                </button>
            </div>

            <div class="cart-table-wrapper">
                <table class="cart-table" id="cartTable">
                    <thead>
                        <tr>
                            <th style="width: 36%;">Sản phẩm</th>
                            <th class="text-center" style="width: 21%;">Số lượng</th>
                            <th class="text-right" style="width: 18%;">Đơn giá</th>
                            <th class="text-right" style="width: 19%;">Thành tiền</th>
                            <th class="text-center" style="width: 6%;"></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                        <!-- JS render -->
                    </tbody>
                </table>
                <div id="cartEmpty" class="cart-empty">
                    <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23CBD5E1' stroke-width='1.5'><circle cx='9' cy='21' r='1'/><circle cx='20' cy='21' r='1'/><path d='M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6'/></svg>" width="64" height="64" alt="Empty Cart">
                    <p>Giỏ hàng đang trống</p>
                    <span>Vui lòng chọn thuốc từ danh sách bên trái để thêm vào giỏ.</span>
                </div>
            </div>
        </div>
    </main>

    <!-- C. CỘT PHẢI: KHÁCH HÀNG & THANH TOÁN -->
    <aside class="pos-col pos-col-right">
        <form id="checkoutForm" method="post">
            <?= pms_csrf_field() ?>
            <input type="hidden" name="cart_json" id="cartJson">
            
            <!-- Card Khách Hàng -->
            <div class="right-card mb-3">
                <div class="right-card__title">
                    <span>👤 Khách hàng</span>
                    <button type="button" class="btn-add-customer" onclick="toggleNewCustomer()">+ Thêm mới</button>
                </div>
                
                <div class="customer-selection" id="customerSelectionBox">
                    <div class="search-input-wrapper">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="customerSearch" class="form-control" placeholder="Tìm khách hàng (F3)..." oninput="filterCustomers()">
                    </div>
                    <select name="customer_id" id="customerId" class="form-control mt-2" onchange="updateCustomerInfo()">
                        <option value="0" data-points="0" data-phone="">Khách lẻ</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['customer_id'] ?>" data-points="<?= (int)$c['loyalty_points'] ?>" data-phone="<?= pms_h($c['phone']) ?>">
                            <?= pms_h($c['customer_name']) ?> (<?= (int)$c['loyalty_points'] ?>đ)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="customer-info-display mt-2" id="customerInfoDisplay" style="display:none;">
                        <span class="badge-point">Điểm: <strong id="lblCusPoints">0</strong></span>
                        <span class="badge-phone">SĐT: <strong id="lblCusPhone"></strong></span>
                    </div>
                </div>

                <div class="new-customer-box" id="newCustomerBox" style="display:none;">
                    <input type="text" name="customer_name" id="newCustomerName" class="form-control mb-2" placeholder="Tên khách hàng mới">
                    <input type="text" name="customer_phone" id="newCustomerPhone" class="form-control" placeholder="Số điện thoại">
                </div>

                <textarea class="form-control mt-2" name="order_note" placeholder="Ghi chú đơn hàng (không bắt buộc)..." rows="2"></textarea>
            </div>

            <!-- Card Thanh Toán -->
            <div class="right-card mb-3">
                <div class="right-card__title"><span>💳 Thanh toán</span></div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label mb-2" style="display:block; font-weight: 700; color: var(--pos-text-main);">Hình thức thanh toán</label>
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
                
                <div class="form-group row-group">
                    <label>Chiết khấu (đ)</label>
                    <input type="number" name="discount" id="discount" class="form-control text-right" value="0" oninput="updateTotals()">
                </div>
                
                <div class="form-group row-group">
                    <label>Dùng điểm</label>
                    <input type="number" name="use_points" id="usePoints" class="form-control text-right" value="0" oninput="updateTotals()">
                </div>

                <div class="form-group row-group mt-3" style="border-top: 1px dashed #cbd5e1; padding-top: 12px;">
                    <label>Khách đưa</label>
                    <input type="number" id="cashReceived" class="form-control text-right highlight-input" placeholder="Nhập số tiền..." oninput="calcChange()">
                </div>
                
                <div class="quick-cash-grid mt-2">
                    <button type="button" onclick="quickCash(10000)">10k</button>
                    <button type="button" onclick="quickCash(20000)">20k</button>
                    <button type="button" onclick="quickCash(50000)">50k</button>
                    <button type="button" onclick="quickCash(100000)">100k</button>
                    <button type="button" onclick="quickCash(200000)">200k</button>
                    <button type="button" onclick="quickCash(500000)">500k</button>
                </div>
            </div>

            <!-- Card Tổng Kết -->
            <div class="right-card summary-card">
                <div class="summary-line">
                    <span>Tạm tính</span>
                    <strong id="sumSubtotal">0 đ</strong>
                </div>
                <div class="summary-line text-orange">
                    <span>Chiết khấu & Điểm</span>
                    <strong id="sumDeduct">-0 đ</strong>
                </div>
                <div class="summary-grand mt-2 pt-2">
                    <span>TỔNG CỘNG</span>
                    <strong id="sumGrand" class="text-blue">0 đ</strong>
                </div>
                <div class="summary-line mt-2 text-red change-line">
                    <span>Tiền thừa</span>
                    <strong id="cashChange">0 đ</strong>
                </div>
                
                <div id="cashWarning" class="cash-warning" style="display:none;">
                    ⚠️ Vui lòng nhập tiền khách đưa
                </div>
                
                <button type="button" id="payBtn" class="btn-checkout mt-3" onclick="submitSale()" disabled>
                    THANH TOÁN (F4)
                </button>
            </div>
        </form>
    </aside>
</div>

<!-- Modal Xác nhận -->
<div id="confirmModal" class="pos-modal">
    <div class="pos-modal-content">
        <h3>Xác nhận thanh toán</h3>
        <p>Kiểm tra lại danh sách trước khi xuất kho.</p>
        <ul id="confirmList" class="confirm-list"></ul>
        <div class="confirm-total">
            <span>Tổng thanh toán:</span>
            <strong id="confirmTotal" class="text-blue"></strong>
        </div>
        <div id="qrContainer" style="display:none; margin-bottom: 16px; background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px dashed #cbd5e1;"></div>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeConfirmModal()">Quay lại</button>
            <button type="button" class="btn-checkout-confirm" onclick="processConfirmSale()">Đồng ý (F4)</button>
        </div>
    </div>
</div>

<div id="posToastContainer" class="toast-container"></div>

<style>
/* Base Variables & Reset for POS */
:root {
    --pos-bg: #f1f5f9;
    --pos-white: #ffffff;
    --pos-primary: #0284c7;
    --pos-primary-hover: #0369a1;
    --pos-success: #16a34a;
    --pos-success-hover: #15803d;
    --pos-danger: #ef4444;
    --pos-warning: #f59e0b;
    --pos-orange: #ea580c;
    --pos-text-main: #1e293b;
    --pos-text-muted: #64748b;
    --pos-border: #e2e8f0;
    --pos-radius: 12px;
    --pos-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
}

.pos-wrapper {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr) 300px;
    gap: 16px;
    height: calc(100vh - 80px);
    margin: 0;
    padding: 0;
    background: var(--pos-bg);
    overflow: hidden;
    font-family: 'Inter', sans-serif;
}

.pos-col {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Utilities */
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 16px; }
.pt-2 { padding-top: 8px; }
.text-red { color: var(--pos-danger) !important; }
.text-green { color: var(--pos-success) !important; }
.text-orange { color: var(--pos-orange) !important; }
.text-blue { color: var(--pos-primary) !important; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.form-control {
    width: 100%; padding: 10px 12px; border: 1px solid var(--pos-border); border-radius: 8px;
    font-size: 14px; font-family: inherit; transition: all 0.2s; outline: none; background: #fff;
}
.form-control:focus { border-color: var(--pos-primary); box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1); }

/* CỘT TRÁI: Tìm kiếm & Thuốc */
.pos-col-left {
    background: transparent;
    gap: 12px;
}
.pos-search-box {
    background: var(--pos-white);
    padding: 16px;
    border-radius: var(--pos-radius);
    box-shadow: var(--pos-shadow);
}
.pos-search-box input {
    width: 100%; padding: 12px 16px; font-size: 15px; font-weight: 500;
    border: 2px solid var(--pos-border); border-radius: 8px; outline: none; background: #f8fafc;
    transition: 0.2s; box-sizing: border-box;
}
.pos-search-box input:focus { border-color: var(--pos-primary); background: #fff; }
.pos-search-shortcuts { margin-top: 10px; font-size: 11.5px; color: #A0AEC0; display: flex; gap: 8px; justify-content: center; align-items: center; }
.pos-search-shortcuts kbd { background: #EDF2F7; padding: 3px 6px; border-radius: 4px; font-weight: 700; color: #4A5568; }
.pos-filters {
    display: flex; gap: 6px; margin-top: 12px; overflow: hidden;
}
.filter-btn {
    flex: 1; padding: 6px 4px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer;
    border: 1px solid var(--pos-border); background: #fff; color: var(--pos-text-muted); text-align: center;
    white-space: nowrap; transition: 0.2s;
}
.filter-btn.active { background: var(--pos-primary); color: #fff; border-color: var(--pos-primary); }
.filter-btn:hover:not(.active) { background: #f1f5f9; }

.pos-product-list {
    flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px;
    padding-right: 4px;
}
.pos-product-list::-webkit-scrollbar { width: 6px; }
.pos-product-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

.product-card {
    display: flex; flex-direction: column; gap: 6px;
    background: var(--pos-white); border-radius: 10px; padding: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02); border: 1px solid var(--pos-border); cursor: pointer;
    transition: all 0.2s; position: relative;
}
.product-card:hover:not(.disabled) { transform: translateY(-2px); border-color: var(--pos-primary); box-shadow: 0 8px 12px -3px rgba(0,0,0,0.08); }
.product-card.disabled { opacity: 0.6; cursor: not-allowed; filter: grayscale(1); }

.product-card__header { 
    display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;
}
.product-card__name { 
    font-weight: 700; font-size: 14px; color: var(--pos-text-main); line-height: 1.3;
}

.status-badge { 
    font-size: 10px; font-weight: 700; padding: 3px 6px; border-radius: 4px; white-space: nowrap; flex-shrink: 0;
}
.badge-normal { background: #dcfce7; color: #166534; }
.badge-expired { background: #fee2e2; color: #991b1b; }
.badge-expiring { background: #fef9c3; color: #854d0e; }
.badge-near-expiry { background: #ffedd5; color: #9a3412; }

.product-card__body { display: contents; } /* Let direct children be part of product-card flex */

.product-card__info { 
    font-size: 12px; color: var(--pos-text-muted); display: flex; flex-direction: column; gap: 3px;
}
.info-line { 
    display: flex; align-items: center; gap: 6px;
}
.info-line span { 
    width: 65px; flex-shrink: 0; color: #94a3b8; font-weight: 500;
}
.info-line strong { 
    color: var(--pos-text-main); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.product-card__action { 
    display: flex; justify-content: flex-end; align-items: center; margin-top: 2px; border-top: 1px dashed #e2e8f0; padding-top: 8px;
}
.product-card__price { 
    font-size: 16px; font-weight: 800; color: var(--pos-primary);
}
.btn-add-cart {
    background: #e0f2fe; color: var(--pos-primary); border: none; width: 32px; height: 32px;
    border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;
}
.product-card:hover .btn-add-cart { background: var(--pos-primary); color: #fff; }

/* CỘT GIỮA: Giỏ hàng */
.pos-col-main { background: var(--pos-white); border-radius: var(--pos-radius); box-shadow: var(--pos-shadow); display: flex; flex-direction: column; }
.cart-container { display: flex; flex-direction: column; flex: 1; overflow: hidden; }
.cart-header { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pos-border); }
.cart-header h2 { margin: 0; font-size: 18px; font-weight: 800; color: var(--pos-text-main); }
.btn-clear-cart {
    display: flex; align-items: center; gap: 6px; background: #fee2e2; color: var(--pos-danger);
    border: none; padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; transition: 0.2s;
}
.btn-clear-cart:hover { background: #fecaca; }

.cart-fefo-notice {
    background: #f0fdfa; color: #0f766e; padding: 10px 20px; font-size: 13px; font-weight: 600;
    display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #ccfbf1;
}

.cart-table-wrapper { flex: 1; overflow-y: auto; position: relative; }
.cart-table { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 0 !important; }
.cart-table th { position: sticky; top: 0; background: #f8fafc; padding: 12px 8px; text-align: left; font-size: 12px; font-weight: 700; color: var(--pos-text-muted); text-transform: uppercase; z-index: 10; border-bottom: 1px solid var(--pos-border); }
.cart-table td { padding: 12px 8px; border-bottom: 1px solid var(--pos-border); font-size: 14px; vertical-align: middle; }
.cart-item-name { font-weight: 700; color: var(--pos-text-main); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; margin-bottom: 4px; word-wrap: break-word; }
.cart-item-meta { font-size: 12px; color: var(--pos-text-muted); }

.qty-controls { display: inline-flex; align-items: center; background: #f1f5f9; border-radius: 6px; padding: 2px; }
.qty-controls button { width: 24px !important; height: 24px !important; border: none !important; background: transparent !important; font-weight: 800 !important; font-size: 16px !important; color: var(--pos-text-muted) !important; cursor: pointer !important; border-radius: 4px !important; line-height: 1 !important; box-shadow: none !important; padding: 0 !important; transform: none !important; }
.qty-controls button:hover { background: #e2e8f0 !important; color: var(--pos-text-main) !important; }
.qty-controls input { width: 32px !important; text-align: center; border: none !important; background: transparent !important; font-weight: 700 !important; font-size: 14px !important; color: var(--pos-text-main) !important; outline: none !important; padding: 0 !important; box-shadow: none !important; -moz-appearance: textfield; }
.qty-controls input::-webkit-outer-spin-button, .qty-controls input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

.btn-remove-item { background: transparent !important; border: none !important; color: #94a3b8 !important; cursor: pointer !important; padding: 6px !important; border-radius: 6px !important; transition: 0.2s !important; box-shadow: none !important; transform: none !important; }
.btn-remove-item:hover { color: var(--pos-danger) !important; background: #fee2e2 !important; }

.cart-empty { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: #fff; z-index: 5; }
.cart-empty p { font-size: 18px; font-weight: 700; color: var(--pos-text-main); margin: 16px 0 8px; }
.cart-empty span { font-size: 14px; color: var(--pos-text-muted); }

/* CỘT PHẢI: Khách hàng & Thanh toán */
.pos-col-right { overflow-y: auto; padding-right: 4px; }
.pos-col-right::-webkit-scrollbar { width: 4px; }
.pos-col-right::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

.right-card { background: var(--pos-white); border-radius: var(--pos-radius); padding: 16px; box-shadow: var(--pos-shadow); }
.right-card__title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-weight: 800; font-size: 15px; color: var(--pos-text-main); }
.btn-add-customer { font-size: 12px; font-weight: 600; color: var(--pos-primary); background: transparent; border: none; cursor: pointer; }

.search-input-wrapper { position: relative; }
.search-input-wrapper svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.search-input-wrapper input.form-control { padding-left: 36px !important; }

.customer-info-display { display: flex; gap: 12px; font-size: 12px; }
.badge-point { background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 6px; }
.badge-phone { background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; }

.row-group { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.row-group label { font-size: 13px; font-weight: 600; color: var(--pos-text-muted); }
.row-group .form-control { width: 180px; }
.highlight-input { font-size: 16px; font-weight: 700; color: var(--pos-primary); background: #f0f9ff; border-color: #bae6fd; }

.quick-cash-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.quick-cash-grid button { background: #f8fafc; border: 1px solid var(--pos-border); border-radius: 6px; padding: 8px 0; font-size: 12px; font-weight: 700; color: var(--pos-text-main); cursor: pointer; transition: 0.2s; }
.quick-cash-grid button:hover { background: #e0f2fe; border-color: #7dd3fc; color: var(--pos-primary); }

.summary-card { border: 2px solid var(--pos-border); }
.summary-line { display: flex; justify-content: space-between; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--pos-text-muted); }
.summary-line strong { color: var(--pos-text-main); font-size: 15px; }
.summary-grand { display: flex; justify-content: space-between; align-items: center; border-top: 2px dashed var(--pos-border); }
.summary-grand span { font-size: 16px; font-weight: 800; color: var(--pos-text-main); }
.summary-grand strong { font-size: 24px; font-weight: 900; }
.change-line { font-size: 15px; }
.change-line strong { font-size: 18px; }

.cash-warning {
    background: #fef3c7; color: #92400e; padding: 8px 12px; border-radius: 8px;
    font-size: 13px; font-weight: 700; text-align: center; margin-top: 8px;
    border: 1px solid #fbbf24; animation: pulse-warn 1.5s infinite;
}
@keyframes pulse-warn { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }

.btn-checkout {
    width: 100%; padding: 16px; border: none; border-radius: var(--pos-radius);
    background: var(--pos-success); color: #fff; font-size: 16px; font-weight: 800;
    cursor: pointer; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); transition: 0.2s;
}
.btn-checkout:hover:not(:disabled) { background: var(--pos-success-hover); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(22, 163, 74, 0.4); }
.btn-checkout:disabled { background: #cbd5e1; box-shadow: none; cursor: not-allowed; transform: none; }

/* Banners */
.pos-success-banner { display: flex; gap: 16px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px; border-radius: var(--pos-radius); align-items: center; }
.success-icon { width: 40px; height: 40px; background: #22c55e; color: #fff; border-radius: 50%; display: grid; place-items: center; font-size: 20px; font-weight: 800; }
.success-content h3 { margin: 0 0 4px; font-size: 16px; color: #166534; font-weight: 800; }
.success-content p { margin: 0 0 10px; font-size: 13px; color: #15803d; }
.success-actions { display: flex; gap: 10px; }
.success-actions button { background: #16a34a; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; }
.btn-continue { color: #16a34a; text-decoration: none; font-weight: 700; font-size: 13px; padding: 6px 0; }

.pos-error-banner { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 14px; text-align: center; }

/* Modal */
.pos-modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.pos-modal-content { background: #fff; width: 480px; border-radius: 16px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.pos-modal-content h3 { margin: 12px 0 8px; font-size: 20px; color: var(--pos-text-main); font-weight: 800; }
.pos-modal-content p { margin: 0 0 16px; color: var(--pos-text-muted); font-size: 14px; }
.confirm-list { list-style: none; padding: 0; margin: 0 0 16px; max-height: 300px; overflow-y: auto; border-top: 1px solid var(--pos-border); border-bottom: 1px solid var(--pos-border); }
.confirm-list li { padding: 12px 0; border-bottom: 1px dashed var(--pos-border); display: flex; justify-content: space-between; align-items: center; }
.confirm-list li:last-child { border-bottom: none; }
.confirm-list li div strong { color: var(--pos-text-main); font-size: 15px; }
.confirm-list li div span { display: block; font-size: 13px; color: var(--pos-text-muted); margin-top: 4px; }
.confirm-total { display: flex; justify-content: space-between; align-items: center; font-size: 18px; font-weight: 800; margin-bottom: 24px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 12px; }
.btn-cancel { background: #f1f5f9; color: #475569; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; }
.btn-checkout-confirm { background: var(--pos-success); color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 800; cursor: pointer; }

/* Toasts */
.toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
.toast-msg { background: #334155; color: #fff; padding: 12px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); animation: slideIn 0.3s forwards; }
@keyframes slideIn { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
@keyframes shake { 0%,100% { transform: translateX(0); } 20%,60% { transform: translateX(-6px); } 40%,80% { transform: translateX(6px); } }

/* Responsive */
@media (max-width: 1200px) {
    .pos-wrapper { grid-template-columns: 300px 1fr 300px; }
}
@media (max-width: 992px) {
    .pos-wrapper { grid-template-columns: 1fr; height: auto; overflow: visible; display: flex; flex-direction: column; }
    .pos-product-list { max-height: 400px; }
    .cart-table-wrapper { min-height: 300px; max-height: 400px; }
}
/* Payment Methods Card Selector */
.payment-selector { display: flex; gap: 8px; width: 100%; margin-top: 4px; }
.payment-method-card { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 12px 8px; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s; background: #fff; gap: 6px; color: #64748b; }
.payment-method-card:hover { border-color: var(--pos-primary); background: #f8fafc; }
.payment-method-card.active { border-color: var(--pos-primary); background: #f0f9ff; color: var(--pos-primary); box-shadow: 0 4px 12px rgba(2, 132, 199, 0.1); }
.payment-method-card svg { width: 24px; height: 24px; stroke-width: 2.5; }
.payment-method-card span { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; }
</style>

<script>
let cart = [];
let confirmOpen = false;

function formatMoney(num) {
    return new Intl.NumberFormat('vi-VN').format(num) + ' đ';
}

function showToast(msg) {
    const container = document.getElementById('posToastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast-msg';
    toast.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> ${msg}`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

function addToCart(id, name, price, stock, expiry) {
    const existing = cart.find(i => i.drug_id === id);
    if (existing) {
        if (existing.quantity < stock) {
            existing.quantity++;
            showToast('Đã tăng số lượng: ' + name);
        } else {
            alert('Không đủ hàng tồn kho!');
        }
    } else {
        if (stock > 0) {
            cart.push({ drug_id: id, drug_name: name, quantity: 1, price: price, stock: stock, expiry: expiry });
            showToast('Đã thêm: ' + name);
        } else {
            alert('Sản phẩm đã hết hàng!');
        }
    }
    renderCart();
}

function updateQty(index, delta) {
    const item = cart[index];
    let newQty = item.quantity;
    
    if (typeof delta === 'number') {
        newQty += delta;
    } else {
        // Handle manual input
        newQty = parseInt(delta.value) || 1;
    }

    if (newQty > 0 && newQty <= item.stock) {
        item.quantity = newQty;
    } else if (newQty > item.stock) {
        alert('Vượt quá tồn kho!');
        item.quantity = item.stock;
    } else if (newQty <= 0) {
        item.quantity = 1;
    }
    renderCart();
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

function clearCart() {
    if (cart.length > 0 && confirm('Bạn có chắc chắn muốn xóa toàn bộ giỏ hàng?')) {
        cart = [];
        renderCart();
    }
}

function renderCart() {
    const body = document.getElementById('cartBody');
    const empty = document.getElementById('cartEmpty');
    const payBtn = document.getElementById('payBtn');
    const title = document.getElementById('cartTitle');
    
    body.innerHTML = '';
    title.innerText = `🛒 Giỏ hàng (${cart.length} sản phẩm)`;
    
    if (cart.length === 0) {
        empty.style.display = 'flex';
    } else {
        empty.style.display = 'none';
        
        cart.forEach((item, idx) => {
            const tr = document.createElement('tr');
            const subtotal = item.quantity * item.price;
            tr.innerHTML = `
                <td>
                    <div class="cart-item-name">${item.drug_name}</div>
                    <div class="cart-item-meta">Hạn: ${item.expiry}</div>
                </td>
                <td class="text-center">
                    <div class="qty-controls">
                        <button type="button" class="btn-clear" style="background: transparent !important; border: none !important; color: #64748b !important; box-shadow: none !important; width: 24px !important; height: 24px !important; padding: 0 !important;" onclick="updateQty(${idx}, -1)">-</button>
                        <input type="number" value="${item.quantity}" onchange="updateQty(${idx}, this)" style="background: transparent !important; border: none !important; box-shadow: none !important; width: 32px !important;">
                        <button type="button" class="btn-clear" style="background: transparent !important; border: none !important; color: #64748b !important; box-shadow: none !important; width: 24px !important; height: 24px !important; padding: 0 !important;" onclick="updateQty(${idx}, 1)">+</button>
                    </div>
                </td>
                <td class="text-right"><strong>${formatMoney(item.price).replace(' đ','')}</strong></td>
                <td class="text-right text-blue"><strong>${formatMoney(subtotal).replace(' đ','')}</strong></td>
                <td class="text-center">
                    <button type="button" class="btn-remove btn-remove-item" style="background: transparent !important; border: none !important; color: #ef4444 !important; box-shadow: none !important; padding: 4px !important;" onclick="removeItem(${idx})" title="Xóa">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"></path></svg>
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });
    }
    updateTotals();
}

function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const points = parseInt(document.getElementById('usePoints').value) || 0;
    const deduction = discount + (points * 100);
    const grand = Math.max(0, subtotal - deduction);
    
    document.getElementById('sumSubtotal').innerText = formatMoney(subtotal);
    document.getElementById('sumDeduct').innerText = '-' + formatMoney(deduction);
    document.getElementById('sumGrand').innerText = formatMoney(grand);
    document.getElementById('sumGrand').dataset.val = grand;
    calcChange();
    validatePayButton();
}

function calcChange() {
    const grand = parseFloat(document.getElementById('sumGrand').dataset.val) || 0;
    const payType = document.getElementById('paymentType').value;
    const input = document.getElementById('cashReceived');
    
    if (payType === 'transfer') {
        input.value = grand;
    }
    
    const received = parseFloat(input.value) || 0;
    const change = Math.max(0, received - grand);
    const el = document.getElementById('cashChange');
    
    el.innerText = formatMoney(change);
    
    if (received > 0 && received < grand && payType !== 'transfer') {
        el.style.color = 'var(--pos-danger)';
    } else {
        el.style.color = 'var(--pos-success)';
    }
    validatePayButton();
}

function validatePayButton() {
    const payBtn = document.getElementById('payBtn');
    const warning = document.getElementById('cashWarning');
    const grand = parseFloat(document.getElementById('sumGrand').dataset.val) || 0;
    const received = parseFloat(document.getElementById('cashReceived').value) || 0;
    const payType = document.getElementById('paymentType').value;
    const hasItems = cart.length > 0;
    
    let canPay = false;
    if (!hasItems) {
        canPay = false;
        warning.style.display = 'none';
    } else if (payType === 'transfer') {
        canPay = true;
        warning.style.display = 'none';
    } else if (received >= grand && grand > 0) {
        canPay = true;
        warning.style.display = 'none';
    } else {
        canPay = false;
        warning.style.display = hasItems ? 'block' : 'none';
    }
    
    payBtn.disabled = !canPay;
}

function selectPayment(val) {
    document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('active'));
    document.querySelector(`.payment-method-card[data-value="${val}"]`).classList.add('active');
    document.getElementById('paymentType').value = val;
    onPaymentTypeChange();
}

function onPaymentTypeChange() {
    const payType = document.getElementById('paymentType').value;
    const cashInput = document.getElementById('cashReceived');
    const cashSection = cashInput.closest('.row-group');
    const quickGrid = document.querySelector('.quick-cash-grid');
    
    if (payType === 'transfer') {
        const grand = parseFloat(document.getElementById('sumGrand').dataset.val) || 0;
        cashInput.value = grand;
        cashSection.style.opacity = '0.5';
        cashInput.readOnly = true;
        quickGrid.style.display = 'none';
    } else {
        cashInput.value = '';
        cashSection.style.opacity = '1';
        cashInput.readOnly = false;
        quickGrid.style.display = 'grid';
    }
    calcChange();
}

function quickCash(amount) {
    const input = document.getElementById('cashReceived');
    const current = parseFloat(input.value) || 0;
    input.value = current + amount;
    calcChange();
}

function toggleNewCustomer() {
    const selectBox = document.getElementById('customerSelectionBox');
    const newBox = document.getElementById('newCustomerBox');
    const select = document.getElementById('customerId');
    
    if (newBox.style.display === 'none') {
        newBox.style.display = 'block';
        selectBox.style.display = 'none';
        select.value = '0';
        updateCustomerInfo();
    } else {
        newBox.style.display = 'none';
        selectBox.style.display = 'block';
    }
}

function updateCustomerInfo() {
    const select = document.getElementById('customerId');
    const opt = select.options[select.selectedIndex];
    const pointsInput = document.getElementById('usePoints');
    const infoDisplay = document.getElementById('customerInfoDisplay');
    
    const isSpecial = select.value !== "0";
    
    if (isSpecial) {
        document.getElementById('customerSearch').value = opt.text.split(' (')[0];
        document.getElementById('lblCusPoints').innerText = opt.dataset.points;
        document.getElementById('lblCusPhone').innerText = opt.dataset.phone || 'Trống';
        infoDisplay.style.display = 'flex';
        
        pointsInput.max = opt.dataset.points;
        if (parseInt(pointsInput.value) > parseInt(opt.dataset.points)) {
            pointsInput.value = opt.dataset.points;
        }
    } else {
        infoDisplay.style.display = 'none';
        pointsInput.value = 0;
    }
    
    updateTotals();
}

function filterCustomers() {
    const q = document.getElementById('customerSearch').value.toLowerCase();
    const select = document.getElementById('customerId');
    const options = select.options;
    
    let firstMatch = -1;
    for (let i = 0; i < options.length; i++) {
        const text = options[i].text.toLowerCase();
        const phone = (options[i].dataset.phone || "").toLowerCase();
        const visible = text.includes(q) || phone.includes(q) || i === 0;
        options[i].style.display = visible ? 'block' : 'none';
        
        if (visible && i !== 0 && firstMatch === -1) {
            firstMatch = i;
        }
    }
    
    if (q !== "" && firstMatch !== -1) {
        select.selectedIndex = firstMatch;
        updateCustomerInfo();
    }
}

// Product Search & Filter
document.getElementById('posSearch').addEventListener('input', applyFilters);
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        applyFilters();
    });
});

function applyFilters() {
    const q = document.getElementById('posSearch').value.toLowerCase().trim();
    const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
    
    const cards = Array.from(document.querySelectorAll('.product-card'));
    
    cards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const active = card.dataset.active.toLowerCase();
        const code = (card.dataset.code || '').toLowerCase();
        const status = card.dataset.expiryStatus;
        const stock = parseInt(card.dataset.stock);
        
        let matchText = name.includes(q) || active.includes(q) || code === q || code.includes(q);
        let matchFilter = true;
        
        const threshold = 10; // Ngưỡng sắp hết hàng
        if (activeFilter === 'expiring') {
            matchFilter = (status === 'expiring' || status === 'near-expiry' || status === 'expired');
        } else if (activeFilter === 'bestseller') {
            const sold = parseInt(card.dataset.sold) || 0;
            matchFilter = (sold > 0); 
        }
        
        card.style.display = (matchText && matchFilter) ? 'flex' : 'none';
    });

    // Sắp xếp động
    const list = document.getElementById('posProductList');
    if (activeFilter === 'bestseller') {
        cards.sort((a, b) => (parseInt(b.dataset.sold) || 0) - (parseInt(a.dataset.sold) || 0));
        cards.forEach(c => list.appendChild(c));
    } else {
        cards.sort((a, b) => a.dataset.name.localeCompare(b.dataset.name));
        cards.forEach(c => list.appendChild(c));
    }
}

// Submit flow
function submitSale() {
    if (cart.length === 0) return;
    
    // Kiểm tra tiền khách đưa
    const grand = parseFloat(document.getElementById('sumGrand').dataset.val) || 0;
    const received = parseFloat(document.getElementById('cashReceived').value) || 0;
    const payType = document.getElementById('paymentType').value;
    
    if (payType !== 'transfer' && received < grand) {
        const cashInput = document.getElementById('cashReceived');
        cashInput.style.border = '2px solid var(--pos-danger)';
        cashInput.style.animation = 'shake 0.4s ease';
        cashInput.focus();
        
        if (received === 0) {
            alert('Vui lòng nhập số tiền khách đưa trước khi thanh toán!');
        } else {
            alert('Tiền khách đưa (' + formatMoney(received) + ') chưa đủ! Cần tối thiểu: ' + formatMoney(grand));
        }
        
        setTimeout(() => {
            cashInput.style.border = '';
            cashInput.style.animation = '';
        }, 1500);
        return;
    }
    
    if (!confirmOpen) {
        document.getElementById('cartJson').value = JSON.stringify(cart);
        const list = document.getElementById('confirmList');
        list.innerHTML = '';
        
        cart.forEach(item => {
            const subtotal = item.quantity * item.price;
            list.innerHTML += `
                <li>
                    <div>
                        <strong>${item.drug_name}</strong>
                        <span>${item.quantity} x ${formatMoney(item.price)} (Hạn: ${item.expiry})</span>
                    </div>
                    <strong>${formatMoney(subtotal)}</strong>
                </li>
            `;
        });
        
        document.getElementById('confirmTotal').innerText = document.getElementById('sumGrand').innerText;
        
        const qrContainer = document.getElementById('qrContainer');
        const confirmBtn = document.querySelector('.btn-checkout-confirm');
        if (payType === 'transfer') {
            confirmBtn.innerText = 'Xác nhận đã thanh toán';
            qrContainer.innerHTML = '<p style="text-align:center;">Đang tạo mã QR...</p>';
            qrContainer.style.display = 'block';
            fetch('ajax_create_qr.php?amount=' + grand)
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        qrContainer.innerHTML = `<img src="${res.qrDataURL}" alt="QR Code" style="max-width:250px; display:block; margin:0 auto; border-radius: 8px;">
                                                 <p style="text-align:center; font-weight: bold; margin-top: 12px; font-size: 15px; color: var(--pos-primary);">Vui lòng quét mã QR để thanh toán</p>`;
                    } else {
                        qrContainer.innerHTML = `<p class="text-red text-center">Lỗi tạo mã QR: ${res.message}</p>`;
                    }
                }).catch(e => {
                    qrContainer.innerHTML = `<p class="text-red text-center">Lỗi mạng khi tạo mã QR</p>`;
                });
        } else {
            confirmBtn.innerText = 'Đồng ý (F4)';
            qrContainer.style.display = 'none';
        }

        document.getElementById('confirmModal').style.display = 'flex';
        confirmOpen = true;
    } else {
        processConfirmSale();
    }
}

function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
    confirmOpen = false;
}

function processConfirmSale() {
    document.getElementById('checkoutForm').submit();
}

// Shortcuts
window.addEventListener('keydown', e => {
    if (e.key === 'F2') { e.preventDefault(); document.getElementById('posSearch').focus(); }
    if (e.key === 'F3') { e.preventDefault(); document.getElementById('customerSearch').focus(); }
    if (e.key === 'F4') { e.preventDefault(); submitSale(); }
    if (e.key === 'Escape' && confirmOpen) { e.preventDefault(); closeConfirmModal(); }
});
</script>

<?php pms_render_footer(); ?>