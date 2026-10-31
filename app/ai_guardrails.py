import re
from typing import Tuple, Optional

DISCLAIMER_TEXT = (
    "⚠️ CẢNH BÁO QUAN TRỌNG TỪ HỆ THỐNG:\n"
    "Thông tin do AI sinh ra chỉ có giá trị THAM KHẢO NỘI BỘ cho nhân viên nhà thuốc. "
    "Hệ thống AI KHÔNG đưa ra lời khuyên điều trị y tế, KHÔNG tự ý chỉ định liều dùng ngoài đơn, "
    "và KHÔNG thay thế vai trò chuyên môn của Bác sĩ điều trị hoặc Dược sĩ phụ trách chuyên môn. "
    "Mọi thắc mắc lâm sàng hoặc tư vấn cho khách hàng bắt buộc phải tham vấn Dược sĩ trực."
)

# Các từ khóa và mẫu câu nguy hiểm vượt thẩm quyền AI y tế
OUT_OF_SCOPE_PATTERNS = [
    r"kê đơn",
    r"(uống|dùng)\s+(thuốc|liều)\s+gì",
    r"nên\s+(uống|dùng)\s+thuốc",
    r"chữa.*ung thư|ung thư|khối u ác tính",
    r"quá liều",
    r"tự tử",
    r"thuốc độc|pha chế độc",
    r"bỏ qua lời bác sĩ",
    r"thay thế thuốc bác sĩ",
    r"phá thai"
]

def check_prompt_safety(prompt: str) -> Tuple[bool, Optional[str]]:
    """
    Kiểm tra prompt người dùng trước khi gửi cho AI Engine.
    Trả về: (is_safe, refusal_reason)
    """
    clean_p = prompt.strip().lower()

    for pattern in OUT_OF_SCOPE_PATTERNS:
        if re.search(pattern, clean_p):
            return False, (
                "Yêu cầu vi phạm nguyên tắc an toàn y tế và vượt quá phạm vi của Trợ lý nội bộ nhà thuốc. "
                "AI tuyệt đối không được kê đơn bệnh lý lâm sàng, chẩn đoán bệnh từ xa, hoặc tư vấn trường hợp nguy cấp. "
                "Vui lòng hướng dẫn người bệnh đến cơ sở y tế gần nhất hoặc gặp trực tiếp Bác sĩ/Dược sĩ phụ trách."
            )

    return True, None

def wrap_with_disclaimer(response_text: str) -> str:
    """
    Đảm bảo câu trả lời luôn luôn đi kèm Disclaimer pháp lý y tế.
    """
    return f"{response_text}\n\n---\n{DISCLAIMER_TEXT}"
