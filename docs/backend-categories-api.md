# Categories API

## Overview

This document describes the public read endpoints for the MobieX
category catalog.

Base URL:

`/api/categories`

Authentication is not required for category read endpoints.

Only categories with `status = true` are visible.

## 1. List categories

### Request

`GET /api/categories`

### Behavior

- Returns visible categories only.
- Uses Laravel pagination.
- Default page size: 15 records.
- Sorts by `sort_order ASC`, then `id ASC`.

### Query parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| page | integer | 1 | Pagination page |
| tree | boolean | false | Return hierarchical tree when true |

### Response

HTTP 200

```json
{
    "data": [
        {
            "id": 1,
            "parent_id": null,
            "name": "Phones",
            "slug": "phones",
            "description": null,
            "image": null,
            "sort_order": 1,
            "status": true,
            "created_at": "2026-11-02T10:00:00.000000Z",
            "updated_at": "2026-11-02T10:00:00.000000Z"
        }
    ],
    "links": {},
    "meta": {},
    "message": "Categories retrieved successfully."
}
```

The `links` and `meta` properties are populated by Laravel pagination.

## 2. Retrieve category tree

### Request

`GET /api/categories?tree=true`

### Behavior

- Returns the complete visible category hierarchy.
- Does not paginate.
- Root categories have `parent_id = null`.
- Child categories are nested under their parent.
- Categories whose parent is hidden are not exposed as orphaned roots.
- Children are ordered by `sort_order`, then `id`.

### Response

HTTP 200

```json
{
    "data": [
        {
            "id": 1,
            "parent_id": null,
            "name": "Phones",
            "slug": "phones",
            "description": null,
            "image": null,
            "sort_order": 1,
            "status": true,
            "children": [
                {
                    "id": 2,
                    "parent_id": 1,
                    "name": "Smartphones",
                    "slug": "smartphones",
                    "description": null,
                    "image": null,
                    "sort_order": 1,
                    "status": true,
                    "children": [
                        {
                            "id": 3,
                            "parent_id": 2,
                            "name": "Android Phones",
                            "slug": "android-phones",
                            "description": null,
                            "image": null,
                            "sort_order": 1,
                            "status": true,
                            "children": []
                        }
                    ]
                }
            ]
        }
    ],
    "message": "Categories retrieved successfully."
}
```

## 3. Retrieve category details

### Request

`GET /api/categories/{id}`

### Behavior

Returns one visible category.

Hidden categories are treated as not found.

### Response

HTTP 200

```json
{
    "message": "Category retrieved successfully.",
    "data": {
        "category": {
            "id": 1,
            "parent_id": null,
            "name": "Phones",
            "slug": "phones",
            "description": null,
            "image": null,
            "sort_order": 1,
            "status": true,
            "created_at": "2026-11-02T10:00:00.000000Z",
            "updated_at": "2026-11-02T10:00:00.000000Z"
        }
    }
}
```

### Error responses

| HTTP status | Condition |
|---|---|
| 404 | Category does not exist |
| 404 | Category is hidden |

## 4. Visibility and business rules

The current schema uses the `status` boolean column.

- `status = true`: visible.
- `status = false`: hidden.

The final D-07 decision about category and brand visibility/deletion
must be confirmed separately.

This read implementation does not introduce soft deletes.

## 5. Authorization

The GET endpoints are public.

Category write endpoints will require authentication, an active
account, and the `categories.manage` permission.

## 6. Implementation notes

- No products table or migration is created by this module.
- The category tree is assembled from the existing parent_id
  relationship.
- Tree construction uses one category query to avoid N+1 queries.
- Product-reference deletion guards belong to the category
  deletion implementation and depend on the product schema.
