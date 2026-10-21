<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, string> */
    public array $params = [];
    private ?array $json = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $server,
        public readonly array $files = [],
        private readonly string $rawBody = '',
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        $path = '/' . trim(rawurldecode($uri), '/');
        $raw = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? (string) file_get_contents('php://input') : '';
        return new self($method, $path, $_GET, $_POST, $_SERVER, $_FILES, $raw);
    }

    /** Base path when the application is installed in a sub directory. */
    public static function basePath(): string
    {
        static $base = null;
        if ($base === null) {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            $base = ($dir === '.' || $dir === '/') ? '' : $dir;
        }
        return $base;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        $json = $this->json();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        $v = $this->input($key);
        return in_array($v, [true, 1, '1', 'on', 'yes', 'true'], true);
    }

    /** @return array<int|string, mixed> */
    public function arr(string $key): array
    {
        $v = $this->input($key, []);
        return is_array($v) ? $v : [];
    }

    public function json(): array
    {
        if ($this->json === null) {
            $this->json = [];
            if ($this->rawBody !== '' && str_contains($this->header('Content-Type'), 'application/json')) {
                $decoded = json_decode($this->rawBody, true);
                $this->json = is_array($decoded) ? $decoded : [];
            }
        }
        return $this->json;
    }

    /** Unparsed request body (needed to verify signed webhooks). */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type') {
            return (string) ($this->server['CONTENT_TYPE'] ?? $this->server[$key] ?? '');
        }
        return (string) ($this->server[$key] ?? '');
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('Accept'), 'application/json')
            || str_starts_with($this->path, '/api/');
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['SERVER_PORT'] ?? null) == 443
            || strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function fullPath(): string
    {
        $qs = $this->query ? '?' . http_build_query($this->query) : '';
        return $this->path . $qs;
    }
}
