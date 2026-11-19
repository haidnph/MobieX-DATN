
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MoMo Sandbox / Production Configuration
    |--------------------------------------------------------------------------
    */
    'partner_code' => env('MOMO_PARTNER_CODE', 'SANDBOX_PARTNER'),
    'access_key' => env('MOMO_ACCESS_KEY', 'SANDBOX_ACCESS_KEY'),
    'secret_key' => env('MOMO_SECRET_KEY', 'SANDBOX_SECRET_KEY'),
    'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
    'return_url' => env('MOMO_RETURN_URL', 'http://localhost:5173/payment/momo/return'),
    'notify_url' => env('MOMO_NOTIFY_URL', 'http://localhost:8000/api/payment/momo/ipn'),
];
