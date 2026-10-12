<?php

return [
    'base_url' => env('GBIF_BASE_URL', 'https://api.gbif.org'),
    'user_agent' => env('GBIF_USER_AGENT', 'chrysis.net-gbif/0.1 (+https://www.chrysis.net)'),
    'timeout' => (int) env('GBIF_TIMEOUT', 30),
    'retries' => (int) env('GBIF_RETRIES', 5),
    'backoff_ms' => (int) env('GBIF_BACKOFF_MS', 500),
    'rate_per_second' => (int) env('GBIF_RATE_PER_SECOND', 2),
    'cache' => [
        'match' => 60 * 60 * 24 * 30,
        'occurrence' => 60 * 60,
    ],
    'chrysididae_key' => 7903,
    // Download API (optional; disabled when empty)
    'download' => [
        'user' => env('GBIF_DOWNLOAD_USER'),
        'password' => env('GBIF_DOWNLOAD_PASSWORD'),
        'email' => env('GBIF_DOWNLOAD_EMAIL'),
    ],
];
