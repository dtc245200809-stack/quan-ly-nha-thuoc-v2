import pytest
from datetime import date, timedelta
from app.database import get_db_connection, init_db
from app.inventory_service import (
    allocate_fefo,
    get_medicine_stock_summary,
    process_sale_transaction,
    get_expiry_alerts,
    get_low_stock_alerts
)

@pytest.fixture(autouse=True)
def setup_db():
    init_db()

def test_fefo_order_resolution():
    """
    Kiểm tra nguyên tắc FEFO: Lô có hạn sử dụng gần nhất phải luôn được allocate trước.
    """
    conn = get_db_connection()
    cursor = conn.cursor()
    
    # Lấy ID của thuốc Paracetamol (mã THUOC001)
    cursor.execute("SELECT id FROM medicines WHERE code = 'THUOC001'")
    row = cursor.fetchone()
    assert row is not None, "Thuốc THUOC001 phải tồn tại trong database"
    med_id = row[0]

    allocations, total_cost = allocate_fefo(cursor, med_id, requested_qty=10)
    conn.close()

    assert len(allocations) > 0, "Phải phân bổ được lô hàng"
    assert total_cost > 0
    # Kiểm tra phân bổ lô có hạn gần nhất
    assert "expiry_date" in allocations[0]

def test_block_expired_batches():
    """
    Kiểm tra hệ thống không được đưa các lô đã hết hạn vào luồng xuất kho khả dụng.
    """
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT id FROM medicines WHERE code = 'THUOC001'")
    med_id = cursor.fetchone()[0]

    # Tạo một lô giả định đã hết hạn 10 ngày trước
    past_date = (date.today() - timedelta(days=10)).isoformat()
    cursor.execute("""
        INSERT INTO batches (
            batch_number, medicine_id, supplier_id, import_date, 
            manufacture_date, expiry_date, quantity_imported, quantity_remaining, 
            import_price, selling_price, status
        ) VALUES ('TEST_EXPIRED_BATCH', ?, 1, '2023-01-01', '2023-01-01', ?, 50, 50, 1000, 2000, 'active')
    """, (med_id, past_date))
    conn.commit()

    allocations, _ = allocate_fefo(cursor, med_id, requested_qty=5)
    allocated_batch_numbers = [a["batch_number"] for a in allocations]
    conn.close()

    assert "TEST_EXPIRED_BATCH" not in allocated_batch_numbers, "Lô đã quá hạn tuyệt đối không được xuất kho FEFO"

def test_sale_transaction_deducts_inventory():
    """
    Kiểm tra quy trình bán thuốc trừ chính xác tồn kho theo lô FEFO.
    """
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT id FROM medicines WHERE code = 'THUOC006'")
    row = cursor.fetchone()
    assert row is not None, "Thuốc THUOC006 phải tồn tại trong database"
    med_id = row[0]
    conn.close()

    # Lấy số lượng khả dụng ban đầu
    stock_before = get_medicine_stock_summary(med_id)["available_stock"]

    sale_qty = 5
    result = process_sale_transaction(
        cashier_id=1,
        customer_name="Test Customer",
        customer_phone="0999888777",
        prescription_code=None,
        items=[{"medicine_id": med_id, "quantity": sale_qty}],
        discount=0,
        payment_method="cash"
    )

    assert result["success"] is True
    assert result["total_amount"] > 0

    # Kiểm tra tồn kho sau khi bán
    stock_after = get_medicine_stock_summary(med_id)["available_stock"]
    assert stock_after == stock_before - sale_qty, "Tồn kho tổng phải giảm chính xác bằng số lượng bán ra"

def test_expiry_and_low_stock_alerts():
    """
    Kiểm tra tính năng cảnh báo lô cận hạn và cảnh báo tồn kho dưới ngưỡng an toàn.
    """
    expiry_alerts = get_expiry_alerts(days_threshold=90)
    assert isinstance(expiry_alerts, list)

    low_stocks = get_low_stock_alerts()
    assert isinstance(low_stocks, list)
    for alert in low_stocks:
        assert alert["available_stock"] <= alert["min_stock_alert"]
        assert alert["shortage"] >= 0
