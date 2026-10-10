<?php

return [

    'tagline' => 'SelfDrive by GMT',

    'quota_bytes' => 15 * 1024 * 1024 * 1024,

    'max_upload_kb' => (int) env('SDRIVE_MAX_UPLOAD_KB', 40960),

    'onlyoffice' => [
        'url' => rtrim((string) env('ONLYOFFICE_URL', ''), '/'),
        'internal_url' => rtrim((string) env('ONLYOFFICE_INTERNAL_URL', ''), '/'),
        'jwt_secret' => (string) env('ONLYOFFICE_JWT_SECRET', ''),
    ],

];
