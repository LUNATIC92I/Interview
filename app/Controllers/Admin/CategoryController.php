<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Services\AuditLogger;

final class CategoryController extends AdminController
{
    public function index(Request $request): Response
    {
        $items = Database::fetchAll(
            'SELECT x.*, (SELECT COUNT(*) FROM professions p WHERE p.category_id = x.id) AS profession_count FROM (' .
            'SELECT c.*, COALESCE(t1.name, t2.name) AS name FROM profession_categories c
             LEFT JOIN profession_category_translations t1 ON t1.category_id = c.id AND t1.locale = :l
             LEFT JOIN profession_category_translations t2 ON t2.category_id = c.id AND t2.locale = :o) x ORDER BY x.sort_order',
            ['l' => locale(), 'o' => locale() === 'fr' ? 'en' : 'fr']
        );
        return $this->view('admin/categories/index', ['title' => t('admin.categories.title'), 'items' => $items]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/categories/form', ['title' => t('admin.categories.new'), 'item' => null, 'tr' => []]);
    }

    public function edit(Request $request, string $id): Response
    {
        $item = Database::fetch('SELECT * FROM profession_categories WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->view('admin/categories/form', ['title' => t('admin.categories.edit'), 'item' => $item, 'tr' => Category::translations((int) $id)]);
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        Database::fetch('SELECT id FROM profession_categories WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->save($request, (int) $id);
    }

    private function save(Request $request, ?int $id): Response
    {
        $v = $this->validate($request, ['slug' => 'required|slug|max:80', 'icon' => 'required|max:40', 'sort_order' => 'required|int', 'name_fr' => 'required|max:150', 'name_en' => 'required|max:150']);
        $errors = $v->errors();
        if (!isset($errors['slug']) && Database::fetchColumn('SELECT id FROM profession_categories WHERE slug = :s AND id <> :id', ['s' => $request->str('slug'), 'id' => $id ?? 0])) {
            $errors['slug'] = t('validation.unique');
        }
        if ($errors) {
            return $this->back($request, $id ? "/admin/categories/$id/edit" : '/admin/categories/new', $errors);
        }
        $tr = self::translations($request->post, ['name', 'description']);
        Database::transaction(static function () use (&$id, $request, $tr) {
            $row = ['slug' => $request->str('slug'), 'icon' => $request->str('icon'), 'sort_order' => $request->int('sort_order'), 'is_active' => $request->bool('is_active') ? 1 : 0, 'updated_at' => now()];
            if ($id === null) {
                $id = Database::insert('profession_categories', $row + ['created_at' => now()]);
            } else {
                Database::update('profession_categories', $row, ['id' => $id]);
            }
            foreach ($tr as $loc => $t) {
                Database::query(
                    'INSERT INTO profession_category_translations (category_id, locale, name, description) VALUES (:c, :l, :n, :d)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)',
                    ['c' => $id, 'l' => $loc, 'n' => $t['name'], 'd' => $t['description'] ?: null]
                );
            }
        });
        AuditLogger::log('admin.category_saved', 'category', $id);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/categories');
    }

    public function destroy(Request $request, string $id): Response
    {
        $id = (int) $id;
        if (Database::fetchColumn('SELECT COUNT(*) FROM professions WHERE category_id = :id', ['id' => $id])
            || Database::fetchColumn('SELECT COUNT(*) FROM questions WHERE category_id = :id', ['id' => $id])
            || Database::fetchColumn('SELECT COUNT(*) FROM scenarios WHERE category_id = :id', ['id' => $id])) {
            Database::update('profession_categories', ['is_active' => 0, 'updated_at' => now()], ['id' => $id]);
            $this->flash('info', t('admin.deactivated_in_use'));
        } else {
            Database::delete('profession_categories', ['id' => $id]);
            $this->flash('success', t('admin.deleted'));
        }
        AuditLogger::log('admin.category_deleted', 'category', $id);
        return $this->redirect('/admin/categories');
    }
}
