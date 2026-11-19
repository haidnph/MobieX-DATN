# HƯỚNG DẪN HỆ THỐNG EMAIL XÁC NHẬN ĐƠN HÀNG MOBIEX

Tài liệu kỹ thuật và hướng dẫn vận hành hệ thống **Tự động gửi Email xác nhận đơn hàng (Order Confirmation Email)** cho nền tảng thương mại điện tử MobieX.

---

## 1. TỔNG QUAN KIẾN TRÚC

Hệ thống được thiết kế theo chuẩn Enterprise E-commerce, đảm bảo 5 tiêu chí cốt lõi:

1. **Tự động hóa 100%**: Kích hoạt gửi email ngay khi đơn hàng hoàn tất hoặc thanh toán trực tuyến thành công.
2. **Chuẩn xác nghiệp vụ (Zero False Positives)**: Phân định rạch ròi giữa COD (tiền mặt khi nhận hàng) và Thanh toán trực tuyến (VNPay / ZaloPay / MoMo). Tuyệt đối không bao giờ thông báo "Đã thanh toán" khi cổng thanh toán chưa xác nhận hợp lệ.
3. **Chống gửi trùng lặp (Strict Idempotency)**: Cơ chế kiểm tra log kép ngăn chặn triệt để việc khách hàng nhận nhiều email trùng khi trình duyệt redirect và Webhook IPN cùng phản hồi trong vài mili-giây.
4. **Khả năng tự phục hồi (Fault Tolerance & Resilience)**: Quá trình gửi email được thực thi thông qua Queue / `DB::afterCommit`. Lỗi mạng từ SMTP server hoặc dịch vụ mail bên thứ ba **không bao giờ làm hỏng đơn hàng hoặc rollback transaction** của khách hàng.
5. **Theo dõi & Kiểm toán (Audit Logging)**: Bảng `order_email_logs` lưu trữ toàn bộ lịch sử gửi, địa chỉ nhận, trạng thái (`pending`, `sent`, `failed`), thời gian gửi và thông điệp lỗi (nếu có).

---

## 2. LUỒNG NGHIỆP VỤ (BUSINESS WORKFLOW)

```
[Khách hàng Checkout]
        │
        ▼
[Tạo đơn hàng trong DB Transaction]
        │
        ├── Thành công? ──► Commit Transaction
        │                          │
        │                          ▼
        │                  [DB::afterCommit]
        │                          │
        │       ┌──────────────────┴──────────────────┐
        │       ▼                                     ▼
        │   [Đơn hàng COD]                   [Đơn hàng Online (VNPay/ZaloPay/MoMo)]
        │       │                                     │
        │       ▼                                     ▼
        │  Gửi Email Xác Nhận               Chuyển hướng cổng thanh toán
        │  - Phương thức: COD               (Trạng thái: Chờ thanh toán)
        │  - Tình trạng: Chưa thanh toán              │
        │  - Lưu ý: Chuẩn bị tiền mặt                  ▼
        │                                   [Khách hàng thanh toán trên Cổng]
        │                                             │
        │                         ┌───────────────────┴───────────────────┐
        │                         ▼                                       ▼
        │                   [Thành công]                            [Thất bại/Hủy]
        │                         │                                       │
        │                         ▼                                       ▼
        │              Return URL / Webhook IPN               Cập nhật payment_status=failed
        │                         │                               (Không gửi xác nhận thanh toán)
        │                         ▼
        │              Xác thực chữ ký số hợp lệ
        │              Cập nhật payment_status = 'paid'
        │              Cập nhật status = 'confirmed'
        │                         │
        │                         ▼
        │              Gửi Email Xác Nhận Thanh Toán
        │              - Tiêu đề: Xác nhận đơn hàng & thanh toán thành công
        │              - Tình trạng: ĐÃ THANH TOÁN THÀNH CÔNG (Badge xanh lá)
        │              - Kèm Mã giao dịch ngân hàng / Cổng thanh toán
```

---

## 3. CÁC THÀNH PHẦN KỸ THUẬT ĐÃ TRIỂN KHAI

### 3.1. Database Migration
- File: `backend/database/migrations/2026_11_19_180000_add_customer_email_and_tracking_to_orders_table.php`
- Bổ sung vào bảng `orders`:
  - `customer_email` (varchar 150, nullable, index): Địa chỉ email nhận thông báo của đơn hàng (hỗ trợ cả Guest Checkout lẫn người dùng đăng nhập).
  - `confirmation_email_sent_at` (timestamp, nullable): Thời điểm gửi email xác nhận thành công.
  - `confirmation_email_status` (varchar 30, nullable, default `'pending'`, index): Trạng thái gửi email (`pending`, `sent`, `failed`).
  - `confirmation_email_error` (text, nullable): Lưu vết lỗi SMTP/hệ thống nếu gửi thất bại.
- Bảng mới `order_email_logs`:
  - Lưu trữ audit trail: `order_id`, `recipient_email`, `email_type`, `subject`, `payment_status_snapshot`, `status`, `error_message`, `sent_at`.

### 3.2. Models & Accessors
- `App\Models\Order`:
  - Thêm quan hệ `emailLogs(): HasMany`
  - Accessor `effective_customer_email`: Tự động fallback giữa `customer_email` của đơn hàng và `user->email` của tài khoản đăng nhập.
- `App\Models\OrderItem`:
  - Accessor `image_url`: Tự động phân giải đường dẫn ảnh sản phẩm/biến thể sang CDN Cloudinary hoặc URL local đầy đủ.
- `App\Models\OrderEmailLog`:
  - Quản lý bản ghi lịch sử gửi email.

### 3.3. Mailable & Giao diện Email HTML
- Mailable: `App\Mail\OrderConfirmationMail` (hỗ trợ `ShouldQueue`).
- Template HTML: `backend/resources/views/emails/orders/confirmation.blade.php`:
  - Chuẩn Responsive HTML email (tương thích Gmail, Apple Mail, Outlook, Thunderbird, mobile & desktop).
  - Sử dụng layout thẻ `<table>` chuyên dụng cho email client với max-width 600px.
  - Header thương hiệu MobieX hiện đại với gradient xanh navy `#0f172a` và `#1e3a8a`.
  - Huy hiệu trạng thái thanh toán linh hoạt:
    - COD: "CHƯA THANH TOÁN (THANH TOÁN KHI NHẬN HÀNG)" (màu cam hổ phách) kèm hướng dẫn chuẩn bị tiền mặt.
    - Online Paid: "ĐÃ THANH TOÁN THÀNH CÔNG" (màu xanh lục `#16a34a`) kèm Mã giao dịch ngân hàng.
    - Online Pending: "CHỜ THANH TOÁN" (màu vàng).
  - Bảng chi tiết sản phẩm: Tên sản phẩm, phân loại biến thể, SKU, đơn giá, số lượng, thành tiền.
  - Bảng tổng kết tài chính: Tạm tính, Giảm giá (kèm mã coupon nếu có), Phí vận chuyển, **Tổng cộng thanh toán**.
  - Nút kêu gọi hành động (CTA): "XEM CHI TIẾT ĐƠN HÀNG" trỏ trực tiếp về trang chi tiết đơn hàng (với User) hoặc trang tra cứu công khai `orders/lookup?code=...&phone=...` (với Guest).
  - Footer thông tin hỗ trợ khách hàng, chính sách đổi trả, cảnh báo bảo mật phishing và bản quyền MobieX.
- Template Plain Text: `backend/resources/views/emails/orders/confirmation_plain.blade.php`:
  - Dành cho các client không hỗ trợ HTML hoặc người dùng ưu tiên xem văn bản thuần.

### 3.4. Dịch vụ Email & Job Queue
- Job: `App\Jobs\SendOrderConfirmationEmailJob`:
  - Kế thừa `ShouldQueue`, cấu hình `$tries = 3`, `$backoff = [15, 60, 300]`, `$timeout = 60`.
  - Tự động bắt lỗi, cập nhật trạng thái `failed` và ghi log nếu cả 3 lần thử lại đều không thành công.
- Service: `App\Services\OrderMailService`:
  - Đăng ký Singleton trong `AppServiceProvider`.
  - `sendOrderConfirmation(Order $order, bool $force = false)`: Điều phối gửi email an toàn, kiểm tra tính hợp lệ của email và chống gửi trùng.
  - `resendConfirmationEmail(Order $order)`: Cho phép Quản trị viên gửi lại email xác nhận từ Admin panel.
  - `getEmailHistory(Order $order)`: Truy vấn danh sách lịch sử gửi email của đơn hàng.

### 3.5. Tích hợp Controller & API
- `OrderController::store`:
  - Nhận và validate `customer_email` (tùy chọn).
  - Gửi email xác nhận COD thông qua `DB::afterCommit`.
- `PaymentController`:
  - Tích hợp gửi email xác nhận thanh toán thành công trong `vnpayReturn`, `vnpayIpn`, `zalopayReturn`, `zalopayCallback`, `momoReturn`, `momoCallback`.
- `AdminOrderController`:
  - `POST /api/admin/orders/{id}/resend-confirmation-email`: Quản trị viên gửi lại email xác nhận.
  - `GET /api/admin/orders/{id}/email-logs`: Xem lịch sử gửi email của đơn hàng.
- `routes/api.php`: Đăng ký đầy đủ route quản trị dưới quyền `orders.view` và `orders.manage`.

---

## 4. HƯỚNG DẪN CẤU HÌNH GỬI EMAIL (ENVIRONMENT SETUP)

Mọi cấu hình email được điều khiển qua file `backend/.env`.

### 4.1. Môi trường phát triển nội bộ (Local Development)

Mặc định hệ thống dùng driver `log` để kiểm tra trực quan nội dung email mà không cần kết nối internet hay tài khoản SMTP thật:

```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@mobiex.vn"
MAIL_FROM_NAME="MobieX Vietnam"
```

Khi đơn hàng được tạo, toàn bộ mã HTML và Plain Text của email sẽ được ghi vào file:
`backend/storage/logs/laravel.log`.

---

### 4.2. Cấu hình gửi thật qua Gmail SMTP (Khuyến nghị cho Demo & Testing)

Để gửi email thật đến hộp thư Gmail của khách hàng:

1. Bật xác thực 2 bước (2-Step Verification) trên tài khoản Google.
2. Tạo **Mật khẩu ứng dụng (App Password)** dành riêng cho "Mail":
   - Vào Google Account → Bảo mật → Mật khẩu ứng dụng.
   - Đặt tên ví dụ: `MobieX Backend`.
   - Google sẽ cấp mật khẩu 16 ký tự (ví dụ: `abcd efgh ijkl mnop`).
3. Cập nhật `backend/.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_email@gmail.com
MAIL_FROM_NAME="MobieX Vietnam"
```

Sau khi sửa `.env`, xóa cache cấu hình:
```bash
php artisan config:clear
```

---

### 4.3. Cấu hình Mailtrap / Brevo / Resend (Khuyến nghị cho Staging / Production)

#### Dùng Mailtrap (Dành cho kiểm thử chuyên nghiệp):
```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@mobiex.vn"
MAIL_FROM_NAME="MobieX Vietnam"
```

#### Dùng Brevo (Sendinblue) hoặc SendGrid (Production):
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your_brevo_login
MAIL_PASSWORD=your_brevo_smtp_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@mobiex.vn"
MAIL_FROM_NAME="MobieX Vietnam"
```

---

## 5. VẬN HÀNH HÀNG ĐỢI (QUEUE WORKER)

Trong môi trường Production, để tối ưu tốc độ checkout của khách hàng, email nên được xử lý bất đồng bộ qua Database Queue:

1. Trong `backend/.env`:
   ```env
   QUEUE_CONNECTION=database
   ```
2. Chạy Queue Worker:
   ```bash
   php artisan queue:work --tries=3 --timeout=60
   ```
3. Trong môi trường Development hiện tại, `QUEUE_CONNECTION=sync` được sử dụng để email được gửi ngay lập tức trong luồng xử lý mà không cần bật worker riêng.

---

## 6. KẾT QUẢ KIỂM THỬ TỔNG THỂ (TEST RESULTS)

| Bộ kiểm thử | Số lượng bài test | Kết quả | Ghi chú |
|---|---|---|---|
| `OrderConfirmationEmailTest` | 9 tests | **9/9 PASSED** | Kiểm tra toàn diện luồng COD, Online Paid, Chống trùng lặp, Admin Resend, Audit Logs, HTML/Plain rendering |
| Bộ test Đơn hàng & Thanh toán liên quan | 31 tests | **31/31 PASSED** | `EndToEndCheckoutTest`, `OrderCreationStockReservationTest`, `VNPayPaymentCreationTest`, `ZaloPayPaymentCreationTest`, `MoMoPaymentCreationTest`,... |
| Toàn bộ Backend PHPUnit Suite | 578 tests | **578/578 PASSED** | Không có bất kỳ regression nào trên toàn bộ 578 tests |
| Toàn bộ Frontend Vitest Suite | 147 tests | **147/147 PASSED** | 24 test files, 147 test cases đều xanh |
| Production Build Frontend | Vite Build | **0 ERRORS** | Build thành công trong 347ms |

---

## 7. QUẢN TRỊ VIÊN THAO TÁC GỬI LẠI EMAIL

Khi khách hàng báo chưa nhận được email xác nhận hoặc muốn nhận lại bản sao email:
1. Quản trị viên truy cập trang quản lý đơn hàng.
2. Gọi API `POST /api/admin/orders/{id}/resend-confirmation-email` (yêu cầu quyền `orders.manage`).
3. Hệ thống sẽ bỏ qua cờ đã gửi trước đó, gửi lại email xác nhận mới nhất với trạng thái thanh toán hiện tại và cập nhật audit log.
4. Xem danh sách các lần gửi qua `GET /api/admin/orders/{id}/email-logs`.
