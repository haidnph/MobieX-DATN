
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

Hidden brands can still be managed by ID. This allows administrators to
update and reactivate a hidden brand.

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

Not implemented yet.

The intended policy is to physically delete only when no products reference
the brand. When deletion is unsafe, the API will return `409 Conflict`
and the brand should be hidden by setting `status` to `false`.
