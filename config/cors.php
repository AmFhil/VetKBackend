<?php
return [
    'paths' => [
        'api/*',
        'api/v3/*',
        'sanctum/*',
        'google/*',
        'linkedin/*',
        'vetkonect/public/api/*',
        'vetkonect/public/sanctum/*',
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5174',
        'https://nextjs-frontend-beta-drab.vercel.app',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false, // No cookies needed
];
