<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Browser-like HTTP client for end to end tests against the built-in PHP server: keeps cookies,
 * extracts CSRF tokens from pages and does not follow redirects automatically.
 */
final class HttpClient
{
    private string $jar;
    public ?string $csrf = null;

    public function __construct(private readonly string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'ipjar');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** @return array{status:int, body:string, headers:array<string,string>, location:?string} */
    public function request(string $method, string $path, array $data = [], array $headers = [], bool $json = false, ?array $file = null): array
    {
        $ch = curl_init($this->base . $path);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 60,
        ];
        if ($method !== 'GET') {
            if ($json) {
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Accept: application/json';
                $opts[CURLOPT_POSTFIELDS] = json_encode($data);
            } elseif ($file) {
                $data[$file['field']] = new \CURLFile($file['path'], $file['mime'] ?? 'text/csv', $file['name']);
                $opts[CURLOPT_POSTFIELDS] = $data;
            } else {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
            }
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headerText = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $parsed = [];
        foreach (explode("\r\n", $headerText) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $parsed[strtolower(trim($k))] = trim($v);
            }
        }
        if (preg_match('/name="_csrf" value="([a-f0-9]+)"/', $body, $m) || preg_match('/"csrf":"([a-f0-9]+)"/', $body, $m)) {
            $this->csrf = $m[1];
        }
        return ['status' => $status, 'body' => $body, 'headers' => $parsed, 'location' => $parsed['location'] ?? null];
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** POST a form, fetching a CSRF token first from $tokenPage when none is known. */
    public function post(string $path, array $data = [], ?string $tokenPage = null, bool $withToken = true): array
    {
        if ($withToken) {
            if ($this->csrf === null) {
                $this->get($tokenPage ?? '/login');
            }
            $data['_csrf'] ??= $this->csrf;
        }
        return $this->request('POST', $path, $data);
    }

    public function json(string $path, array $data = []): array
    {
        $res = $this->request('POST', $path, $data, ['X-CSRF-Token: ' . $this->csrf], true);
        $res['json'] = json_decode($res['body'], true) ?? [];
        return $res;
    }

    public function getJson(string $path): array
    {
        $res = $this->request('GET', $path, [], ['Accept: application/json']);
        $res['json'] = json_decode($res['body'], true) ?? [];
        return $res;
    }

    public function upload(string $path, array $data, array $file): array
    {
        $data['_csrf'] = $this->csrf;
        return $this->request('POST', $path, $data, [], false, $file);
    }

    public function register(string $email, string $password = 'Password123', string $name = 'Test User'): array
    {
        $this->get('/register');
        return $this->post('/register', ['display_name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $password, 'terms' => '1']);
    }

    public function login(string $email, string $password = 'Password123'): array
    {
        $this->get('/login');
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }
}
