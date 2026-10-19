<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Difficulty;
use App\Services\AuditLogger;
use App\Services\EvaluationService;
use App\Services\Mailer;
use App\Services\Settings;

final class SettingsController extends AdminController
{
    public function index(Request $request): Response
    {
        $values = [];
        foreach (Settings::DEFAULTS as $key => $_) {
            $values[$key] = Settings::get($key);
        }
        return $this->view('admin/settings/index', [
            'title' => t('admin.settings.title'),
            'values' => $values,
            'mailConfigured' => Mailer::isConfigured(),
            'llmConfigured' => (string) \App\Core\Config::get('ai.provider') !== '' && (string) \App\Core\Config::get('ai.api_key') !== '',
            'llmActive' => EvaluationService::llmAvailable(),
        ]);
    }

    public function update(Request $request): Response
    {
        $v = $this->validate($request, [
            'max_sessions_per_day' => 'required|int|between:1,500',
            'default_question_count' => 'required|int|between:3,15',
            'contact_email' => 'required|email',
            'maintenance_message' => 'nullable|max:500',
        ]);
        if ($v->fails()) {
            return $this->back($request, '/admin/settings', $v->errors());
        }
        foreach (Settings::DEFAULTS as $key => [$type]) {
            $value = $type === 'bool' ? ($request->bool($key) ? '1' : '0') : $request->str($key);
            Settings::set($key, $value);
        }
        AuditLogger::log('admin.settings_updated', 'settings');
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/settings');
    }

    public function difficulties(Request $request): Response
    {
        return $this->view('admin/settings/difficulties', ['title' => t('admin.difficulties.title'), 'items' => Difficulty::all()]);
    }

    public function updateDifficulties(Request $request): Response
    {
        $errors = [];
        foreach ($request->arr('levels') as $id => $row) {
            if (!is_array($row) || trim((string) ($row['name_fr'] ?? '')) === '' || trim((string) ($row['name_en'] ?? '')) === '') {
                $errors['levels'] = t('validation.required');
            }
        }
        if ($errors) {
            return $this->back($request, '/admin/difficulties', $errors);
        }
        foreach ($request->arr('levels') as $id => $row) {
            Database::query(
                'UPDATE difficulty_levels SET name_fr = :nf, name_en = :ne, description_fr = :df, description_en = :de WHERE id = :id',
                ['nf' => mb_substr(trim((string) $row['name_fr']), 0, 80), 'ne' => mb_substr(trim((string) $row['name_en']), 0, 80),
                 'df' => mb_substr(trim((string) ($row['description_fr'] ?? '')), 0, 255) ?: null,
                 'de' => mb_substr(trim((string) ($row['description_en'] ?? '')), 0, 255) ?: null, 'id' => (int) $id]
            );
        }
        Difficulty::flush();
        AuditLogger::log('admin.difficulties_updated', 'difficulty');
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/difficulties');
    }
}
