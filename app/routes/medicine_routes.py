from fastapi import APIRouter, HTTPException, Depends, Query
from typing import Optional, List
from app.models import MedicineCreate
from app.auth import get_current_user, require_roles
from app.database import get_db_connection
from app.inventory_service import get_medicine_stock_summary

router = APIRouter(prefix="/api/medicines", tags=["Medicines"])

@router.get("/")
def list_medicines(
    q: Optional[str] = None,
    category_id: Optional[int] = None,
    prescription_required: Optional[bool] = None
):
    conn = get_db_connection()
    cursor = conn.cursor()

    query = """
    SELECT m.*, c.name as category_name, u.name as unit_name
    FROM medicines m
    LEFT JOIN categories c ON m.category_id = c.id
    LEFT JOIN units u ON m.unit_id = u.id
    WHERE m.is_active = 1
    """
    params = []

    if q:
        query += " AND (m.name LIKE ? OR m.code LIKE ? OR m.active_ingredient LIKE ?)"
        wildcard = f"%{q}%"
        params.extend([wildcard, wildcard, wildcard])

    if category_id:
        query += " AND m.category_id = ?"
        params.append(category_id)

    if prescription_required is not None:
        query += " AND m.prescription_required = ?"
        params.append(1 if prescription_required else 0)

    query += " ORDER BY m.id DESC"
    cursor.execute(query, params)
    medicines = [dict(r) for r in cursor.fetchall()]
    conn.close()

    # Ghép thông tin tồn kho
    stock_summaries = {s["id"]: s["available_stock"] for s in get_medicine_stock_summary()}
    for m in medicines:
        m["available_stock"] = stock_summaries.get(m["id"], 0)

    return medicines

@router.get("/categories")
def list_categories():
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM categories ORDER BY id ASC")
    rows = [dict(r) for r in cursor.fetchall()]
    conn.close()
    return rows

@router.get("/units")
def list_units():
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM units ORDER BY id ASC")
    rows = [dict(r) for r in cursor.fetchall()]
    conn.close()
    return rows

@router.get("/{medicine_id}")
def get_medicine(medicine_id: int):
    stock_info = get_medicine_stock_summary(medicine_id)
    if not stock_info:
        raise HTTPException(status_code=404, detail="Không tìm thấy thuốc")

    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT m.*, c.name as category_name, u.name as unit_name
        FROM medicines m
        LEFT JOIN categories c ON m.category_id = c.id
        LEFT JOIN units u ON m.unit_id = u.id
        WHERE m.id = ?
    """, (medicine_id,))
    med = dict(cursor.fetchone())

    # Lấy danh sách các lô của thuốc
    cursor.execute("""
        SELECT b.*, s.name as supplier_name
        FROM batches b
        LEFT JOIN suppliers s ON b.supplier_id = s.id
        WHERE b.medicine_id = ?
        ORDER BY b.expiry_date ASC
    """, (medicine_id,))
    med["batches"] = [dict(b) for b in cursor.fetchall()]
    med["available_stock"] = stock_info["available_stock"]
    med["expired_stock"] = stock_info["expired_stock"]

    conn.close()
    return med

@router.post("/", dependencies=[Depends(require_roles("admin", "pharmacist"))])
def create_medicine(payload: MedicineCreate):
    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        cursor.execute("""
            INSERT INTO medicines (
                code, name, category_id, unit_id, active_ingredient, 
                concentration, route, min_stock_alert, usage_instruction, 
                contraindication, side_effects, storage_condition, prescription_required
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            payload.code.strip().upper(), payload.name.strip(), payload.category_id, payload.unit_id,
            payload.active_ingredient.strip(), payload.concentration, payload.route, payload.min_stock_alert,
            payload.usage_instruction, payload.contraindication, payload.side_effects, payload.storage_condition,
            1 if payload.prescription_required else 0
        ))
        conn.commit()
        med_id = cursor.lastrowid
        return {"success": True, "medicine_id": med_id, "message": "Thêm mới thuốc thành công!"}
    except Exception as e:
        conn.rollback()
        raise HTTPException(status_code=400, detail=f"Lỗi khi thêm thuốc: {str(e)}")
    finally:
        conn.close()
