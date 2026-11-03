# Brands API

Base URL: `/api`

The Brands API provides public read endpoints and protected management
endpoints for the product catalog.

## Visibility rules

- Only brands with `status = true` are returned by public read endpoints.
- Hidden brands are treated as nonexistent and return HTTP 404.
- Public read endpoints do not require authentication.
- All brand write endpoints require Sanctum authentication, an active
  account and the `brands.manage` permission.
- `status` is the visibility flag in the current schema.
- Do not use `is_active` or `deleted_at` as substitutes.

## 1. List brands

**GET** `/api/brands`

Returns a paginated list of visible brands.

### Authentication

Not required.

### Query parameters

| Parameter | Type | Default | Description |
|---|---|---:|---|
| page | integer | 1 | Requested pagination page |

Page size is fixed at 15 records.

Records are ordered by:

1. `name` ascending.
2. `id` ascending.

### Success response

HTTP `200 OK`

```json
{
  "message": "Brands retrieved successfully.",
  "data": [
    {
      "id": 1,
      "name": "Apple",
      "slug": "apple",
      "logo": "brands/apple.png",
      "description": "Apple smartphone brand.",
      "status": true,
      "created_at": "2026-11-03T08:00:00.000000Z",
      "updated_at": "2026-11-03T08:00:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/brands?page=1",
    "last": "http://localhost:8000/api/brands?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [],
    "path": "http://localhost:8000/api/brands",
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

## 2. Create brand

**POST** `/api/brands`

Creates a brand.

### Authentication

Required: Sanctum bearer token.

### Permission

`brands.manage`

The authenticated account must also be active.

### Request body

```json
{
  "name": "Samsung",
  "slug": "samsung",
  "logo": "brands/samsung.png",
  "description": "Samsung smartphone brand."
}
```

### Fields

| Field | Type | Required | Rules |
|---|---|---|---|
| name | string | Yes | Maximum 255 characters; must not duplicate an existing brand name |
| slug | string | Yes | Maximum 255 characters; globally unique |
| logo | string/null | No | Nullable; maximum 255 characters |
| description | string/null | No | Nullable string |
| status | boolean | No | Defaults to `true` when omitted |

Unsupported fields are rejected. In particular, `is_active` and
`deleted_at` are not part of the current Brands API contract.

### Success response

HTTP `201 Created`

```json
{
  "message": "Brand created successfully.",
  "data": {
    "brand": {
      "id": 1,
      "name": "Samsung",
      "slug": "samsung",
      "logo": "brands/samsung.png",
      "description": "Samsung smartphone brand.",
      "status": true,
      "created_at": "2026-11-03T08:00:00.000000Z",
      "updated_at": "2026-11-03T08:00:00.000000Z"
    }
  }
}
```

The ID and timestamp values are illustrative.

### Error responses

| Status | Meaning |
|---|---|
| `401 Unauthorized` | No authenticated user |
| `403 Forbidden` | Inactive account or missing `brands.manage` permission |
| `422 Unprocessable Content` | Missing/invalid fields, duplicate name/slug or unsupported fields |

## 3. Update brand

**PATCH** `/api/brands/{id}`

Partially updates an existing brand.

### Authentication

Required: Sanctum bearer token.

### Permission

`brands.manage`

The authenticated account must also be active.

### Request body

All fields are optional, but at least one supported field must be provided.

```json
{
  "name": "Samsung Electronics",
  "slug": "samsung-electronics",
  "logo": "brands/samsung.png",
  "description": "Samsung Electronics smartphone brand.",
  "status": true
}
```

### Fields

| Field | Type | Required | Rules |
|---|---|---|---|
| name | string | No | Maximum 255 characters; unique excluding the current brand |
| slug | string | No | Maximum 255 characters; unique excluding the current brand |
| logo | string/null | No | Nullable; maximum 255 characters |
| description | string/null | No | Nullable string |
| status | boolean | No | Controls visibility |

When a field is omitted, its existing value is preserved.

An explicitly supplied `null` clears `logo` or `description`.

An empty request body is rejected.

Unsupported fields are rejected. In particular, `id`, `is_active`,
`deleted_at`, `created_at` and `updated_at` cannot be modified.

Hidden brands can still be managed by ID. This allows administrators
to update and reactivate a hidden brand.

### Success response

HTTP `200 OK`

```json
{
  "message": "Brand updated successfully.",
  "data": {
    "brand": {
      "id": 1,
      "name": "Samsung Electronics",
      "slug": "samsung-electronics",
      "logo": "brands/samsung.png",
      "description": "Samsung Electronics smartphone brand.",
      "status": true,
      "created_at": "2026-11-03T08:00:00.000000Z",
      "updated_at": "2026-11-03T09:00:00.000000Z"
    }
  }
}
```

### Error responses

| Status | Meaning |
|---|---|
| `401 Unauthorized` | No authenticated user |
| `403 Forbidden` | Inactive account or missing `brands.manage` permission |
| `404 Not Found` | Brand does not exist |
| `422 Unprocessable Content` | Invalid fields, duplicate name/slug, empty payload or unsupported fields |

## 4. Delete brand

**DELETE** `/api/brands/{id}`

Deletes a brand only when referential integrity is preserved.

### Authentication

Required: Sanctum bearer token.

### Permission

`brands.manage`

The authenticated account must also be active.

### Deletion policy

The Brands module uses physical deletion instead of soft deletion.

The `status` field is reserved for visibility:

- `true`: visible to public read endpoints.
- `false`: hidden from public read endpoints.

A hidden brand can still be updated, reactivated or deleted by an
authorized administrator.

### Product reference protection

The Product/Stock module owns the `products` table and its migration.

The Brands module must never create a placeholder products table.

When the products table does not exist, deletion is permitted because
there are currently no records in that table that can reference the brand.

When the products table exists, the following requirements apply:

1. `products.brand_id` must exist.
2. `products.brand_id` must reference `brands.id`.
3. The foreign key must use `ON DELETE RESTRICT` or `ON DELETE NO ACTION`.
4. No product may currently reference the brand.

If any of these requirements cannot be verified, deletion is rejected
with `409 Conflict`.

The API also translates foreign-key violations reported by the database
into a safe `409 Conflict` response.

The Product/Stock migration owner must maintain the restrictive foreign
key when integrating the products table.

### Conflict handling

When a product references a brand:

```http
HTTP/1.1 409 Conflict
```

```json
{
  "message": "Brand cannot be deleted while products reference it. Set status to false to hide the brand instead."
}
```

To hide the brand instead:

**PATCH** `/api/brands/{id}`

```json
{
  "status": false
}
```

### Success response

HTTP `200 OK`

```json
{
  "message": "Brand deleted successfully."
}
```

### Error responses

| Status | Meaning |
|---|---|
| `401 Unauthorized` | No authenticated user |
| `403 Forbidden` | Inactive account or missing `brands.manage` permission |
| `404 Not Found` | Brand does not exist |
| `409 Conflict` | Product reference exists, Product schema is incomplete or a database foreign-key constraint prevents deletion |

### Transaction and concurrency protection

The deletion operation runs inside a database transaction.

The target brand is locked using `lockForUpdate()`.

The Product foreign key is required as the final protection against
concurrent product creation and accidental deletion of referenced
brands.

The application must never bypass referential integrity constraints.
