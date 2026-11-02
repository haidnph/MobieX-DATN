<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
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
     * Validation rules for creating a category.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug'),
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
     * Reject unsupported fields and duplicate root category names.
     *
     * Root category names must be unique at application level because
     * MySQL UNIQUE constraints do not prevent duplicate values when
     * parent_id is NULL.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (
                    array_diff(
                        array_keys($this->all()),
                        self::ALLOWED_FIELDS,
                    ) as $field
                ) {
                    $validator->errors()->add(
                        (string) $field,
                        'This field is not allowed.',
                    );
                }

                if (
                    $validator->errors()->has('name')
                    || $validator->errors()->has('parent_id')
                ) {
                    return;
                }

                // A category without a parent is a root category.
                if ($this->input('parent_id') !== null) {
                    return;
                }

                $name = $this->input('name');

                if (! is_string($name) || trim($name) === '') {
                    return;
                }

                $rootNameExists = Category::query()
                    ->whereNull('parent_id')
                    ->where('name', $name)
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
