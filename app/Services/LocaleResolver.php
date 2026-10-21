<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;

/**
 * Locale priority: explicit session choice, then Accept-Language, then the configured default.
 * The session choice is synchronised with the user's stored preference on login and on switch.
 */
final class LocaleResolver
{
    public static function resolve(Request $request): string
    {
        $supported = Config::get('app.locales', ['fr', 'en']);
        $session = Session::get('locale');
        if (is_string($session) && in_array($session, $supported, true)) {
            return $session;
        }
        $header = strtolower($request->header('Accept-Language'));
        foreach (explode(',', $header) as $part) {
            $code = substr(trim($part), 0, 2);
            if (in_array($code, $supported, true)) {
                return $code;
            }
        }
        return (string) Config::get('app.locale', 'fr');
    }
}
