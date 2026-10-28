<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use LogicException;

final class AuthTokenExpiration
{
    public static function expiresAt(): Carbon
    {
        $days = (int) config(
            'authentication.token_expiration_days',
            30
        );

        if ($days < 1) {
            throw new LogicException(
                'AUTH_TOKEN_EXPIRATION_DAYS must be greater than zero.'
            );
        }

        return now()->addDays($days);
    }
}
