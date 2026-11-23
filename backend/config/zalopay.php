<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZaloPay Sandbox / Production Configuration
    |--------------------------------------------------------------------------
    */
    'app_id' => env('ZALOPAY_APP_ID', ''),
    'key1' => env('ZALOPAY_KEY1', ''),
    'key2' => env('ZALOPAY_KEY2', ''),
    'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/create'),
    'query_endpoint' => env('ZALOPAY_QUERY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/query'),
    'refund_endpoint' => env('ZALOPAY_REFUND_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/refund'),
    'refund_query_endpoint' => env('ZALOPAY_REFUND_QUERY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/query_refund'),
    'return_url' => env('ZALOPAY_RETURN_URL', 'http://localhost:5173/payment/zalopay/return'),
    'callback_url' => env('ZALOPAY_CALLBACK_URL', 'http://localhost:8000/api/payments/zalopay/callback'),
];
