<?php

/**
 * Router for PHP's built-in development server only:
 *   php -S 127.0.0.1:8000 -t public server.php
 * Static files under /public are served directly; everything else goes to public/index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__ . '/public' . $path) && !str_ends_with($path, '.php')) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/public/index.php';
