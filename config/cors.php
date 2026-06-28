<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5000',
        'http://127.0.0.1:5000',
        'https://front.twindix.com',
        'https://assessment.twindix.com',
    ],
    'allowed_origins_patterns' => ['/^https:\/\/.*\.twindix\.com$/'],

    'allowed_headers' => ['*'],
    // Expose Content-Disposition so cross-origin JS (front.twindix) can read the
    // server-chosen report filename ("Academic Finder - Name - code - date.pdf")
    // from the report-pdf download response instead of hardcoding its own name.
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 0,
    'supports_credentials' => false,
];
