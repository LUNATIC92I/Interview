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
use App\Models\Skill;
use App\Services\AuditLogger;

/** Professions are managed entirely from the back office: no code change is needed to add one. */
final class ProfessionController extends AdminController
{
    public function index(Request $request): Response
    {
        $q = mb_substr(trim((string) ($request->query['q'] ?? '')), 0, 100);
        $category = (int) ($request->query['category'] ?? 0) ?: null;
        $page = Paginator::page($request->query);
        $result = Profession::catalog($q, $category, $page, 30, false);
        return $this->view('admin/professions/index', [
            'title' => t('admin.professions.title'), 'items' => $result['items'], 'q' => $q, 'category' => $category,
            'categories' => Category::all(false),
            'pager' => new Paginator($result['total'], $page, 30, '/admin/professions', ['q' => $q, 'category' => $category]),
        ]);
    }

    private function form(?array $item, array $tr, array $skillIds, string $title): Response
    {
        return $this->view('admin/professions/form', [
            'title' => $title, 'item' => $item, 'tr' => $tr, 'skillIds' => $skillIds,
            'categories' => Category::all(false), 'skills' => Skill::all(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, [], [], t('admin.professions.new'));
    }

    public function edit(Request $request, string $id): Response
    {
        $item = Database::fetch('SELECT * FROM professions WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $skillIds = array_map('intval', array_column(Database::fetchAll('SELECT skill_id FROM profession_skills WHERE profession_id = :id', ['id' => (int) $id]), 'skill_id'));
        return $this->form($item, Profession::translations((int) $id), $skillIds, t('admin.professions.edit'));
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        Database::fetch('SELECT id FROM professions WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->save($request, (int) $id);
    }

    private function save(Request $request, ?int $id): Response
    {
        $v = $this->validate($request, [
            'slug' => 'required|slug|max:100', 'category_id' => 'required|int', 'icon' => 'required|max:40',
            'level_min' => 'required|int|between:1,5', 'level_max' => 'required|int|between:1,5', 'sort_order' => 'required|int',
            'name_fr' => 'required|max:150', 'name_en' => 'required|max:150',
        ]);
        $errors = $v->errors();
        if (!isset($errors['slug']) && Database::fetchColumn('SELECT id FROM professions WHERE slug = :s AND id <> :id', ['s' => $request->str('slug'), 'id' => $id ?? 0])) {
            $errors['slug'] = t('validation.unique');
        }
        if (!Database::fetchColumn('SELECT 1 FROM profession_categories WHERE id = :id', ['id' => $request->int('category_id')])) {
            $errors['category_id'] = t('validation.in');
        }
        if ($request->int('level_min') > $request->int('level_max')) {
            $errors['level_max'] = t('admin.questions.level_order');
        }
        if ($errors) {
            return $this->back($request, $id ? "/admin/professions/$id/edit" : '/admin/professions/new', $errors);
        }
        $tr = self::translations($request->post, ['name', 'summary', 'responsibilities']);
        $skillIds = array_map('intval', $request->arr('skills'));
        Database::transaction(static function () use (&$id, $request, $tr, $skillIds) {
            $row = [
                'category_id' => $request->int('category_id'), 'slug' => $request->str('slug'), 'icon' => $request->str('icon'),
                'level_min' => $request->int('level_min'), 'level_max' => $request->int('level_max'),
                'sort_order' => $request->int('sort_order'), 'is_active' => $request->bool('is_active') ? 1 : 0, 'updated_at' => now(),
            ];
            if ($id === null) {
                $id = Database::insert('professions', $row + ['created_at' => now()]);
            } else {
                Database::update('professions', $row, ['id' => $id]);
            }
            foreach ($tr as $loc => $t) {
                Database::query(
                    'INSERT INTO profession_translations (profession_id, locale, name, summary, responsibilities) VALUES (:p, :l, :n, :s, :r)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), summary = VALUES(summary), responsibilities = VALUES(responsibilities)',
                    ['p' => $id, 'l' => $loc, 'n' => $t['name'], 's' => $t['summary'] ?: null, 'r' => $t['responsibilities'] ?: null]
                );
            }
            Database::delete('profession_skills', ['profession_id' => $id]);
            foreach (array_unique($skillIds) as $sid) {
                if (Database::fetchColumn('SELECT 1 FROM skills WHERE id = :id', ['id' => $sid])) {
                    Database::insert('profession_skills', ['profession_id' => $id, 'skill_id' => $sid]);
                }
            }
        });
        AuditLogger::log('admin.profession_saved', 'profession', $id);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/professions');
    }

    public function destroy(Request $request, string $id): Response
    {
        $id = (int) $id;
        $used = (int) Database::fetchColumn('SELECT COUNT(*) FROM interview_sessions WHERE profession_id = :id', ['id' => $id])
            + (int) Database::fetchColumn('SELECT COUNT(*) FROM questions WHERE profession_id = :id', ['id' => $id])
            + (int) Database::fetchColumn('SELECT COUNT(*) FROM scenarios WHERE profession_id = :id', ['id' => $id]);
        if ($used) {
            Database::update('professions', ['is_active' => 0, 'updated_at' => now()], ['id' => $id]);
            $this->flash('info', t('admin.deactivated_in_use'));
        } else {
            Database::query('UPDATE profiles SET target_profession_id = NULL WHERE target_profession_id = :id', ['id' => $id]);
            Database::delete('professions', ['id' => $id]);
            $this->flash('success', t('admin.deleted'));
        }
        AuditLogger::log('admin.profession_deleted', 'profession', $id);
        return $this->redirect('/admin/professions');
    }
}
