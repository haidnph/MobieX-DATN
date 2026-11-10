<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZaloPay Sandbox / Production Configuration
    |--------------------------------------------------------------------------
    */
    'app_id' => env('ZALOPAY_APP_ID', '2553'),
    'key1' => env('ZALOPAY_KEY1', 'PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL'),
    'key2' => env('ZALOPAY_KEY2', 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz'),
    'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/create'),
    'query_endpoint' => env('ZALOPAY_QUERY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/query'),
    'return_url' => env('ZALOPAY_RETURN_URL', 'http://localhost:5173/payment/zalopay/return'),
    'callback_url' => env('ZALOPAY_CALLBACK_URL', 'http://localhost:8000/api/payments/zalopay/callback'),
];
