<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBrandRequest extends FormRequest
{
    /**
     * Fields supported by the brand-management API.
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
     * Validation rules for partially updating a brand.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $brandId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'name')
                    ->ignore($brandId),
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'slug')
                    ->ignore($brandId),
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
     * Reject unsupported fields and empty PATCH payloads.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $input = $this->all();

                // Reject fields outside the documented API contract.
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
                        'brand',
                        'At least one brand field must be provided.',
                    );
                }
            },
        ];
    }
}
