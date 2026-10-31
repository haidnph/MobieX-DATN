# MobieX-DATN — Backend Profile & Addresses API

Version: 1.0

Module: Profile & Addresses (C-02)

Framework: Laravel REST API

Authentication: Laravel Sanctum Bearer Token

Status: In progress — Profile endpoints

---

## 1. Overview

This document describes the Profile & Addresses API.

The module uses the authenticated Sanctum user as the
resource owner.

Clients must never select the profile owner by submitting
a user_id.

D-13:

- GET /api/me provides the authenticated identity
  and RBAC information.
- GET /api/profile provides editable profile details.

Profile fields currently supported:

- name
- email (read-only)
- phone

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

## 3. Endpoint summary

| Method | Endpoint | Description | Success |
|---|---|---|---:|
| GET | /api/profile | Retrieve own profile | 200 |
| PATCH | /api/profile | Update own profile | 200 |

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

Only explicitly approved fields are returned.

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

At least one editable field must be supplied.

### Validation

| Field | Rules |
|---|---|
| name | Optional, string, required when supplied, max 255 |
| phone | Optional, nullable, string, max 20 |

Only name and phone can be updated through this endpoint.

The following are not editable here:

- email
- password
- is_active
- roles
- user_id

Unknown or unsupported fields are rejected.

Sending phone as null clears the existing phone number.

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

### Errors

- 401: unauthenticated.
- 403: account is inactive.
- 422: invalid or unsupported fields.

## 6. Ownership and security

The server obtains the user from the authenticated
Sanctum request.

No client-provided user_id is used to select the
profile being read or modified.

Profile updates use validated fields only.

## 7. Address endpoints

Address endpoints will be documented alongside
their implementation slices.

No address endpoint is introduced by the current
Profile API slice.