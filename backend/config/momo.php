<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MoMo Sandbox / Production Configuration
    |--------------------------------------------------------------------------
    */
    'partner_code' => env('MOMO_PARTNER_CODE', ''),
    'access_key' => env('MOMO_ACCESS_KEY', ''),
    'secret_key' => env('MOMO_SECRET_KEY', ''),
    'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
    'query_endpoint' => env('MOMO_QUERY_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/query'),
    'refund_endpoint' => env('MOMO_REFUND_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/refund'),
    'refund_query_endpoint' => env('MOMO_REFUND_QUERY_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/refund/query'),
    'return_url' => env('MOMO_RETURN_URL', 'http://localhost:5173/payment/momo/return'),
    'callback_url' => env('MOMO_CALLBACK_URL', 'http://localhost:8000/api/payments/momo/callback'),
];
