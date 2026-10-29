# MobieX-DATN — Backend RBAC & User Management API

Version: 0.1
Module: RBAC & User Management (A-11)
Framework: Laravel REST API
Authentication: Laravel Sanctum Bearer Token
Status: Draft — requires team approval

## 1. Purpose and scope

This document defines the proposed API contract and permission matrix for
the RBAC & User Management module.

It is the contract-first deliverable for implementation phase 3 in
section 7.4 of the Backend Master Plan.

The following decisions remain subject to confirmation:

- D-05: role matrix.
- D-06: permissions by module and default customer permissions.
- D-12: admin user-management API contract.
- D-16: account-status administration policy.

The existing Auth implementation already provides the `users.is_active`
field and the `active` middleware. This module must reuse them instead
of creating a duplicate account-status field or migration.

## 2. Authentication and authorization

All endpoints in this document require:

    Authorization: Bearer <access_token>
    Accept: application/json
    Content-Type: application/json

Every admin endpoint must require:

1. Authentication via `auth:sanctum`.
2. An active account via `active`.
3. The permission specified in the endpoint contract.

Expected behavior:

| Condition | HTTP status |
|---|---:|
| Missing or invalid authentication | 401 |
| Inactive account | 403 |
| Authenticated but missing required permission | 403 |
| User or role does not exist | 404 |
| Invalid request data | 422 |
| Operation would leave the system without an active admin | 409 |

Error responses use JSON with a `message` field. Validation errors use
Laravel's JSON validation response format.

The `active` middleware must be applied after authentication.

## 3. Proposed role and permission matrix

### 3.1. System roles

| Slug | Display name | Purpose |
|---|---|---|
| `admin` | Admin | Manage users and all modules in this scope |
| `staff` | Staff | Manage catalog modules if approved by the team |
| `customer` | Customer | Standard customer account without admin permissions |

Role slugs are stable identifiers. Display names may be localized
without changing the slugs.

These roles are seeded by the backend. The API does not expose CRUD
operations for creating, renaming, or deleting system roles.

### 3.2. Permissions

| Permission slug | Meaning |
|---|---|
| `users.view` | Read the admin user list, user details, roles, and permissions |
| `users.manage` | Assign/remove user roles and change account activation |
| `categories.manage` | Create, update, and manage categories |
| `brands.manage` | Create, update, and manage brands |
| `attributes.manage` | Create, update, and manage attributes |

The three catalog permissions are defined here because the RBAC module
owns their initial permission definitions and seed data. Their actual
routes will be protected by the respective catalog feature branches.

### 3.3. Proposed role assignment

| Permission | Admin | Staff | Customer |
|---|---:|---:|---:|
| `users.view` | Yes | No | No |
| `users.manage` | Yes | No | No |
| `categories.manage` | Yes | Yes* | No |
| `brands.manage` | Yes | Yes* | No |
| `attributes.manage` | Yes | Yes* | No |

*Staff catalog permissions require team approval before the seeder is
implemented.*

The `customer` role receives no administrative permissions by default.

When a user has multiple roles, the effective permission set is the
union of the permissions assigned to those roles.

## 4. Endpoint summary

All paths below include the `/api` prefix.

| Method | Endpoint | Permission | Success |
|---|---|---|---:|
| GET | `/api/admin/users` | `users.view` | 200 |
| GET | `/api/admin/users/{user}` | `users.view` | 200 |
| GET | `/api/admin/roles` | `users.view` | 200 |
| GET | `/api/admin/permissions` | `users.view` | 200 |
| POST | `/api/admin/users/{user}/roles` | `users.manage` | 200 |
| DELETE | `/api/admin/users/{user}/roles/{roleSlug}` | `users.manage` | 200 |
| PATCH | `/api/admin/users/{user}/status` | `users.manage` | 200 |

This scope intentionally excludes:

- Creating users through the admin API.
- Deleting users.
- CRUD operations for roles and permissions.
- Editing role-to-permission assignments through the API.

Registration remains part of Auth. System roles and their permission
assignments are maintained through seeders and version-controlled code.

## 5. Admin user list

### Request

    GET /api/admin/users?page=1

Required permission: `users.view`.

The response uses Laravel pagination with a default page size of 15.
The implementation must not expose passwords, remember tokens, access
tokens, or other authentication secrets.

### Proposed success response — 200 OK

    {
      "message": "Admin users retrieved successfully.",
      "data": [
        {
          "id": 1,
          "name": "Example User",
          "email": "user@example.com",
          "phone": null,
          "is_active": true,
          "roles": [
            {
              "slug": "customer",
              "name": "Customer"
            }
          ]
        }
      ],
      "links": {},
      "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1
      }
    }

The `links` and `meta` fields follow the actual Laravel paginator
structure. The values shown above are illustrative.

The `data` collection must contain only explicitly approved user fields.

## 6. Admin user detail

### Request

    GET /api/admin/users/{user}

Required permission: `users.view`.

### Proposed success response — 200 OK

    {
      "message": "Admin user retrieved successfully.",
      "data": {
        "user": {
          "id": 1,
          "name": "Example User",
          "email": "user@example.com",
          "phone": null,
          "is_active": true,
          "roles": [
            {
              "slug": "customer",
              "name": "Customer"
            }
          ]
        }
      }
    }

A nonexistent user returns 404.

Sensitive authentication data must never be included in the response.

## 7. List roles

### Request

    GET /api/admin/roles

Required permission: `users.view`.

### Proposed success response — 200 OK

    {
      "message": "Roles retrieved successfully.",
      "data": {
        "roles": [
          {
            "slug": "admin",
            "name": "Admin",
            "permissions": [
              "users.view",
              "users.manage",
              "categories.manage",
              "brands.manage",
              "attributes.manage"
            ]
          },
          {
            "slug": "customer",
            "name": "Customer",
            "permissions": []
          }
        ]
      }
    }

The response includes the effective permission slugs assigned to each
role. It is read-only.

## 8. List permissions

### Request

    GET /api/admin/permissions

Required permission: `users.view`.

### Proposed success response — 200 OK

    {
      "message": "Permissions retrieved successfully.",
      "data": {
        "permissions": [
          {
            "slug": "users.view",
            "name": "View users"
          },
          {
            "slug": "users.manage",
            "name": "Manage users"
          },
          {
            "slug": "categories.manage",
            "name": "Manage categories"
          },
          {
            "slug": "brands.manage",
            "name": "Manage brands"
          },
          {
            "slug": "attributes.manage",
            "name": "Manage attributes"
          }
        ]
      }
    }

The response is read-only. Permissions are registered and seeded by
the backend, not created through an admin endpoint.

## 9. Assign a role to a user

### Request

    POST /api/admin/users/{user}/roles

Required permission: `users.manage`.

Request body:

    {
      "role": "staff"
    }

Validation:

- `role` is required and must be a valid system-role slug.
- The target user must exist.
- An existing assignment must not create a duplicate pivot row.

### Success response — 200 OK

    {
      "message": "User role assigned successfully.",
      "data": {
        "user": {
          "id": 1,
          "name": "Example User",
          "email": "user@example.com",
          "phone": null,
          "is_active": true,
          "roles": [
            {
              "slug": "customer",
              "name": "Customer"
            },
            {
              "slug": "staff",
              "name": "Staff"
            }
          ]
        }
      }
    }

Assigning a role that is already assigned should not create a duplicate
record.

Invalid request data returns 422. A nonexistent target user returns 404.

## 10. Remove a role from a user

### Request

    DELETE /api/admin/users/{user}/roles/{roleSlug}

Required permission: `users.manage`.

The operation removes only the specified role assignment. It must not
delete the role definition or its permissions.

The operation is idempotent: removing an already absent assignment
does not create duplicate data or delete unrelated role assignments.

### Success response — 200 OK

    {
      "message": "User role removed successfully.",
      "data": {
        "user": {
          "id": 1,
          "name": "Example User",
          "email": "user@example.com",
          "phone": null,
          "is_active": true,
          "roles": []
        }
      }
    }

The implementation must reject an operation that would remove the
last active administrator's `admin` role.

As a lockout safeguard, an administrator must not remove their own
`admin` role through this endpoint.

If the target user or role does not exist, return 404. An operation
that violates administrator-protection rules returns 409.

## 11. Change account activation

### Request

    PATCH /api/admin/users/{user}/status

Required permission: `users.manage`.

Request body:

    {
      "is_active": false
    }

Validation:

- `is_active` is required and must be a boolean.
- The target user must exist.
- The endpoint updates the existing `users.is_active` field.
- No new status field or duplicate migration may be introduced.

### Success response — 200 OK

    {
      "message": "User status updated successfully.",
      "data": {
        "user": {
          "id": 1,
          "name": "Example User",
          "email": "user@example.com",
          "phone": null,
          "is_active": false,
          "roles": [
            {
              "slug": "customer",
              "name": "Customer"
            }
          ]
        }
      }
    }

An inactive account must not be allowed to access protected endpoints.
The existing Auth behavior remains unchanged.

The implementation must reject attempts to deactivate the last active
administrator. An administrator must not deactivate their own account
through this endpoint.

These safeguards are proposed policy and must be confirmed before
the endpoint is implemented.

## 12. Data and security requirements

The RBAC implementation must use these tables:

- `roles`
- `permissions`
- `role_user`
- `permission_role`

Both pivot tables use composite primary keys to prevent duplicate
assignments.

Additional requirements:

- `roles.slug` and `permissions.slug` must be unique.
- Seeders must be idempotent and safe to run repeatedly.
- Effective permissions must include the permissions from every role.
- Admin routes must use authentication, active-account checks, and
  permission middleware.
- A missing permission returns 403; it must not be treated as a 404.
- User responses must not expose password hashes, remember tokens,
  or access tokens.
- Account status must reuse the schema and behavior implemented by Auth.
- Role assignment and account-status changes must be protected against
  administrator lockout.
- Role and permission definitions are read-only through this API.

## 13. Required tests for implementation slices

The subsequent implementation commits must cover at least:

- Unauthenticated access returns 401.
- Inactive users cannot access admin endpoints.
- Users without the required permission receive 403.
- Users with the required permission can perform the operation.
- Users with multiple roles receive the union of permissions.
- Pagination and user response fields are correct.
- Invalid role assignments return 422.
- Duplicate role assignment does not create duplicate rows.
- Role removal does not affect unrelated roles.
- Account activation is updated correctly.
- The last active administrator cannot be removed or deactivated.
- Seeders can run repeatedly without duplicate data.
- Passwords, tokens, and other secrets are never serialized.

Every implementation commit must include the code, tests, and the
relevant API documentation update.

## 14. Decisions required before schema implementation

Before implementing the RBAC schema and seeders, confirm:

1. D-05: approve `admin`, `staff`, `customer`, or provide the final roles.
2. D-06: approve the permission names and the proposed staff permission
   assignments.
3. D-12: approve the seven endpoints and their request/response contracts.
4. D-16: confirm that the existing `is_active` policy remains authoritative
   and that admins may change it under the safeguards above.

Until these decisions are recorded, this document remains a proposal.
