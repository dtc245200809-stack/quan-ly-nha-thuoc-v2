from pydantic import BaseModel, Field
from typing import List, Optional
from datetime import date

# ----------------- Auth Models -----------------
class LoginRequest(BaseModel):
    username: str
    password: str

class UserResponse(BaseModel):
    id: int
    username: str
    full_name: str
    role: str
    is_active: bool

class TokenResponse(BaseModel):
    access_token: str
    token_type: str = "bearer"
    user: UserResponse

# ----------------- Medicine & Category Models -----------------
class CategoryCreate(BaseModel):
    name: str
    description: Optional[str] = None

class MedicineCreate(BaseModel):
    code: str
    name: str
    category_id: int
    unit_id: int
    active_ingredient: str
    concentration: Optional[str] = None
    route: str = "Uống"
    min_stock_alert: int = 20
    usage_instruction: Optional[str] = None
    contraindication: Optional[str] = None
    side_effects: Optional[str] = None
    storage_condition: Optional[str] = "Nhiệt độ dưới 30°C, nơi khô ráo, tránh ánh sáng trực tiếp"
    prescription_required: bool = False

# ----------------- Batch & Inventory Models -----------------
class BatchCreate(BaseModel):
    batch_number: str
    medicine_id: int
    supplier_id: Optional[int] = None
    import_date: date
    manufacture_date: date
    expiry_date: date
    quantity_imported: int
    import_price: float
    selling_price: float

class BatchStatusUpdate(BaseModel):
    status: str # active, expired, disposed, recalled

# ----------------- Sale & Invoice Models -----------------
class CartItem(BaseModel):
    medicine_id: int
    quantity: int

class InvoiceCreate(BaseModel):
    customer_name: Optional[str] = "Khách lẻ"
    customer_phone: Optional[str] = None
    prescription_code: Optional[str] = None
    discount: float = 0.0
    payment_method: str = "cash"
    note: Optional[str] = None
    items: List[CartItem]

# ----------------- AI Models -----------------
class AISummarizeRequest(BaseModel):
    medicine_id: int

class AISOPChatRequest(BaseModel):
    question: str

class AIExpiryAnalysisRequest(BaseModel):
    threshold_days: int = 60

class AIConfig(BaseModel):
    provider: str = "local" # local, openai, gemini, claude
    api_key: Optional[str] = None
    model_name: Optional[str] = None
