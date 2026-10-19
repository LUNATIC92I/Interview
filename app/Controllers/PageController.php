<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Models\Category;
use App\Models\Domain;
use App\Services\Auth;

final class PageController extends Controller
{
    public function home(Request $request): Response
    {
        $counts = [
            'professions' => (int) Database::fetchColumn('SELECT COUNT(*) FROM professions WHERE is_active = 1'),
            'questions' => (int) Database::fetchColumn('SELECT COUNT(*) FROM questions WHERE is_active = 1'),
            'scenarios' => (int) Database::fetchColumn('SELECT COUNT(*) FROM scenarios WHERE is_active = 1'),
        ];
        return $this->view('pages/home', [
            'title' => t('home.meta_title'),
            'categories' => Category::withCounts(),
            'counts' => $counts,
        ]);
    }

    public function simulations(Request $request): Response
    {
        return $this->view('pages/simulations', [
            'title' => t('simulations.title'),
            'types' => array_keys(Domain::INTERVIEW_TYPES),
            'modes' => Domain::MODES,
        ]);
    }

    public function features(Request $request): Response
    {
        return $this->view('pages/features', ['title' => t('features.title')]);
    }

    public function resources(Request $request): Response
    {
        $loc = Translator::locale();
        $items = Database::fetchAll(
            'SELECT c.*, COALESCE(t1.title, t2.title) AS title, COALESCE(t1.summary, t2.summary) AS summary, (t1.title IS NULL) AS is_fallback
             FROM learning_contents c
             LEFT JOIN learning_content_translations t1 ON t1.content_id = c.id AND t1.locale = :l
             LEFT JOIN learning_content_translations t2 ON t2.content_id = c.id AND t2.locale = :o
             WHERE c.is_published = 1 ORDER BY c.sort_order, c.id',
            ['l' => $loc, 'o' => Translator::other($loc)]
        );
        return $this->view('pages/resources', ['title' => t('resources.title'), 'items' => $items]);
    }

    public function resource(Request $request, string $slug): Response
    {
        $loc = Translator::locale();
        $item = Database::fetch(
            'SELECT c.*, COALESCE(t1.title, t2.title) AS title, COALESCE(t1.summary, t2.summary) AS summary,
                COALESCE(t1.body, t2.body) AS body, (t1.title IS NULL) AS is_fallback
             FROM learning_contents c
             LEFT JOIN learning_content_translations t1 ON t1.content_id = c.id AND t1.locale = :l
             LEFT JOIN learning_content_translations t2 ON t2.content_id = c.id AND t2.locale = :o
             WHERE c.slug = :s AND c.is_published = 1',
            ['l' => $loc, 'o' => Translator::other($loc), 's' => $slug]
        );
        if (!$item) {
            throw new HttpException(404);
        }
        return $this->view('pages/resource', ['title' => $item['title'], 'item' => $item]);
    }

    public function privacy(Request $request): Response
    {
        return $this->view('pages/privacy', ['title' => t('privacy.title')]);
    }

    /**
     * Change the interface language. Only the locale changes: the PHP session, drafts and simulation
     * progress are untouched. The redirect target must be a local path (no open redirect).
     */
    public function switchLocale(Request $request, string $locale): Response
    {
        if (!in_array($locale, Translator::locales(), true)) {
            throw new HttpException(404);
        }
        Session::set('locale', $locale);
        if ($id = Auth::id()) {
            Database::update('users', ['locale' => $locale, 'updated_at' => now()], ['id' => $id]);
            Database::update('user_preferences', ['locale' => $locale, 'updated_at' => now()], ['user_id' => $id]);
        }
        $to = (string) ($request->query['redirect'] ?? '/');
        if (!preg_match('#^/(?!/)[^\\\\\s]*$#', $to)) {
            $to = '/';
        }
        return Response::redirect($to);
    }
}
