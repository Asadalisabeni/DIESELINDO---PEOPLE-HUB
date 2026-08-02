<?php

return [
    'blind_index_key' => env('PEOPLEHUB_BLIND_INDEX_KEY', env('APP_KEY')),

    'login' => [
        'max_attempts' => (int) env('IAM_MAX_LOGIN_ATTEMPTS', 5),
        'rate_limit_per_minute' => (int) env('IAM_LOGIN_RATE_LIMIT', 5),
        'lock_minutes' => (int) env('IAM_ACCOUNT_LOCK_MINUTES', 15),
    ],
    'password' => [
        'minimum_length' => (int) env('IAM_PASSWORD_MIN_LENGTH', 12),
        'check_compromised' => (bool) env('IAM_PASSWORD_COMPROMISED_CHECK', false),
    ],
];
