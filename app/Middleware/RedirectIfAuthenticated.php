<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

final class RedirectIfAuthenticated implements MiddlewareInterface
{
    public function handle(Request $request): ?Response
    {
        return Auth::check() ? Response::redirect('/dashboard') : null;
    }
}
