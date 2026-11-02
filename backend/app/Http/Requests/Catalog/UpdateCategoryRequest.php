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
        // Authentication and authorization are enforced by route middleware.
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
     * Reject unsupported fields, empty payloads and duplicate root names.
     *
     * The root-name validation considers the category's resulting
     * parent_id, including the existing parent_id when it is omitted
     * from the PATCH payload.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $input = $this->all();

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

                if (
                    $validator->errors()->has('name')
                    || $validator->errors()->has('parent_id')
                ) {
                    return;
                }

                $category = Category::query()->find(
                    $this->route('id'),
                );

                // Let the controller return 404 if the category does not exist.
                if (! $category instanceof Category) {
                    return;
                }

                $effectiveParentId = array_key_exists(
                    'parent_id',
                    $input,
                )
                    ? $input['parent_id']
                    : $category->parent_id;

                // Nested category names are not subject to root-name uniqueness.
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
}
