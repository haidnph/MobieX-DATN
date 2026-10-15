# C-01: Tài khoản

## Cấu hình cơ sở dữ liệu

1. Khởi động MySQL trong Laragon.
2. Import `docs/database/auth.sql` bằng phpMyAdmin hoặc MySQL CLI. Nếu đã tạo database trước đó, import lại để thêm bảng `user_remember_tokens`.
3. Đặt các biến môi trường `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` cho PHP/Apache. Mặc định dùng `127.0.0.1:3306`, database `mobilex_store`, user `root`, mật khẩu rỗng.
4. Mở `?page=register` để tạo tài khoản hoặc `?page=login` để đăng nhập.

Mật khẩu được lưu bằng `password_hash`; truy vấn dùng PDO prepared statements; các form có CSRF token; session ID được đổi sau khi xác thực. Tùy chọn ghi nhớ tạo cookie HttpOnly/SameSite với token ngẫu nhiên chỉ lưu dạng hash trong DB và hết hạn sau 30 ngày.

## Trạng thái quên mật khẩu

Các màn hình `?page=forgot-password` và `?page=reset-password` đã có tuyến và giao diện. Chúng chưa gửi liên kết đặt lại mật khẩu vì dự án chưa cấu hình nhà cung cấp email. Cần cấu hình SMTP/mail transport trước khi bật thao tác gửi token.
