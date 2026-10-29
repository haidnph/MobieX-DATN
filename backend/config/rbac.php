<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local development administrator
    |--------------------------------------------------------------------------
    |
    | These values are used exclusively by AdminUserSeeder.
    | Credentials are intentionally empty by default.
    |
    */
    'admin' => [
        'name' => env('RBAC_ADMIN_NAME', ''),
        'email' => env('RBAC_ADMIN_EMAIL', ''),
        'password' => env('RBAC_ADMIN_PASSWORD', ''),
    ],
];
