<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttributeRequest extends FormRequest
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
     * Validation rules for creating an attribute.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/',
                Rule::unique('attributes', 'code'),
            ],

            'type' => [
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
     * Reject fields outside the attribute API contract.
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
