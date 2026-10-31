# 💊 Pharmacy Management System - Migration Master Plan

This document serves as the **Source of Truth** for the 2026 Architectural Refactor and Design System update.

## 🎨 1. Design System (Fixed)

- **Font Primary:** "Be Vietnam Pro" (Fallback: "Inter", sans-serif)
- **Primary Color:** `#15803D` (Pharmacy Green - Deep)
- **Primary Hover:** `#166534`
- **Secondary:** `#22C55E`
- **Trust Blue / Info:** `#0369A1`
- **Background:** `#F8FAFC`
- **Surface/Card:** `#FFFFFF`
- **Border:** `#E2E8F0`
- **Text Main:** `#0F172A`
- **Text Muted:** `#64748B`

### Status Colors (Business Logic)
- **Healthy/Safe:** `#16A34A` (Green)
- **Near Expiry:** `#F59E0B` (Amber/Yellow)
- **Danger / Low Stock:** `#DC2626` (Red)
- **System Info:** `#0369A1` (Blue)

---

## 🏗️ 2. New Architecture

- `/assets/css/`: `variables.css`, `layout.css`, `components.css`, `dark.css`.
- `/assets/js/`: `main.js`, `animations.js`.
- `/includes/core/`: `app.php` (Business Logic), `db.php` (DB Connection).
- `/includes/layout/`: `header.php`, `sidebar.php`, `topbar.php`, `footer.php`.
- `/modules/pos/`: POS Terminal, Invoices, Returns, Prescriptions.
- `/modules/inventory/`: Stock Manager, Item Catalog, Batch Alerts.
- `/modules/admin/`: User Management, System Logs, Customers.

---

## 🔗 3. CRUD Consolidation Rules

1. **Users:** Gộp 10 file `admin_*.php` + `delete_*.php` -> `modules/admin/users.php`.
2. **Inventory:** Gộp `stock.php` + `update/delete` -> `modules/inventory/stock_manager.php`.
3. **Master Data:** Gộp `catalog.php` + `suppliers.php` -> `modules/inventory/master_data.php`.
4. **Logic Preservation:** **DO NOT** modify function bodies in `app.php` (especially FEFO and Auth logic).

---

## ⚠️ 4. Critical Constraints
- **NO NEON / NO CYBER** aesthetics.
- **Micro-animations only** (fade-up, count-up).
- **Session & Role Auth** must remain the first line of every file.
