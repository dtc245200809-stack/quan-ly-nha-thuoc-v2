import pytest
from unittest.mock import patch, MagicMock
from app.ai_config_manager import (
    update_gemini_keys,
    get_public_ai_config,
    call_gemini_with_key_rotation,
    load_ai_config,
    get_key_fingerprint
)

def test_gemini_key_pool_configuration():
    """Kiểm tra việc nạp danh sách nhiều key và che mờ bảo mật"""
    raw_keys = [
        "AIzaSyDemoKeyOne1111111111111111111",
        "AIzaSyDemoKeyTwo2222222222222222222",
        "AIzaSyDemoKeyThree33333333333333333"
    ]
    cfg = update_gemini_keys(raw_keys, model_name="gemini-1.5-flash", provider="gemini")
    assert cfg["total_keys"] == 3
    assert len(cfg["keys"]) == 3
    assert cfg["keys"][0]["display"].startswith("AIza")
    assert cfg["keys"][0]["display"].endswith("1111")
    assert cfg["keys"][0]["status"] == "active"

def test_gemini_auto_key_rotation_on_429_quota_exceeded():
    """
    Kiểm tra cơ chế TỰ ĐỘNG XOAY KEY khi gặp mã lỗi HTTP 429 (ResourceExhausted/Quota Limit).
    Key 1 trả về 429 -> Hệ thống tự động chuyển sang Key 2 và hoàn tất thành công.
    """
    raw_keys = [
        "AIzaSyExhaustedKey11111111111111111",
        "AIzaSyWorkingBackupKey2222222222222"
    ]
    update_gemini_keys(raw_keys, model_name="gemini-1.5-flash", provider="gemini")

    # Giả lập response: Lần gọi đầu (Key 1) trả về 429, lần gọi 2 (Key 2) trả về 200
    mock_resp_429 = MagicMock()
    mock_resp_429.status_code = 429
    mock_resp_429.text = "Resource has been exhausted (e.g. check quota)."

    mock_resp_200 = MagicMock()
    mock_resp_200.status_code = 200
    mock_resp_200.json.return_value = {
        "candidates": [
            {
                "content": {
                    "parts": [{"text": "Bản tóm tắt thuốc thành công từ Key dự phòng 2"}]
                }
            }
        ]
    }

    with patch("requests.post", side_effect=[mock_resp_429, mock_resp_200]):
        result = call_gemini_with_key_rotation("Tra cứu Paracetamol", "System instruction")
        assert result["success"] is True
        assert "từ Key dự phòng 2" in result["text"]
        assert result["rotated"] is True
        assert result["rotations_made"] == 1

    # Kiểm tra trạng thái lưu trữ: Key 1 phải được đánh dấu quota_exhausted
    live_cfg = load_ai_config()
    key1_fp = get_key_fingerprint(raw_keys[0])
    assert live_cfg["key_stats"][key1_fp]["status"] == "quota_exhausted"

def test_gemini_fallback_when_all_keys_exhausted():
    """
    Kiểm tra khi toàn bộ key trong bể đều hết hạn ngạch -> Báo lỗi có cấu trúc để kích hoạt Local Engine.
    """
    raw_keys = ["AIzaSyKeyA111111111111111111111", "AIzaSyKeyB222222222222222222222"]
    update_gemini_keys(raw_keys)

    mock_resp_429 = MagicMock()
    mock_resp_429.status_code = 429
    mock_resp_429.text = "Quota exceeded"

    with patch("requests.post", return_value=mock_resp_429):
        result = call_gemini_with_key_rotation("Tra cứu", "System")
        assert result["success"] is False
        assert result["reason"] == "all_keys_exhausted_or_failed"
