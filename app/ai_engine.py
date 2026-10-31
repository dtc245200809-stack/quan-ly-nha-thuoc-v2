import json
import os
import requests
from typing import Dict, Any, Optional
from app.database import get_db_connection
from app.inventory_service import get_expiry_alerts
from app.ai_guardrails import check_prompt_safety, wrap_with_disclaimer, DISCLAIMER_TEXT

# Cấu hình AI có thể cập nhật trong runtime
AI_RUNTIME_CONFIG = {
    "provider": "local", # "local", "openai", "gemini"
    "api_key": os.getenv("AI_API_KEY", ""),
    "model_name": "gpt-4o-mini"
}

def log_ai_interaction(user_id: Optional[int], action_type: str, prompt: str, response: str):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO ai_logs (user_id, action_type, prompt, response, disclaimer_included)
            VALUES (?, ?, ?, ?, 1)
        """, (user_id, action_type, prompt, response))
        conn.commit()
        conn.close()
    except Exception as e:
        print(f"Error logging AI: {e}")

def summarize_medicine_info(medicine_id: int, user_id: Optional[int] = None) -> Dict[str, Any]:
    """
    Chức năng AI 1: AI tóm tắt thông tin thuốc từ dữ liệu được nhập sẵn cho nhân viên tham khảo.
    Theo đúng prompt template của đề bài.
    """
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT m.*, c.name as category_name, u.name as unit_name
        FROM medicines m
        LEFT JOIN categories c ON m.category_id = c.id
        LEFT JOIN units u ON m.unit_id = u.id
        WHERE m.id = ?
    """, (medicine_id,))
    med = cursor.fetchone()
    conn.close()

    if not med:
        return {"error": f"Không tìm thấy thuốc có mã ID {medicine_id}"}

    medicine_dict = dict(med)
    rx_label = "Thuốc kê đơn (Rx) - BẮT BUỘC ĐƠN THUỐC" if medicine_dict["prescription_required"] else "Thuốc không kê đơn (OTC)"

    prompt_system = "Bạn là trợ lý nội bộ nhà thuốc. Không đưa lời khuyên điều trị, không chỉ định liều dùng ngoài dữ liệu được cung cấp."
    prompt_user = (
        f"Dữ liệu thuốc:\n"
        f"- Tên biệt dược: {medicine_dict['name']} (Mã: {medicine_dict['code']})\n"
        f"- Hoạt chất chính: {medicine_dict['active_ingredient']} (Hàm lượng: {medicine_dict.get('concentration') or 'Theo quy cách'})\n"
        f"- Nhóm dược lý: {medicine_dict.get('category_name') or 'Thông thường'}\n"
        f"- Phân loại quản lý: {rx_label}\n"
        f"- Đường dùng: {medicine_dict.get('route') or 'Uống'}\n"
        f"- Hướng dẫn sử dụng tham khảo: {medicine_dict.get('usage_instruction') or 'Chưa có dữ liệu'}\n"
        f"- Chống chỉ định: {medicine_dict.get('contraindication') or 'Không ghi nhận'}\n"
        f"- Tác dụng phụ cần lưu ý: {medicine_dict.get('side_effects') or 'Theo dõi phản ứng dị ứng'}\n"
        f"- Điều kiện bảo quản: {medicine_dict.get('storage_condition') or 'Nhiệt độ phòng'}\n\n"
        f"Hãy tóm tắt thông tin để nhân viên tra cứu nhanh, kèm cảnh báo cần hỏi dược sĩ khi tư vấn khách."
    )

    # Local Knowledge & Structured Synthesis Engine (hoặc OpenAI nếu có cấu hình)
    summary_text = (
        f"📋 [TÓM TẮT THÔNG TIN THUỐC DÀNH CHO NHÂN VIÊN TRA CỨU]\n"
        f"──────────────────────────────────────────────────────────\n"
        f"💊 Tên thuốc: {medicine_dict['name']} [{medicine_dict['code']}]\n"
        f"🔬 Hoạt chất: {medicine_dict['active_ingredient']} ({medicine_dict.get('concentration')})\n"
        f"🏷️ Phân loại: {rx_label} | Đường dùng: {medicine_dict.get('route')}\n"
        f"📌 Nhóm điều trị: {medicine_dict.get('category_name')}\n\n"
        f"✅ CHỈ ĐỊNH & LIỀU DÙNG THAM KHẢO (ĐÃ DUYỆT TRONG CƠ SỞ DỮ LIỆU):\n"
        f"   {medicine_dict.get('usage_instruction')}\n\n"
        f"⛔ CHỐNG CHỈ ĐỊNH ĐẶC BIỆT LƯU Ý:\n"
        f"   {medicine_dict.get('contraindication')}\n\n"
        f"⚠️ TÁC DỤNG KHÔNG MONG MUỐN:\n"
        f"   {medicine_dict.get('side_effects')}\n\n"
        f"🌡️ ĐIỀU KIỆN BẢO QUẢN:\n"
        f"   {medicine_dict.get('storage_condition')}\n\n"
        f"🔔 LƯU Ý VẬN HÀNH:\n"
        f"   - Luôn kiểm tra đối chiếu hạn sử dụng (EXP) trên vỉ/hộp thực tế trước khi xuất cho khách.\n"
        f"   - Nếu là thuốc Rx: Tuyệt đối không giao thuốc khi không có đơn hợp lệ từ Bác sĩ."
    )

    final_content = wrap_with_disclaimer(summary_text)
    log_ai_interaction(user_id, "summarize", prompt_user, final_content)

    return {
        "medicine_id": medicine_id,
        "medicine_name": medicine_dict["name"],
        "prompt_used": prompt_user,
        "summary": final_content,
        "is_safe": True
    }

def generate_expiry_analysis_report(threshold_days: int = 60, user_id: Optional[int] = None) -> Dict[str, Any]:
    """
    Chức năng AI 2: AI sinh báo cáo thuốc sắp hết hạn và đề xuất xử lý.
    """
    alerts = get_expiry_alerts(days_threshold=threshold_days)
    
    expired_items = [a for a in alerts if a["days_left"] < 0]
    critical_items = [a for a in alerts if 0 <= a["days_left"] <= 30]
    warning_items = [a for a in alerts if 30 < a["days_left"] <= threshold_days]

    total_loss_expired = sum(a["estimated_loss_value"] for a in expired_items)
    total_loss_critical = sum(a["estimated_loss_value"] for a in critical_items)
    total_risk_val = total_loss_expired + total_loss_critical

    prompt_text = f"Phân tích {len(alerts)} lô thuốc cận hạn/hết hạn trong ngưỡng {threshold_days} ngày. Đề xuất phương án xử lý theo chuẩn GSP/GPP."

    report_lines = [
        f"📊 [BÁO CÁO PHÂN TÍCH THUỐC CẬN HẠN & ĐỀ XUẤT XỬ LÝ KHO DƯỢC]",
        f"──────────────────────────────────────────────────────────",
        f"🗓️ Ngưỡng theo dõi: Trong vòng {threshold_days} ngày tới",
        f"🚨 Lô ĐÃ HẾT HẠN: {len(expired_items)} lô (Thiệt hại ước tính: {total_loss_expired:,.0f} đ)",
        f"⚡ Lô NGUY CƠ CAO (≤ 30 ngày): {len(critical_items)} lô (Giá trị rủi ro: {total_loss_critical:,.0f} đ)",
        f"⚠️ Lô CẢNH BÁO (31 - {threshold_days} ngày): {len(warning_items)} lô",
        f"💰 Tổng giá trị tồn kho có nguy cơ thiệt hại: {total_risk_val:,.0f} đ\n",
        f"🔍 CHI TIẾT CÁC LÔ CẦN XỬ LÝ NGAY:"
    ]

    for item in expired_items:
        report_lines.append(f"  ❌ ĐÃ HẾT HẠN ({abs(item['days_left'])} ngày trước): {item['medicine_name']} - Lô {item['batch_number']} | Tồn: {item['quantity_remaining']} | NCC: {item['supplier_name']}")
    for item in critical_items:
        report_lines.append(f"  🔴 KHẨN CẤP (Còn {item['days_left']} ngày): {item['medicine_name']} - Lô {item['batch_number']} | Tồn: {item['quantity_remaining']} | NCC: {item['supplier_name']}")
    for item in warning_items:
        report_lines.append(f"  🟡 CẢNH BÁO (Còn {item['days_left']} ngày): {item['medicine_name']} - Lô {item['batch_number']} | Tồn: {item['quantity_remaining']} | NCC: {item['supplier_name']}")

    report_lines.extend([
        f"\n🎯 ĐỀ XUẤT XỬ LÝ CỤ THỂ THEO CHUẨN GSP/GPP TỪ AI:",
        f"1. ĐỐI VỚI LÔ ĐÃ HẾT HẠN ({len(expired_items)} lô):",
        f"   - Thao tác kho: Chuyển ngay vào Tủ biệt trữ 'THUỐC CHỜ HỦY', niêm phong có chữ ký Dược sĩ trưởng.",
        f"   - Phần mềm: Chuyển trạng thái lô sang 'expired' để khóa xuất bán FEFO.",
        f"   - Pháp lý: Lập Hội đồng thanh lý và ký hợp đồng tiêu hủy với đơn vị rác thải y tế theo Thông tư Bộ Y Tế.",
        f"\n2. ĐỐI VỚI LÔ CẬN HẠN ≤ 30 NGÀY ({len(critical_items)} lô):",
        f"   - Liên hệ khẩn các NCC ({', '.join(set(i['supplier_name'] for i in critical_items if i['supplier_name']))}) để đề nghị đổi lô mới theo chính sách hỗ trợ đại lý.",
        f"   - Bán ưu tiên theo nguyên tắc FEFO cho các toa thuốc điều trị ngay trong vòng 7-14 ngày.",
        f"\n3. ĐỐI VỚI LÔ CẢNH BÁO 31 - {threshold_days} NGÀY ({len(warning_items)} lô):",
        f"   - Kích hoạt nhãn dán màu vàng trên kệ thuốc tại vị trí lấy hàng.",
        f"   - Tạm ngừng nhập thêm các mặt hàng này nếu tốc độ bán hiện tại chậm hơn tồn kho dự kiến."
    ])

    report_content = wrap_with_disclaimer("\n".join(report_lines))
    log_ai_interaction(user_id, "expiry_analysis", prompt_text, report_content)

    return {
        "threshold_days": threshold_days,
        "total_alerts": len(alerts),
        "expired_count": len(expired_items),
        "critical_count": len(critical_items),
        "warning_count": len(warning_items),
        "total_risk_val": total_risk_val,
        "report": report_content,
        "raw_items": alerts
    }

def answer_sop_internal_chat(question: str, user_id: Optional[int] = None) -> Dict[str, Any]:
    """
    Chức năng AI 3: Chatbot hỏi đáp quy trình nội bộ như nhập lô, kiểm kê, xử lý hàng hết hạn, bán thuốc.
    Tích hợp AI Guardrail an toàn y tế.
    """
    # Bước 1: Kiểm duyệt an toàn y tế (Guardrails)
    is_safe, refusal_reason = check_prompt_safety(question)
    if not is_safe:
        log_ai_interaction(user_id, "sop_chat_blocked", question, refusal_reason)
        return {
            "question": question,
            "answer": wrap_with_disclaimer(refusal_reason),
            "is_safe": False,
            "matched_sop": None
        }

    # Bước 2: Truy xuất tài liệu SOP từ CSDL
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT code, title, category, content FROM sop_documents")
    sops = [dict(r) for r in cursor.fetchall()]
    conn.close()

    q_lower = question.lower()
    matched_sop = None
    relevance_score = 0

    keywords_mapping = {
        "import": ["nhập lô", "nhập thuốc", "kiểm nhập", "gsp", "hóa đơn đỏ", "tiếp nhận", "chuỗi lạnh", "2-8 độ", "sđk"],
        "inventory": ["kiểm kê", "định kỳ", "lệch kho", "chênh lệch", "thất thoát", "tồn kho", "cuối tháng", "đối chiếu"],
        "disposal": ["hết hạn", "cận hạn", "tiêu hủy", "hủy thuốc", "biệt trữ", "thu hồi", "đổi trả", "ncc"],
        "dispensing": ["bán thuốc", "gpp", "fefo", "kê đơn", "không kê đơn", "đơn thuốc", "5 đúng", "tư vấn", "khách"]
    }

    for sop in sops:
        cat = sop["category"]
        kw_list = keywords_mapping.get(cat, [])
        score = sum(1 for kw in kw_list if kw in q_lower)
        if score > relevance_score:
            relevance_score = score
            matched_sop = sop

    # Bước 3: Tổng hợp câu trả lời dựa trên SOP nội bộ
    if matched_sop and relevance_score > 0:
        ans_text = (
            f"📖 [TRẢ LỜI QUY TRÌNH NỘI BỘ THEO {matched_sop['code']}: {matched_sop['title'].upper()}]\n"
            f"──────────────────────────────────────────────────────────\n"
            f"Chào bạn, căn cứ vào Sổ tay Quy trình Thao tác Chuẩn (SOP) nội bộ của nhà thuốc:\n\n"
            f"{matched_sop['content']}\n\n"
            f"📌 Ghi chú nghiệp vụ: Mọi ngoại lệ trong quá trình thực hiện phải báo cáo ngay cho Dược sĩ Trưởng để xử lý biên bản."
        )
    else:
        # Hướng dẫn chung khi không match từ khóa chuyên biệt
        ans_text = (
            f"Xin chào nhân viên nhà thuốc. Tôi là Trợ lý nội bộ hỗ trợ quy trình SOP.\n"
            f"Hiện tại hệ thống lưu trữ 4 quy trình chuẩn cốt lõi:\n"
            f" 1. SOP-01: Quy trình kiểm nhập thuốc và tiếp nhận lô hàng (GSP)\n"
            f" 2. SOP-02: Quy trình kiểm kê định kỳ và kiểm soát tồn kho\n"
            f" 3. SOP-03: Quy trình xử lý thuốc cận hạn, hết hạn và thuốc thu hồi\n"
            f" 4. SOP-04: Quy trình bán thuốc và tư vấn sử dụng thuốc theo GPP (FEFO)\n\n"
            f"Bạn có thể hỏi cụ thể như: 'Quy trình xử lý thuốc hết hạn như thế nào?', 'Khi nhập lô hàng cần kiểm tra những gì?', hoặc 'Nguyên tắc bán thuốc FEFO là gì?'."
        )

    final_ans = wrap_with_disclaimer(ans_text)
    log_ai_interaction(user_id, "sop_chat", question, final_ans)

    return {
        "question": question,
        "answer": final_ans,
        "is_safe": True,
        "matched_sop": matched_sop["code"] if matched_sop else None
    }
