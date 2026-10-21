<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;
use App\Services\Mailer;
use App\Services\Settings;

/**
 * Blocks simulations for unverified accounts only when e-mail is configured and the setting requires it.
 */
final class RequireVerifiedEmail implements MiddlewareInterface
{
    public function handle(Request $request): ?Response
    {
        $user = Auth::user();
        if ($user === null || $user['email_verified_at'] !== null || !Mailer::isConfigured() || !Settings::bool('require_email_verification')) {
            return null;
        }
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => t('auth.verify_required')], 403);
        }
        Session::flash('warning', t('auth.verify_required'));
        return Response::redirect('/dashboard');
    }
}
