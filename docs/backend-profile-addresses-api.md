# MobieX-DATN — Backend Profile & Addresses API

Version: 1.3

Module: Profile & Addresses (C-02)

Framework: Laravel REST API

Authentication: Laravel Sanctum Bearer Token

Status: Profile, password change, address schema and address CRUD implemented;
default address endpoint pending.

---

## 1. Overview

This document describes the Profile & Addresses API.

The module uses the authenticated Sanctum user as the
resource owner.

Clients must never select the profile owner by submitting
a `user_id`.

D-13:

- `GET /api/me` provides the authenticated identity
  and RBAC information.
- `GET /api/profile` provides editable profile details.

## 2. Authentication

All endpoints require:

    Authorization: Bearer <access_token>
    Accept: application/json
    Content-Type: application/json

The authenticated account must be active.

Expected HTTP status:

| Condition | Status |
|---|---:|
| Missing or invalid authentication | 401 |
| Inactive account | 403 |
| Invalid request data | 422 |
| Resource not found or not owned | 404 |

## 3. Endpoint summary

### Profile

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/profile` | Retrieve own profile |
| PATCH | `/api/profile` | Update own profile |
| PATCH | `/api/profile/password` | Change own password |

### Addresses

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/addresses` | List own addresses |
| POST | `/api/addresses` | Create an address |
| PATCH | `/api/addresses/{id}` | Update own address |
| DELETE | `/api/addresses/{id}` | Delete own address |
| PATCH | `/api/addresses/{id}/default` | Set default address (pending) |

## 4. Get profile

### Request

    GET /api/profile

### Success — 200 OK

    {
      "message": "Profile retrieved successfully.",
      "data": {
        "user": {
          "id": 1,
          "name": "Test Customer",
          "email": "customer@example.com",
          "phone": "0912345678"
        }
      }
    }

Only approved profile fields are returned.

Passwords, remember tokens, account activation flags,
roles and access tokens are not included.

## 5. Update profile

### Request

    PATCH /api/profile

Example:

    {
      "name": "Updated Customer",
      "phone": "0987654321"
    }

### Validation

| Field | Rules |
|---|---|
| `name` | Optional, string, required when supplied, max 255 |
| `phone` | Optional, nullable, string, max 20 |

At least one editable field must be supplied.

Only `name` and `phone` can be updated.

Unknown or unsupported fields are rejected.

Sending `phone` as `null` clears the existing phone number.

### Success — 200 OK

    {
      "message": "Profile updated successfully.",
      "data": {
        "user": {
          "id": 1,
          "name": "Updated Customer",
          "email": "customer@example.com",
          "phone": "0987654321"
        }
      }
    }

## 6. Change password

### Request

    PATCH /api/profile/password

Example:

    {
      "current_password": "CurrentPassword123",
      "password": "NewPassword12345",
      "password_confirmation": "NewPassword12345"
    }

### Validation

- `current_password`: required and must match the
  authenticated user's current password using Sanctum.
- `password`: required, must satisfy PasswordPolicy
  and must be confirmed.
- `password_confirmation`: must match `password`.

The shared PasswordPolicy requires:

- Minimum 15 characters.
- Maximum 64 characters.
- Maximum 72 UTF-8 bytes with the current bcrypt
  hashing configuration.

The password is stored as a hash through the User model's
`hashed` cast.

### Success — 200 OK

    {
      "message": "Password changed successfully."
    }

### Token revocation

After a successful password change:

- The current Bearer token remains valid.
- All other Sanctum personal access tokens belonging to
  the same user are revoked.
- Tokens belonging to other users are unaffected.
- Password update and token revocation run in one
  database transaction.

## 7. Address data model

### Table

    user_addresses

### Columns

| Column | Type | Nullable | Default |
|---|---|---|---|
| `id` | BIGINT | No | Auto increment |
| `user_id` | BIGINT | No | — |
| `label` | VARCHAR(50) | Yes | NULL |
| `full_name` | VARCHAR(255) | No | — |
| `phone` | VARCHAR(20) | No | — |
| `city` | VARCHAR(100) | No | — |
| `district` | VARCHAR(100) | Yes | NULL |
| `ward` | VARCHAR(100) | Yes | NULL |
| `address_line` | VARCHAR(255) | No | — |
| `is_default` | BOOLEAN | No | false |
| `created_at` | TIMESTAMP | Yes | — |
| `updated_at` | TIMESTAMP | Yes | — |

### Relationships

- User has many addresses.
- Address belongs to one user.
- `user_id` references `users.id`.
- Deleting a user cascades to their addresses.

### Default address

The `is_default` field defaults to `false`.

The address CRUD endpoints do not allow clients to
write `is_default`.

Default address management will be implemented
separately with transactional logic.

## 8. List addresses

### Request

    GET /api/addresses

The endpoint returns only addresses belonging to
the authenticated user.

Pagination uses Laravel's default pagination with
15 records per page.

Optional query parameter:

    GET /api/addresses?page=2

### Success — 200 OK

    {
      "data": [
        {
          "id": 1,
          "label": "Home",
          "full_name": "Test Customer",
          "phone": "0912345678",
          "city": "Ha Noi",
          "district": "Cau Giay",
          "ward": "Dich Vong",
          "address_line": "123 Example Street",
          "is_default": false,
          "created_at": "2026-10-31T10:00:00.000000Z",
          "updated_at": "2026-10-31T10:00:00.000000Z"
        }
      ],
      "message": "Addresses retrieved successfully.",
      "links": {},
      "meta": {}
    }

The `links` and `meta` objects are generated by
Laravel pagination.

## 9. Create address

### Request

    POST /api/addresses

Example:

    {
      "label": "Home",
      "full_name": "Test Customer",
      "phone": "0912345678",
      "city": "Ha Noi",
      "district": "Cau Giay",
      "ward": "Dich Vong",
      "address_line": "123 Example Street"
    }

### Validation

| Field | Rules |
|---|---|
| `label` | Optional, nullable, string, max 50 |
| `full_name` | Required, string, max 255 |
| `phone` | Required, string, max 20 |
| `city` | Required, string, max 100 |
| `district` | Optional, nullable, string, max 100 |
| `ward` | Optional, nullable, string, max 100 |
| `address_line` | Required, string, max 255 |

The following fields are not accepted:

- `user_id`
- `is_default`
- Any unsupported field

Ownership is assigned from the authenticated user's
relationship, never from request input.

New addresses default to `is_default = false`.

### Success — 201 Created

    {
      "message": "Address created successfully.",
      "data": {
        "address": {
          "id": 1,
          "label": "Home",
          "full_name": "Test Customer",
          "phone": "0912345678",
          "city": "Ha Noi",
          "district": "Cau Giay",
          "ward": "Dich Vong",
          "address_line": "123 Example Street",
          "is_default": false,
          "created_at": "2026-10-31T10:00:00.000000Z",
          "updated_at": "2026-10-31T10:00:00.000000Z"
        }
      }
    }

## 10. Update address

### Request

    PATCH /api/addresses/{id}

Example:

    {
      "full_name": "Updated Customer",
      "city": "Ho Chi Minh City",
      "district": null,
      "ward": null
    }

Only supplied fields are updated.

### Validation

The same field types and maximum lengths as
address creation apply.

When supplied:

- `full_name`, `phone`, `city`, `address_line`
  cannot be null or empty.
- `label`, `district`, `ward` can be null.
- At least one supported field must be supplied.

The following are rejected:

- `user_id`
- `is_default`
- Unsupported fields

### Success — 200 OK

    {
      "message": "Address updated successfully.",
      "data": {
        "address": {
          "id": 1,
          "label": "Home",
          "full_name": "Updated Customer",
          "phone": "0912345678",
          "city": "Ho Chi Minh City",
          "district": null,
          "ward": null,
          "address_line": "123 Example Street",
          "is_default": false,
          "created_at": "2026-10-31T10:00:00.000000Z",
          "updated_at": "2026-10-31T10:05:00.000000Z"
        }
      }
    }

## 11. Delete address

### Request

    DELETE /api/addresses/{id}

An address can only be deleted by its owner.

### Success — 200 OK

    {
      "message": "Address deleted successfully."
    }

The current CRUD implementation deletes the requested
owned address without automatically promoting another
address to default.

The special rule for deleting a default address remains
subject to D-14 and the dedicated default-address slice.

## 12. Ownership and security

Every address lookup for update and delete is scoped
through the authenticated user's `addresses()` relationship.

An address belonging to another user returns 404.

The API does not reveal whether the address exists
under another account.

The `user_id` value is never taken from client input.

The API Resource does not expose `user_id`.

## 13. Error handling

| Scenario | HTTP status |
|---|---:|
| Guest accesses address endpoints | 401 |
| Inactive user accesses address endpoints | 403 |
| Invalid or missing required fields | 422 |
| Unsupported fields | 422 |
| Empty PATCH request | 422 |
| Address does not exist | 404 |
| Address belongs to another user | 404 |

Validation uses Laravel's standard JSON response:

    {
      "message": "The given data was invalid.",
      "errors": {
        "full_name": [
          "The full name field is required."
        ]
      }
    }

## 14. Default address

Pending implementation:

    PATCH /api/addresses/{id}/default

The default-address feature will:

- Set an address as default.
- Ensure at most one default address per user.
- Use a database transaction when changing the default.
- Test default-address deletion according to D-14.

The current CRUD slice does not implement this endpoint.