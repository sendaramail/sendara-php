<?php

declare(strict_types=1);

return [
    'api_key' => env('SENDARA_API_KEY'),

    'base_url' => env('SENDARA_BASE_URL', 'https://api.sendara.dev'),

    'timeout' => (int) env('SENDARA_TIMEOUT', 30),

    'max_retries' => (int) env('SENDARA_MAX_RETRIES', 2),
];
