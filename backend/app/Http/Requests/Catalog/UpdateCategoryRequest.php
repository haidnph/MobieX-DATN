<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    private const ALLOWED_FIELDS = [
        'name',
        'slug',
        'parent_id',
        'description',
        'image',
        'sort_order',
        'status',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for partially updating a category.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $categoryId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')
                    ->ignore($categoryId),
            ],

            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'image' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:2147483647',
            ],

            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Reject unsupported fields, empty payloads, duplicate root names
     * and category hierarchy cycles.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $input = $this->all();

                // Reject fields outside the category API contract.
                foreach (
                    array_diff(
                        array_keys($input),
                        self::ALLOWED_FIELDS,
                    ) as $field
                ) {
                    $validator->errors()->add(
                        (string) $field,
                        'This field is not allowed.',
                    );
                }

                // PATCH must contain at least one supported field.
                $providedFields = array_intersect(
                    array_keys($input),
                    self::ALLOWED_FIELDS,
                );

                if ($providedFields === []) {
                    $validator->errors()->add(
                        'category',
                        'At least one category field must be provided.',
                    );
                }

                // Do not perform database-dependent validation
                // when these fields already have validation errors.
                if (
                    $validator->errors()->has('name')
                    || $validator->errors()->has('parent_id')
                ) {
                    return;
                }

                $category = Category::query()->find(
                    $this->route('id'),
                );

                // The controller will handle the 404 response.
                if (! $category instanceof Category) {
                    return;
                }

                /*
                 * Hierarchy cycle protection.
                 *
                 * Only validate when parent_id is explicitly included
                 * and is not NULL.
                 */
                if (
                    array_key_exists('parent_id', $input)
                    && $input['parent_id'] !== null
                ) {
                    $proposedParentId = (int) $input['parent_id'];

                    if ($this->wouldCreateCycle(
                        $category,
                        $proposedParentId,
                    )) {
                        $validator->errors()->add(
                            'parent_id',
                            'The selected parent category would create a hierarchy cycle.',
                        );

                        return;
                    }
                }

                /*
                 * Determine the category's resulting parent_id.
                 *
                 * If parent_id is omitted, retain its existing parent.
                 */
                $effectiveParentId = array_key_exists(
                    'parent_id',
                    $input,
                )
                    ? $input['parent_id']
                    : $category->parent_id;

                // Root-name uniqueness only applies to root categories.
                if ($effectiveParentId !== null) {
                    return;
                }

                $name = $input['name'] ?? $category->name;

                if (! is_string($name) || trim($name) === '') {
                    return;
                }

                $rootNameExists = Category::query()
                    ->whereNull('parent_id')
                    ->where('name', $name)
                    ->where('id', '!=', $category->getKey())
                    ->exists();

                if ($rootNameExists) {
                    $validator->errors()->add(
                        'name',
                        'A root category with this name already exists.',
                    );
                }
            },
        ];
    }

    /**
     * Determine whether assigning the proposed parent creates a cycle.
     *
     * Starting from the proposed parent, traverse its ancestor chain.
     *
     * A cycle exists if:
     * - The category being updated is encountered.
     * - An already existing cycle is encountered in the ancestor chain.
     *
     * The parent_id map is retrieved in one query to avoid N+1 queries.
     */
    private function wouldCreateCycle(
        Category $category,
        int $proposedParentId,
    ): bool {
        $parentByCategoryId = Category::query()
            ->pluck('parent_id', 'id')
            ->all();

        $categoryId = (int) $category->getKey();

        $visited = [];

        $currentAncestorId = $proposedParentId;

        while (true) {
            // The category cannot be its own parent.
            if ($currentAncestorId === $categoryId) {
                return true;
            }

            // Protect against an already corrupted hierarchy.
            if (isset($visited[$currentAncestorId])) {
                return true;
            }

            $visited[$currentAncestorId] = true;

            $nextParentId = $parentByCategoryId[$currentAncestorId] ?? null;

            // Reached a root category.
            if ($nextParentId === null) {
                return false;
            }

            $currentAncestorId = (int) $nextParentId;
        }
    }
}
