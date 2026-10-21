<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Paginator;
use App\Models\Category;
use App\Models\Profession;
use App\Models\Scenario;

final class ProfessionController extends Controller
{
    public function index(Request $request): Response
    {
        $q = mb_substr(trim((string) ($request->query['q'] ?? '')), 0, 100);
        $category = (int) ($request->query['category'] ?? 0) ?: null;
        $page = Paginator::page($request->query);
        $perPage = 24;
        $result = Profession::catalog($q, $category, $page, $perPage);
        return $this->view('professions/index', [
            'title' => t('professions.title'),
            'items' => $result['items'],
            'categories' => Category::withCounts(),
            'q' => $q,
            'category' => $category,
            'pager' => new Paginator($result['total'], $page, $perPage, '/professions', ['q' => $q, 'category' => $category]),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $profession = Profession::findBySlug($slug);
        if (!$profession) {
            throw new HttpException(404);
        }
        $loc = locale();
        $samples = Database::fetchAll(
            'SELECT q.id, q.question_type, q.difficulty, COALESCE(t1.body, t2.body) AS body
             FROM questions q
             LEFT JOIN question_translations t1 ON t1.question_id = q.id AND t1.locale = :l
             LEFT JOIN question_translations t2 ON t2.question_id = q.id AND t2.locale = :o
             WHERE q.is_active = 1 AND q.profession_id = :p ORDER BY q.difficulty, q.id LIMIT 4',
            ['l' => $loc, 'o' => $loc === 'fr' ? 'en' : 'fr', 'p' => $profession['id']]
        );
        $viewer = \App\Services\Auth::user();
        $accessState = !\App\Services\AccessService::paywallEnabled() ? 'open'
            : ($viewer && \App\Services\AccessService::hasAccess((int) $viewer['id'], (int) $profession['id']) ? 'unlocked' : 'locked');
        return $this->view('professions/show', [
            'accessState' => $accessState,
            'price' => format_price(\App\Services\AccessService::price(), \App\Services\AccessService::currency()),
            'title' => $profession['name'],
            'profession' => $profession,
            'skills' => Profession::skills((int) $profession['id']),
            'stats' => Profession::questionStats($profession),
            'samples' => $samples,
            'scenarios' => Scenario::forProfession($profession),
            'related' => Profession::related($profession),
        ]);
    }
}
