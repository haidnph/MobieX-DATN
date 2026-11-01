<?php

namespace App\Http\Requests\Addresses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAddressRequest extends FormRequest
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
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:20',
            ],

            'city' => [
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
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Reject fields outside the address creation contract.
     *
     * user_id and is_default are intentionally not writable here.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
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
                        array_keys($this->all()),
                        $allowedFields,
                    ) as $field
                ) {
                    $validator->errors()->add(
                        (string) $field,
                        'This field is not allowed.',
                    );
                }
            },
        ];
    }
}
