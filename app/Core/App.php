<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;
use App\Services\Auth;
use App\Services\LocaleResolver;

/**
 * HTTP kernel: starts the session, resolves the locale, applies CSRF protection and route middleware,
 * dispatches to the controller and converts failures into safe, translated error pages.
 */
final class App
{
    /** @var array<string, class-string<MiddlewareInterface>> */
    private const MIDDLEWARE = [
        'auth' => \App\Middleware\Authenticate::class,
        'guest' => \App\Middleware\RedirectIfAuthenticated::class,
        'admin' => \App\Middleware\RequireAdmin::class,
        'verified' => \App\Middleware\RequireVerifiedEmail::class,
    ];

    public function __construct(private readonly Router $router)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            Session::start($request);
            Translator::setLocale(LocaleResolver::resolve($request));
            View::share('request', $request);

            $match = $this->router->match($request->method, $request->path);
            if ($match === null) {
                throw new HttpException(404);
            }
            if ($match === false) {
                throw new HttpException(405);
            }
            [$handler, $params, $middleware] = $match;
            $request->params = $params;

            // Payment provider webhooks come from outside the browser session: they are authenticated by their
            // own signature (see PaymentService::handleWebhook) instead of the CSRF token.
            $isWebhook = str_starts_with($request->path, '/webhooks/');
            if (!$isWebhook && in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !Csrf::verify($request)) {
                Logger::security('CSRF token mismatch', ['path' => $request->path, 'ip' => $request->ip()]);
                throw new HttpException(419);
            }

            foreach ($middleware as $name) {
                $class = self::MIDDLEWARE[$name] ?? throw new \LogicException('Unknown middleware ' . $name);
                $result = (new $class())->handle($request);
                if ($result instanceof Response) {
                    return $this->secure($result, $request);
                }
            }

            [$class, $method] = $handler;
            $response = (new $class())->$method($request, ...array_values($params));
            if (is_string($response)) {
                $response = Response::html($response);
            }
            return $this->secure($response, $request);
        } catch (HttpException $e) {
            return $this->secure($this->errorResponse($request, $e->status), $request);
        } catch (DatabaseUnavailableException $e) {
            return $this->secure($this->errorResponse($request, 503), $request);
        } catch (\Throwable $e) {
            ErrorHandler::report($e);
            if (ErrorHandler::isDebug()) {
                return Response::html('<pre>' . e($e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>', 500);
            }
            return $this->secure($this->errorResponse($request, 500), $request);
        }
    }

    private function errorResponse(Request $request, int $status): Response
    {
        $known = [403, 404, 405, 419, 429, 500, 503];
        $code = in_array($status, $known, true) ? $status : 500;
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => t('errors.' . $code . '.title'), 'message' => t('errors.' . $code . '.text')], $code);
        }
        try {
            $user = $code === 503 ? null : Auth::user();
        } catch (\Throwable) {
            $user = null;
        }
        View::share('authUser', $user);
        try {
            $html = View::render('errors/error', ['code' => $code, 'title' => t('errors.' . $code . '.title')], 'layouts/minimal');
        } catch (\Throwable $e) {
            ErrorHandler::report($e);
            $html = '<!doctype html><meta charset="utf-8"><title>Error ' . $code . '</title><p>Error ' . $code . '</p>';
        }
        return Response::html($html, $code);
    }

    private function secure(Response $response, Request $request): Response
    {
        $csp = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; "
            . "frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'";
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => $csp,
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($request->isSecure()) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        if (isset($_SESSION['user_id'])) {
            $defaults['Cache-Control'] = 'private, no-store';
        }
        $response->headers = array_merge($defaults, $response->headers);
        return $response;
    }
}
