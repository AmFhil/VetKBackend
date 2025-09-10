<?php

return [
    'paths' => ['api/*', 'sanctum/*', 'google/*', 'linkedin/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://nextjs-frontend-beta-drab.vercel.app'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
