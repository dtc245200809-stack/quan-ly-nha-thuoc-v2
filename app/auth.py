import secrets
import time
from typing import Optional, Dict
from fastapi import Header, HTTPException, Depends, status
from app.database import get_db_connection, hash_password

# Đơn giản, an toàn không cần phụ thuộc thư viện jwt ngoài
TOKEN_STORE: Dict[str, dict] = {}

def create_access_token(user_id: int, username: str, role: str, full_name: str) -> str:
    token = f"rx_{secrets.token_hex(24)}"
    TOKEN_STORE[token] = {
        "user_id": user_id,
        "username": username,
        "role": role,
        "full_name": full_name,
        "created_at": time.time()
    }
    return token

def authenticate_user(username: str, password: str):
    conn = get_db_connection()
    cursor = conn.cursor()
    pwd_hash = hash_password(password)
    cursor.execute(
        "SELECT id, username, full_name, role, is_active FROM users WHERE username = ? AND password_hash = ?",
        (username, pwd_hash)
    )
    user = cursor.fetchone()
    conn.close()

    if not user or not user["is_active"]:
        return None
    return dict(user)

def get_current_user(authorization: Optional[str] = Header(None)):
    if not authorization:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Thiếu Authorization Header",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    parts = authorization.split()
    if len(parts) != 2 or parts[0].lower() != "bearer":
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Định dạng Bearer token không hợp lệ",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    token = parts[1]
    user_session = TOKEN_STORE.get(token)
    if not user_session:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Phiên đăng nhập không hợp lệ hoặc đã hết hạn",
            headers={"WWW-Authenticate": "Bearer"},
        )
    return user_session

def require_roles(*allowed_roles):
    def role_checker(current_user: dict = Depends(get_current_user)):
        if current_user["role"] not in allowed_roles:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Quyền hạn không đủ! Yêu cầu một trong các quyền: {', '.join(allowed_roles)}. Quyền hiện tại: {current_user['role']}"
            )
        return current_user
    return role_checker
