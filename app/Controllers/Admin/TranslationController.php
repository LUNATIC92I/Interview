<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Translator;
use App\Helpers\Paginator;
use App\Services\AuditLogger;

/**
 * Interface strings come from lang/fr.php and lang/en.php; administrators can override any key here.
 * The page also lists content (questions, professions, scenarios) missing a French or English version.
 */
final class TranslationController extends AdminController
{
    public function index(Request $request): Response
    {
        $q = mb_strtolower(mb_substr(trim((string) ($request->query['q'] ?? '')), 0, 100));
        $onlyMissing = !empty($request->query['missing']);
        Translator::reset();
        $fr = Translator::lines('fr');
        $en = Translator::lines('en');
        $files = [
            'fr' => Translator::flatten(require BASE_PATH . '/lang/fr.php'),
            'en' => Translator::flatten(require BASE_PATH . '/lang/en.php'),
        ];
        $keys = array_unique(array_merge(array_keys($files['fr']), array_keys($files['en'])));
        sort($keys);
        $rows = [];
        foreach ($keys as $key) {
            $row = ['key' => $key, 'fr' => $fr[$key] ?? '', 'en' => $en[$key] ?? '',
                'overridden' => ($fr[$key] ?? null) !== ($files['fr'][$key] ?? null) || ($en[$key] ?? null) !== ($files['en'][$key] ?? null)];
            if ($onlyMissing && $row['fr'] !== '' && $row['en'] !== '') {
                continue;
            }
            if ($q !== '' && !str_contains(mb_strtolower($key . ' ' . $row['fr'] . ' ' . $row['en']), $q)) {
                continue;
            }
            $rows[] = $row;
        }
        $page = Paginator::page($request->query);
        $per = 40;
        $missingContent = [
            'questions_en' => (int) Database::fetchColumn("SELECT COUNT(*) FROM questions q WHERE NOT EXISTS (SELECT 1 FROM question_translations t WHERE t.question_id = q.id AND t.locale = 'en')"),
            'questions_fr' => (int) Database::fetchColumn("SELECT COUNT(*) FROM questions q WHERE NOT EXISTS (SELECT 1 FROM question_translations t WHERE t.question_id = q.id AND t.locale = 'fr')"),
            'professions' => (int) Database::fetchColumn('SELECT COUNT(*) FROM professions p WHERE (SELECT COUNT(*) FROM profession_translations t WHERE t.profession_id = p.id) < 2'),
            'scenarios' => (int) Database::fetchColumn('SELECT COUNT(*) FROM scenarios s WHERE (SELECT COUNT(*) FROM scenario_translations t WHERE t.scenario_id = s.id) < 2'),
        ];
        return $this->view('admin/translations/index', [
            'title' => t('admin.translations.title'),
            'rows' => array_slice($rows, ($page - 1) * $per, $per),
            'q' => $q, 'onlyMissing' => $onlyMissing, 'missingContent' => $missingContent,
            'pager' => new Paginator(count($rows), $page, $per, '/admin/translations', ['q' => $q, 'missing' => $onlyMissing ? '1' : '']),
        ]);
    }

    public function update(Request $request): Response
    {
        $key = $request->str('key');
        $files = [
            'fr' => Translator::flatten(require BASE_PATH . '/lang/fr.php'),
            'en' => Translator::flatten(require BASE_PATH . '/lang/en.php'),
        ];
        if (!isset($files['fr'][$key]) && !isset($files['en'][$key])) {
            $this->flash('error', t('validation.in'));
            return $this->redirect('/admin/translations');
        }
        foreach (['fr', 'en'] as $loc) {
            $value = trim((string) ($request->post['value_' . $loc] ?? ''));
            if ($value === '' || $value === ($files[$loc][$key] ?? null)) {
                Database::delete('translation_overrides', ['locale' => $loc, 'translation_key' => $key]);
            } else {
                Database::query(
                    'INSERT INTO translation_overrides (locale, translation_key, value, updated_by, updated_at) VALUES (:l, :k, :v, :u, :d)
                     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
                    ['l' => $loc, 'k' => $key, 'v' => mb_substr($value, 0, 2000), 'u' => \App\Services\Auth::id(), 'd' => now()]
                );
            }
        }
        Translator::reset();
        AuditLogger::log('admin.translation_updated', 'translation', $key);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/translations?q=' . urlencode($key));
    }
}
