# Admin Role and Permission Read Endpoints

Module: RBAC & User Management
Framework: Laravel REST API
Authentication: Laravel Sanctum Bearer Token

## 1. Scope

This implementation slice adds two read-only admin endpoints:

- `GET /api/admin/roles`
- `GET /api/admin/permissions`

The API contract is defined in:

`docs/backend-rbac-user-management-api.md`

Relevant sections:

- Section 4: Endpoint summary
- Section 7: List roles
- Section 8: List permissions

## 2. Authentication and authorization

Both endpoints require:

- `auth:sanctum`
- `active`
- `permission:users.view`

Expected responses:

| Condition | HTTP status |
|---|---:|
| Missing or invalid authentication | 401 |
| Inactive account | 403 |
| Missing `users.view` permission | 403 |
| Successful request | 200 |

## 3. List roles

### Request

`GET /api/admin/roles`

### Response

```json
{
    "message": "Roles retrieved successfully.",
    "data": {
        "roles": [
            {
                "slug": "admin",
                "name": "Admin",
                "permissions": [
                    "users.manage",
                    "users.view"
                ]
            }
        ]
    }
}
```

## 4. List permissions

### Request

`GET /api/admin/permissions`

### Response

```json
{
    "message": "Permissions retrieved successfully.",
    "data": {
        "permissions": [
            {
                "slug": "attributes.manage",
                "name": "Manage attributes"
            },
            {
                "slug": "brands.manage",
                "name": "Manage brands"
            },
            {
                "slug": "categories.manage",
                "name": "Manage categories"
            },
            {
                "slug": "users.manage",
                "name": "Manage users"
            },
            {
                "slug": "users.view",
                "name": "View users"
            }
        ]
    }
}
```
