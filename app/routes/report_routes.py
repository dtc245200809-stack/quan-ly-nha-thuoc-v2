from fastapi import APIRouter
from app.database import get_db_connection
from app.inventory_service import get_expiry_alerts, get_low_stock_alerts

router = APIRouter(prefix="/api/reports", tags=["Reports & Analytics"])

@router.get("/summary")
def get_summary_report():
    conn = get_db_connection()
    cursor = conn.cursor()

    # 1. Tổng doanh thu & tổng hóa đơn
    cursor.execute("SELECT COUNT(*), COALESCE(SUM(final_amount), 0) FROM invoices")
    inv_count, total_revenue = cursor.fetchone()

    # 2. Tổng số loại thuốc
    cursor.execute("SELECT COUNT(*) FROM medicines WHERE is_active = 1")
    total_medicines = cursor.fetchone()[0]

    # 3. Tổng giá trị tồn kho
    cursor.execute("SELECT COALESCE(SUM(quantity_remaining * import_price), 0), COALESCE(SUM(quantity_remaining), 0) FROM batches WHERE status = 'active'")
    inventory_val, total_units_in_stock = cursor.fetchone()

    conn.close()

    # Cảnh báo
    expiry_alerts = get_expiry_alerts(days_threshold=60)
    low_stocks = get_low_stock_alerts()

    return {
        "total_revenue": total_revenue,
        "total_invoices": inv_count,
        "total_medicines": total_medicines,
        "inventory_value": inventory_val,
        "total_units_in_stock": total_units_in_stock,
        "expiring_batches_count": len(expiry_alerts),
        "low_stock_medicines_count": len(low_stocks)
    }

@router.get("/revenue-by-day")
def get_revenue_by_day():
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT date(created_at) as sale_date, COUNT(*) as invoice_count, SUM(final_amount) as daily_revenue
        FROM invoices
        GROUP BY date(created_at)
        ORDER BY sale_date DESC
        LIMIT 14
    """)
    rows = [dict(r) for r in cursor.fetchall()]
    conn.close()
    return rows

@router.get("/top-selling")
def get_top_selling_medicines(limit: int = 5):
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT m.id, m.name, m.code, SUM(ii.quantity) as total_sold, SUM(ii.subtotal) as total_revenue
        FROM invoice_items ii
        JOIN medicines m ON ii.medicine_id = m.id
        GROUP BY m.id
        ORDER BY total_sold DESC
        LIMIT ?
    """, (limit,))
    rows = [dict(r) for r in cursor.fetchall()]
    conn.close()
    return rows
