<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MoMo Sandbox / Production Configuration
    |--------------------------------------------------------------------------
    */
    'partner_code' => env('MOMO_PARTNER_CODE', 'MOMOBKUN20180529'),
    'access_key' => env('MOMO_ACCESS_KEY', 'klm0567shj9qRrA'),
    'secret_key' => env('MOMO_SECRET_KEY', 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa'),
    'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
    'query_endpoint' => env('MOMO_QUERY_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/query'),
    'return_url' => env('MOMO_RETURN_URL', 'http://localhost:5173/payment/momo/return'),
    'callback_url' => env('MOMO_CALLBACK_URL', 'http://localhost:8000/api/payments/momo/callback'),
];
