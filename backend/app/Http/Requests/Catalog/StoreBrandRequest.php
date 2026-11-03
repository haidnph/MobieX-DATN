<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBrandRequest extends FormRequest
{
    /**
     * Fields supported by the public brand-management contract.
     *
     * @var list<string>
     */
    private const ALLOWED_FIELDS = [
        'name',
        'slug',
        'logo',
        'description',
        'status',
    ];

    public function authorize(): bool
    {
        // Authentication and permission checks are enforced by route middleware.
        return true;
    }

    /**
     * Validation rules for creating a brand.
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
                Rule::unique('brands', 'name'),
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'slug'),
            ],

            'logo' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Reject fields outside the documented API contract.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unsupportedFields = array_diff(
                    array_keys($this->all()),
                    self::ALLOWED_FIELDS,
                );

                foreach ($unsupportedFields as $field) {
                    $validator->errors()->add(
                        (string) $field,
                        'This field is not allowed.',
                    );
                }
            },
        ];
    }
}
