# Hướng Dẫn Cấu Hình Public HTTPS Tunnel & VNPay IPN Cho Môi Trường Local

## 1. Vì Sao `localhost` Không Nhận Được VNPay IPN?

Trong kiến trúc thanh toán của **VNPay**:
- **Return URL** (`VNP_RETURN_URL=http://localhost:5173/payment/vnpay/return`) chạy qua **trình duyệt của khách hàng** nên hoạt động bình thường trên `localhost`.
- **IPN URL** (`/api/payments/vnpay/ipn`) là request **Server-to-Server** do máy chủ VNPay gửi trực tiếp từ Internet về Backend của MobieX. Máy chủ VNPay **không thể truy cập** `http://localhost:8000` hoặc `http://127.0.0.1:8000`.
- Ngoài ra, VNPay **không nhận tham số `vnp_IpnUrl` trong URL tạo thanh toán** — Merchant bắt buộc phải cấu hình IPN URL cố định trên **VNPay Merchant Portal** ứng với `VNP_TMN_CODE` của mình.

> ⚠️ **Lưu ý về mã Sandbox công khai (`CGXZLS0Z`):**
> `CGXZLS0Z` là Terminal Code demo dùng chung. Bạn không thể đăng nhập VNPay Merchant Portal để đổi IPN URL của `CGXZLS0Z` về máy local của bạn. Để kiểm thử IPN tự động thực tế trên VNPay Sandbox, hãy đăng ký tài khoản Merchant Sandbox riêng tại https://sandbox.vnpayment.vn/devreg/.

---

## 2. Khởi Động Public HTTPS Tunnel Trên Máy Local

Dự án đã cấu hình sẵn `TrustProxies` trong Laravel (`backend/bootstrap/app.php`) và script `start_tunnel.bat`.

### Bước 1: Cài đặt `cloudflared` (chỉ làm 1 lần)
```powershell
winget install --id Cloudflare.cloudflared -e
```
*(Hoặc dùng `ngrok`: `winget install --id Ngrok.Ngrok -e`)*

### Bước 2: Chạy Backend & Tunnel
```powershell
# Terminal 1: Backend Laravel
cd backend
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2: Public HTTPS Tunnel
.\start_tunnel.bat
```
Khi chạy thành công, `cloudflared` sẽ cấp một URL công khai dạng:
```text
https://random-name-here.trycloudflare.com
```

### Bước 3: Kiểm Tra Tunnel Đã Thông Tới Backend
Mở PowerShell hoặc trình duyệt và kiểm tra endpoint `/api/health` và `/api/payments/vnpay/ipn`:
```powershell
curl.exe -i https://random-name-here.trycloudflare.com/api/health
curl.exe -i "https://random-name-here.trycloudflare.com/api/payments/vnpay/ipn"
```
- `/api/health` trả về `HTTP 200` `{"status":"ok",...}`.
- `/api/payments/vnpay/ipn` (không có chữ ký) trả về `HTTP 200` `{"RspCode":"97","Message":"Invalid Signature"}` — chứng tỏ route **không bị Authentication, CSRF hay CORS chặn** và sẵn sàng nhận callback từ VNPay.

---

## 3. Phân Biệt Cấu Hình: Local Dev vs Sandbox + Tunnel vs Production

| Biến môi trường | Local Dev (Không Tunnel) | Sandbox + Public Tunnel (Nhận IPN thật) | Production |
|---|---|---|---|
| `APP_ENV` | `local` | `local` | `production` |
| `APP_URL` | `http://localhost:8000` | `https://<tunnel-host>` | `https://api.mobiex.vn` |
| `FRONTEND_URL` | `http://localhost:5173` | `http://localhost:5173` (hoặc tunnel frontend) | `https://mobiex.vn` |
| `VNP_TMN_CODE` | `CGXZLS0Z` hoặc mã sandbox riêng | **Mã Sandbox riêng** đã đăng ký IPN | Mã Live Production do VNPay cấp |
| `VNP_HASH_SECRET` | Secret tương ứng | Secret tương ứng | Secret Production |
| `VNP_URL` | `https://sandbox.vnpayment.vn/paymentv2/vpcpay.html` | `https://sandbox.vnpayment.vn/paymentv2/vpcpay.html` | `https://pay.vnpay.vn/vpcpay.html` |
| `VNP_TRANSACTION_API_URL` | `https://sandbox.vnpayment.vn/merchant_webapi/api/transaction` | `https://sandbox.vnpayment.vn/merchant_webapi/api/transaction` | `https://pay.vnpay.vn/merchant_webapi/api/transaction` |
| `VNP_RETURN_URL` | `http://localhost:5173/payment/vnpay/return` | `http://localhost:5173/payment/vnpay/return` | `https://mobiex.vn/payment/vnpay/return` |
| `VNP_IPN_URL` *(khai báo trên Portal VNPay)* | Không truy cập được từ ngoài | `https://<tunnel-host>/api/payments/vnpay/ipn` | `https://api.mobiex.vn/api/payments/vnpay/ipn` |
| `ZALOPAY_CALLBACK_URL` | `http://localhost:8000/api/payments/zalopay/callback` | `https://<tunnel-host>/api/payments/zalopay/callback` | `https://api.mobiex.vn/api/payments/zalopay/callback` |
| `MOMO_CALLBACK_URL` | `http://localhost:8000/api/payments/momo/callback` | `https://<tunnel-host>/api/payments/momo/callback` | `https://api.mobiex.vn/api/payments/momo/callback` |

Sau khi cập nhật `backend/.env`, chạy:
```powershell
cd backend
php artisan config:clear
```

---

## 4. Cơ Chế Bảo Vệ Khi IPN Đến Chậm Hoặc QueryDR Sandbox Gặp Lỗi

1. **Khi VNPay IPN đến đúng hạn hoặc đến trước Return URL:**
   `GET /api/payments/vnpay/ipn` kiểm tra HMAC-SHA512, `vnp_TmnCode`, `vnp_TxnRef`, `vnp_Amount`, `vnp_ResponseCode=00` và `vnp_TransactionStatus=00` → cập nhật `payments.status = success`, `orders.payment_status = paid`, `orders.status = confirmed` và gửi email xác nhận thanh toán.
2. **Khi Return URL đến trước IPN:**
   `GET /api/payments/vnpay/return` **không bao giờ** tự ý đánh dấu `paid`. Frontend gọi `POST /api/payments/orders/{orderId}/verify-status` để Backend thực hiện truy vấn **QueryDR** trực tiếp sang VNPay (`merchant_webapi/api/transaction`) và xác minh chữ ký phản hồi trước khi chốt `paid`.
3. **Khi chạy Local không có IPN VÀ VNPay Sandbox QueryDR trả về lỗi tạm thời (ví dụ `vnp_ResponseCode = 99`):**
   Hệ thống giữ trạng thái đơn an toàn ở `pending`, ghi log đầy đủ trong `backend/storage/logs/laravel.log`, và giao diện thông báo rõ **"Không thể xác minh kết quả tạm thời — đang giữ đơn và chờ IPN"**, tuyệt đối không giả mạo trạng thái "Thanh toán thành công".
