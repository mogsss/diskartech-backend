<?php

return [
    // Keep local development compatible; production should explicitly select cloudinary.
    'driver' => env('UPLOAD_STORAGE', 'public'),
    'document_link_minutes' => 60,
    'max_bytes' => 10 * 1024 * 1024,
    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_FOLDER', 'diskartech'),
    ],
];
