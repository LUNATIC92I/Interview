<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple router: static and parameterised paths ({id} is numeric, other {name} parameters are slugs) with per route middleware.
 */
final class Router
{
    /** @var list<array{method:string, pattern:string, regex:string, handler:array, middleware:list<string>}> */
    private array $routes = [];
    /** @var list<string> */
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $routes): void
    {
        [$prevPrefix, $prevMw] = [$this->groupPrefix, $this->groupMiddleware];
        $this->groupPrefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $routes($this);
        [$this->groupPrefix, $this->groupMiddleware] = [$prevPrefix, $prevMw];
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $pattern = '/' . trim($this->groupPrefix . $path, '/');
        $regex = '#^' . preg_replace_callback(
            '/\{(\w+)\}/',
            static fn ($m) => $m[1] === 'id' ? '(?P<id>\d+)' : '(?P<' . $m[1] . '>[A-Za-z0-9_-]+)',
            $pattern
        ) . '$#';
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    /** @return array{0: array, 1: array<string,string>, 2: list<string>}|null|false  false = method not allowed */
    public function match(string $method, string $path): array|false|null
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $m)) {
                if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                    $allowed = true;
                    continue;
                }
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$route['handler'], $params, $route['middleware']];
            }
        }
        return $allowed ? false : null;
    }
}
