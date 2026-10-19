<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Paginator;
use App\Models\Category;
use App\Models\Profession;
use App\Models\Question;
use App\Models\Rubric;
use App\Models\Skill;
use App\Services\AuditLogger;
use App\Services\QuestionCsv;
use App\Services\QuestionService;
use App\Services\RateLimiter;

final class QuestionController extends AdminController
{
    public function index(Request $request): Response
    {
        $filters = array_map(static fn ($v) => is_string($v) ? mb_substr(trim($v), 0, 100) : '', array_intersect_key($request->query, array_flip(['q', 'type', 'difficulty', 'profession', 'category', 'active', 'scope', 'missing'])));
        $page = Paginator::page($request->query);
        $result = Question::search($filters, $page, 25);
        return $this->view('admin/questions/index', [
            'title' => t('admin.questions.title'), 'items' => $result['items'], 'filters' => $filters,
            'professions' => Profession::options(false), 'categories' => Category::all(false),
            'pager' => new Paginator($result['total'], $page, 25, '/admin/questions', $filters),
        ]);
    }

    private function form(?array $item, array $tr, array $skillIds, array $followups, string $title): Response
    {
        return $this->view('admin/questions/form', [
            'title' => $title, 'item' => $item, 'tr' => $tr, 'skillIds' => $skillIds, 'followups' => $followups,
            'professions' => Profession::options(false), 'categories' => Category::all(false),
            'rubrics' => Rubric::all(), 'skills' => Skill::all(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, [], [], [], t('admin.questions.new'));
    }

    public function edit(Request $request, string $id): Response
    {
        $item = Database::fetch('SELECT * FROM questions WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $skillIds = array_map('intval', array_column(Database::fetchAll('SELECT skill_id FROM question_skills WHERE question_id = :id', ['id' => (int) $id]), 'skill_id'));
        $followups = [];
        foreach (Database::fetchAll('SELECT * FROM question_followups WHERE question_id = :id AND is_active = 1 ORDER BY sort_order', ['id' => (int) $id]) as $f) {
            $trs = array_column(Database::fetchAll('SELECT * FROM question_followup_translations WHERE followup_id = :f', ['f' => $f['id']]), null, 'locale');
            $followups[] = ['trigger_type' => $f['trigger_type'], 'min_words' => $f['min_words'],
                'fr' => $trs['fr']['body'] ?? '', 'en' => $trs['en']['body'] ?? '', 'kw_fr' => $trs['fr']['trigger_keywords'] ?? '', 'kw_en' => $trs['en']['trigger_keywords'] ?? ''];
        }
        $usage = Question::usageCount((int) $id);
        return $this->form($item + ['usage' => $usage], Question::translations((int) $id), $skillIds, $followups, t('admin.questions.edit'));
    }

    /** Build the normalised question payload from the form. */
    private function payload(Request $request): array
    {
        $p = $request->post;
        $data = [
            'code' => trim((string) ($p['code'] ?? '')),
            'question_type' => (string) ($p['question_type'] ?? ''),
            'profession_id' => ($p['profession_id'] ?? '') !== '' ? (int) $p['profession_id'] : null,
            'category_id' => ($p['category_id'] ?? '') !== '' ? (int) $p['category_id'] : null,
            'level_min' => (int) ($p['level_min'] ?? 1),
            'level_max' => (int) ($p['level_max'] ?? 5),
            'difficulty' => (int) ($p['difficulty'] ?? 2),
            'rubric_id' => (int) ($p['rubric_id'] ?? 0),
            'recommended_seconds' => (int) ($p['recommended_seconds'] ?? 180),
            'is_active' => !empty($p['is_active']),
            'skill_ids' => array_map('intval', is_array($p['skills'] ?? null) ? $p['skills'] : []),
            'translations' => [],
            'followups' => [],
        ];
        foreach (['fr', 'en'] as $loc) {
            $data['translations'][$loc] = [
                'body' => (string) ($p['body_' . $loc] ?? ''),
                'guidance' => (string) ($p['guidance_' . $loc] ?? ''),
                'evaluation_criteria' => (string) ($p['criteria_' . $loc] ?? ''),
                'expected_elements' => QuestionService::parseElements((string) ($p['expected_' . $loc] ?? '')),
                'common_mistakes' => QuestionService::parseLines((string) ($p['mistakes_' . $loc] ?? '')),
                'example_answer' => (string) ($p['example_' . $loc] ?? ''),
                'keywords' => (string) ($p['keywords_' . $loc] ?? ''),
            ];
        }
        foreach (is_array($p['followups'] ?? null) ? $p['followups'] : [] as $f) {
            if (!is_array($f)) {
                continue;
            }
            $data['followups'][] = [
                'trigger_type' => (string) ($f['trigger_type'] ?? 'always'), 'min_words' => (int) ($f['min_words'] ?? 0),
                'fr' => (string) ($f['fr'] ?? ''), 'en' => (string) ($f['en'] ?? ''),
                'kw_fr' => (string) ($f['kw_fr'] ?? ''), 'kw_en' => (string) ($f['kw_en'] ?? ''),
            ];
        }
        return $data;
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request);
        if ($errors = QuestionService::validate($data)) {
            return $this->back($request, '/admin/questions/new', $errors);
        }
        $id = QuestionService::save(null, $data);
        AuditLogger::log('admin.question_created', 'question', $id, ['code' => $data['code']]);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/questions/' . $id . '/edit');
    }

    public function update(Request $request, string $id): Response
    {
        $id = (int) $id;
        Database::fetch('SELECT id FROM questions WHERE id = :id', ['id' => $id]) ?? throw new HttpException(404);
        $data = $this->payload($request);
        if ($errors = QuestionService::validate($data, $id)) {
            return $this->back($request, "/admin/questions/$id/edit", $errors);
        }
        QuestionService::save($id, $data);
        AuditLogger::log('admin.question_updated', 'question', $id, ['code' => $data['code']]);
        $this->flash('success', t('admin.saved'));
        return $this->redirect("/admin/questions/$id/edit");
    }

    public function destroy(Request $request, string $id): Response
    {
        Database::fetch('SELECT id FROM questions WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $result = QuestionService::delete((int) $id);
        AuditLogger::log('admin.question_' . $result, 'question', (int) $id);
        $this->flash($result === 'deleted' ? 'success' : 'info', t($result === 'deleted' ? 'admin.deleted' : 'admin.deactivated_in_use'));
        return $this->redirect('/admin/questions');
    }

    public function export(Request $request): Response
    {
        AuditLogger::log('admin.questions_exported', 'question');
        return Response::download(QuestionCsv::export(), 'interviewpro-questions-' . gmdate('Ymd-His') . '.csv', 'text/csv; charset=UTF-8');
    }

    public function importForm(Request $request): Response
    {
        return $this->view('admin/questions/import', ['title' => t('admin.import.title'), 'columns' => QuestionCsv::COLUMNS, 'result' => null]);
    }

    public function import(Request $request): Response
    {
        if (!RateLimiter::attempt('csv-import:' . \App\Services\Auth::id(), 20, 3600)) {
            throw new HttpException(429);
        }
        $check = QuestionCsv::validateUpload($request->files['file'] ?? null);
        $result = $check['ok']
            ? QuestionCsv::import($check['path'], $request->bool('dry_run'))
            : ['created' => 0, 'updated' => 0, 'errors' => $check['errors']];
        if ($check['ok'] && !$result['errors'] && !$request->bool('dry_run')) {
            AuditLogger::log('admin.questions_imported', 'question', null, ['created' => $result['created'], 'updated' => $result['updated']]);
        }
        $result['dry_run'] = $request->bool('dry_run');
        return $this->view('admin/questions/import', ['title' => t('admin.import.title'), 'columns' => QuestionCsv::COLUMNS, 'result' => $result]);
    }
}
