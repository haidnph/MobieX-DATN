# MobieX-DATN — Backend Auth API

Version: 1.0  
Module: Authentication (C-01)  
Framework: Laravel REST API  
Authentication: Laravel Sanctum Bearer Token

## 1. Overview

Tài liệu mô tả các endpoint xác thực tài khoản của Laravel backend trong thư mục `backend/`.

Các chức năng bao gồm:

- Register
- Login
- Logout
- Get current user
- Request password reset
- Reset password

### Base URL

Local development mặc định theo `backend/.env.example`:

```text
http://127.0.0.1:8001/api
```

Thay đổi host hoặc port theo cấu hình `APP_URL` của môi trường đang chạy.

### Request headers

Các request gửi JSON nên sử dụng:

```http
Accept: application/json
Content-Type: application/json
```

Các endpoint yêu cầu xác thực phải gửi thêm:

```http
Authorization: Bearer <access_token>
```

Token được cấp khi đăng ký hoặc đăng nhập. Không gửi token trong query string và không ghi token vào log.

## 2. Endpoint summary

| Method | Endpoint | Authentication | Success |
|---|---|---|---|
| POST | `/api/auth/register` | Not required | 201 Created |
| POST | `/api/auth/login` | Not required | 200 OK |
| POST | `/api/auth/logout` | Bearer token | 200 OK |
| GET | `/api/me` | Bearer token | 200 OK |
| POST | `/api/auth/forgot-password` | Not required | 200 OK |
| POST | `/api/auth/reset-password` | Not required | 200 OK |

## 3. Register

### Request

```http
POST /api/auth/register
```

```json
{
  "name": "Test Customer",
  "email": "customer@example.com",
  "phone": "0912345678",
  "password": "StrongPass123",
  "password_confirmation": "StrongPass123"
}
```

`phone` is optional.

### Validation

- `name`: required, string, maximum 255 characters.
- `email`: required, valid email, maximum 255 characters, unique.
- `phone`: optional, string, maximum 20 characters.
- `password`: required, minimum 8 characters and must match `password_confirmation`.

### Success — 201 Created

```json
{
  "message": "Registration successful.",
  "data": {
    "user": {
      "id": 1,
      "name": "Test Customer",
      "email": "customer@example.com",
      "phone": "0912345678"
    },
    "token": "<access_token>",
    "token_type": "Bearer"
  }
}
```

The returned token can be used to access protected endpoints.

### Errors

- `422 Unprocessable Entity`: validation failed, including duplicate email.
- Validation errors are returned in Laravel's JSON validation response format.

## 4. Login

### Request

```http
POST /api/auth/login
```

```json
{
  "email": "customer@example.com",
  "password": "StrongPass123"
}
```

### Success — 200 OK

```json
{
  "message": "Login successful.",
  "data": {
    "user": {
      "id": 1,
      "name": "Test Customer",
      "email": "customer@example.com",
      "phone": "0912345678"
    },
    "token": "<access_token>",
    "token_type": "Bearer"
  }
}
```

### Errors

- `401 Unauthorized`: invalid credentials or an inactive account.
- `422 Unprocessable Entity`: missing or invalid email/password input.

The current implementation uses the same authentication error message for incorrect credentials and inactive accounts.

## 5. Logout

### Request

```http
POST /api/auth/logout
Authorization: Bearer <access_token>
```

No request body is required.

### Success — 200 OK

```json
{
  "message": "Logout successful."
}
```

The current Bearer access token is revoked. Other tokens belonging to the same user are not revoked by this endpoint.

### Errors

- `401 Unauthorized`: missing or invalid authentication, or a request without a revocable Sanctum Bearer token.

After logout, the revoked token must no longer be used for authenticated requests.

## 6. Get current user

### Request

```http
GET /api/me
Authorization: Bearer <access_token>
```

### Success — 200 OK

```json
{
  "message": "Current user retrieved successfully.",
  "data": {
    "user": {
      "id": 1,
      "name": "Test Customer",
      "email": "customer@example.com",
      "phone": "0912345678"
    }
  }
}
```

The user is resolved from the authenticated token. The endpoint does not use a client-provided `user_id` to select the account.

Only explicitly approved user fields are returned. Passwords and access tokens are not included.

### Errors

- `401 Unauthorized`: authentication is missing or invalid.

## 7. Forgot password

### Request

```http
POST /api/auth/forgot-password
```

```json
{
  "email": "customer@example.com"
}
```

### Response — 200 OK

```json
{
  "message": "If the email address exists, a password reset link will be sent."
}
```

The API deliberately uses a generic response for registered and unregistered email addresses to reduce account enumeration.

When an account exists and a reset notification is sent, the reset link points to the frontend route:

```text
<FRONTEND_URL>/reset-password?token=<reset_token>&email=<email>
```

The frontend must provide a reset-password screen and submit the email and token back to the backend.

### Validation error

`422 Unprocessable Entity` is returned when the email input fails validation.

### Email configuration

For local development, `backend/.env.example` uses:

```dotenv
MAIL_MAILER=log
FRONTEND_URL=http://localhost:5173
```

With `MAIL_MAILER=log`, email content is written to the Laravel log rather than sent to a real mailbox.

For production, configure a real mail transport and the correct frontend URL.

The Password Broker configuration currently sets:

- Token expiry: 60 minutes.
- Token creation throttle: 60 seconds.

Repeated requests inside the throttle window might not generate a new notification even though the API returns the same generic response.

## 8. Reset password

### Request

```http
POST /api/auth/reset-password
```

```json
{
  "email": "customer@example.com",
  "token": "<reset_token>",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

The `token` is the reset token received through the reset link. It is not the Sanctum Bearer access token.

### Validation

- `email`: required, valid email, maximum 255 characters.
- `token`: required string.
- `password`: required, minimum 8 characters, and must match `password_confirmation`.

### Success — 200 OK

```json
{
  "message": "Password reset successful."
}
```

After a successful reset:

- The new password is stored as a hash.
- The reset token is consumed and cannot be reused.
- Existing Sanctum API tokens for that user are revoked.
- The user must log in again with the new password.

### Errors

Invalid or expired reset token:

HTTP `422 Unprocessable Entity`

```json
{
  "message": "The password reset token is invalid or has expired."
}
```

Invalid request fields also return HTTP `422` with Laravel validation errors.

## 9. Common HTTP status codes

| Status | Meaning |
|---|---|
| 200 | Request succeeded |
| 201 | Account created |
| 401 | Authentication failed or is required |
| 422 | Validation failed or reset token is invalid/expired |
| 429 | Too many requests; the applicable rate limit has been exceeded |

Validation responses follow Laravel's JSON validation format. Clients should handle field errors rather than relying only on the message text.

## 10. Example authenticated request

Use the token returned by login or registration:

```bash
curl -X GET "http://127.0.0.1:8001/api/me" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <access_token>"
```

Logout:

```bash
curl -X POST "http://127.0.0.1:8001/api/auth/logout" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <access_token>"
```

## 11. Local verification

Run from the `backend/` directory:

```bash
php artisan route:list --path=api/auth -vv
php artisan route:list --path=api/me -vv
php artisan test
```

The Auth implementation has been verified locally with 26 passing tests and 144 assertions at the time this document was prepared.

Run the test suite again after changing the implementation or API contract.

## 12. Rate limiting

The public authentication endpoints have independent rate limits
for IP addresses and, where applicable, normalized email addresses.

| Endpoint | Limit |
|---|---|
| `POST /api/auth/register` | 5 requests per IP per hour |
| `POST /api/auth/login` | 5 failed attempts per normalized email per 5 minutes, plus 30 requests per IP per minute |
| `POST /api/auth/forgot-password` | 3 requests per email per 15 minutes, plus 10 requests per IP per hour |
| `POST /api/auth/reset-password` | 5 requests per email per 15 minutes, plus 10 requests per IP per 15 minutes |

Email-based login throttling counts failed authentication attempts.
A successful login clears that email's failed-attempt counter.
The separate IP-based login limit still applies.

Email values are trimmed and normalized to lowercase before their
rate-limit keys are generated. Email and IP identifiers are hashed
using HMAC-SHA256 with the application key. Raw email addresses,
passwords, and reset tokens are not used as rate-limit keys.

When a limit is exceeded, the endpoint returns HTTP `429 Too Many
Requests`. Clients should respect the `Retry-After` response header
when it is present.

These are initial hardening thresholds. Review them with the frontend
team before production deployment.
