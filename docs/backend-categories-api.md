
# Categories API

## Overview

This document describes the category read and write endpoints.

Base URL:

`/api/categories`

## 1. List categories

### Request

`GET /api/categories`

### Authentication

Public endpoint.

### Behavior

- Returns visible categories only.
- Uses Laravel pagination.
- Default page size: 15 records.
- Sorts by `sort_order ASC`, then `id ASC`.

## 2. Retrieve category tree

### Request

`GET /api/categories?tree=true`

### Authentication

Public endpoint.

### Behavior

- Returns the complete visible category hierarchy.
- Does not paginate.
- Root categories have `parent_id = null`.
- Children are nested under their parents.
- Hidden categories are not included.
- Categories with hidden or unavailable parents are not exposed as roots.

## 3. Retrieve category details

### Request

`GET /api/categories/{id}`

### Authentication

Public endpoint.

### Responses

- 200: Category found and visible.
- 404: Category does not exist or is hidden.

## 4. Create category

### Request

`POST /api/categories`

### Authentication

Required.

### Permissions

`categories.manage`

### Request body

```json
{
    "name": "Smartphones",
    "slug": "smartphones",
    "parent_id": null,
    "description": "Smartphone categories",
    "image": "categories/smartphones.jpg",
    "sort_order": 1,
    "status": true
}
```

### Fields

| Field | Type | Required | Rules |
|---|---|---|---|
| name | string | Yes | Maximum 255 characters |
| slug | string | Yes | Maximum 255, globally unique |
| parent_id | integer/null | No | Must reference an existing category |
| description | string/null | No | Nullable |
| image | string/null | No | Maximum 255 characters |
| sort_order | integer | No | Minimum 0 |
| status | boolean | No | Defaults to true in database |

### Business validation

- Slugs must be unique.
- Root category names must be unique.
- Nested category names may match other categories.
- Unsupported fields are rejected.
- `is_active` is not part of the current request contract.

### Success response

HTTP 201

```json
{
    "message": "Category created successfully.",
    "data": {
        "category": {
            "id": 1,
            "parent_id": null,
            "name": "Smartphones",
            "slug": "smartphones",
            "description": "Smartphone categories",
            "image": "categories/smartphones.jpg",
            "sort_order": 1,
            "status": true
        }
    }
}
```

## 5. Update category

### Request

`PATCH /api/categories/{id}`

### Authentication

Required.

### Permissions

`categories.manage`

### Request body

All fields are optional, but at least one supported field must be provided.

Example:

```json
{
    "name": "Android Smartphones",
    "sort_order": 2
}
```

### Business validation

- Only submitted fields are updated.
- Slug must remain globally unique.
- Keeping the category's existing slug is allowed.
- Root category names must remain unique.
- Empty payloads are rejected.
- Unsupported fields are rejected.
- Nonexistent category IDs return 404.

### Success response

HTTP 200

```json
{
    "message": "Category updated successfully.",
    "data": {
        "category": {
            "id": 1,
            "parent_id": null,
            "name": "Android Smartphones",
            "slug": "smartphones",
            "sort_order": 2,
            "status": true
        }
    }
}
```

## 6. Authorization

Category read endpoints are public.

Category write endpoints require:

- Sanctum authentication.
- An active user account.
- The `categories.manage` permission.

The RBAC permission is already defined in RolePermissionSeeder.

The current permission matrix grants category management to admin and staff.

## 7. Error responses

| HTTP status | Condition |
|---|---|
| 401 | User is unauthenticated |
| 403 | User is inactive or lacks categories.manage |
| 404 | Category does not exist |
| 422 | Validation failure |

Validation response follows Laravel's JSON validation format:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "slug": [
            "The slug has already been taken."
        ]
    }
}
```

## 8. Visibility and deletion decisions

The current category schema uses the `status` boolean column.

- `status = true`: visible.
- `status = false`: hidden.

The final D-07 decision regarding category and brand visibility/deletion
must be confirmed with the group.

Soft deletes have not been introduced.

The category deletion endpoint is not implemented in this release.

## 9. Hierarchy protection

The parent_id field must reference an existing category.

Self-parent and multi-level hierarchy cycle protection will be
implemented in the next hierarchy-protection commit.

## 10. Product references

This module does not create or own the products table.

Product-reference deletion checks depend on the product schema
being integrated by the Product/Stock owner.

No product-reference deletion logic is implemented in this release.
