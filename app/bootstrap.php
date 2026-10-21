<?php

declare(strict_types=1);

/**
 * Application bootstrap: autoloading, environment, configuration and error handling.
 * Shared by the web front controller, the CLI console and the test runner.
 */

use App\Core\Config;
use App\Core\Env;
use App\Core\ErrorHandler;

define('BASE_PATH', dirname(__DIR__));

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    // Composer is optional: a minimal PSR-4 autoloader keeps the app usable on classic hosting.
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    require_once BASE_PATH . '/app/Helpers/functions.php';
}

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

ErrorHandler::register((bool) Config::get('app.debug', false));
