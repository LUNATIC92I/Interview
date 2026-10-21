<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Synchroniser token pattern. The token is bound to the session and checked on every state changing request,
 * either from the "_csrf" form field or the "X-CSRF-Token" header used by fetch() calls.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(Request $request): bool
    {
        $sent = (string) ($request->post['_csrf'] ?? $request->header('X-CSRF-Token'));
        if ($sent === '') {
            $json = $request->json();
            $sent = (string) ($json['_csrf'] ?? '');
        }
        $expected = $_SESSION['_csrf'] ?? '';
        return $expected !== '' && $sent !== '' && hash_equals($expected, $sent);
    }
}
