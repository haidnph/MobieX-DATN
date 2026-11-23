# 📱 MOBIEX — Nền Tảng Thương Mại Điện Tử Thiết Bị Di Động

> **Dự án:** Nền tảng Thương mại Điện tử Thiết bị Di động & Công nghệ MobieX  
> **Kiến trúc:** Phân tách hoàn toàn (Decoupled Architecture) — **Backend RESTful API** (Laravel 13) + **Frontend Single Page Application** (React 18 + Vite + Tailwind CSS v4).

---

## ⚡ Quick Start (5 phút)

### Windows (One-Click)

```bat
# Chạy setup tự động (CMD)
setup.bat

# Hoặc PowerShell
.\setup.ps1
```

### Thủ công (macOS / Linux / Windows)

```bash
# Backend
cd backend
composer install
cp .env.example .env        # Trên Windows: copy .env.example .env
php artisan mobiex:setup    # migrate + seed + storage:link tự động

# Frontend
cd ../frontend
npm install
```

### Khởi động server

```bash
# Terminal 1 — Backend API
cd backend
php artisan serve
# → http://127.0.0.1:8000

# Terminal 2 — Frontend SPA
cd frontend
npm run dev
# → http://localhost:5173
```

---

## 👤 Local Admin Account

The project does not create privileged users with default credentials. For local development, set `RBAC_ADMIN_NAME`, `RBAC_ADMIN_EMAIL`, and `RBAC_ADMIN_PASSWORD` in the untracked backend `.env`, then run `php artisan db:seed`. Demo customer data is used to link seeded orders and has no known login password.

---

## 🏗️ Kiến Trúc Hệ Thống

```
MobieX-DATN/
├── setup.bat              # 🪟 Windows one-click setup
├── setup.ps1              # 🪟 PowerShell setup (hỗ trợ -Fresh, -SkipFrontend)
├── backend/               # 🔵 Laravel 13 REST API
│   ├── app/
│   │   ├── Console/Commands/MobieXSetupCommand.php   # php artisan mobiex:setup
│   │   ├── Http/Controllers/                          # API Controllers
│   │   ├── Models/                                    # Eloquent Models (48 tables)
│   │   ├── Services/                                  # Business Logic
│   │   └── Mail/OrderConfirmationMail.php             # Order email
│   ├── database/
│   │   ├── migrations/    # 33 migration files
│   │   └── seeders/       # 16 seeders (1,283 products, 15 categories, ...)
│   ├── .env.example       # Template môi trường
│   └── api.json           # OpenAPI v3.1 contract
├── frontend/              # 🔴 React 18 + Vite + Tailwind CSS v4
│   ├── src/
│   │   ├── features/      # auth, catalog, cart, checkout, orders, admin
│   │   ├── contexts/      # AuthContext, CartContext, ToastContext
│   │   └── routes/        # AppRoutes, ProtectedRoute, PermissionRoute
│   └── vite.config.js     # Proxy /api → :8000
└── docs/                  # 📚 Tài liệu kỹ thuật
```

---

## 🔧 Yêu Cầu Hệ Thống

| Công cụ | Phiên bản tối thiểu | Khuyến nghị |
|---------|---------------------|-------------|
| PHP | 8.2+ | 8.3 |
| Composer | 2.x | 2.9+ |
| MySQL / MariaDB | 8.0+ | MySQL 8.0 |
| Node.js | 18+ | 24 LTS |
| npm | 9+ | 11+ |

> **Khuyến nghị:** Dùng [Laragon](https://laragon.org) trên Windows — cung cấp PHP, MySQL và Apache/Nginx trong một gói.

---

## ⚙️ Cấu Hình Môi Trường

Sao chép `.env.example` → `.env` và điều chỉnh:

```dotenv
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mobiex_unified
DB_USERNAME=root
DB_PASSWORD=

# App URL
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:5173

# Mail (mặc định: ghi log vào storage/logs/laravel.log)
MAIL_MAILER=log
# Production — Gmail SMTP:
# MAIL_MAILER=smtp
# MAIL_HOST=smtp.gmail.com
# MAIL_PORT=587
# MAIL_USERNAME=your@gmail.com
# MAIL_PASSWORD=your_app_password
# MAIL_ENCRYPTION=tls

# Cloud Storage (tùy chọn — mặc định dùng local disk)
MEDIA_STORAGE_DRIVER=local
# MEDIA_STORAGE_DRIVER=cloudinary
# CLOUDINARY_CLOUD_NAME=your_cloud_name
# CLOUDINARY_API_KEY=your_api_key
# CLOUDINARY_API_SECRET=your_api_secret
```

---

## 🚀 Artisan Commands

```bash
# Setup toàn bộ dự án (recommended cho fresh clone)
php artisan mobiex:setup

# Reset hoàn toàn database và seed lại (WARNING: xóa toàn bộ data)
php artisan mobiex:setup --fresh

# Chỉ chạy seeders (database đã migrate rồi)
php artisan mobiex:setup --seed-only

# Migrate media cũ từ local → Cloudinary
php artisan media:migrate-to-cloudinary
```

---

## ⚡ Tính Năng Chính

### 🛒 Storefront (Khách hàng)
- Trang chủ động với CMS sections (Hero Slider, Categories, Flash Sale, Featured Products, Brand Slider, Trust Services)
- Danh mục sản phẩm với bộ lọc (hãng, khoảng giá, bộ nhớ), tìm kiếm debounced, phân trang URL
- Chi tiết sản phẩm: chọn màu/dung lượng, real-time tồn kho, gallery ảnh
- Giỏ hàng: tăng/giảm số lượng, chặn vượt tồn kho, áp mã voucher
- Thanh toán: COD + VNPay + ZaloPay + MoMo (tất cả Sandbox)
- Theo dõi đơn hàng timeline, hủy đơn tự hoàn kho
- Đánh giá sản phẩm sau khi nhận hàng
- **Email xác nhận đơn hàng** tự động (HTML responsive, hình ảnh sản phẩm)

### 🛡️ Admin Portal
- Dashboard: doanh thu, biểu đồ 7 ngày, cảnh báo cạn kho
- State Machine đơn hàng: `Pending → Confirmed → Processing → Shipping → Delivered`
- Quản lý sản phẩm, biến thể, hình ảnh (Local + Cloudinary)
- Quản lý Banner và Homepage CMS (kéo-thả sort order, publish/draft)
- Quản lý Coupon, Thương hiệu, Danh mục
- Kiểm duyệt đánh giá
- RBAC phân quyền chi tiết (super-admin, admin, staff, customer)
- Resend email xác nhận đơn hàng, xem log email

---

## 🧪 Kiểm Thử

```bash
# Chạy toàn bộ test suite
cd backend
php artisan test

# Backend Unit Tests
php artisan test --testsuite=Unit

# Backend Feature Tests
php artisan test --testsuite=Feature

# Frontend Tests (Vitest)
cd frontend
npm test
```

---

## 🌐 API Documentation

Sau khi khởi động backend server, truy cập:
- **Interactive API Docs (Scramble):** http://127.0.0.1:8000/docs/api
- **OpenAPI JSON Contract:** [`backend/api.json`](backend/api.json)

---

## Payment Sandbox Configuration

Configure gateway credentials in the untracked backend `.env` using the `VNP_*`, `ZALOPAY_*`, and `MOMO_*` variables from [`backend/.env.example`](backend/.env.example). Do not commit merchant credentials, including sandbox credentials.

---

## 📦 Database & Seed Data

Sau khi chạy `php artisan migrate --seed` hoặc `mobiex:setup`, database sẽ có:

| Bảng | Số lượng | Ghi chú |
|------|----------|---------|
| `products` | ~1,283 | Điện thoại, tablet, phụ kiện |
| `product_variants` | ~1,265 | Màu sắc, bộ nhớ |
| `product_images` | ~1,304 | URL Unsplash HTTPS |
| `categories` | 15 | Điện thoại, Tablet, Laptop, ... |
| `brands` | 13 | Apple, Samsung, Xiaomi, ... |
| `banners` | 3+ | Hero slider |
| `homepage_sections` | 9 | CMS sections (Hero, Flash Sale, ...) |
| `coupons` | 3 | Demo voucher codes |
| `users` | varies | demo customer plus optional local admin configured through `RBAC_ADMIN_*` |

---

## 🔀 Git Workflow

- **Nhánh chính:** `develop`
- **Commit convention:** Conventional Commits (`feat(...)`, `fix(...)`, `chore(...)`)
- **Merge strategy:** Squash and Merge

---

## 🛠️ Troubleshooting

**Lỗi kết nối database:**
```
SQLSTATE[HY000] [2002] No connection could be made
```
→ Khởi động MySQL (Laragon: nhấn Start All)

**Lỗi APP_KEY empty:**
```bash
php artisan key:generate
```

**Lỗi public/storage không tồn tại:**
```bash
php artisan storage:link
```

**Lỗi CORS khi gọi API từ frontend:**
→ Kiểm tra `FRONTEND_URL` trong `.env` trùng với địa chỉ frontend đang chạy

**Queue job không xử lý (email không gửi):**
```bash
# Chạy queue worker thủ công
php artisan queue:work

# Hoặc đổi sang sync driver (email gửi ngay, không cần queue worker)
# QUEUE_CONNECTION=sync  ← trong .env
```
