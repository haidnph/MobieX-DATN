<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncCategoryAttributesRequest extends FormRequest
{
    /**
     * Fields supported by the category attribute assignment API.
     *
     * @var list<string>
     */
    private const ALLOWED_FIELDS = [
        'attribute_ids',
    ];

    public function authorize(): bool
    {
        /*
         * Authentication, active account and permission checks are enforced
         * by route middleware.
         */
        return true;
    }

    /**
     * Validation rules for replacing category attribute assignments.
     *
     * Important:
     * - "present" allows an explicitly empty array.
     * - "array" requires an array payload.
     * - "list" rejects associative/object-style arrays.
     * - "distinct" prevents duplicate attribute IDs.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'attribute_ids' => [
                'present',
                'array',
                'list',
            ],

            'attribute_ids.*' => [
                'required',
                'integer',
                'min:1',
                'distinct',
                Rule::exists('attributes', 'id'),
            ],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attribute_ids.present' => 'The attribute_ids field must be provided.',

            'attribute_ids.array' => 'The attribute_ids field must be an array.',

            'attribute_ids.list' => 'The attribute_ids field must be a JSON list.',

            'attribute_ids.*.required' => 'Every attribute ID is required.',

            'attribute_ids.*.integer' => 'Every attribute ID must be an integer.',

            'attribute_ids.*.min' => 'Every attribute ID must be greater than zero.',

            'attribute_ids.*.distinct' => 'Duplicate attribute IDs are not allowed.',

            'attribute_ids.*.exists' => 'One or more selected attributes do not exist.',
        ];
    }

    /**
     * Reject fields outside the assignment contract.
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
