import json
import os
import requests
from typing import Dict, Any, List, Optional
from datetime import datetime

CONFIG_PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "ai_config.json")

DEFAULT_CONFIG = {
    "provider": "gemini", # "gemini", "local", "openai"
    "gemini_keys": [],
    "model_name": "gemini-2.5-flash",
    "current_key_index": 0,
    "auto_rotate": True,
    "temperature": 0.3,
    "key_stats": {} # key_hash -> {status: "active"|"quota_exhausted"|"invalid", uses: int, errors: int, last_used: str}
}

def get_key_fingerprint(key: str) -> str:
    if not key or len(key) < 8:
        return "invalid_key"
    return f"...{key[-6:]}"

def load_ai_config() -> Dict[str, Any]:
    if not os.path.exists(CONFIG_PATH):
        save_ai_config(DEFAULT_CONFIG)
        return DEFAULT_CONFIG.copy()
    try:
        with open(CONFIG_PATH, "r", encoding="utf-8") as f:
            data = json.load(f)
            # Ensure all keys exist
            for k, v in DEFAULT_CONFIG.items():
                if k not in data:
                    data[k] = v
            return data
    except Exception as e:
        print(f"Error loading AI config: {e}")
        return DEFAULT_CONFIG.copy()

def save_ai_config(cfg: Dict[str, Any]) -> bool:
    try:
        with open(CONFIG_PATH, "w", encoding="utf-8") as f:
            json.dump(cfg, f, indent=2, ensure_ascii=False)
        return True
    except Exception as e:
        print(f"Error saving AI config: {e}")
        return False

def update_gemini_keys(keys_raw: List[str], model_name: str = "gemini-1.5-flash", provider: str = "gemini") -> Dict[str, Any]:
    cfg = load_ai_config()
    cleaned_keys = []
    for k in keys_raw:
        k_clean = k.strip()
        if k_clean and len(k_clean) > 10 and k_clean not in cleaned_keys:
            cleaned_keys.append(k_clean)

    cfg["provider"] = provider
    cfg["gemini_keys"] = cleaned_keys
    cfg["model_name"] = model_name
    cfg["current_key_index"] = 0

    # Khởi tạo stats mới cho các key được cập nhật
    new_stats = {}
    for k in cleaned_keys:
        fp = get_key_fingerprint(k)
        new_stats[fp] = {
            "status": "active",
            "uses": 0,
            "errors": 0,
            "last_used": None,
            "last_error": None
        }
    cfg["key_stats"] = new_stats
    save_ai_config(cfg)
    return get_public_ai_config()

def get_public_ai_config() -> Dict[str, Any]:
    cfg = load_ai_config()
    keys = cfg.get("gemini_keys", [])
    masked_keys = []
    for idx, k in enumerate(keys):
        fp = get_key_fingerprint(k)
        stat = cfg.get("key_stats", {}).get(fp, {"status": "active", "uses": 0, "errors": 0})
        masked_keys.append({
            "index": idx,
            "display": f"{k[:4]}****{k[-4:]}",
            "fingerprint": fp,
            "status": stat.get("status", "active"),
            "uses": stat.get("uses", 0),
            "errors": stat.get("errors", 0),
            "last_error": stat.get("last_error")
        })

    return {
        "provider": cfg.get("provider", "gemini"),
        "model_name": cfg.get("model_name", "gemini-1.5-flash"),
        "total_keys": len(keys),
        "current_key_index": cfg.get("current_key_index", 0),
        "auto_rotate": cfg.get("auto_rotate", True),
        "keys": masked_keys
    }

def call_gemini_with_key_rotation(prompt: str, system_instruction: str = "") -> Dict[str, Any]:
    """
    Gọi Google Gemini API có cơ chế TỰ ĐỘNG XOAY KEY (Auto Key Rotation):
    - Thử key hiện tại.
    - Nếu gặp lỗi 429 (ResourceExhausted / Quota Limit) hoặc lỗi token:
      + Đánh dấu key là 'quota_exhausted'
      + Tự động xoay sang key kế tiếp trong danh sách
      + Thử lại cho đến khi thành công hoặc duyệt hết các key.
    - Nếu toàn bộ key đều hết hoặc chưa cấu hình key, trả về thông báo để kích hoạt Local Knowledge Engine.
    """
    cfg = load_ai_config()
    keys = cfg.get("gemini_keys", [])
    provider = cfg.get("provider", "gemini")
    model = cfg.get("model_name", "gemini-1.5-flash")

    if provider != "gemini" or not keys:
        return {"success": False, "reason": "no_gemini_keys", "message": "Chưa cấu hình Gemini API Key"}

    total_keys = len(keys)
    start_index = cfg.get("current_key_index", 0) % total_keys
    attempts = 0

    full_system = (
        system_instruction or 
        "Bạn là trợ lý nội bộ nhà thuốc. TUYỆT ĐỐI không đưa lời khuyên điều trị, không chỉ định liều dùng ngoài dữ liệu được cung cấp. Luôn nhắc nhở nhân viên hỏi ý kiến Dược sĩ phụ trách chuyên môn khi tư vấn người bệnh."
    )

    request_payload = {
        "contents": [
            {
                "role": "user",
                "parts": [
                    {"text": f"System Directive:\n{full_system}\n\nNội dung công việc:\n{prompt}"}
                ]
            }
        ],
        "generationConfig": {
            "temperature": cfg.get("temperature", 0.3),
            "maxOutputTokens": 1024
        }
    }

    last_error_msg = ""
    rotated_count = 0

    while attempts < total_keys:
        curr_idx = (start_index + attempts) % total_keys
        active_key = keys[curr_idx]
        fp = get_key_fingerprint(active_key)
        url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={active_key}"

        try:
            resp = requests.post(
                url,
                headers={"Content-Type": "application/json"},
                json=request_payload,
                timeout=15
            )

            # Cập nhật thông số sử dụng
            stat = cfg.setdefault("key_stats", {}).setdefault(fp, {"uses": 0, "errors": 0, "status": "active"})
            stat["last_used"] = datetime.now().isoformat()

            if resp.status_code == 200:
                data = resp.json()
                try:
                    generated_text = data["candidates"][0]["content"]["parts"][0]["text"]
                    stat["uses"] = stat.get("uses", 0) + 1
                    stat["status"] = "active"
                    cfg["current_key_index"] = curr_idx
                    save_ai_config(cfg)

                    return {
                        "success": True,
                        "text": generated_text.strip(),
                        "model": model,
                        "key_fingerprint": fp,
                        "rotated": rotated_count > 0,
                        "rotations_made": rotated_count
                    }
                except (KeyError, IndexError) as parse_err:
                    last_error_msg = f"Phản hồi từ Gemini không đúng cấu trúc: {str(parse_err)}"
                    stat["errors"] = stat.get("errors", 0) + 1
            elif resp.status_code == 429:
                # Quota Exceeded / Rate Limit -> Kích hoạt xoay key
                stat["errors"] = stat.get("errors", 0) + 1
                stat["status"] = "quota_exhausted"
                stat["last_error"] = "Hết Token / Quota Limit (HTTP 429)"
                rotated_count += 1
                print(f"[GEMINI AUTO ROTATION] Key {fp} bị hết Token (429), đang tự động chuyển sang key tiếp theo...")
            elif resp.status_code in [400, 403]:
                # Invalid API key
                stat["errors"] = stat.get("errors", 0) + 1
                stat["status"] = "invalid"
                stat["last_error"] = f"Lỗi xác thực (HTTP {resp.status_code})"
                rotated_count += 1
                print(f"[GEMINI AUTO ROTATION] Key {fp} không hợp lệ ({resp.status_code}), chuyển sang key khác...")
            else:
                stat["errors"] = stat.get("errors", 0) + 1
                stat["last_error"] = f"HTTP {resp.status_code}: {resp.text[:100]}"
                last_error_msg = f"HTTP {resp.status_code}"

        except requests.exceptions.RequestException as req_err:
            last_error_msg = f"Lỗi kết nối: {str(req_err)}"

        attempts += 1

    # Nếu tất cả các key đều thử qua mà thất bại
    cfg["current_key_index"] = (start_index + 1) % total_keys
    save_ai_config(cfg)

    return {
        "success": False,
        "reason": "all_keys_exhausted_or_failed",
        "message": f"Toàn bộ {total_keys} Gemini API key đều đã hết Token hoặc lỗi. Đã chuyển sang Local AI Engine.",
        "last_error": last_error_msg,
        "rotations_attempted": rotated_count
    }
