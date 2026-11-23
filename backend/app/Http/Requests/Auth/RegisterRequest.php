<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')), 'UTF-8')]);
        }
    }

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
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $exists = User::query()
                        ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $value), 'UTF-8')])
                        ->exists();

                    if ($exists) {
                        $fail('The email has already been taken.');
                    }
                },
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'password' => [
                'required',
                'string',
                new PasswordPolicy,
                'confirmed',
            ],
        ];
    }
}
