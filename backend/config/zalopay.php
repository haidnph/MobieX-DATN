
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZaloPay Sandbox / Production Configuration
    |--------------------------------------------------------------------------
    */
    'app_id' => env('ZALOPAY_APP_ID', 'SANDBOX_APP_ID'),
    'key1' => env('ZALOPAY_KEY1', 'SANDBOX_KEY1'),
    'key2' => env('ZALOPAY_KEY2', 'SANDBOX_KEY2'),
    'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/create'),
    'callback_url' => env('ZALOPAY_CALLBACK_URL', 'http://localhost:8000/api/payment/zalopay/callback'),
    'redirect_url' => env('ZALOPAY_REDIRECT_URL', 'http://localhost:5173/payment/zalopay/return'),
];
