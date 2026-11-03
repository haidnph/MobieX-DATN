# Brands API

Base URL: `/api`

The Brands API provides public read endpoints and protected
management endpoints for the product catalog.

## Visibility rules

- Only brands with `status = true` are returned by public read endpoints.
- Hidden brands are treated as nonexistent and return HTTP 404.
- Public read endpoints do not require authentication.
- Brand management endpoints are documented here as part of the
  overall contract but are implemented in subsequent development slices.

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