from fastapi import APIRouter, HTTPException, Depends, Query
from datetime import date, datetime
from typing import Optional
from app.models import BatchCreate, BatchStatusUpdate
from app.auth import get_current_user, require_roles
from app.database import get_db_connection
from app.inventory_service import get_expiry_alerts, get_low_stock_alerts

router = APIRouter(prefix="/api/batches", tags=["Batches & Stock"])

@router.get("/")
def list_batches(
    medicine_id: Optional[int] = None,
    status: Optional[str] = None
):
    conn = get_db_connection()
    cursor = conn.cursor()

    query = """
    SELECT 
        b.*, m.name as medicine_name, m.code as medicine_code,
        s.name as supplier_name, s.phone as supplier_phone
    FROM batches b
    JOIN medicines m ON b.medicine_id = m.id
    LEFT JOIN suppliers s ON b.supplier_id = s.id
    WHERE 1=1
    """
    params = []

    if medicine_id:
        query += " AND b.medicine_id = ?"
        params.append(medicine_id)

    if status:
        query += " AND b.status = ?"
        params.append(status)

    query += " ORDER BY b.expiry_date ASC, b.id DESC"
    cursor.execute(query, params)
    batches = [dict(b) for b in cursor.fetchall()]
    conn.close()

    today = date.today()
    for b in batches:
        exp = datetime.strptime(b["expiry_date"], "%Y-%m-%d").date()
        b["days_left"] = (exp - today).days

    return batches

@router.post("/", dependencies=[Depends(require_roles("admin", "pharmacist"))])
def create_batch(payload: BatchCreate):
    conn = get_db_connection()
    cursor = conn.cursor()

    if payload.expiry_date <= payload.manufacture_date:
        raise HTTPException(status_code=400, detail="Hạn sử dụng (EXP) phải sau Ngày sản xuất (MFG)")

    if payload.quantity_imported <= 0:
        raise HTTPException(status_code=400, detail="Số lượng nhập phải lớn hơn 0")

    try:
        cursor.execute("""
            INSERT INTO batches (
                batch_number, medicine_id, supplier_id, import_date, 
                manufacture_date, expiry_date, quantity_imported, quantity_remaining, 
                import_price, selling_price, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        """, (
            payload.batch_number.strip().upper(), payload.medicine_id, payload.supplier_id,
            payload.import_date.isoformat(), payload.manufacture_date.isoformat(), payload.expiry_date.isoformat(),
            payload.quantity_imported, payload.quantity_imported, payload.import_price, payload.selling_price
        ))
        conn.commit()
        batch_id = cursor.lastrowid
        return {"success": True, "batch_id": batch_id, "message": "Nhập kho lô thuốc thành công!"}
    except Exception as e:
        conn.rollback()
        raise HTTPException(status_code=400, detail=f"Lỗi khi nhập lô: {str(e)}")
    finally:
        conn.close()

@router.put("/{batch_id}/status", dependencies=[Depends(require_roles("admin", "pharmacist"))])
def update_status(batch_id: int, payload: BatchStatusUpdate):
    if payload.status not in ["active", "expired", "disposed", "recalled"]:
        raise HTTPException(status_code=400, detail="Trạng thái lô không hợp lệ")

    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("UPDATE batches SET status = ? WHERE id = ?", (payload.status, batch_id))
    if cursor.rowcount == 0:
        conn.close()
        raise HTTPException(status_code=404, detail="Không tìm thấy lô thuốc")
    conn.commit()
    conn.close()
    return {"success": True, "message": f"Cập nhật trạng thái lô sang '{payload.status}' thành công"}

@router.get("/suppliers")
def list_suppliers():
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM suppliers ORDER BY id ASC")
    rows = [dict(r) for r in cursor.fetchall()]
    conn.close()
    return rows

@router.get("/alerts/expiry")
def expiry_alerts(threshold_days: int = Query(60, ge=1, le=365)):
    return get_expiry_alerts(days_threshold=threshold_days)

@router.get("/alerts/low-stock")
def low_stock_alerts():
    return get_low_stock_alerts()
