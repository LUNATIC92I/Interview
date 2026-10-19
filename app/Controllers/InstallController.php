<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Migrator;
use App\Services\UserService;

/**
 * Optional web installer for hosting without shell access. Enabled only when INSTALL_TOKEN is set in .env
 * and no administrator exists yet; it runs migrations, loads the reference data and creates the first admin.
 * Remove INSTALL_TOKEN once the installation is done.
 */
final class InstallController extends Controller
{
    private function guard(): void
    {
        if (Config::get('app.install_token', '') === '') {
            throw new HttpException(404);
        }
        try {
            if (UserService::adminExists()) {
                throw new HttpException(404);
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            // Tables not created yet: installation allowed.
        }
    }

    public function show(Request $request): Response
    {
        $this->guard();
        return $this->view('auth/install', ['title' => t('install.title')], 'layouts/auth');
    }

    public function install(Request $request): Response
    {
        $this->guard();
        if (!hash_equals((string) Config::get('app.install_token'), $request->str('token'))) {
            return $this->back($request, '/install', ['token' => t('install.bad_token')]);
        }
        $v = $this->validate($request, [
            'email' => 'required|email',
            'display_name' => 'required|max:120',
            'password' => 'required|min:' . Config::get('security.password_min_length', 10) . '|password|confirmed',
        ]);
        if ($v->fails()) {
            return $this->back($request, '/install', $v->errors());
        }
        (new Migrator(BASE_PATH . '/database/migrations'))->migrate();
        require_once BASE_PATH . '/database/seeders/DatabaseSeeder.php';
        (new \DatabaseSeeder(static fn () => null))->run();
        UserService::createAdmin($request->str('email'), $request->str('display_name'), (string) $request->post['password']);
        $this->flash('success', t('install.done'));
        return $this->redirect('/login');
    }
}
