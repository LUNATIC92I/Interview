<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;

final class Authenticate implements MiddlewareInterface
{
    public function handle(Request $request): ?Response
    {
        if (Auth::check()) {
            return null;
        }
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => t('auth.session_expired')], 401);
        }
        if ($request->method === 'GET') {
            Session::set('intended', $request->fullPath());
        }
        Session::flash('info', Session::wasExpired() ? t('auth.session_expired') : t('auth.login_required'));
        return Response::redirect('/login');
    }
}
