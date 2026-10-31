from fastapi import APIRouter, HTTPException, Depends
from typing import Optional
from app.models import InvoiceCreate
from app.auth import get_current_user
from app.database import get_db_connection
from app.inventory_service import process_sale_transaction

router = APIRouter(prefix="/api/invoices", tags=["Sales & Invoices"])

@router.post("/")
def create_sale_invoice(
    payload: InvoiceCreate,
    current_user: dict = Depends(get_current_user)
):
    if not payload.items:
        raise HTTPException(status_code=400, detail="Giỏ hàng trống! Vui lòng chọn ít nhất một loại thuốc.")

    try:
        items_payload = [{"medicine_id": it.medicine_id, "quantity": it.quantity} for it in payload.items]
        result = process_sale_transaction(
            cashier_id=current_user["user_id"],
            customer_name=payload.customer_name,
            customer_phone=payload.customer_phone,
            prescription_code=payload.prescription_code,
            items=items_payload,
            discount=payload.discount,
            payment_method=payload.payment_method,
            note=payload.note or ""
        )
        return result
    except ValueError as ve:
        raise HTTPException(status_code=400, detail=str(ve))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Lỗi hệ thống khi lập hóa đơn: {str(e)}")

@router.get("/")
def list_invoices(limit: int = 50):
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT i.*, u.full_name as cashier_name
        FROM invoices i
        LEFT JOIN users u ON i.cashier_id = u.id
        ORDER BY i.id DESC
        LIMIT ?
    """, (limit,))
    rows = [dict(r) for r in cursor.fetchall()]
    conn.close()
    return rows

@router.get("/{invoice_id}")
def get_invoice_detail(invoice_id: int):
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT i.*, u.full_name as cashier_name
        FROM invoices i
        LEFT JOIN users u ON i.cashier_id = u.id
        WHERE i.id = ?
    """, (invoice_id,))
    inv = cursor.fetchone()
    if not inv:
        conn.close()
        raise HTTPException(status_code=404, detail="Không tìm thấy hóa đơn")

    invoice_data = dict(inv)

    cursor.execute("""
        SELECT ii.*, m.name as medicine_name, m.code as medicine_code, u.name as unit_name, b.batch_number, b.expiry_date
        FROM invoice_items ii
        JOIN medicines m ON ii.medicine_id = m.id
        JOIN batches b ON ii.batch_id = b.id
        LEFT JOIN units u ON m.unit_id = u.id
        WHERE ii.invoice_id = ?
    """, (invoice_id,))
    invoice_data["items"] = [dict(it) for it in cursor.fetchall()]
    conn.close()
    return invoice_data
