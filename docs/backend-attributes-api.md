# Attributes API

**Module:** A-03 — Attributes  
**Branch:** `feature/catalog-attributes`  
**Backend owner:** Kiên  
**Status:** Contract accepted; Commit 02 schema, Commit 03 public read endpoints and Commit 04 attribute management endpoints implemented; Attribute Values and category assignments pending

## 1. Overview

The Attributes module manages product attributes, their values,
and their direct assignments to product categories.

Examples:

- RAM: 8GB, 12GB, 16GB
- Storage: 128GB, 256GB, 512GB
- Color: Black, White, Blue

The module integrates with the existing ProductVariant models
through AttributeValue IDs.

## 2. Existing implementation audit

The following Laravel models already exist:

- `App\Models\Attribute`
- `App\Models\AttributeValue`
- `App\Models\ProductVariant`

Existing relationships:

- Attribute has many AttributeValue.
- AttributeValue belongs to Attribute.
- AttributeValue belongs to many ProductVariant.
- ProductVariant belongs to many AttributeValue.

The existing ProductVariant code already references:

- `attribute_values`
- `variant_attribute_values`
- `attribute_value_ids`

However, the current develop branch does not contain migrations
for the Attributes or ProductVariant-related tables.

The existing ProductVariant controller is not registered as
an API route in `routes/api.php`.

Do not recreate existing models or relationships unnecessarily.

## 3. Database ownership

Kiên owns the complete A-03 implementation:

- Database design and migrations.
- Eloquent models and relationships.
- Attributes API.
- Attribute Values API.
- Category Attribute assignments.
- Validation and RBAC.
- ProductVariant integration required by A-03.
- Feature tests and documentation.
- All implementation commits.

The existing Product/Stock module remains an integration
dependency. A-03 must not recreate its products or
product_variants tables.

## 4. Database conventions

Use the following conventions:

- MySQL 8.0+
- InnoDB.
- utf8mb4_unicode_ci.
- BIGINT UNSIGNED primary and foreign keys.
- Laravel timestamps.
- No soft deletes.
- Explicit foreign-key constraints.
- Database migrations must support rollback.

Use non-prefixed Laravel table names.

Do not use the legacy `datn_` tables as Laravel migration
targets. The legacy database is a separate schema.

## 5. Database schema contract

### 5.1. attributes

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| name | VARCHAR(100) | NOT NULL |
| code | VARCHAR(100) | NOT NULL, UNIQUE |
| type | VARCHAR(50) | NOT NULL, DEFAULT 'select' |
| sort_order | INT | NOT NULL, DEFAULT 0 |
| status | TINYINT(1) | NOT NULL, DEFAULT 1 |
| created_at | TIMESTAMP | NULL |
| updated_at | TIMESTAMP | NULL |

Rules:

- `code` must be globally unique.
- `code` uses lowercase snake_case.
- `name` is the display name.
- `type` is stored as VARCHAR, not a database ENUM.
- Initial supported types are `select` and `color`.
- Default type is `select`.
- `sort_order` must be greater than or equal to zero.
- `status = 1` means visible.
- `status = 0` means hidden.

### 5.2. attribute_values

| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| attribute_id | BIGINT UNSIGNED | FK, NOT NULL |
| value | VARCHAR(255) | NOT NULL |
| color_code | VARCHAR(20) | NULL |
| sort_order | INT | NOT NULL, DEFAULT 0 |
| status | TINYINT(1) | NOT NULL, DEFAULT 1 |
| created_at | TIMESTAMP | NULL |
| updated_at | TIMESTAMP | NULL |

Constraints:

- `attribute_id` references `attributes.id`.
- ON DELETE CASCADE.
- UNIQUE (`attribute_id`, `value`).

Rules:

- A value belongs to exactly one attribute.
- The same value can exist under different attributes.
- The same value cannot be duplicated within one attribute.
- `color_code` is nullable.
- Color attributes use a valid hexadecimal color code.
- Non-color attributes must not use `color_code`.
- `sort_order` must be greater than or equal to zero.
- `status` controls public visibility.

### 5.3. attribute_category

Many-to-many relationship between attributes and categories.

| Column | Type | Constraints |
|---|---|---|
| attribute_id | BIGINT UNSIGNED | PK, FK |
| category_id | BIGINT UNSIGNED | PK, FK |

Constraints:

- Composite primary key (`attribute_id`, `category_id`).
- `attribute_id` references `attributes.id`.
- `category_id` references `categories.id`.
- Both foreign keys use ON DELETE CASCADE.
- Add an index on `category_id`.

This pivot does not require timestamps.

Business rules:

- A category can have multiple attributes.
- An attribute can belong to multiple categories.
- Duplicate assignments are prohibited.
- Assignments are direct relationships.
- Child categories do not automatically inherit attributes
  from their parent categories.

### 5.4. ProductVariant integration

The existing Laravel code uses the following relationship:

`variant_attribute_values`

Columns:

- `variant_id`
- `attribute_value_id`

Composite primary key:

`(variant_id, attribute_value_id)`

The existing model relationships and table naming must
remain compatible.

The current develop branch does not contain the required
ProductVariant migrations.

Do not create a migration with a foreign key referencing
a nonexistent `product_variants` table.

The ProductVariant integration must be completed only
after the dependency schema is available.

Integration rules:

- Every selected AttributeValue must exist.
- A variant can use multiple attributes.
- A variant can only select one value per attribute.
- Duplicate AttributeValue IDs are rejected.
- AttributeValue must belong to the correct Attribute.

Example:

Valid:

- RAM = 8GB
- Storage = 256GB
- Color = Black

Invalid:

- RAM = 8GB
- RAM = 16GB

The last rule requires application-level validation,
because the composite primary key alone does not prevent
two values belonging to the same attribute.

## 6. Attribute visibility and deletion

### 6.1. Public visibility

Public read endpoints only return records with:

`status = true`

Hidden attributes and values must not appear in public
catalog responses.

Hidden records can still be managed by an authorized
administrator through their IDs.

### 6.2. Attribute deletion

Physical deletion is allowed only when no AttributeValue
is referenced by a ProductVariant.

When referenced:

- Return HTTP 409.
- Do not silently remove the variant's selected value.
- Recommend setting `status = false` instead.

Category assignments are removed through pivot foreign-key
cascades when an attribute is deleted.

### 6.3. AttributeValue deletion

A value cannot be physically deleted while it is used
by a ProductVariant.

Return HTTP 409 and recommend setting `status = false`.

The API must check references before deletion.

The ProductVariant foreign key should prevent accidental
deletion of referenced values where the Laravel integration
migration is available.

## 7. Authentication and permissions

Public read endpoints do not require authentication.

All write endpoints require:

- Sanctum authentication.
- Active user account.
- `attributes.manage` permission.

Use the existing middleware:

- `auth:sanctum`
- `active`
- `permission:attributes.manage`

The `attributes.manage` permission already exists in
RolePermissionSeeder.

Do not create duplicate permissions or modify the RBAC
schema for this module.

## 8. API endpoints

Base URL:

`/api`

### 8.1. Attribute read endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/attributes | List active attributes |
| GET | /api/attributes/{id} | Retrieve attribute details |

GET `/api/attributes`:

- Public endpoint.
- Return active attributes only.
- Use Laravel pagination.
- Default page size: 15.
- Support `page`.
- Support `category_id` filtering.
- Support `type` filtering.
- Order by `sort_order ASC`, then `id ASC`.

Attribute details include active AttributeValues.

### 8.2. Attribute management endpoints

| Method | Endpoint | Description |
|---|---|---|
| POST | /api/attributes | Create attribute |
| PATCH | /api/attributes/{id} | Update attribute |
| DELETE | /api/attributes/{id} | Delete attribute |

All write endpoints require `attributes.manage`.

POST example:

    {
      "name": "RAM",
      "code": "ram",
      "type": "select",
      "sort_order": 1,
      "status": true
    }

Validation:

| Field | Rules |
|---|---|
| name | Required, string, max 100 |
| code | Required, lowercase snake_case, max 100, unique |
| type | Required, supported type: select or color |
| sort_order | Nullable integer, min 0 |
| status | Nullable boolean |

PATCH:

- Partial update.
- At least one supported field is required.
- Unique code excludes the current attribute.
- Empty payload is rejected.
- Unsupported fields are rejected.

### 8.3. AttributeValue endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/attributes/{attributeId}/values | List values |
| POST | /api/attributes/{attributeId}/values | Create value |
| PATCH | /api/attributes/{attributeId}/values/{valueId} | Update value |
| DELETE | /api/attributes/{attributeId}/values/{valueId} | Delete value |

GET:

- Public endpoint.
- Return active values only.
- Order by `sort_order ASC`, then `id ASC`.

POST example:

    {
      "value": "16GB",
      "color_code": null,
      "sort_order": 1,
      "status": true
    }

Validation:

| Field | Rules |
|---|---|
| value | Required, string, max 255 |
| color_code | Nullable, valid hexadecimal color code for color attributes |
| sort_order | Nullable integer, min 0 |
| status | Nullable boolean |

Business rules:

- Value must belong to the attribute in the URL.
- Duplicate values within the same attribute are rejected.
- A value belonging to another attribute cannot be updated
  through this endpoint.
- An explicitly hidden value is excluded from public reads.

### 8.4. Category Attribute assignments

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/categories/{categoryId}/attributes | List assigned attributes |
| PUT | /api/categories/{categoryId}/attributes | Replace assignments |

GET:

- Public endpoint.
- Return active attributes assigned directly to the category.
- Do not inherit attributes from parent categories.

PUT example:

    {
      "attribute_ids": [1, 2, 3]
    }

Rules:

- Requires Sanctum and `attributes.manage`.
- Category must exist.
- `attribute_ids` must be an array.
- All IDs must exist.
- Duplicate IDs are rejected.
- Empty array removes all existing assignments.
- Use a database transaction.
- Use Eloquent `sync()` to replace assignments.
- Return the resulting assignments.

## 9. Response conventions

Follow the current Categories and Brands API response
conventions.

### List

HTTP 200.

Use Laravel paginated Resource Collection:

    {
      "message": "Attributes retrieved successfully.",
      "data": [],
      "links": {},
      "meta": {}
    }

### Detail

    {
      "message": "Attribute retrieved successfully.",
      "data": {
        "attribute": {}
      }
    }

### Create

HTTP 201.

    {
      "message": "Attribute created successfully.",
      "data": {
        "attribute": {}
      }
    }

### Update

HTTP 200.

    {
      "message": "Attribute updated successfully.",
      "data": {
        "attribute": {}
      }
    }

### Delete

HTTP 200.

    {
      "message": "Attribute deleted successfully."
    }

### Validation errors

Use Laravel's standard JSON validation response:

HTTP 422.

    {
      "message": "The given data was invalid.",
      "errors": {}
    }

Do not introduce a new response envelope for this module.

## 10. HTTP status codes

| Status | Meaning |
|---|---|
| 200 | Success |
| 201 | Created |
| 401 | Unauthenticated |
| 403 | Missing permission or inactive account |
| 404 | Resource not found or hidden on public read |
| 409 | Resource is referenced and cannot be deleted |
| 422 | Validation failure |

## 11. Testing requirements

### Schema tests

- Attributes table exists.
- AttributeValues table exists.
- Category pivot exists.
- Correct columns and defaults.
- Attribute code uniqueness.
- AttributeValue composite uniqueness.
- Foreign-key constraints.
- Pivot composite primary key.
- Cascade behavior.
- Rollback behavior.

### Attribute read tests

- Public listing.
- Pagination.
- Sorting.
- Category filter.
- Type filter.
- Attribute detail.
- Hidden attributes excluded.
- Active values included.
- Hidden values excluded.
- 404 behavior.

### Attribute write tests

- Create.
- Update.
- Delete.
- Duplicate code.
- Invalid type.
- Invalid sort_order.
- Empty PATCH.
- Unsupported fields.
- 401 and 403.
- Referenced attribute cannot be deleted.

### AttributeValue tests

- Public list.
- Create.
- Update.
- Delete.
- Duplicate value.
- Same value under different attributes.
- Wrong parent attribute returns 404.
- Invalid color code.
- Referenced value cannot be deleted.

### Category assignment tests

- List direct assignments.
- Assign multiple attributes.
- Replace existing assignments.
- Remove all assignments.
- Reject duplicate IDs.
- Reject nonexistent category.
- Reject nonexistent attribute.
- No parent-to-child inheritance.
- 401 and 403.

### ProductVariant integration tests

- AttributeValue existence validation.
- Duplicate IDs rejected.
- One value per attribute per variant.
- Existing relationships remain intact.
- No product or stock schema is recreated.

## 12. Implementation boundaries

The A-03 branch must not:

- Recreate existing Attribute models.
- Recreate Product or ProductVariant tables.
- Change existing Auth or RBAC schemas.
- Change Category or Brand fields.
- Create duplicate attributes or attribute_values tables.
- Use legacy `datn_*` tables as migration targets.
- Introduce soft deletes.
- Expose hidden attributes or values through public reads.

All implementation commits must include the tests and
endpoint documentation corresponding to the implemented
feature.

Each commit must pass the full test suite and
`git diff --check` before it is committed.

## 13. Schema implementation — Commit 02

The following tables are owned and implemented by the
Attributes module:

- attributes
- attribute_values
- attribute_category

### Migration order

1. create_attributes_table
2. create_attribute_values_table
3. create_attribute_category_table

### Attributes constraints

- Unique: uq_attributes_code
- Index: idx_attributes_status_sort_id

### Attribute values constraints

- Unique: uq_attribute_values_attribute_value
- Index: idx_attribute_values_visibility_sort
- Foreign key: fk_attribute_values_attribute
- ON DELETE CASCADE
- ON UPDATE CASCADE

### Category assignment constraints

- Composite PK: pk_attribute_category
- Index: idx_attribute_category_category_id
- Foreign key: fk_attribute_category_attribute
- Foreign key: fk_attribute_category_category
- Both foreign keys use ON DELETE CASCADE.

### Model relationships

Attribute:

- HasMany AttributeValue
- BelongsToMany Category

AttributeValue:

- BelongsTo Attribute
- BelongsToMany ProductVariant

Category:

- BelongsToMany Attribute

### ProductVariant dependency

The A-03 schema commit does not create products,
product_variants or variant_attribute_values.

The existing Eloquent relationship is preserved.

ProductVariant database integration must be completed
only after the product_variants table is available.

The ProductVariant relationship must protect referenced
AttributeValues against accidental physical deletion.

The one-value-per-attribute rule is enforced at
the application layer.

## 14. Attribute read API implementation — Commit 03

The public read endpoints are implemented as follows:

- `GET /api/attributes` returns visible attributes only (`status = true`).
- The list uses Laravel pagination with a fixed default page size of 15.
- Results are ordered by `sort_order ASC`, then `id ASC` for stable pagination.
- `page` selects the requested pagination page.
- `category_id` filters attributes assigned directly to the specified category.
  Child categories do not inherit assignments from their parents.
- `type` accepts the supported values `select` and `color`.
- Invalid `category_id` or `type` filters return the standard JSON validation
  response with HTTP 422.
- List items do not include nested values.
- `GET /api/attributes/{id}` returns a visible attribute and its visible
  AttributeValues only, ordered by `sort_order ASC`, then `id ASC`.
- A hidden or nonexistent attribute returns HTTP 404.
- Both endpoints are public and do not require Sanctum authentication.

The read API uses `AttributeResource` and `AttributeValueResource` and is
covered by `AttributeReadTest`, including public access, visibility, ordering,
pagination, category/type filtering, nested values, and not-found behavior.

## 15. Attribute management implementation — Commit 04

The following management endpoints are implemented:

| Method | Endpoint | Description |
|---|---|---|
| POST | /api/attributes | Create attribute |
| PATCH | /api/attributes/{id} | Partially update attribute |
| DELETE | /api/attributes/{id} | Delete attribute |

### 15.1. Authorization

All write endpoints require:

- `auth:sanctum`
- `active`
- `permission:attributes.manage`

The existing permission is reused. No permission or RBAC migration is introduced.

### 15.2. Create attribute

POST `/api/attributes`

Required fields:

- `name`: string, max 100.
- `code`: lowercase snake_case, max 100, globally unique.
- `type`: `select` or `color`.

Optional fields:

- `sort_order`: integer, 0 or greater.
- `status`: boolean.

Omitted `sort_order` and `status` use database defaults.

Explicit null is rejected because both database columns are NOT NULL.

Success returns HTTP 201 and an AttributeResource response.

### 15.3. Update attribute

PATCH `/api/attributes/{id}`

- Supports partial updates.
- At least one supported field is required.
- Duplicate codes are rejected.
- The current attribute is excluded from unique-code validation.
- Unsupported fields are rejected.
- Empty payloads are rejected.
- Hidden attributes remain manageable by ID.

Success returns HTTP 200.

### 15.4. Delete attribute

DELETE `/api/attributes/{id}`

- Physical deletion is allowed when no ProductVariant references
  any AttributeValue owned by the attribute.
- Existing AttributeValues and category assignments are removed
  through database cascade constraints.
- Referenced values prevent deletion and return HTTP 409.
- Set `status = false` to hide an attribute that cannot be deleted.

ProductVariant integration remains conditional on the existing
Product/Stock schema.

When `variant_attribute_values` exists, the implementation
requires:

- An `attribute_value_id` column.
- A foreign key to `attribute_values.id`.
- `ON DELETE RESTRICT` or `NO ACTION`.

If the dependency cannot be verified safely, deletion returns
HTTP 409 instead of assuming that cascading deletion is safe.

The application does not create a placeholder ProductVariant
or pivot table.

### 15.5. Error handling

| Status | Meaning |
|---|---|
| 201 | Attribute created |
| 200 | Attribute updated or deleted |
| 401 | Unauthenticated |
| 403 | Inactive account or missing permission |
| 404 | Attribute not found |
| 409 | Attribute deletion conflicts with references or unsafe dependency schema |
| 422 | Validation failure |

### 15.6. Feature Tests

`AttributeWriteTest` covers:

- Public access restrictions for all write methods.
- Authentication and `attributes.manage` permission.
- Inactive account rejection.
- Creation and database defaults.
- Duplicate code validation.
- Supported types and code format.
- Partial updates and unchanged unique codes.
- Empty PATCH and unsupported fields.
- Deletion cascades.
- ProductVariant reference protection.
- Missing or unsafe ProductVariant dependency schema.
- Not-found behavior.

All tests run using the configured test database.
