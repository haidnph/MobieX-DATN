<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAttributeRequest extends FormRequest
{
    /**
     * Fields supported by the attribute-management API.
     *
     * @var list<string>
     */
    private const ALLOWED_FIELDS = [
        'name',
        'code',
        'type',
        'sort_order',
        'status',
    ];

    public function authorize(): bool
    {
        // Authentication and permission checks are enforced by route middleware.
        return true;
    }

    /**
     * Validation rules for partially updating an attribute.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $attributeId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/',
                Rule::unique('attributes', 'code')
                    ->ignore($attributeId),
            ],

            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'select',
                    'color',
                ]),
            ],

            'sort_order' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:2147483647',
            ],

            'status' => [
                'sometimes',
                'required',
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

                // Reject fields outside the attribute API contract.
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
                        'attribute',
                        'At least one attribute field must be provided.',
                    );
                }
            },
        ];
    }
}
