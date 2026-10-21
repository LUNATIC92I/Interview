<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\Context;
use Tests\Support\TestCase;

/**
 * Starts a second server pointing to an unreachable database and checks the friendly 503 page.
 */
final class ResilienceTest extends TestCase
{
    public function testDatabaseUnavailableShowsFriendlyError(): void
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
        fclose($sock);
        $env = array_merge(getenv(), ['DB_PASSWORD' => 'definitely-wrong', 'DB_USERNAME' => 'nobody', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'APP_DEBUG' => 'false']);
        $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public', BASE_PATH . '/server.php'], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, BASE_PATH, $env);
        try {
            $client = new \Tests\Support\HttpClient("http://127.0.0.1:$port");
            $r = ['status' => 0, 'body' => ''];
            for ($i = 0; $i < 40 && $r['status'] === 0; $i++) {
                usleep(100000);
                $r = $client->get('/');
            }
            $this->assertSame(503, $r['status']);
            $this->assertContains('base de données', $r['body'] . '', 'friendly message');
            $this->assertNotContains('SQLSTATE', $r['body']);
            $this->assertNotContains('PDOException', $r['body']);
            $api = $client->getJson('/professions');
            $this->assertSame(503, $api['status']);
        } finally {
            proc_terminate($proc);
        }
    }
}
