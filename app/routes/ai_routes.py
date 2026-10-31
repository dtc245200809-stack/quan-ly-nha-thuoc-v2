from fastapi import APIRouter, HTTPException, Depends, Query
from typing import Optional
from app.models import AISummarizeRequest, AISOPChatRequest, AIExpiryAnalysisRequest
from app.auth import get_current_user
from app.database import get_db_connection
from app.ai_engine import (
    summarize_medicine_info,
    generate_expiry_analysis_report,
    answer_sop_internal_chat
)

router = APIRouter(prefix="/api/ai", tags=["AI Pharmacy Assistant"])

@router.post("/summarize-medicine")
def summarize_medicine(payload: AISummarizeRequest):
    result = summarize_medicine_info(payload.medicine_id)
    if "error" in result:
        raise HTTPException(status_code=404, detail=result["error"])
    return result

@router.post("/expiry-report")
def expiry_analysis(payload: AIExpiryAnalysisRequest):
    return generate_expiry_analysis_report(threshold_days=payload.threshold_days)

@router.post("/sop-chat")
def sop_chat(payload: AISOPChatRequest):
    if not payload.question.strip():
        raise HTTPException(status_code=400, detail="Nội dung câu hỏi không được để trống")
    return answer_sop_internal_chat(payload.question.strip())

@router.get("/sop-documents")
def list_sop_documents():
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT id, code, title, category, content, updated_at FROM sop_documents ORDER BY code ASC")
    rows = [dict(r) for r in cursor.fetchall()]
    conn.close()
    return rows

@router.get("/logs")
def get_ai_logs(limit: int = Query(50, ge=1, le=200)):
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT l.*, u.username, u.full_name
        FROM ai_logs l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.id DESC
        LIMIT ?
    """, (limit,))
    rows = [dict(r) for r in cursor.fetchall()]
    conn.close()
    return rows
