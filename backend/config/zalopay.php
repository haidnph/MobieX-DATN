<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZaloPay Sandbox / Production Configuration
    |--------------------------------------------------------------------------
    */
    'app_id' => env('ZALOPAY_APP_ID', '2554'),
    'key1' => env('ZALOPAY_KEY1', 'sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn'),
    'key2' => env('ZALOPAY_KEY2', 'trMrHtvjo6myautxDUiAcYsVtaeQ8nhf'),
    'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/create'),
    'query_endpoint' => env('ZALOPAY_QUERY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/query'),
    'return_url' => env('ZALOPAY_RETURN_URL', 'http://localhost:5173/payment/zalopay/return'),
    'callback_url' => env('ZALOPAY_CALLBACK_URL', 'http://localhost:8000/api/payments/zalopay/callback'),
];
