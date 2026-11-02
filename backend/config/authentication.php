<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Personal Access Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Sanctum Bearer tokens issued by the Auth API expire after this
    | number of days. Existing issued tokens retain their own expires_at.
    |
    */

    'token_expiration_days' => (int) env(
        'AUTH_TOKEN_EXPIRATION_DAYS',
        30
    ),
];
