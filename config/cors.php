<?php

return [
    'paths' => ['api/*', 'sanctum/*', 'google/*', 'linkedin/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://nextjs-frontend-beta-drab.vercel.app', 'http://localhost:3000', 'http://localhost:5174'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
