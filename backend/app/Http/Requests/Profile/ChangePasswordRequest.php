<?php

namespace App\Http\Requests\Profile;

use App\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangePasswordRequest extends FormRequest
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
            'current_password' => [
                'required',
                'string',
                'current_password:sanctum',
            ],

            'password' => [
                'required',
                'string',
                new PasswordPolicy,
                'confirmed',
            ],
        ];
    }

    /**
     * Ensure the request contains only fields supported by the password API.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedFields = [
                    'current_password',
                    'password',
                    'password_confirmation',
                ];

                foreach (
                    array_diff(array_keys($this->all()), $allowedFields) as $field
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
