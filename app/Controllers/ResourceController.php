<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Translator;
use App\Models\Profession;
use App\Models\Scenario;
use App\Services\AccessService;

/**
 * Resources of the professions unlocked by the candidate: full question library with guidance,
 * expected elements, common mistakes and indicative answers, scenarios, preparation guides and the
 * candidate's own results for that profession.
 */
final class ResourceController extends Controller
{
    public function index(Request $request): Response
    {
        $uid = (int) $this->user()['id'];
        return $this->view('resources/index', [
            'title' => t('my_resources.title'),
            'unlocked' => AccessService::unlocked($uid),
            'paywall' => AccessService::paywallEnabled(),
            'trialsLeft' => AccessService::trialsLeft($uid),
            'price' => format_price(AccessService::price(), AccessService::currency()),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $profession = Profession::findBySlug($slug) ?? throw new HttpException(404);
        $uid = (int) $this->user()['id'];
        if (!AccessService::hasAccess($uid, (int) $profession['id'])) {
            $this->flash('info', t('access.locked_flash', ['price' => format_price(AccessService::price(), AccessService::currency())]));
            return $this->redirect('/checkout/' . $profession['slug']);
        }
        $loc = Translator::locale();
        $params = ['l' => $loc, 'o' => Translator::other($loc), 'l2' => $loc, 'o2' => Translator::other($loc), 'p' => $profession['id'], 'c' => $profession['category_id']];
        $rows = Database::fetchAll(
            'SELECT q.id, q.question_type, q.level_min, q.level_max, q.difficulty, q.recommended_seconds,
                (q.profession_id IS NOT NULL) AS is_specific, (q.profession_id IS NULL AND q.category_id IS NOT NULL) AS is_sector,
                COALESCE(t1.body, t2.body) AS body, COALESCE(t1.guidance, t2.guidance) AS guidance,
                COALESCE(t1.expected_elements, t2.expected_elements) AS expected_elements,
                COALESCE(t1.common_mistakes, t2.common_mistakes) AS common_mistakes,
                COALESCE(e1.body, e2.body) AS example_answer, (t1.body IS NULL) AS is_fallback
             FROM questions q
             LEFT JOIN question_translations t1 ON t1.question_id = q.id AND t1.locale = :l
             LEFT JOIN question_translations t2 ON t2.question_id = q.id AND t2.locale = :o
             LEFT JOIN answer_examples e1 ON e1.question_id = q.id AND e1.locale = :l2
             LEFT JOIN answer_examples e2 ON e2.question_id = q.id AND e2.locale = :o2
             WHERE q.is_active = 1 AND (q.profession_id = :p OR (q.profession_id IS NULL AND (q.category_id = :c OR q.category_id IS NULL)))
             ORDER BY q.question_type, (q.profession_id IS NULL), (q.category_id IS NULL), q.difficulty, q.id',
            $params
        );
        $library = [];
        foreach ($rows as $row) {
            $row['expected_elements'] = json_list($row['expected_elements']);
            $row['common_mistakes'] = json_list($row['common_mistakes']);
            $library[$row['question_type']][] = $row;
        }
        $types = array_keys($library);
        $guides = Database::fetchAll(
            'SELECT c.slug, c.content_type, COALESCE(t1.title, t2.title) AS title, COALESCE(t1.summary, t2.summary) AS summary
             FROM learning_contents c
             LEFT JOIN learning_content_translations t1 ON t1.content_id = c.id AND t1.locale = :l
             LEFT JOIN learning_content_translations t2 ON t2.content_id = c.id AND t2.locale = :o
             WHERE c.is_published = 1 ORDER BY c.sort_order, c.id',
            ['l' => $loc, 'o' => Translator::other($loc)]
        );
        $results = Database::fetch(
            "SELECT COUNT(*) AS sessions, ROUND(AVG(overall_score)) AS average, MAX(overall_score) AS best
             FROM interview_sessions WHERE user_id = :u AND profession_id = :p AND status = 'completed'",
            ['u' => $uid, 'p' => $profession['id']]
        );
        $access = Database::fetch('SELECT * FROM profession_access WHERE user_id = :u AND profession_id = :p AND revoked_at IS NULL', ['u' => $uid, 'p' => $profession['id']]);
        return $this->view('resources/show', [
            'title' => t('my_resources.profession_title', ['profession' => $profession['name']]),
            'profession' => $profession,
            'skills' => Profession::skills((int) $profession['id']),
            'library' => $library,
            'questionCount' => count($rows),
            'scenarios' => Scenario::forProfession($profession),
            'guides' => $guides,
            'results' => $results,
            'access' => $access,
            'typesCovered' => $types,
        ]);
    }
}
