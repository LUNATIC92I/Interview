<?php

use App\Core\Env;

return [
    'session_idle_minutes' => (int) Env::get('SESSION_IDLE_MINUTES', 30),
    'session_absolute_hours' => (int) Env::get('SESSION_ABSOLUTE_HOURS', 12),
    'secure_cookie' => Env::get('SESSION_SECURE_COOKIE', 'auto'),
    'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_window_minutes' => (int) Env::get('LOGIN_WINDOW_MINUTES', 15),
    'password_min_length' => 10,
    'reset_token_minutes' => 60,
    'verify_token_hours' => 48,
    // Expensive operations (per user per hour)
    'max_sessions_per_hour' => 30,
    'max_pdf_per_hour' => 30,
    'max_import_rows' => 2000,
    'max_import_bytes' => 2 * 1024 * 1024,
];
