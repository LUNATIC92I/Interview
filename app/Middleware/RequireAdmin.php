<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

final class RequireAdmin implements MiddlewareInterface
{
    public function handle(Request $request): ?Response
    {
        if (!Auth::check()) {
            return (new Authenticate())->handle($request);
        }
        if (!Auth::isAdmin()) {
            Logger::security('Forbidden admin access', ['user_id' => Auth::id(), 'path' => $request->path]);
            throw new HttpException(403);
        }
        return null;
    }
}
