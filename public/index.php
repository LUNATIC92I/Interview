<?php

declare(strict_types=1);

/**
 * Single public entry point. Everything outside /public (code, configuration, logs, reports) is never
 * served directly by the web server.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Request;
use App\Core\Router;

$router = new Router();
(require BASE_PATH . '/routes/web.php')($router);

(new App($router))->handle(Request::capture())->send();
