# 📱 MOBIEX E-COMMERCE PLATFORM (FPT POLYTECHNIC DATN)

> **Dự án:** Nền tảng Thương mại Điện tử Thiết bị Di động & Công nghệ MobieX  
> **Kiến trúc:** Phân tách hoàn toàn (Decoupled Architecture) — **Backend RESTful API** (Laravel 12) + **Frontend Single Page Application** (React 18 + Vite + Tailwind CSS v4).

---

## 🏗️ 1. TỔNG QUAN KIẾN TRÚC TOÀN HỆ THỐNG

Dự án MobieX được thiết kế theo tiêu chuẩn hiện đại, loại bỏ hoàn toàn mã nguồn Monolithic cũ để phân định rõ ràng trách nhiệm giữa Frontend và Backend:

```
MobieX-DATN/
  ├── backend/           # 🔵 Dịch vụ API Backend (Laravel 12 REST API, Sanctum, RBAC, Eloquent ORM)
  │   ├── app/           # Models, Http Controllers, Services, FormRequests
  │   ├── config/        # vnpay.php, rbac.php, cors.php, auth.php
  │   ├── database/      # Migrations 45 bảng Canonical, Seeders dữ liệu chuẩn
  │   ├── routes/        # api.php (toàn bộ REST endpoints công khai và admin)
  │   ├── tests/         # Bộ kiểm thử tự động toàn diện (425 tests passed)
  │   └── api.json       # Hợp đồng chuẩn OpenAPI v3.1 (Single Source of Truth)
  │
  ├── frontend/          # 🔴 Ứng dụng Client Single Page Application (React 18 + Vite + Tailwind CSS)
  │   ├── src/
  │   │   ├── api/       # axiosClient.js (Bearer token, 401 handler, 422 flattener), endpoints.js
  │   │   ├── components/# Common UI (Button, Input, Modal, Badge, Spinner, Pagination)
  │   │   ├── contexts/  # AuthContext, CartContext, ToastContext
  │   │   ├── features/  # Feature-Driven Modules (auth, catalog, cart, checkout, orders, admin)
  │   │   ├── layouts/   # StorefrontLayout, AdminLayout
  │   │   └── routes/    # AppRoutes, ProtectedRoute, PermissionRoute
  │   ├── vite.config.js # Reverse Proxy /api -> port 8000
  │   └── index.html
  │
  └── docs/              # 📚 Tài liệu kỹ thuật, hướng dẫn sandbox VNPay, hợp đồng API
```

---

## ⚡ 2. CÁC TÍNH NĂNG ĐẶC TẢ CỐT LÕI

### 🛒 Dành cho Khách hàng (Storefront)
- **Khám phá sản phẩm:** Danh mục động, Hero slider banners, tìm kiếm debounced, bộ lọc hãng/khoảng giá/bộ nhớ và phân trang đồng bộ qua URL.
- **Chi tiết sản phẩm & Biến thể:** Chọn màu sắc/dung lượng, cập nhật giá và tồn kho khả dụng thời gian thực (`available_quantity`), xem review đã kiểm duyệt.
- **Giỏ hàng & Khuyến mãi:** Tăng giảm số lượng có chặn vượt tồn kho, áp dụng mã voucher chiết khấu tiền mặt hoặc phần trăm.
- **Thanh toán linh hoạt:** Đặt hàng COD hoặc chuyển hướng thanh toán trực tuyến qua Cổng VNPay Sandbox với xác thực HMAC-SHA512.
- **Quản lý đơn hàng:** Theo dõi dòng thời gian (timeline), hủy đơn hàng tự động hoàn kho khi còn ở trạng thái `pending`, viết bài đánh giá 1-5 sao sau khi đơn hoàn tất (`delivered`).

### 🛡️ Dành cho Quản trị viên (Admin Portal)
- **Bảng điều khiển (Dashboard):** Đo lường doanh thu thực thu (`settled`), doanh thu theo ngày, số đơn chờ và cảnh báo cạn kho (`≤ 5`). Biểu đồ doanh số 7 ngày.
- **State Machine Đơn hàng một chiều:** `Pending ➔ Confirmed ➔ Processing ➔ Shipping ➔ Delivered`. Xuất kho thực tế (`dispatch`) tại mốc `shipping`, giải phóng kho giữ chỗ khi `cancelled`.
- **Quản lý Mã giảm giá (Coupons):** CRUD toàn diện chiến dịch voucher, giới hạn lượt dùng, đơn tối thiểu và hạn sử dụng.
- **Quản lý Sản phẩm & Banners:** CRUD thiết bị, tự động tạo slug chuẩn SEO, điều chỉnh hình ảnh tiếp thị.
- **Kiểm duyệt Đánh giá (Reviews):** Phê duyệt hoặc ẩn đánh giá của khách hàng trước khi hiển thị ra bên ngoài.

---

## 🚀 3. HƯỚNG DẪN KHỞI CHẠY DỰ ÁN LOCAL

### Bước 1: Khởi động Backend API (Laravel 12)
Yêu cầu: PHP 8.2+, MySQL (Laragon / XAMPP).
```powershell
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
* Backend API hoạt động tại: `http://127.0.0.1:8000`
* Tài liệu API tương tác (Scramble): `http://127.0.0.1:8000/docs/api`

### Bước 2: Khởi động Frontend SPA (React 18 + Vite)
Yêu cầu: Node.js 18+.
```powershell
cd frontend
npm install
npm run dev
```
* Ứng dụng SPA hoạt động tại: `http://localhost:5173`
* Tự động proxy các lệnh gọi `/api/*` ngầm sang Backend port 8000.

---

## 🧪 4. KIỂM THỬ VÀ CHUẨN MỰC MÃ NGUỒN

* **Kiểm thử tự động Backend:**
  ```powershell
  cd backend
  php artisan test
  # Kết quả: 425 passed (1,896 assertions) — 100% tỷ lệ đạt
  ```

* **Kiểm tra định dạng mã nguồn Backend (PSR-12):**
  ```powershell
  cd backend
  vendor\bin\pint --test
  ```

* **Kiểm tra đóng gói Frontend:**
  ```powershell
  cd frontend
  npm run build
  # Kết quả: ✓ built in ~250ms (0 errors, 0 warnings)
  ```

---

## 👥 5. THÀNH VIÊN VÀ QUY ƯỚC NHÁNH (GIT WORKFLOW)

- Toàn bộ tính năng phát triển dựa trên nhánh `develop`.
- Quy chuẩn Commit: Theo chuẩn Conventional Commits (`feat(...)`, `fix(...)`, `chore(...)`).
- Merge: Sử dụng phương thức **Squash and Merge** để lịch sử cam kết luôn phẳng, tường minh và chuyên nghiệp.
