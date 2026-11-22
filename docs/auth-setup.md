# C-01: Tài khoản

Ứng dụng dùng frontend React tại `frontend/` và Laravel REST API tại `backend/`. Tài khoản được xác thực qua Sanctum Bearer Token; không dùng các trang PHP/PDO `?page=login` hoặc `?page=register`.

## Khởi động local

1. Khởi động MySQL trong Laragon và cấu hình kết nối trong `backend/.env`.
2. Tạo key và chạy migration nếu đây là lần đầu cài đặt:

   ```powershell
   cd backend
   php artisan key:generate
   php artisan migrate --seed
   ```

3. Khởi động backend tại cổng `8000`:

   ```powershell
   php artisan serve --host=127.0.0.1 --port=8000
   ```

4. Mở terminal khác, khởi động frontend:

   ```powershell
   cd frontend
   npm install
   npm run dev
   ```

5. Mở `http://localhost:5173`. Vite chuyển tiếp `/api` tới `http://127.0.0.1:8000`.

## API đăng nhập

Frontend gửi `POST /api/auth/login` với JSON:

```json
{
  "email": "customer@example.com",
  "password": "your-password"
}
```

Email được trim và chuẩn hóa chữ thường trước khi tra cứu. Thành công trả về `data.user`, `data.token`, `data.token_type` và `data.expires_at`. Frontend lưu token rồi gửi `Authorization: Bearer <token>` cho API cần đăng nhập.

Chi tiết endpoint, response và các mã lỗi nằm trong [backend-auth-api.md](backend-auth-api.md).
