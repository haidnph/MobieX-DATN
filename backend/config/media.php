<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Media Storage Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "local", "cloudinary"
    | Default is "local" to guarantee full backward-compatibility and zero
    | external service dependency by default.
    |
    */

    'default' => env('MEDIA_STORAGE_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Fallback on Cloud Failure
    |--------------------------------------------------------------------------
    |
    | If true, uploads will automatically fall back to local disk if Cloudinary
    | upload fails or credentials are not configured.
    |
    */

    'fallback_to_local' => env('MEDIA_FALLBACK_TO_LOCAL', true),

    /*
    |--------------------------------------------------------------------------
    | Media Drivers Configuration
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'local' => [
            'disk' => 'public',
            'url_prefix' => '/storage',
        ],

        'cloudinary' => [
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => env('CLOUDINARY_API_KEY'),
            'api_secret' => env('CLOUDINARY_API_SECRET'),
            'url' => env('CLOUDINARY_URL'),
            'folder' => env('CLOUDINARY_FOLDER', 'mobiex'),
            'secure' => (bool) env('CLOUDINARY_SECURE', true),
            'default_transformation' => [
                'quality' => 'auto',
                'fetch_format' => 'auto',
            ],
        ],

    ],

];
