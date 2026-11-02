## 11. Category Hierarchy Cycle Protection

Category hierarchy must not contain circular parent-child references.

The API validates the proposed parent category before updating
the category.

### Validation rules

The following updates are rejected:

1. A category cannot be its own parent.
2. A category cannot become a child of its direct child.
3. A category cannot become a child of any descendant.
4. An already cyclic ancestor chain cannot be extended through
   another hierarchy update.

### Example: self-parent

PATCH /api/categories/1

```json
{
    "parent_id": 1
}
```

Response:

HTTP 422

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "parent_id": [
            "The selected parent category would create a hierarchy cycle."
        ]
    }
}
```

### Example: multi-level cycle

Initial hierarchy:

```text
Phones (1)
└── Smartphones (2)
    └── Android (3)
```

Invalid request:

PATCH /api/categories/1

```json
{
    "parent_id": 3
}
```

The API rejects the request because category 3 is a descendant
of category 1.

### Valid hierarchy updates

Moving a category under an unrelated category is allowed,
provided the resulting hierarchy does not contain a cycle.

Existing descendants remain attached to the moved category.

### Implementation

The update validation retrieves the category parent map in
one database query, then traverses the proposed parent's
ancestor chain in memory.

This prevents N+1 queries during cycle validation.
