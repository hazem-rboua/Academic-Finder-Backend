<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000'), 'https://twindix-academic-finder.netlify.app', 'https://academicfinder.twindix.com', 'http://localhost:5173', 'http://localhost:3000'],

    'allowed_origins_patterns' => ['/^https:\/\/.*\.twindix\.com$/'],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
