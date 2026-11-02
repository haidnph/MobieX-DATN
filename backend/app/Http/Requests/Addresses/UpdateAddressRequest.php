<?php

namespace App\Http\Requests\Addresses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'full_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
            ],

            'city' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'district' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'ward' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'address_line' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Reject unsupported fields and empty update requests.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $input = $this->all();

                $allowedFields = [
                    'label',
                    'full_name',
                    'phone',
                    'city',
                    'district',
                    'ward',
                    'address_line',
                ];

                foreach (
                    array_diff(
                        array_keys($input),
                        $allowedFields,
                    ) as $field
                ) {
                    $validator->errors()->add(
                        (string) $field,
                        'This field is not allowed.',
                    );
                }

                if (
                    array_intersect(
                        array_keys($input),
                        $allowedFields,
                    ) === []
                ) {
                    $validator->errors()->add(
                        'address',
                        'At least one address field must be provided.',
                    );
                }
            },
        ];
    }
}
