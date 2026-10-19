<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\Auth;

abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'layouts/app'): Response
    {
        View::share('old', Session::pullOld());
        View::share('flash', Session::pullFlash());
        $user = Auth::user();
        View::share('authUser', $user);
        View::share('reduceMotion', $user ? (bool) \App\Core\Database::fetchColumn('SELECT reduce_motion FROM user_preferences WHERE user_id = :u', ['u' => $user['id']]) : false);
        return Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    /** Redirect back to a form with input and errors. */
    protected function back(Request $request, string $to, array $errors, ?string $message = null): Response
    {
        Session::withInput($request->post, $errors);
        Session::flash('error', $message ?? t('validation.form_errors'));
        return Response::redirect($to);
    }

    protected function validate(Request $request, array $rules): Validator
    {
        return Validator::make(array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $request->post), $rules);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function user(): array
    {
        $user = Auth::user();
        if ($user === null) {
            throw new HttpException(403);
        }
        return $user;
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }
}
