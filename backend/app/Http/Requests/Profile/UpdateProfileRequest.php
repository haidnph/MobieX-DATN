<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],
        ];
    }

    /**
     * Ensure the request contains only fields supported by the profile API.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $input = $this->all();
                $allowedFields = ['name', 'phone'];

                foreach (
                    array_diff(array_keys($input), $allowedFields) as $field
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
                        'profile',
                        'At least one of name or phone must be provided.',
                    );
                }
            },
        ];
    }
}
