import json
import os
from typing import Dict, Any, Optional
from app.database import get_db_connection
from app.inventory_service import get_expiry_alerts
from app.ai_guardrails import check_prompt_safety, wrap_with_disclaimer, DISCLAIMER_TEXT
from app.ai_config_manager import call_gemini_with_key_rotation, load_ai_config, get_public_ai_config

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
    Áp dụng Google Gemini với cơ chế tự động xoay Key khi hết Token.
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

    prompt_system = (
        "[SYSTEM INSTRUCTION - UNIFIED PHARMACY ASSISTANT]\n"
        "Bạn là Trợ lý AI Nội bộ phục vụ Dược sĩ, Quản lý và Thu ngân tại Nhà thuốc.\n"
        "Quy tắc Kịch bản 1 (Tóm tắt thông tin thuốc):\n"
        "- Trình bày dạng danh sách gạch đầu dòng ngắn gọn:\n"
        "  + Tên thuốc & Hoạt chất: [Thông tin]\n"
        "  + Công dụng chính: [Thông tin]\n"
        "  + Liều dùng tham khảo / Chống chỉ định: [Thông tin nếu có]\n"
        "- Kết thúc BẮT BUỘC bằng dòng cảnh báo cố định:\n"
        "  \"⚠️ Cảnh báo: Thông tin trên chỉ mang tính chất tham khảo tra cứu nội bộ. Nhân viên cần xác nhận lại với Dược sĩ chuyên môn trước khi tư vấn cho khách hàng.\""
    )
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

    # Thử gọi Gemini AI với cơ chế xoay key tự động
    gemini_res = call_gemini_with_key_rotation(prompt_user, prompt_system)
    used_engine = "gemini" if gemini_res.get("success") else "local_engine"
    engine_note = ""

    if gemini_res.get("success"):
        summary_raw = gemini_res["text"]
        if gemini_res.get("rotated"):
            engine_note = f"\n[Hệ thống: Đã tự động xoay sang Gemini Key {gemini_res.get('key_fingerprint')} do key trước hết Token]"
    else:
        # Fallback Local Smart Engine
        summary_raw = (
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

    final_content = wrap_with_disclaimer(f"{summary_raw}{engine_note}")
    log_ai_interaction(user_id, f"summarize_{used_engine}", prompt_user, final_content)

    return {
        "medicine_id": medicine_id,
        "medicine_name": medicine_dict["name"],
        "prompt_used": prompt_user,
        "summary": final_content,
        "engine": used_engine,
        "key_rotated": gemini_res.get("rotated", False),
        "is_safe": True
    }

def generate_expiry_analysis_report(threshold_days: int = 60, user_id: Optional[int] = None) -> Dict[str, Any]:
    """
    Chức năng AI 2: AI sinh báo cáo thuốc sắp hết hạn và đề xuất xử lý.
    Hỗ trợ gọi Gemini với Multi-Key Rotation hoặc Fallback cục bộ.
    """
    alerts = get_expiry_alerts(days_threshold=threshold_days)
    
    expired_items = [a for a in alerts if a["days_left"] < 0]
    critical_items = [a for a in alerts if 0 <= a["days_left"] <= 30]
    warning_items = [a for a in alerts if 30 < a["days_left"] <= threshold_days]

    total_loss_expired = sum(a["estimated_loss_value"] for a in expired_items)
    total_loss_critical = sum(a["estimated_loss_value"] for a in critical_items)
    total_risk_val = total_loss_expired + total_loss_critical

    # Chuẩn bị dữ liệu đầu vào cho AI
    context_lines = [
        f"- Ngưỡng phân tích: {threshold_days} ngày",
        f"- Số lô đã hết hạn: {len(expired_items)} lô (Thiệt hại: {total_loss_expired:,.0f} đ)",
        f"- Số lô cận hạn nguy cơ cao (<= 30 ngày): {len(critical_items)} lô (Rủi ro: {total_loss_critical:,.0f} đ)",
        f"- Số lô cảnh báo (31 - {threshold_days} ngày): {len(warning_items)} lô",
        "\nChi tiết các lô cần lưu ý:"
    ]
    for i in expired_items[:5]:
        context_lines.append(f"  + [HẾT HẠN] {i['medicine_name']} (Lô: {i['batch_number']}), Tồn: {i['quantity_remaining']}, NCC: {i['supplier_name']}")
    for i in critical_items[:5]:
        context_lines.append(f"  + [CẬN HẠN {i['days_left']} ngày] {i['medicine_name']} (Lô: {i['batch_number']}), Tồn: {i['quantity_remaining']}, NCC: {i['supplier_name']}")

    prompt_ai = (
        f"Hãy đóng vai trò Chuyên gia quản trị kho dược GSP/GPP. Dựa trên dữ liệu kho sau:\n"
        f"{chr(10).join(context_lines)}\n\n"
        f"Yêu cầu:\n"
        f"1. Tổng quan tình trạng rủi ro tài chính của kho.\n"
        f"2. Đề xuất quy trình xử lý cụ thể cho từng nhóm lô (Lô đã hết hạn, Lô cận hạn nguy cơ cao, Lô cảnh báo) "
        f"theo chuẩn SOP-03 của nhà thuốc (cách ly biệt trữ, hoàn trả NCC, ưu tiên bán FEFO, lập biên bản hủy thuốc)."
    )

    sys_inst = "Bạn là chuyên gia quản trị kho dược theo tiêu chuẩn GSP và GPP của Bộ Y Tế. Đề xuất quy trình thực tế, chặt chẽ, an toàn."
    gemini_res = call_gemini_with_key_rotation(prompt_ai, sys_inst)
    used_engine = "gemini" if gemini_res.get("success") else "local_engine"
    engine_note = ""

    if gemini_res.get("success"):
        report_raw = gemini_res["text"]
        if gemini_res.get("rotated"):
            engine_note = f"\n[Hệ thống: Đã tự động xoay sang Gemini Key {gemini_res.get('key_fingerprint')} do key trước hết Token]"
    else:
        # Fallback Local Rule-based Report
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
        report_raw = "\n".join(report_lines)

    report_content = wrap_with_disclaimer(f"{report_raw}{engine_note}")
    log_ai_interaction(user_id, f"expiry_analysis_{used_engine}", prompt_ai, report_content)

    return {
        "threshold_days": threshold_days,
        "total_alerts": len(alerts),
        "expired_count": len(expired_items),
        "critical_count": len(critical_items),
        "warning_count": len(warning_items),
        "total_risk_val": total_risk_val,
        "report": report_content,
        "engine": used_engine,
        "key_rotated": gemini_res.get("rotated", False),
        "raw_items": alerts
    }

def answer_sop_internal_chat(question: str, user_id: Optional[int] = None) -> Dict[str, Any]:
    """
    Chức năng AI 3: Chatbot hỏi đáp quy trình nội bộ như nhập lô, kiểm kê, xử lý hàng hết hạn, bán thuốc.
    Tích hợp AI Guardrail an toàn y tế + Gemini Multi-Key Rotation.
    """
    # Bước 1: Kiểm duyệt an toàn y tế (Guardrails)
    is_safe, refusal_reason = check_prompt_safety(question)
    if not is_safe:
        log_ai_interaction(user_id, "sop_chat_blocked", question, refusal_reason)
        return {
            "question": question,
            "answer": wrap_with_disclaimer(refusal_reason),
            "is_safe": False,
            "engine": "guardrail_blocker",
            "matched_sop": None
        }

    # Bước 2: Truy xuất tài liệu SOP từ CSDL
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT code, title, category, content FROM sop_documents")
    sops = [dict(r) for r in cursor.fetchall()]
    conn.close()

    sop_context = "\n\n".join([f"[{s['code']}: {s['title']}]\n{s['content']}" for s in sops])

    # Bước 3: Thử gửi truy vấn tới Gemini với ngữ cảnh SOP
    prompt_with_context = (
        f"Bạn là Trợ lý Sổ tay Quy trình Thao tác Chuẩn (SOP) nội bộ nhà thuốc. "
        f"Căn cứ vào các tài liệu quy trình chuẩn sau đây để trả lời câu hỏi của nhân viên:\n\n"
        f"--- TÀI LIỆU QUY TRÌNH SOP ---\n{sop_context}\n-----------------------------\n\n"
        f"Câu hỏi của nhân viên: {question}\n\n"
        f"Hãy trả lời rõ ràng, theo các bước chuẩn mực, viện dẫn đúng mã SOP (SOP-01, SOP-02, SOP-03, hoặc SOP-04)."
    )

    sys_inst = (
        "Bạn là Trợ lý nội bộ nhà thuốc. TUYỆT ĐỐI không đưa lời khuyên điều trị, không chỉ định liều dùng ngoài dữ liệu được cung cấp. "
        "Chỉ trả lời câu hỏi liên quan đến quy trình vận hành kho, kiểm kê, xuất nhập, hủy thuốc theo tài liệu cung cấp."
    )

    gemini_res = call_gemini_with_key_rotation(prompt_with_context, sys_inst)
    used_engine = "gemini" if gemini_res.get("success") else "local_engine"
    engine_note = ""

    if gemini_res.get("success"):
        ans_raw = gemini_res["text"]
        if gemini_res.get("rotated"):
            engine_note = f"\n[Hệ thống: Đã tự động xoay sang Gemini Key {gemini_res.get('key_fingerprint')} do key trước hết Token]"
        matched_code = "GEMINI_RAG"
    else:
        # Fallback Local Keyword Matching
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

        if matched_sop and relevance_score > 0:
            ans_raw = (
                f"📖 [TRẢ LỜI QUY TRÌNH NỘI BỘ THEO {matched_sop['code']}: {matched_sop['title'].upper()}]\n"
                f"──────────────────────────────────────────────────────────\n"
                f"Chào bạn, căn cứ vào Sổ tay Quy trình Thao tác Chuẩn (SOP) nội bộ của nhà thuốc:\n\n"
                f"{matched_sop['content']}\n\n"
                f"📌 Ghi chú nghiệp vụ: Mọi ngoại lệ trong quá trình thực hiện phải báo cáo ngay cho Dược sĩ Trưởng để xử lý biên bản."
            )
            matched_code = matched_sop["code"]
        else:
            ans_raw = (
                f"Xin chào nhân viên nhà thuốc. Tôi là Trợ lý nội bộ hỗ trợ quy trình SOP.\n"
                f"Hiện tại hệ thống lưu trữ 4 quy trình chuẩn cốt lõi:\n"
                f" 1. SOP-01: Quy trình kiểm nhập thuốc và tiếp nhận lô hàng (GSP)\n"
                f" 2. SOP-02: Quy trình kiểm kê định kỳ và kiểm soát tồn kho\n"
                f" 3. SOP-03: Quy trình xử lý thuốc cận hạn, hết hạn và thuốc thu hồi\n"
                f" 4. SOP-04: Quy trình bán thuốc và tư vấn sử dụng thuốc theo GPP (FEFO)\n\n"
                f"Bạn có thể hỏi cụ thể như: 'Quy trình xử lý thuốc hết hạn như thế nào?', 'Khi nhập lô hàng cần kiểm tra những gì?', hoặc 'Nguyên tắc bán thuốc FEFO là gì?'."
            )
            matched_code = None

    final_ans = wrap_with_disclaimer(f"{ans_raw}{engine_note}")
    log_ai_interaction(user_id, f"sop_chat_{used_engine}", question, final_ans)

    return {
        "question": question,
        "answer": final_ans,
        "is_safe": True,
        "engine": used_engine,
        "key_rotated": gemini_res.get("rotated", False),
        "matched_sop": matched_code
    }
