
# Categories API

## Overview

The Categories API manages the hierarchical product category catalog.

Base URL:

`/api/categories`

## 1. Authentication and Permissions

Read endpoints are public.

Write endpoints require:

- Sanctum authentication.
- An active user account.
- The `categories.manage` permission.

Permission middleware:

`permission:categories.manage`

The existing RBAC permission matrix grants category management
to admin and staff.

## 2. List Categories

### Request

`GET /api/categories`

### Behavior

- Returns visible categories only.
- Uses Laravel pagination.
- Default page size: 15.
- Sorts by `sort_order ASC`, then `id ASC`.
- Hidden categories are excluded.

### Query parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| page | integer | 1 | Pagination page |
| tree | boolean | false | Return hierarchical tree when true |

## 3. Retrieve Category Tree

### Request

`GET /api/categories?tree=true`

### Behavior

- Returns the complete visible hierarchy.
- Does not paginate.
- Root categories have `parent_id = null`.
- Children are nested under their parent.
- Hidden categories are excluded.
- Children whose parent is hidden are not exposed as root categories.
- Children are ordered by `sort_order`, then `id`.

## 4. Retrieve Category Details

### Request

`GET /api/categories/{id}`

### Responses

- 200: Category exists and is visible.
- 404: Category does not exist.
- 404: Category is hidden.

## 5. Create Category

### Request

`POST /api/categories`

### Authentication

Required.

### Permission

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
| status | boolean | No | Database default true |

### Business validation

- Slugs must be unique.
- Root category names must be unique.
- Nested categories may share names.
- Unsupported fields are rejected.
- Empty required values are rejected.

### Success

HTTP 201

## 6. Update Category

### Request

`PATCH /api/categories/{id}`

### Authentication

Required.

### Permission

`categories.manage`

### Request body

All supported fields are optional, but at least one must be provided.

Example:

```json
{
    "name": "Android Smartphones",
    "sort_order": 2
}
```

### Business validation

- Only submitted fields are updated.
- Slugs must remain globally unique.
- Keeping the existing slug is allowed.
- Root category names must remain unique.
- Empty payloads are rejected.
- Unsupported fields are rejected.
- Parent category must exist.
- Hierarchy cycles are rejected.
- Nonexistent category IDs return 404.

### Success

HTTP 200

## 7. Delete Category

### Request

`DELETE /api/categories/{id}`

### Authentication

Required.

### Permission

`categories.manage`

### Business rules

A category can only be hard-deleted when:

1. It has no child categories.
2. No products reference it through `category_id`, when
   the product reference schema is available.

Children are checked regardless of visibility status.

Product references are checked only when:

- The products table exists.
- The products table contains `category_id`.

If the products table exists but the expected `category_id`
column is missing, deletion fails safely with HTTP 409.

No placeholder products table is created by the Categories module.

### Successful response

HTTP 200

```json
{
    "message": "Category deleted successfully."
}
```

### Conflict: category has children

HTTP 409

```json
{
    "message": "Category cannot be deleted while it has child categories. Set status to false to hide it instead."
}
```

### Conflict: products reference the category

HTTP 409

```json
{
    "message": "Category cannot be deleted while products reference it. Set status to false to hide it instead."
}
```

### Conflict: incomplete product schema

HTTP 409

Deletion is refused when the products table exists but its
expected category_id column is unavailable.

## 8. Visibility

The current category schema uses the `status` boolean column.

- `status = true`: visible.
- `status = false`: hidden.

Hidden categories are excluded from public list, tree and
detail endpoints.

When a category cannot be deleted because it has dependencies,
the administrator can hide it using PATCH:

```json
{
    "status": false
}
```

The final D-07 decision remains subject to group approval.

Soft deletes have not been introduced.

## 9. Hierarchy Protection

The API rejects:

- A category becoming its own parent.
- A category becoming a child of its descendant.
- Multi-level hierarchy cycles.

The implementation validates the proposed parent's ancestor
chain before saving the update.

## 10. Product References

The Product model uses `category_id` to reference Categories.

The Products schema is owned by the Product/Stock module.

The Categories module does not create or own the products table.

Product-reference deletion checks activate when the products
table and its category_id column have been integrated.

Until then, product-reference verification remains tracked
under D-15.

## 11. HTTP Status Codes

| Status | Meaning |
|---|---|
| 200 | Successful read, update or deletion |
| 201 | Category created |
| 401 | Unauthenticated |
| 403 | Inactive account or missing permission |
| 404 | Category not found |
| 409 | Deletion conflict or unsafe product schema |
| 422 | Validation error |
