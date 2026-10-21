<?php

use App\Core\Env;

return [
    'driver' => Env::get('MAIL_DRIVER', 'log'),
    'from' => Env::get('MAIL_FROM', 'no-reply@example.com'),
    'from_name' => Env::get('MAIL_FROM_NAME', 'InterviewPro'),
    'host' => Env::get('MAIL_HOST', ''),
    'port' => (int) Env::get('MAIL_PORT', 587),
    'username' => (string) Env::get('MAIL_USERNAME', ''),
    'password' => (string) Env::get('MAIL_PASSWORD', ''),
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
];
