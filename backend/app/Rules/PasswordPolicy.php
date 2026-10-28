<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PasswordPolicy implements ValidationRule
{
    public const MIN_CHARACTERS = 15;

    public const MAX_CHARACTERS = 64;

    public const MAX_BYTES = 72;

    /**
     * Validate a password against the shared Auth policy.
     *
     * The current default Laravel hasher is bcrypt, which has a
     * 72-byte input limit. Review this rule if the hashing driver
     * changes in the future.
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (! is_string($value)) {
            return;
        }

        $characters = mb_strlen($value, 'UTF-8');

        if ($characters < self::MIN_CHARACTERS) {
            $fail(
                'The :attribute field must be at least 15 characters.'
            );

            return;
        }

        if ($characters > self::MAX_CHARACTERS) {
            $fail(
                'The :attribute field must not exceed 64 characters.'
            );

            return;
        }

        if (strlen($value) > self::MAX_BYTES) {
            $fail(
                'The :attribute field must not exceed 72 UTF-8 bytes '
                    .'with the current password hashing configuration.'
            );
        }
    }
}
