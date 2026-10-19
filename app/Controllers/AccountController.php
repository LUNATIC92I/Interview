<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Domain;
use App\Models\Profession;
use App\Services\AuditLogger;
use App\Services\Auth;
use App\Services\UserService;

final class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user();
        return $this->view('account/index', [
            'payments' => PaymentController::history((int) $user['id']),
            'title' => t('account.title'),
            'profile' => Database::fetch('SELECT * FROM profiles WHERE user_id = :u', ['u' => $user['id']]) ?? [],
            'prefs' => Database::fetch('SELECT * FROM user_preferences WHERE user_id = :u', ['u' => $user['id']]) ?? [],
            'professions' => Profession::options(),
            'logins' => Database::fetchAll('SELECT * FROM login_history WHERE user_id = :u ORDER BY created_at DESC LIMIT 15', ['u' => $user['id']]),
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $user = $this->user();
        $v = $this->validate($request, [
            'display_name' => 'required|max:120',
            'headline' => 'nullable|max:160',
            'current_position' => 'nullable|max:160',
            'country' => 'nullable|max:80',
            'bio' => 'nullable|max:2000',
            'experience_level' => 'nullable|in:' . implode(',', array_keys(Domain::LEVELS)),
            'target_profession_id' => 'nullable|int',
        ]);
        $errors = $v->errors();
        $profId = $request->int('target_profession_id') ?: null;
        if ($profId && !Profession::find($profId)) {
            $errors['target_profession_id'] = t('validation.in');
        }
        if ($errors) {
            return $this->back($request, '/account#profile', $errors);
        }
        Database::transaction(static function () use ($user, $request, $profId) {
            Database::update('users', ['display_name' => $request->str('display_name'), 'updated_at' => now()], ['id' => $user['id']]);
            Database::query(
                'INSERT INTO profiles (user_id, headline, current_position, target_profession_id, experience_level, country, bio, updated_at)
                 VALUES (:u, :h, :c, :p, :l, :co, :b, :d)
                 ON DUPLICATE KEY UPDATE headline = VALUES(headline), current_position = VALUES(current_position),
                    target_profession_id = VALUES(target_profession_id), experience_level = VALUES(experience_level),
                    country = VALUES(country), bio = VALUES(bio), updated_at = VALUES(updated_at)',
                ['u' => $user['id'], 'h' => $request->str('headline') ?: null, 'c' => $request->str('current_position') ?: null,
                 'p' => $profId, 'l' => $request->int('experience_level') ?: null, 'co' => $request->str('country') ?: null,
                 'b' => $request->str('bio') ?: null, 'd' => now()]
            );
        });
        Auth::refresh();
        $this->flash('success', t('account.profile_saved'));
        return $this->redirect('/account#profile');
    }

    public function updatePreferences(Request $request): Response
    {
        $user = $this->user();
        $v = $this->validate($request, [
            'locale' => 'required|in:fr,en',
            'default_mode' => 'required|in:' . implode(',', array_keys(Domain::MODES)),
            'default_question_count' => 'required|int|between:3,15',
        ]);
        if ($v->fails()) {
            return $this->back($request, '/account#preferences', $v->errors());
        }
        $locale = $request->str('locale');
        Database::query(
            'INSERT INTO user_preferences (user_id, locale, default_mode, default_question_count, reduce_motion, email_notifications, updated_at)
             VALUES (:u, :l, :m, :q, :r, :e, :d)
             ON DUPLICATE KEY UPDATE locale = VALUES(locale), default_mode = VALUES(default_mode), default_question_count = VALUES(default_question_count),
                reduce_motion = VALUES(reduce_motion), email_notifications = VALUES(email_notifications), updated_at = VALUES(updated_at)',
            ['u' => $user['id'], 'l' => $locale, 'm' => $request->str('default_mode'), 'q' => $request->int('default_question_count'),
             'r' => $request->bool('reduce_motion') ? 1 : 0, 'e' => $request->bool('email_notifications') ? 1 : 0, 'd' => now()]
        );
        Database::update('users', ['locale' => $locale, 'updated_at' => now()], ['id' => $user['id']]);
        Session::set('locale', $locale);
        $this->flash('success', t('account.preferences_saved', [], $locale));
        return $this->redirect('/account#preferences');
    }

    public function updatePassword(Request $request): Response
    {
        $user = $this->user();
        $v = $this->validate($request, [
            'current_password' => 'required',
            'password' => 'required|min:' . Config::get('security.password_min_length', 10) . '|max:200|password|confirmed',
        ]);
        $errors = $v->errors();
        if (!isset($errors['current_password']) && !password_verify((string) $request->post['current_password'], $user['password_hash'])) {
            $errors['current_password'] = t('account.wrong_password');
        }
        if ($errors) {
            return $this->back($request, '/account#security', $errors);
        }
        Database::update('users', ['password_hash' => password_hash((string) $request->post['password'], PASSWORD_DEFAULT), 'updated_at' => now()], ['id' => $user['id']]);
        Session::regenerate();
        AuditLogger::log('user.password_changed', 'user', (int) $user['id']);
        $this->flash('success', t('account.password_saved'));
        return $this->redirect('/account#security');
    }

    public function delete(Request $request): Response
    {
        $user = $this->user();
        $errors = [];
        if (!password_verify((string) ($request->post['current_password'] ?? ''), $user['password_hash'])) {
            $errors['delete_password'] = t('account.wrong_password');
        }
        if (mb_strtoupper($request->str('confirm')) !== mb_strtoupper(t('account.delete_word'))) {
            $errors['confirm'] = t('account.delete_confirm_error', ['word' => t('account.delete_word')]);
        }
        if ($errors) {
            return $this->back($request, '/account#danger', $errors);
        }
        if (in_array('admin', $user['roles'], true)
            && (int) Database::fetchColumn("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.code = 'admin'") <= 1) {
            return $this->back($request, '/account#danger', [], t('account.last_admin'));
        }
        UserService::deleteAccount((int) $user['id']);
        Auth::logout();
        Session::flash('success', t('account.deleted'));
        return $this->redirect('/');
    }
}
