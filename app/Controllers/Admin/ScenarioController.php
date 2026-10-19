<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Domain;
use App\Models\Profession;
use App\Models\Scenario;
use App\Services\AuditLogger;

final class ScenarioController extends AdminController
{
    public function index(Request $request): Response
    {
        $items = Database::fetchAll(
            'SELECT s.*, COALESCE(t1.title, t2.title) AS title, (SELECT COUNT(*) FROM scenario_questions q WHERE q.scenario_id = s.id) AS question_count,
                (SELECT COUNT(*) FROM interview_sessions i WHERE i.scenario_id = s.id) AS usage_count
             FROM scenarios s
             LEFT JOIN scenario_translations t1 ON t1.scenario_id = s.id AND t1.locale = :l
             LEFT JOIN scenario_translations t2 ON t2.scenario_id = s.id AND t2.locale = :o ORDER BY s.id',
            ['l' => locale(), 'o' => locale() === 'fr' ? 'en' : 'fr']
        );
        return $this->view('admin/scenarios/index', ['title' => t('admin.scenarios.title'), 'items' => $items]);
    }

    private function questionOptions(): array
    {
        return Database::fetchAll(
            'SELECT q.id, q.code, q.question_type, COALESCE(t1.body, t2.body) AS body FROM questions q
             LEFT JOIN question_translations t1 ON t1.question_id = q.id AND t1.locale = :l
             LEFT JOIN question_translations t2 ON t2.question_id = q.id AND t2.locale = :o
             WHERE q.is_active = 1 ORDER BY q.code',
            ['l' => locale(), 'o' => locale() === 'fr' ? 'en' : 'fr']
        );
    }

    private function form(?array $item, array $tr, array $questionIds, string $title): Response
    {
        return $this->view('admin/scenarios/form', [
            'title' => $title, 'item' => $item, 'tr' => $tr, 'questionIds' => $questionIds,
            'questions' => $this->questionOptions(), 'professions' => Profession::options(false), 'categories' => Category::all(false),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, [], [], t('admin.scenarios.new'));
    }

    public function edit(Request $request, string $id): Response
    {
        $item = Database::fetch('SELECT * FROM scenarios WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->form($item, Scenario::translations((int) $id), Scenario::questionIds((int) $id), t('admin.scenarios.edit'));
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        Database::fetch('SELECT id FROM scenarios WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->save($request, (int) $id);
    }

    private function save(Request $request, ?int $id): Response
    {
        $v = $this->validate($request, [
            'slug' => 'required|slug|max:100', 'interview_type' => 'required|in:' . implode(',', array_keys(Domain::INTERVIEW_TYPES)),
            'experience_level' => 'required|int|between:1,5', 'difficulty' => 'required|int|between:1,5',
            'title_fr' => 'required|max:200', 'title_en' => 'required|max:200',
        ]);
        $errors = $v->errors();
        if (!isset($errors['slug']) && Database::fetchColumn('SELECT id FROM scenarios WHERE slug = :s AND id <> :id', ['s' => $request->str('slug'), 'id' => $id ?? 0])) {
            $errors['slug'] = t('validation.unique');
        }
        $questionIds = array_values(array_unique(array_filter(array_map('intval', $request->arr('questions')))));
        foreach ($questionIds as $qid) {
            if (!Database::fetchColumn('SELECT 1 FROM questions WHERE id = :id', ['id' => $qid])) {
                $errors['questions'] = t('validation.in');
            }
        }
        if (count($questionIds) < 2 || count($questionIds) > 15) {
            $errors['questions'] = t('admin.scenarios.question_count');
        }
        $professionId = $request->int('profession_id') ?: null;
        $categoryId = $request->int('category_id') ?: null;
        if ($errors) {
            return $this->back($request, $id ? "/admin/scenarios/$id/edit" : '/admin/scenarios/new', $errors);
        }
        $tr = self::translations($request->post, ['title', 'description']);
        Database::transaction(static function () use (&$id, $request, $tr, $questionIds, $professionId, $categoryId) {
            $row = [
                'slug' => $request->str('slug'), 'profession_id' => $professionId, 'category_id' => $professionId ? null : $categoryId,
                'interview_type' => $request->str('interview_type'), 'experience_level' => $request->int('experience_level'),
                'difficulty' => $request->int('difficulty'), 'is_active' => $request->bool('is_active') ? 1 : 0, 'updated_at' => now(),
            ];
            if ($id === null) {
                $id = Database::insert('scenarios', $row + ['created_at' => now()]);
            } else {
                Database::update('scenarios', $row, ['id' => $id]);
            }
            foreach ($tr as $loc => $t) {
                Database::query(
                    'INSERT INTO scenario_translations (scenario_id, locale, title, description) VALUES (:s, :l, :t, :d)
                     ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description)',
                    ['s' => $id, 'l' => $loc, 't' => $t['title'], 'd' => $t['description'] ?: null]
                );
            }
            Database::delete('scenario_questions', ['scenario_id' => $id]);
            foreach ($questionIds as $i => $qid) {
                Database::insert('scenario_questions', ['scenario_id' => $id, 'question_id' => $qid, 'position' => $i + 1]);
            }
        });
        AuditLogger::log('admin.scenario_saved', 'scenario', $id);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/scenarios');
    }

    public function destroy(Request $request, string $id): Response
    {
        $id = (int) $id;
        if (Database::fetchColumn('SELECT COUNT(*) FROM interview_sessions WHERE scenario_id = :id', ['id' => $id])) {
            Database::update('scenarios', ['is_active' => 0, 'updated_at' => now()], ['id' => $id]);
            $this->flash('info', t('admin.deactivated_in_use'));
        } else {
            Database::delete('scenarios', ['id' => $id]);
            $this->flash('success', t('admin.deleted'));
        }
        AuditLogger::log('admin.scenario_deleted', 'scenario', $id);
        return $this->redirect('/admin/scenarios');
    }
}
