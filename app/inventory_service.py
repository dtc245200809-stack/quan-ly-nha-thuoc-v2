from datetime import date, datetime
import sqlite3
from typing import List, Dict, Any, Tuple
from app.database import get_db_connection

def calculate_days_left(expiry_date_str: str) -> int:
    exp_date = datetime.strptime(expiry_date_str, "%Y-%m-%d").date()
    today = date.today()
    return (exp_date - today).days

def get_medicine_stock_summary(medicine_id: int = None):
    conn = get_db_connection()
    cursor = conn.cursor()
    today_str = date.today().isoformat()

    query = """
    SELECT 
        m.id, m.code, m.name, m.active_ingredient, m.min_stock_alert,
        c.name as category_name, u.name as unit_name,
        COALESCE(SUM(CASE WHEN b.status = 'active' AND b.expiry_date > ? THEN b.quantity_remaining ELSE 0 END), 0) as available_stock,
        COALESCE(SUM(CASE WHEN b.expiry_date <= ? AND b.quantity_remaining > 0 THEN b.quantity_remaining ELSE 0 END), 0) as expired_stock
    FROM medicines m
    LEFT JOIN categories c ON m.category_id = c.id
    LEFT JOIN units u ON m.unit_id = u.id
    LEFT JOIN batches b ON m.id = b.medicine_id
    """
    params = [today_str, today_str]

    if medicine_id:
        query += " WHERE m.id = ? GROUP BY m.id"
        params.append(medicine_id)
        cursor.execute(query, params)
        row = cursor.fetchone()
        conn.close()
        return dict(row) if row else None
    else:
        query += " GROUP BY m.id ORDER BY m.name ASC"
        cursor.execute(query, params)
        rows = [dict(r) for r in cursor.fetchall()]
        conn.close()
        return rows

def allocate_fefo(cursor: sqlite3.Cursor, medicine_id: int, requested_qty: int) -> Tuple[List[Dict[str, Any]], float]:
    """
    Thuật toán FEFO (First Expired, First Out):
    Tìm các lô còn hạn dùng, sắp xếp theo expiry_date ASC, import_date ASC.
    Chỉ lấy các lô có status = 'active' và expiry_date > ngày hiện tại.
    """
    today_str = date.today().isoformat()
    cursor.execute("""
        SELECT id, batch_number, expiry_date, quantity_remaining, selling_price
        FROM batches
        WHERE medicine_id = ? 
          AND status = 'active' 
          AND expiry_date > ?
          AND quantity_remaining > 0
        ORDER BY expiry_date ASC, import_date ASC, id ASC
    """, (medicine_id, today_str))

    available_batches = cursor.fetchall()
    total_available = sum(b["quantity_remaining"] for b in available_batches)

    if total_available < requested_qty:
        raise ValueError(
            f"Không đủ tồn kho khả dụng cho thuốc ID {medicine_id}. "
            f"Yêu cầu: {requested_qty}, Khả dụng theo FEFO: {total_available}"
        )

    allocations = []
    qty_needed = requested_qty
    total_cost = 0.0

    for b in available_batches:
        batch_id = b["id"]
        rem = b["quantity_remaining"]
        price = b["selling_price"]
        
        take = min(rem, qty_needed)
        allocations.append({
            "batch_id": batch_id,
            "batch_number": b["batch_number"],
            "expiry_date": b["expiry_date"],
            "quantity_to_take": take,
            "unit_price": price,
            "subtotal": take * price
        })
        total_cost += take * price
        qty_needed -= take

        if qty_needed == 0:
            break

    return allocations, total_cost

def process_sale_transaction(
    cashier_id: int,
    customer_name: str,
    customer_phone: str,
    prescription_code: str,
    items: List[Dict[str, int]],
    discount: float = 0.0,
    payment_method: str = "cash",
    note: str = ""
) -> Dict[str, Any]:
    """
    Thực hiện lập hóa đơn bán lẻ và trừ tồn kho tự động theo chuẩn FEFO.
    Chạy trong 1 Transaction nguyên tử (ACID).
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        all_allocations = []
        raw_total = 0.0

        for it in items:
            med_id = it["medicine_id"]
            qty = it["quantity"]
            if qty <= 0:
                raise ValueError(f"Số lượng mua phải lớn hơn 0 (Mã thuốc ID {med_id})")

            allocs, cost = allocate_fefo(cursor, med_id, qty)
            for a in allocs:
                a["medicine_id"] = med_id
            all_allocations.extend(allocs)
            raw_total += cost

        final_total = max(0.0, raw_total - discount)
        inv_number = f"HD{datetime.now().strftime('%Y%m%d%H%M%S')}"

        cursor.execute("""
            INSERT INTO invoices (invoice_number, cashier_id, customer_name, customer_phone, prescription_code, total_amount, discount, final_amount, payment_method, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (inv_number, cashier_id, customer_name or "Khách lẻ", customer_phone, prescription_code, raw_total, discount, final_total, payment_method, note))
        
        invoice_id = cursor.lastrowid

        for alloc in all_allocations:
            # Lưu chi tiết hóa đơn
            cursor.execute("""
                INSERT INTO invoice_items (invoice_id, batch_id, medicine_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            """, (invoice_id, alloc["batch_id"], alloc["medicine_id"], alloc["quantity_to_take"], alloc["unit_price"], alloc["subtotal"]))

            # Trừ tồn kho lô tương ứng
            cursor.execute("""
                UPDATE batches
                SET quantity_remaining = quantity_remaining - ?
                WHERE id = ?
            """, (alloc["quantity_to_take"], alloc["batch_id"]))

        conn.commit()

        return {
            "success": True,
            "invoice_id": invoice_id,
            "invoice_number": inv_number,
            "total_amount": raw_total,
            "discount": discount,
            "final_amount": final_total,
            "allocated_items": all_allocations
        }

    except Exception as e:
        conn.rollback()
        raise e
    finally:
        conn.close()

def get_expiry_alerts(days_threshold: int = 60) -> List[Dict[str, Any]]:
    """
    Lấy danh sách các lô thuốc sắp hết hạn (trong vòng days_threshold ngày) hoặc đã quá hạn
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    query = """
    SELECT 
        b.id as batch_id, b.batch_number, b.expiry_date, b.manufacture_date, 
        b.quantity_remaining, b.import_price, b.selling_price, b.status,
        m.id as medicine_id, m.code as medicine_code, m.name as medicine_name, m.active_ingredient,
        s.name as supplier_name, s.phone as supplier_phone
    FROM batches b
    JOIN medicines m ON b.medicine_id = m.id
    LEFT JOIN suppliers s ON b.supplier_id = s.id
    WHERE b.quantity_remaining > 0
    ORDER BY b.expiry_date ASC
    """
    cursor.execute(query)
    rows = cursor.fetchall()
    conn.close()

    alerts = []
    today = date.today()

    for r in rows:
        exp_date = datetime.strptime(r["expiry_date"], "%Y-%m-%d").date()
        days_left = (exp_date - today).days

        if days_left <= days_threshold:
            if days_left < 0:
                severity = "EXPIRED"
                recommendation = "Niêm phong lập tức, chuyển vào tủ biệt trữ chờ tiêu hủy. Tuyệt đối không xuất bán."
            elif days_left <= 30:
                severity = "CRITICAL"
                recommendation = "Khẩn cấp: Liên hệ NCC đổi trả hoặc áp dụng xuất bán ưu tiên theo đơn nếu có chỉ định."
            else:
                severity = "WARNING"
                recommendation = "Cảnh báo: Kiểm tra tồn kho, chuẩn bị kế hoạch hoàn trả hoặc đẩy luân chuyển trước 30 ngày."

            alerts.append({
                "batch_id": r["batch_id"],
                "batch_number": r["batch_number"],
                "medicine_id": r["medicine_id"],
                "medicine_code": r["medicine_code"],
                "medicine_name": r["medicine_name"],
                "active_ingredient": r["active_ingredient"],
                "quantity_remaining": r["quantity_remaining"],
                "expiry_date": r["expiry_date"],
                "days_left": days_left,
                "severity": severity,
                "recommendation": recommendation,
                "supplier_name": r["supplier_name"],
                "supplier_phone": r["supplier_phone"],
                "estimated_loss_value": r["quantity_remaining"] * r["import_price"]
            })

    return alerts

def get_low_stock_alerts() -> List[Dict[str, Any]]:
    """
    Lấy danh sách các thuốc có tồn kho khả dụng thấp hơn ngưỡng tối thiểu
    """
    stock_list = get_medicine_stock_summary()
    low_stocks = []
    for m in stock_list:
        if m["available_stock"] <= m["min_stock_alert"]:
            low_stocks.append({
                "medicine_id": m["id"],
                "code": m["code"],
                "name": m["name"],
                "active_ingredient": m["active_ingredient"],
                "category_name": m["category_name"],
                "unit_name": m["unit_name"],
                "available_stock": m["available_stock"],
                "min_stock_alert": m["min_stock_alert"],
                "shortage": m["min_stock_alert"] - m["available_stock"]
            })
    return low_stocks
