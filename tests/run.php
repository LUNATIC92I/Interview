<?php

declare(strict_types=1);

/**
 * InterviewPro test runner (no external dependency).
 *
 *   php tests/run.php                 run unit and HTTP tests
 *   php tests/run.php --filter=Csv    run test classes whose name contains "Csv"
 *   php tests/run.php --browser       also run the Chromium end to end checks (tests/browser/e2e.mjs)
 *
 * Uses a dedicated database (TEST_DB_DATABASE, default interviewpro_test) which is wiped and rebuilt
 * from the migrations and seeders, and a PHP built-in server started on a free port.
 */

$opts = getopt('', ['filter::', 'browser']);
$testDb = getenv('TEST_DB_DATABASE') ?: 'interviewpro_test';
$env = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'test-key',
    'DB_DATABASE' => $testDb,
    'MAIL_DRIVER' => 'log',
    'SESSION_SECURE_COOKIE' => 'false',
    'LOGIN_MAX_ATTEMPTS' => '5',
    'AI_PROVIDER' => '',
    'INSTALL_TOKEN' => '',
    'PAYMENT_DRIVER' => 'sandbox',
];
foreach ($env as $k => $v) {
    putenv("$k=$v");
}
define('TESTING_UPLOADS', true);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Migrator;
use App\Services\UserService;
use Tests\Support\AssertionFailed;
use Tests\Support\Context;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Tests\\')) {
        $file = BASE_PATH . '/tests/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$out = static fn (string $s) => fwrite(STDOUT, $s . PHP_EOL);

// 1. Fresh test database
$out("Preparing test database `$testDb`...");
$pdo = Database::connection();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $pdo->exec("DROP TABLE `$table`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
(new Migrator(BASE_PATH . '/database/migrations'))->migrate();
require BASE_PATH . '/database/seeders/DatabaseSeeder.php';
(new DatabaseSeeder($out))->run();
UserService::createAdmin(Context::$adminEmail, 'Test Admin', Context::$adminPassword);
// Paid access is exercised by the Payment tests, which switch it on; the other suites run with free access.
App\Services\Settings::set('paywall_enabled', '0');

// 2. Built-in web server
$sock = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
fclose($sock);
Context::$port = $port;
Context::$base = "http://127.0.0.1:$port";
$serverEnv = array_merge(getenv(), $env);
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public', BASE_PATH . '/server.php'],
    [0 => ['pipe', 'r'], 1 => ['file', BASE_PATH . '/tests/output/server.log', 'w'], 2 => ['file', BASE_PATH . '/tests/output/server.log', 'a']],
    $pipes,
    BASE_PATH,
    $serverEnv
);
register_shutdown_function(static function () use ($server) {
    proc_terminate($server);
});
for ($i = 0; $i < 50; $i++) {
    if (@file_get_contents(Context::$base . '/login') !== false) {
        break;
    }
    usleep(100000);
}
$out('Test server on ' . Context::$base);

// 3. Discover and run tests
$filter = $opts['filter'] ?? null;
$files = array_merge(glob(BASE_PATH . '/tests/Unit/*Test.php') ?: [], glob(BASE_PATH . '/tests/Http/*Test.php') ?: []);
$passed = $failed = $assertions = 0;
$failures = [];
$started = microtime(true);
foreach ($files as $file) {
    $class = 'Tests\\' . basename(dirname($file)) . '\\' . basename($file, '.php');
    if ($filter && !str_contains($class, $filter)) {
        continue;
    }
    require_once $file;
    Database::query('DELETE FROM rate_limits');
    $instance = new $class();
    try {
        $instance->setUpClass();
    } catch (Throwable $e) {
        $failed++;
        $failures[] = "$class::setUpClass\n    " . $e->getMessage();
        continue;
    }
    foreach (get_class_methods($instance) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $label = substr(strrchr($class, '\\'), 1) . '::' . $method;
        try {
            $instance->$method();
            $passed++;
            $out("  PASS  $label");
        } catch (Throwable $e) {
            $failed++;
            $where = $e instanceof AssertionFailed ? '' : ' [' . $e::class . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
            $failures[] = "$label\n    " . $e->getMessage() . $where;
            $out("  FAIL  $label");
        }
    }
    $assertions += $instance->assertions;
}

$browserOk = true;
if (isset($opts['browser'])) {
    $out('Running browser end to end checks...');
    passthru('node ' . escapeshellarg(BASE_PATH . '/tests/browser/e2e.mjs') . ' ' . escapeshellarg(Context::$base) . ' ' . escapeshellarg(BASE_PATH . '/tests/output'), $code);
    $browserOk = $code === 0;
}

$out('');
foreach ($failures as $f) {
    $out("FAILED: $f");
}
$out(sprintf('%d passed, %d failed, %d assertions in %.1fs%s', $passed, $failed, $assertions, microtime(true) - $started, isset($opts['browser']) ? ($browserOk ? ', browser checks passed' : ', BROWSER CHECKS FAILED') : ''));
exit($failed === 0 && $browserOk ? 0 : 1);
