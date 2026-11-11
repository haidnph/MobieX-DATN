# MOBIEX API CONTRACT & HƯỚNG DẪN DÙNG CHO TEAM & AI

> **Dành cho:** Toàn bộ thành viên Team MobieX (Frontend & Backend)  
> **Tiêu chuẩn:** OpenAPI Specification 3.1 (Tự động đồng bộ từ Laravel Backend)  
> **Mục tiêu:** Cung cấp nguồn chuẩn (Single Source of Truth) để Postman test và để AI tự động chuyển đổi giao diện cũ sang ReactJS.

---

## I. CÁCH TRUY CẬP VÀ XUẤT TÀI LIỆU API

### 1. Xem giao diện trực quan (Interactive Web UI)
Khởi động Laravel Backend:
```bash
cd backend
php artisan serve
```
Mở trình duyệt truy cập:
* **API Documentation UI:** `http://127.0.0.1:8000/docs/api`
* **Raw OpenAPI JSON Spec:** `http://127.0.0.1:8000/docs/api.json`

> 💡 **Tính năng nổi bật:** Giao diện Stoplight Elements cho phép test trực tiếp từng API (Try It), xem chi tiết các trường Request Body, Header `Authorization: Bearer <token>`, và các mã lỗi trả về (200, 201, 401, 403, 422).

### 2. Xuất file `api.json` offline
Nếu muốn cập nhật file spec sau khi Backend thêm controller/route mới:
```bash
cd backend
php artisan scramble:export
```
File `backend/api.json` sẽ tự động được cập nhật với toàn bộ 72 operations mới nhất.

### 3. Nhập vào Postman (1-Click Import)
1. Mở Postman -> Bấm nút **Import**.
2. Nhập URL: `http://127.0.0.1:8000/docs/api.json` (hoặc kéo thả file `backend/api.json`).
3. Postman sẽ tự động sinh ra một Collection hoàn chỉnh chứa đầy đủ 72 API endpoints kèm theo cấu trúc Request mẫu!

---

## II. HƯỚNG DẪN CHO THÀNH VIÊN DÙNG AI ĐỂ REFACTOR SANG REACTJS

Khi một thành viên nhận nhiệm vụ chuyển đổi một trang từ giao diện HTML/PHP cũ sang ReactJS, hãy làm theo quy trình 3 bước sau:

### Bước 1: Chuẩn bị 2 thứ ném vào AI (ChatGPT / Claude / Cursor)
1. **File `backend/api.json`** (kéo thả file này vào khung chat AI).
2. **File HTML/PHP View cũ** của trang cần làm (ví dụ: `app/Views/pages/home.php` hoặc `login.php`).

### Bước 2: Dùng câu Prompt mẫu (Copy & Paste)

```markdown
Tôi đính kèm file "api.json" (OpenAPI 3.1 Spec của hệ thống Backend MobieX).
Dưới đây là mã nguồn giao diện HTML cũ của trang [Tên trang, ví dụ: Chi tiết sản phẩm]:

```html
[Dán code HTML cũ vào đây]
```

Yêu cầu:
1. Hãy chuyển đổi giao diện HTML trên thành Component ReactJS sử dụng Tailwind CSS (giữ nguyên phong cách thiết kế, chuyển class thành className).
2. Tra cứu trong file `api.json` đính kèm để tìm đúng các endpoint liên quan đến [Ví dụ: xem chi tiết sản phẩm /products/{id} và thêm vào giỏ hàng POST /cart/items].
3. Sử dụng `axiosClient` với base URL `/api` và tự động gắn Bearer Token.
4. Đảm bảo tên các trường trong Request Body và Response khớp 100% với Schema trong file OpenAPI (ví dụ: product_variant_id, quantity...).
5. Xử lý trạng thái Loading (Skeleton), hiển thị thông báo lỗi Validation (422) và thông báo thành công.
```

### Bước 3: Ráp code do AI sinh ra vào dự án Frontend
* AI sẽ sinh ra file Component React (JSX) chuẩn chỉnh, gọi đúng endpoint và đúng tên trường dữ liệu của Backend mà không cần thành viên phải ngồi đoán mò hay gõ lại từng trường.

---

## III. QUY ƯỚC LÕI CỦA BACKEND CẦN LƯU Ý

1. **Authentication:** 
   * Đăng nhập: `POST /api/auth/login` với body `{ "email": "...", "password": "..." }`.
   * Nhận về `{ "token": "...", "user": {...} }`.
   * Mọi request sau đó phải gửi kèm Header: `Authorization: Bearer <token>`.
2. **Format lỗi Validation (422 Unprocessable Entity):**
   ```json
   {
       "message": "The given data was invalid.",
       "errors": {
           "email": ["Email không đúng định dạng."],
           "password": ["Mật khẩu phải có ít nhất 8 ký tự."]
       }
   }
   ```
3. **CORS:** Backend đã cho phép origin `http://localhost:5173` và `http://127.0.0.1:5173` với `supports_credentials: true`.
