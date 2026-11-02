# MobieX-DATN — Backend Profile & Addresses API

Version: 1.4

Module: Profile & Addresses (C-02)

Authentication: Laravel Sanctum Bearer Token

Status: Profile, password change, address schema, address CRUD
and default address management implemented.

---

## 1. Overview

The Profile & Addresses module manages the authenticated
user's profile and personal address book.

The server always identifies the resource owner from
the authenticated Sanctum user.

Clients must never submit a user_id to select another user.

## 2. Authentication

All protected endpoints require:

    Authorization: Bearer <access_token>
    Accept: application/json
    Content-Type: application/json

The authenticated account must be active.

| Condition | HTTP |
|---|---:|
| Unauthenticated | 401 |
| Inactive account | 403 |
| Invalid request | 422 |
| Missing or non-owned resource | 404 |

## 3. Endpoint summary

### Profile

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/profile | Retrieve own profile |
| PATCH | /api/profile | Update own profile |
| PATCH | /api/profile/password | Change own password |

### Addresses

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/addresses | List own addresses |
| POST | /api/addresses | Create address |
| PATCH | /api/addresses/{id} | Update own address |
| DELETE | /api/addresses/{id} | Delete own address |
| PATCH | /api/addresses/{id}/default | Set default address |

## 4. Profile

### GET /api/profile

Returns the authenticated user's profile.

Success: 200 OK.

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

### PATCH /api/profile

Updates editable profile fields.

Example:

    {
      "name": "Updated Customer",
      "phone": "0987654321"
    }

Validation:

- name: optional, string, max 255.
- phone: optional, nullable, string, max 20.

At least one editable field must be supplied.

Email, password, is_active, roles and user_id are not
editable through this endpoint.

Unknown fields are rejected.

Success: 200 OK.

## 5. Change password

### PATCH /api/profile/password

Example:

    {
      "current_password": "CurrentPassword123",
      "password": "NewPassword12345",
      "password_confirmation": "NewPassword12345"
    }

Validation:

- current_password: required and must match the current
  password using the Sanctum guard.
- password: required, must satisfy PasswordPolicy and
  must be confirmed.
- password_confirmation: must match password.

PasswordPolicy:

- Minimum 15 characters.
- Maximum 64 characters.
- Maximum 72 UTF-8 bytes with the current bcrypt configuration.

The password is stored through the User model's hashed cast.

After a successful change:

- The current Bearer token remains valid.
- All other Sanctum tokens of the same user are revoked.
- Other users' tokens are unaffected.
- Password update and token revocation run in one transaction.

Success: 200 OK.

    {
      "message": "Password changed successfully."
    }

## 6. Address data model

Table: user_addresses

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | BIGINT | No | Auto increment |
| user_id | BIGINT | No | — |
| label | VARCHAR(50) | Yes | NULL |
| full_name | VARCHAR(255) | No | — |
| phone | VARCHAR(20) | No | — |
| city | VARCHAR(100) | No | — |
| district | VARCHAR(100) | Yes | NULL |
| ward | VARCHAR(100) | Yes | NULL |
| address_line | VARCHAR(255) | No | — |
| is_default | BOOLEAN | No | false |
| created_at | TIMESTAMP | Yes | — |
| updated_at | TIMESTAMP | Yes | — |

Relationships:

- User has many addresses.
- Address belongs to one user.
- user_id references users.id.
- Deleting a user cascades to their addresses.

The UserAddress model does not allow mass assignment
of user_id.

## 7. List addresses

### GET /api/addresses

Returns only addresses belonging to the authenticated user.

Pagination:

- Default page size: 15.
- Supports the page query parameter.
- Ordered by descending address ID.

Example:

    GET /api/addresses?page=2

Success: 200 OK.

The response contains:

- data: array of AddressResource.
- message: Addresses retrieved successfully.
- links: Laravel pagination links.
- meta: Laravel pagination metadata.

Each address contains:

- id
- label
- full_name
- phone
- city
- district
- ward
- address_line
- is_default
- created_at
- updated_at

user_id is not exposed.

## 8. Create address

### POST /api/addresses

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

Validation:

| Field | Rules |
|---|---|
| label | Optional, nullable, string, max 50 |
| full_name | Required, string, max 255 |
| phone | Required, string, max 20 |
| city | Required, string, max 100 |
| district | Optional, nullable, string, max 100 |
| ward | Optional, nullable, string, max 100 |
| address_line | Required, string, max 255 |

The following fields are not accepted:

- user_id
- is_default
- Any unsupported field

The owner is resolved from the authenticated user relationship.

A newly created address has is_default = false.

Success: 201 Created.

## 9. Update address

### PATCH /api/addresses/{id}

Example:

    {
      "full_name": "Updated Customer",
      "city": "Ho Chi Minh City",
      "district": null
    }

Only supplied fields are updated.

The same type and maximum-length constraints as address
creation apply.

Required when supplied:

- full_name
- phone
- city
- address_line

Nullable:

- label
- district
- ward

At least one supported field must be supplied.

The following fields are rejected:

- user_id
- is_default
- Any unsupported field

Success: 200 OK.

## 10. Delete address

### DELETE /api/addresses/{id}

An address can only be deleted by its owner.

Success: 200 OK.

    {
      "message": "Address deleted successfully."
    }

Deleting a default address does not automatically promote
another address.

The user must explicitly choose a new default address.

## 11. Set default address

### PATCH /api/addresses/{id}/default

Sets the selected address as the authenticated user's
default address.

No request body is required.

### Success: 200 OK

    {
      "message": "Default address updated successfully.",
      "data": {
        "address": {
          "id": 2,
          "label": "Work",
          "full_name": "Test Customer",
          "phone": "0912345678",
          "city": "Ha Noi",
          "district": "Cau Giay",
          "ward": "Dich Vong",
          "address_line": "456 Example Street",
          "is_default": true,
          "created_at": "2026-10-31T10:00:00.000000Z",
          "updated_at": "2026-10-31T10:05:00.000000Z"
        }
      }
    }

### Business rules

1. Every user can have at most one default address.
2. When an address becomes default, all other addresses
   belonging to the same user are set to false.
3. Addresses belonging to other users are unaffected.
4. Setting the current default address again is idempotent.
5. An address belonging to another user returns 404.
6. An unknown address ID returns 404.
7. Setting a default address is performed in a database transaction.

### Concurrency

The endpoint locks the authenticated user's database row
using SELECT FOR UPDATE (lockForUpdate).

Concurrent default-address changes for the same user
are therefore serialized on MySQL/InnoDB.

The operation:

- Locks the user row.
- Finds and locks the selected owned address.
- Clears existing default flags.
- Sets the selected address as default.
- Commits all changes atomically.

### D-14: Default address deletion policy

Applied proposed default from Master Plan v1.2:

When a default address is deleted:

- The selected address is deleted.
- No other address is automatically promoted.
- The user has no default address until explicitly selecting one.

This avoids silently changing the user's delivery destination.

## 12. Ownership and security

Every address operation is scoped to the authenticated user's
addresses relationship.

The implementation never uses a client-supplied user_id
to identify ownership.

Foreign-owned addresses return 404 rather than 403.

The API Resource does not expose user_id.

The client cannot change is_default using regular create
or update endpoints.

Only the dedicated default-address endpoint can change it.

## 13. Error handling

| Scenario | HTTP |
|---|---:|
| Guest | 401 |
| Inactive user | 403 |
| Invalid fields | 422 |
| Unsupported fields | 422 |
| Empty PATCH | 422 |
| Missing address | 404 |
| Address owned by another user | 404 |

Validation response follows Laravel JSON conventions:

    {
      "message": "The given data was invalid.",
      "errors": {
        "full_name": [
          "The full name field is required."
        ]
      }
    }