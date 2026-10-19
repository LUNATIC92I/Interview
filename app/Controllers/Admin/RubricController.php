<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Rubric;
use App\Services\AuditLogger;

/** Scoring grids: names, descriptions and weights (weights of a rubric must total 100). */
final class RubricController extends AdminController
{
    public const EVALUATORS = ['relevance', 'accuracy', 'structure', 'actions', 'results', 'reflection', 'reasoning', 'method', 'communication', 'leadership', 'ethics', 'motivation_fit', 'negotiation'];

    public function index(Request $request): Response
    {
        $items = Rubric::all();
        foreach ($items as &$r) {
            $r['criteria'] = Rubric::criteria((int) $r['id']);
            $r['usage'] = (int) Database::fetchColumn('SELECT COUNT(*) FROM questions WHERE rubric_id = :id', ['id' => $r['id']]);
        }
        unset($r);
        return $this->view('admin/rubrics/index', ['title' => t('admin.rubrics.title'), 'items' => $items]);
    }

    public function edit(Request $request, string $id): Response
    {
        $item = Rubric::find((int) $id) ?? throw new HttpException(404);
        return $this->view('admin/rubrics/form', ['title' => t('admin.rubrics.edit'), 'item' => $item, 'criteria' => Rubric::criteria((int) $id), 'evaluators' => self::EVALUATORS]);
    }

    public function update(Request $request, string $id): Response
    {
        $rubric = Rubric::find((int) $id) ?? throw new HttpException(404);
        $v = $this->validate($request, ['name_fr' => 'required|max:150', 'name_en' => 'required|max:150']);
        $errors = $v->errors();
        $criteria = [];
        $sum = 0;
        foreach ($request->arr('criteria') as $cid => $c) {
            if (!is_array($c)) {
                continue;
            }
            $weight = (int) ($c['weight'] ?? 0);
            $evaluator = (string) ($c['evaluator'] ?? '');
            if ($weight < 0 || $weight > 100 || !in_array($evaluator, self::EVALUATORS, true) || trim((string) ($c['name_fr'] ?? '')) === '' || trim((string) ($c['name_en'] ?? '')) === '') {
                $errors['criteria'] = t('admin.rubrics.invalid_criterion');
            }
            $sum += $weight;
            $criteria[(int) $cid] = $c + ['weight' => $weight];
        }
        if ($sum !== 100) {
            $errors['criteria'] = t('admin.rubrics.sum', ['sum' => $sum]);
        }
        if ($errors) {
            return $this->back($request, "/admin/rubrics/$id/edit", $errors);
        }
        Database::transaction(static function () use ($rubric, $request, $criteria) {
            Database::update('evaluation_rubrics', [
                'name_fr' => $request->str('name_fr'), 'name_en' => $request->str('name_en'),
                'description_fr' => $request->str('description_fr') ?: null, 'description_en' => $request->str('description_en') ?: null,
                'is_active' => $request->bool('is_active') ? 1 : 0, 'updated_at' => now(),
            ], ['id' => $rubric['id']]);
            foreach ($criteria as $cid => $c) {
                Database::query(
                    'UPDATE rubric_criteria SET weight = :w, evaluator = :e, name_fr = :nf, name_en = :ne, description_fr = :df, description_en = :de
                     WHERE id = :id AND rubric_id = :r',
                    ['w' => $c['weight'], 'e' => $c['evaluator'], 'nf' => trim((string) $c['name_fr']), 'ne' => trim((string) $c['name_en']),
                     'df' => trim((string) ($c['description_fr'] ?? '')) ?: null, 'de' => trim((string) ($c['description_en'] ?? '')) ?: null,
                     'id' => $cid, 'r' => $rubric['id']]
                );
            }
        });
        AuditLogger::log('admin.rubric_updated', 'rubric', (int) $rubric['id']);
        $this->flash('success', t('admin.saved'));
        return $this->redirect('/admin/rubrics');
    }
}
