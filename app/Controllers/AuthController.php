<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Services\AuditLogger;
use App\Services\Auth;
use App\Services\Mailer;
use App\Services\RateLimiter;
use App\Services\Settings;
use App\Services\UserService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', ['title' => t('auth.login_title')], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $email = mb_strtolower($request->str('email'));
        $password = (string) ($request->post['password'] ?? '');
        $max = (int) Config::get('security.login_max_attempts', 5);
        $window = (int) Config::get('security.login_window_minutes', 15) * 60;
        $keyAccount = 'login:' . hash('sha256', $email);
        $keyIp = 'login-ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($keyAccount, $max, $window) || RateLimiter::tooManyAttempts($keyIp, $max * 4, $window)) {
            Logger::security('Login throttled', ['ip' => mask_ip($request->ip())]);
            return $this->back($request, '/login', [], t('auth.throttled', ['minutes' => intdiv($window, 60)]));
        }
        $v = $this->validate($request, ['email' => 'required|email', 'password' => 'required']);
        if ($v->fails()) {
            return $this->back($request, '/login', $v->errors());
        }
        $user = Auth::attempt($email, $password);
        if (!$user) {
            RateLimiter::hit($keyAccount, $window);
            RateLimiter::hit($keyIp, $window);
            Logger::security('Failed login', ['ip' => mask_ip($request->ip())]);
            return $this->back($request, '/login', [], t('auth.failed'));
        }
        RateLimiter::clear($keyAccount);
        Auth::login($user, $request);
        AuditLogger::log('user.login', 'user', (int) $user['id'], [], (int) $user['id']);
        $intended = (string) Session::get('intended', '/dashboard');
        Session::forget('intended');
        if (!preg_match('#^/(?!/)#', $intended) || str_starts_with($intended, '/api/')) {
            $intended = '/dashboard';
        }
        $this->flash('success', t('auth.welcome_back', ['name' => $user['display_name']]));
        return $this->redirect($intended);
    }

    public function showRegister(Request $request): Response
    {
        return $this->view('auth/register', ['title' => t('auth.register_title'), 'open' => Settings::bool('registration_open')], 'layouts/auth');
    }

    public function register(Request $request): Response
    {
        if (!Settings::bool('registration_open')) {
            return $this->back($request, '/register', [], t('auth.registration_closed'));
        }
        if (!RateLimiter::attempt('register:' . $request->ip(), 10, 3600)) {
            return $this->back($request, '/register', [], t('errors.429.text'));
        }
        $v = $this->validate($request, [
            'display_name' => 'required|max:120',
            'email' => 'required|email',
            'password' => 'required|min:' . Config::get('security.password_min_length', 10) . '|max:200|password|confirmed',
            'terms' => 'required',
        ]);
        $errors = $v->errors();
        $email = mb_strtolower($request->str('email'));
        if (!isset($errors['email']) && UserService::emailExists($email)) {
            // Same message as success would reveal the account; a neutral validation message is used instead.
            $errors['email'] = t('auth.email_unavailable');
        }
        if ($errors) {
            return $this->back($request, '/register', $errors);
        }
        $id = UserService::create($email, $request->str('display_name'), (string) $request->post['password'], Translator::locale());
        $user = Database::fetch('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        AuditLogger::log('user.registered', 'user', $id, [], $id);
        Auth::login($user, $request);
        if (Mailer::isConfigured() && UserService::sendVerification($user)) {
            $this->flash('info', t('auth.verify_sent'));
        }
        $this->flash('success', t('auth.registered'));
        return $this->redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        $id = Auth::id();
        if ($id) {
            Database::insert('login_history', ['user_id' => $id, 'event' => 'logout', 'ip_masked' => mask_ip($request->ip()), 'user_agent' => $request->userAgent(), 'created_at' => now()]);
        }
        Auth::logout();
        Session::flash('success', t('auth.logged_out'));
        return $this->redirect('/');
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot', ['title' => t('auth.forgot_title'), 'mailConfigured' => Mailer::isConfigured()], 'layouts/auth');
    }

    public function forgot(Request $request): Response
    {
        $v = $this->validate($request, ['email' => 'required|email']);
        if ($v->fails()) {
            return $this->back($request, '/password/forgot', $v->errors());
        }
        if (RateLimiter::attempt('reset:' . $request->ip(), 5, 900)) {
            UserService::requestPasswordReset($request->str('email'));
        }
        // Identical response whether or not the address exists.
        $this->flash('success', t('auth.reset_sent'));
        return $this->redirect('/login');
    }

    public function showReset(Request $request): Response
    {
        $token = (string) ($request->query['token'] ?? '');
        if (!UserService::tokenIsValid('password_reset_tokens', $token)) {
            $this->flash('error', t('auth.reset_invalid'));
            return $this->redirect('/password/forgot');
        }
        return $this->view('auth/reset', ['title' => t('auth.reset_title'), 'token' => $token], 'layouts/auth');
    }

    public function reset(Request $request): Response
    {
        $token = $request->str('token');
        $v = $this->validate($request, ['password' => 'required|min:' . Config::get('security.password_min_length', 10) . '|max:200|password|confirmed']);
        if ($v->fails()) {
            return $this->back($request, '/password/reset?token=' . urlencode($token), $v->errors());
        }
        if (!UserService::resetPassword($token, (string) $request->post['password'])) {
            $this->flash('error', t('auth.reset_invalid'));
            return $this->redirect('/password/forgot');
        }
        $this->flash('success', t('auth.reset_done'));
        return $this->redirect('/login');
    }

    public function verifyEmail(Request $request): Response
    {
        $userId = UserService::verifyEmail((string) ($request->query['token'] ?? ''));
        $this->flash($userId ? 'success' : 'error', t($userId ? 'auth.verified' : 'auth.verify_invalid'));
        return $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }

    public function resendVerification(Request $request): Response
    {
        $user = $this->user();
        if ($user['email_verified_at'] === null && RateLimiter::attempt('verify-mail:' . $user['id'], 3, 3600)) {
            UserService::sendVerification($user);
        }
        $this->flash('info', t('auth.verify_sent'));
        return $this->redirect('/dashboard');
    }
}
