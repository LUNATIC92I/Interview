<?php

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'InterviewPro'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool) Env::get('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
    'key' => (string) Env::get('APP_KEY', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
    'locale' => Env::get('APP_LOCALE', 'fr'),
    'locales' => ['fr', 'en'],
    'install_token' => (string) Env::get('INSTALL_TOKEN', ''),
];
