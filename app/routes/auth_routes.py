from fastapi import APIRouter, HTTPException, Depends, status
from app.models import LoginRequest, TokenResponse, UserResponse
from app.auth import authenticate_user, create_access_token, get_current_user
from app.database import get_db_connection

router = APIRouter(prefix="/api/auth", tags=["Authentication"])

@router.post("/login", response_model=TokenResponse)
def login(payload: LoginRequest):
    user = authenticate_user(payload.username, payload.password)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Tên đăng nhập hoặc mật khẩu không chính xác"
        )
    
    token = create_access_token(
        user_id=user["id"],
        username=user["username"],
        role=user["role"],
        full_name=user["full_name"]
    )

    return {
        "access_token": token,
        "token_type": "bearer",
        "user": {
            "id": user["id"],
            "username": user["username"],
            "full_name": user["full_name"],
            "role": user["role"],
            "is_active": bool(user["is_active"])
        }
    }

@router.get("/me")
def get_my_profile(current_user: dict = Depends(get_current_user)):
    return current_user
