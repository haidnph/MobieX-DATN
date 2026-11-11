<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VNPay Sandbox / Production Configuration
    |--------------------------------------------------------------------------
    */
    'tmn_code' => env('VNP_TMN_CODE', 'SANDBOX_TMN'),
    'hash_secret' => env('VNP_HASH_SECRET', 'SANDBOX_HASH_SECRET'),
    'url' => env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
    'return_url' => env('VNP_RETURN_URL', 'http://localhost:5173/payment/vnpay/return'),
];
