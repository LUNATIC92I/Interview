<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Domain;
use App\Services\AuditLogger;

/** Educational content (articles, tips, checklists) displayed on the public Resources page. */
final class LearningController extends AdminController
{
    public function index(Request $request): Response
    {
        $items = Database::fetchAll(
            'SELECT c.*, COALESCE(t1.title, t2.title) AS title FROM learning_contents c
             LEFT JOIN learning_content_translations t1 ON t1.content_id = c.id AND t1.locale = :l
             LEFT JOIN learning_content_translations t2 ON t2.content_id = c.id AND t2.locale = :o ORDER BY c.sort_order, c.id',
            ['l' => locale(), 'o' => locale() === 'fr' ? 'en' : 'fr']
        );
        return $this->view('admin/learning/index', ['title' => t('admin.learning.title'), 'items' => $items]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/learning/form', ['title' => t('admin.learning.new'), 'item' => null, 'tr' => []]);
    }

    public function edit(Request $request, string $id): Response
    {
        $item = Database::fetch('SELECT * FROM learning_contents WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $tr = array_column(Database::fetchAll('SELECT * FROM learning_content_translations WHERE content_id = :id', ['id' => (int) $id]), null, 'locale');
        return $this->view('admin/learning/form', ['title' => t('admin.learning.edit'), 'item' => $item, 'tr' => $tr]);
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        Database::fetch('SELECT id FROM learning_contents WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->save($request, (int) $id);
    }

    private function save(Request $request, ?int $id): Response
    {
        $v = $this->validate($request, [
            'slug' => 'required|slug|max:120', 'content_type' => 'required|in:article,tip,checklist', 'sort_order' => 'required|int',
            'question_type' => 'nullable|in:' . implode(',', Domain::QUESTION_TYPES),
            'title_fr' => 'required|max:200', 'title_en' => 'required|max:200', 'body_fr' => 'required|max:20000', 'body_en' => 'required|max:20000',
        ]);
        $errors = $v->errors();
        if (!isset($errors['slug']) && Database::fetchColumn('SELECT id FROM learning_contents WHERE slug = :s AND id <> :id', ['s' => $request->str('slug'), 'id' => $id ?? 0])) {
            $errors['slug'] = t('validation.unique');
        }
        if ($errors) {
            return $this->back($request, $id ? "/admin/learning/$id/edit" : '/admin/learning/new', $errors);
        }
        $tr = self::translations($request->post, ['title', 'summary', 'body']);
        Database::transaction(static function () use (&$id, $request, $tr) {
            $row = ['slug' => $request->str('slug'), 'content_type' => $request->str('content_type'), 'question_type' => $request->str('question_type') ?: null,
                'is_published' => $request->bool('is_published') ? 1 : 0, 'sort_order' => $request->int('sort_order'), 'updated_at' => now()];
            if ($id === null) {
                $id = Database::insert('learning_contents', $row + ['created_at' => now()]);
            } else {
                Database::update('learning_contents', $row, ['id' => $id]);
            }
            foreach ($tr as $loc => $t) {
                Database::query(
                    'INSERT INTO learning_content_translations (content_id, locale, title, summary, body) VALUES (:c, :l, :t, :s, :b)
                     ON DUPLICATE KEY UPDATE title = VALUES(title), summary = VALUES(summary), body = VALUES(body)',
                    ['c' => $id, 'l' => $loc, 't' => $t['title'], 's' => $t['summary'] ?: null, 'b' => $t['body']]
                );
            }
        });
        AuditLogger::log('admin.learning_saved', 'learning', $id);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/learning');
    }

    public function destroy(Request $request, string $id): Response
    {
        Database::delete('learning_contents', ['id' => (int) $id]);
        AuditLogger::log('admin.learning_deleted', 'learning', (int) $id);
        $this->flash('success', t('admin.deleted'));
        return $this->redirect('/admin/learning');
    }
}
