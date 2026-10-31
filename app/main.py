import os
from contextlib import asynccontextmanager
from fastapi import FastAPI, Request
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse, RedirectResponse

from app.database import init_db
from app.routes import (
    auth_routes,
    medicine_routes,
    batch_routes,
    invoice_routes,
    report_routes,
    ai_routes
)

@asynccontextmanager
async def lifespan(app: FastAPI):
    # Khởi tạo CSDL khi khởi động ứng dụng
    init_db()
    yield

app = FastAPI(
    title="Hệ Thống Quản Lý Nhà Thuốc Thông Minh Tích Hợp AI",
    description="Hệ thống quản lý dược phẩm, lô hàng FEFO, bán hàng, cảnh báo tồn kho và Trợ lý AI y tế có Guardrails",
    version="2.0.0",
    lifespan=lifespan
)

# CORS Middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Static & Templates setup
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
STATIC_DIR = os.path.join(BASE_DIR, "public")
TEMPLATES_DIR = os.path.join(BASE_DIR, "templates")

if not os.path.exists(STATIC_DIR):
    os.makedirs(STATIC_DIR, exist_ok=True)
if not os.path.exists(TEMPLATES_DIR):
    os.makedirs(TEMPLATES_DIR, exist_ok=True)

app.mount("/static", StaticFiles(directory=STATIC_DIR), name="static")
templates = Jinja2Templates(directory=TEMPLATES_DIR)

# Include API Routers
app.include_router(auth_routes.router)
app.include_router(medicine_routes.router)
app.include_router(batch_routes.router)
app.include_router(invoice_routes.router)
app.include_router(report_routes.router)
app.include_router(ai_routes.router)

@app.get("/health")
def health_check():
    return {
        "status": "healthy",
        "service": "Pharmacy AI Management System",
        "version": "2.0.0",
        "team": ["Thạch Minh Quân", "Nguyễn Trung Kiên", "Lê Huy Hoàng"]
    }

# Web UI Routes
@app.get("/", response_class=HTMLResponse)
def index_view(request: Request):
    return templates.TemplateResponse(request=request, name="dashboard.html", context={"page": "dashboard"})

@app.get("/medicines", response_class=HTMLResponse)
def medicines_view(request: Request):
    return templates.TemplateResponse(request=request, name="medicines.html", context={"page": "medicines"})

@app.get("/batches", response_class=HTMLResponse)
def batches_view(request: Request):
    return templates.TemplateResponse(request=request, name="batches.html", context={"page": "batches"})

@app.get("/sales", response_class=HTMLResponse)
def sales_view(request: Request):
    return templates.TemplateResponse(request=request, name="sales.html", context={"page": "sales"})

@app.get("/alerts", response_class=HTMLResponse)
def alerts_view(request: Request):
    return templates.TemplateResponse(request=request, name="alerts.html", context={"page": "alerts"})

@app.get("/ai-copilot", response_class=HTMLResponse)
def ai_copilot_view(request: Request):
    return templates.TemplateResponse(request=request, name="ai_copilot.html", context={"page": "ai_copilot"})

@app.get("/reports", response_class=HTMLResponse)
def reports_view(request: Request):
    return templates.TemplateResponse(request=request, name="reports.html", context={"page": "reports"})

@app.get("/about", response_class=HTMLResponse)
def about_view(request: Request):
    return templates.TemplateResponse(request=request, name="about.html", context={"page": "about"})

@app.get("/login", response_class=HTMLResponse)
def login_view(request: Request):
    return templates.TemplateResponse(request=request, name="login.html", context={"page": "login"})
