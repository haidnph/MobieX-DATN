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
| GET | `/api/me` | Bearer token + active account | 200 OK |
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
  "password": "StrongPass12345",
  "password_confirmation": "StrongPass12345"
}
```

`phone` is optional.

### Validation

- `name`: required, string, maximum 255 characters.
- `email`: required, valid email, maximum 255 characters, unique.
- `phone`: optional, string, maximum 20 characters.
- `password`: required, minimum 15 characters, maximum 64 characters, no more than 72 UTF-8 bytes, and must match `password_confirmation`.

The password policy is described in section 15.

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
    "token_type": "Bearer",
    "expires_at": "2026-11-27T10:00:00.000000Z"
  }
}
```

The returned token can be used to access protected endpoints.

`expires_at` is the token's expiration timestamp in ISO 8601 UTC format. The example timestamp is illustrative; the actual value is calculated when the token is issued.

Every successfully registered account is assigned the seeded `customer` role. This role has no administrative permissions by default.

### Errors

- `422 Unprocessable Entity`: validation failed, including duplicate email or password policy violations.
- `429 Too Many Requests`: registration IP rate limit exceeded.

Validation errors are returned in Laravel's JSON validation response format.

## 4. Login

### Request

```http
POST /api/auth/login
```

```json
{
  "email": "customer@example.com",
  "password": "StrongPass12345"
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
    "token_type": "Bearer",
    "expires_at": "2026-11-27T10:00:00.000000Z"
  }
}
```

### Errors

- `401 Unauthorized`: invalid credentials or an inactive account.
- `422 Unprocessable Entity`: missing or invalid email/password input.
- `429 Too Many Requests`: login rate limit exceeded.

The login endpoint verifies the password against the stored hash. The new minimum password policy applies when setting or changing a password, not when checking an existing credential at login.

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

Logout deliberately does not require the account to be active. An inactive user can still revoke their current token.

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
      "phone": "0912345678",
      "roles": [
        {
          "slug": "customer",
          "name": "Customer"
        }
      ]
    }
  }
}
```

The user is resolved from the authenticated token. The endpoint does not use a client-provided `user_id` to select the account.

Only explicitly approved user fields are returned. `roles` contains every role assigned to the authenticated user, represented by its stable slug and display name. Passwords and access tokens are not included.

### Errors

- `401 Unauthorized`: authentication is missing, invalid, revoked, or expired.
- `403 Forbidden`: the authenticated user's account is inactive.

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
  "password": "NewPassword12345",
  "password_confirmation": "NewPassword12345"
}
```

The `token` is the reset token received through the reset link. It is not the Sanctum Bearer access token.

### Validation

- `email`: required, valid email, maximum 255 characters.
- `token`: required string.
- `password`: required, minimum 15 characters, maximum 64 characters, no more than 72 UTF-8 bytes, and must match `password_confirmation`.

Register and reset password use the same password policy.

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

Invalid request fields and password policy violations also return HTTP `422` with Laravel validation errors.

## 9. Common HTTP status codes

| Status | Meaning |
|---|---|
| 200 | Request succeeded |
| 201 | Account created |
| 401 | Authentication failed or is required |
| 403 | Authenticated user is not allowed to access the endpoint, including an inactive account |
| 422 | Validation failed, password policy rejected, or reset token is invalid/expired |
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
php artisan schedule:list
php artisan test
vendor/bin/pint --test
composer audit
```

Run the test suite after changing the implementation or API contract. Use the latest test output as the source of truth for the number of passing tests and assertions.

Test suites may use SQLite in-memory through PHPUnit configuration. Verify MySQL migrations separately in the development database before integration.

## 12. Rate limiting

The public authentication endpoints have independent rate limits for IP addresses and, where applicable, normalized email addresses.

| Endpoint | Limit |
|---|---|
| `POST /api/auth/register` | 5 requests per IP per hour |
| `POST /api/auth/login` | 5 failed attempts per normalized email per 5 minutes, plus 30 requests per IP per minute |
| `POST /api/auth/forgot-password` | 3 requests per email per 15 minutes, plus 10 requests per IP per hour |
| `POST /api/auth/reset-password` | 5 requests per email per 15 minutes, plus 10 requests per IP per 15 minutes |

Email-based login throttling counts failed authentication attempts. A successful login clears that email's failed-attempt counter. The separate IP-based login limit still applies.

Email values are trimmed and normalized to lowercase before their rate-limit keys are generated. Email and IP identifiers are hashed using HMAC-SHA256 with the application key. Raw email addresses, passwords, and reset tokens are not used as rate-limit keys.

When a limit is exceeded, the endpoint returns HTTP `429 Too Many Requests`. Clients should respect the `Retry-After` response header when it is present.

These are initial hardening thresholds. Review them with the frontend team before production deployment.

## 13. Bearer token expiration and pruning

### Token lifetime

Bearer tokens issued by registration and login expire 30 days after issuance by default.

The lifetime is configured by `AUTH_TOKEN_EXPIRATION_DAYS` in the environment. The default value is `30`.

The registration and login responses include `data.expires_at`, an ISO 8601 UTC timestamp that identifies when the issued token expires.

Clients should treat the token as unusable at or after this time. Protected endpoints return `401 Unauthorized` when a token is expired, invalid, or revoked.

The current implementation does not provide a refresh-token flow. When a token expires, the user must log in again to obtain a new token.

### Expired-token cleanup

Sanctum's pruning command is scheduled to run daily:

```bash
php artisan sanctum:prune-expired --hours=24
```

The command removes tokens that have been expired for at least 24 hours. Expired tokens are rejected by authentication middleware even before their database records are removed.

### Verify the scheduler

Run from the `backend/` directory:

```bash
php artisan schedule:list
```

For local development, the scheduler can be run in the foreground:

```bash
php artisan schedule:work
```

In production, configure the server's scheduler to invoke Laravel every minute:

```cron
* * * * * cd /path/to/MobieX-DATN/backend && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/MobieX-DATN/backend` with the actual absolute path of the deployed backend directory.

## 14. Active-account protection

The `active` middleware checks the authenticated user's `is_active` state before allowing access to endpoints that require an active account.

For the current Auth API:

- `GET /api/me` requires both `auth:sanctum` and `active`.
- An active user with a valid token can access `/api/me`.
- An inactive user with an otherwise valid token receives `403 Forbidden` from `/api/me`.
- Login rejects inactive accounts with `401 Unauthorized`, using the same authentication error response as invalid credentials.
- `POST /api/auth/logout` intentionally does not use the `active` middleware. An inactive user can still revoke their current token.

Future business endpoints that require an active account should use the same middleware convention. Logout must remain accessible to authenticated inactive users so they can revoke their own tokens.

## 15. Password policy

Register and reset password share a single validation rule.

| Requirement | Policy |
|---|---|
| Minimum length | 15 characters |
| Maximum length | 64 characters |
| Maximum encoded length | 72 UTF-8 bytes with the current bcrypt hasher |
| Confirmation | Required and must match |
| Character composition | No mandatory uppercase, lowercase, digit, or symbol rules |
| Password storage | Hashed through the `User` model's hashed cast |

The 15-character minimum is selected because MFA is not currently implemented. Long passphrases and Unicode characters are permitted, subject to the byte limit imposed by the current password hashing configuration.

The application currently relies on Laravel's default bcrypt hashing driver. Bcrypt has a 72-byte input limit in common implementations. Therefore, passwords exceeding 72 UTF-8 bytes are rejected explicitly instead of being truncated.

A 64-character ASCII passphrase is accepted. Some multibyte Unicode passwords shorter than 64 characters may exceed 72 bytes and will be rejected under this policy. This limitation must be revisited if the project adopts a hashing implementation that safely supports longer inputs.

No password composition requirements are imposed by this rule. The policy is applied identically to account registration and password reset.

Password policy changes should be coordinated with the frontend so that registration and reset forms communicate the same minimum, maximum, confirmation, and byte-limit requirements.

Password changes do not automatically alter the Bearer token expiration policy. Successful password reset continues to revoke all existing Sanctum API tokens, requiring the user to sign in again.
