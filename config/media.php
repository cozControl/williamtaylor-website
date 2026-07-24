<?php

return [
    'provider' => env('MEDIA_PROVIDER', 'cloudinary'),
    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_UPLOAD_FOLDER', env('APP_ENV', 'local').'/william-taylor/media'),
    ],
];
