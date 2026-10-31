from fastapi import APIRouter, HTTPException, Depends, Query
from pydantic import BaseModel
from typing import Optional, List
from app.models import AISummarizeRequest, AISOPChatRequest, AIExpiryAnalysisRequest
from app.auth import get_current_user, require_roles
from app.database import get_db_connection
from app.ai_engine import (
    summarize_medicine_info,
    generate_expiry_analysis_report,
    answer_sop_internal_chat
)
from app.ai_config_manager import (
    load_ai_config,
    save_ai_config,
    update_gemini_keys,
    get_public_ai_config,
    call_gemini_with_key_rotation
)

router = APIRouter(prefix="/api/ai", tags=["AI Pharmacy Assistant"])

class AIAdminConfigPayload(BaseModel):
    provider: str = "gemini"
    model_name: str = "gemini-1.5-flash"
    gemini_keys: List[str] = []

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

# ----------------- ADMIN AI GEMINI CONFIG & MULTI-KEY ROTATION API -----------------
@router.get("/config")
def get_ai_configuration():
    """Lấy thông tin cấu hình AI và trạng thái danh sách Key (đã che mờ bảo mật)"""
    return get_public_ai_config()

@router.post("/config", dependencies=[Depends(require_roles("admin", "pharmacist"))])
def update_ai_configuration(payload: AIAdminConfigPayload):
    """Admin cập nhật danh sách nhiều Gemini API Key và cấu hình model"""
    result = update_gemini_keys(
        keys_raw=payload.gemini_keys,
        model_name=payload.model_name,
        provider=payload.provider
    )
    return {
        "success": True,
        "message": f"Đã lưu thành công cấu hình AI với {result['total_keys']} API Key. Cơ chế tự động xoay key khi hết Token đã kích hoạt!",
        "config": result
    }

@router.post("/test-connection")
def test_ai_connection():
    """Thử nghiệm gọi AI thực tế với key đang kích hoạt để kiểm tra hạn ngạch (Quota)"""
    res = call_gemini_with_key_rotation(
        prompt="Kiểm tra kết nối hệ thống nhà thuốc PharmaAI. Phản hồi 'OK' nếu nhận được.",
        system_instruction="Bạn là AI kiểm tra hệ thống. Chỉ phản hồi ngắn gọn."
    )
    return res
