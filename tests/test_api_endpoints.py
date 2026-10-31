import pytest
from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)

def test_health_endpoint():
    res = client.get("/health")
    assert res.status_code == 200
    data = res.json()
    assert data["status"] == "healthy"
    assert "Thạch Minh Quân" in data["team"]

def test_auth_login():
    # 1. Login đúng tài khoản admin
    res = client.post("/api/auth/login", json={"username": "admin", "password": "admin123"})
    assert res.status_code == 200
    token_data = res.json()
    assert "access_token" in token_data
    assert token_data["user"]["role"] == "admin"

    # 2. Login sai mật khẩu
    res_fail = client.post("/api/auth/login", json={"username": "admin", "password": "wrongpassword"})
    assert res_fail.status_code == 401

def test_medicines_api():
    res = client.get("/api/medicines/")
    assert res.status_code == 200
    meds = res.json()
    assert len(meds) > 0
    assert "code" in meds[0]
    assert "available_stock" in meds[0]

def test_batches_and_alerts_api():
    res = client.get("/api/batches/")
    assert res.status_code == 200
    batches = res.json()
    assert len(batches) > 0

    res_alerts = client.get("/api/batches/alerts/expiry?threshold_days=60")
    assert res_alerts.status_code == 200

    res_low = client.get("/api/batches/alerts/low-stock")
    assert res_low.status_code == 200

def test_ai_api_endpoints():
    # 1. Summarize
    res_sum = client.post("/api/ai/summarize-medicine", json={"medicine_id": 1})
    assert res_sum.status_code == 200
    assert "summary" in res_sum.json()

    # 2. Expiry Report
    res_exp = client.post("/api/ai/expiry-report", json={"threshold_days": 60})
    assert res_exp.status_code == 200
    assert "total_risk_val" in res_exp.json()

    # 3. SOP Chat (Safe)
    res_chat = client.post("/api/ai/sop-chat", json={"question": "Quy trình kiểm nhập lô hàng GSP"})
    assert res_chat.status_code == 200
    assert res_chat.json()["is_safe"] is True

    # 4. SOP Chat (Guardrail Blocked)
    res_blocked = client.post("/api/ai/sop-chat", json={"question": "Kê đơn cho tôi thuốc kháng sinh"})
    assert res_blocked.status_code == 200
    assert res_blocked.json()["is_safe"] is False

    # 5. SOP Documents
    res_docs = client.get("/api/ai/sop-documents")
    assert res_docs.status_code == 200
    assert len(res_docs.json()) >= 4

    # 6. AI Logs
    res_logs = client.get("/api/ai/logs")
    assert res_logs.status_code == 200

def test_reports_api():
    res = client.get("/api/reports/summary")
    assert res.status_code == 200
    assert "total_revenue" in res.json()
    assert "inventory_value" in res.json()
