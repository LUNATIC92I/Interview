<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\InterviewEngine;
use App\Services\UserService;

/**
 * Demonstration data: one candidate with completed simulations in French and English.
 * The password is taken from DEMO_PASSWORD or generated and printed once; nothing is hard coded.
 */
final class DemoSeeder
{
    /** @var callable(string): void */
    private $out;

    public function __construct(callable $out)
    {
        $this->out = $out;
    }

    public function run(): void
    {
        $email = getenv('DEMO_EMAIL') ?: 'demo@interviewpro.local';
        if (Database::fetchColumn('SELECT id FROM users WHERE email = :e', ['e' => $email])) {
            ($this->out)("Demo account $email already exists, nothing to do.");
            return;
        }
        $password = getenv('DEMO_PASSWORD') ?: 'Demo-' . bin2hex(random_bytes(5)) . '9';
        $userId = UserService::create($email, 'Candidat Démo', $password, 'fr');
        Database::update('users', ['email_verified_at' => now()], ['id' => $userId]);

        $runs = [
            ['soc-analyst-junior', 'technical', 'fr', 'realistic', [
                "D'abord je vérifie le contexte du compte et ses habitudes de connexion. Ensuite j'analyse l'adresse IP, la géolocalisation, l'appareil et la MFA, puis je corrèle avec les journaux d'authentification et l'EDR. Si la compromission est probable, je fais révoquer les sessions et réinitialiser le mot de passe, j'escalade au niveau 2 et je documente la chronologie dans le ticket.",
                "Je regarde la règle de détection et le contexte métier, par exemple une maintenance planifiée. Je cherche des indicateurs comme la réputation de l'IP ou le hash du fichier.",
                "Je priorise selon la sévérité et la criticité de l'actif.",
            ]],
            ['project-manager-fr', 'manager', 'fr', 'training', [
                "Je formalise chaque demande dans un registre des changements, j'évalue l'impact sur le délai, le coût et les risques, puis je présente les options au sponsor pour qu'il arbitre. La décision est tracée dans le compte rendu du comité.",
                "Sur un projet de migration en retard de six semaines, j'ai identifié deux causes, replanifié le chemin critique et obtenu un renfort. Résultat : nous avons livré avec seulement deux semaines de retard. J'ai appris à alerter plus tôt.",
            ]],
            ['un-mdb-candidate', 'international', 'en', 'realistic', [
                "Your mandate on access to education matches my six years on school programmes in West Africa. I want to contribute at a larger scale, bringing field experience and indicator monitoring, and I am ready for mobility in a multicultural environment.",
                "A partner ministry needed a dashboard for vaccination tracking. I ran two workshops to understand their needs, adapted the tool and trained twelve staff. They now use it monthly and the request was extended to two provinces.",
            ]],
        ];
        foreach ($runs as $i => [$slug, $type, $locale, $mode, $answers]) {
            $profId = (int) Database::fetchColumn('SELECT id FROM professions WHERE slug = :s', ['s' => $slug]);
            [$cfg] = InterviewEngine::normaliseConfig(['profession_id' => $profId, 'experience_level' => 3, 'interview_type' => $type, 'locale' => $locale,
                'difficulty' => 3, 'duration_minutes' => 20, 'question_count' => 5, 'mode' => $mode]);
            $session = InterviewEngine::create($userId, $cfg);
            foreach ($answers as $text) {
                $s = Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]);
                InterviewEngine::submit($s, (int) $s['current_position'], $text);
            }
            InterviewEngine::finish(Database::fetch('SELECT * FROM interview_sessions WHERE id = :id', ['id' => $session['id']]));
            // Spread the demo history over the last weeks for the progress chart.
            $date = gmdate('Y-m-d H:i:s', time() - (count($runs) - $i) * 6 * 86400);
            Database::update('interview_sessions', ['started_at' => $date, 'completed_at' => $date, 'created_at' => $date], ['id' => $session['id']]);
        }
        Database::insert('user_goals', ['user_id' => $userId, 'goal_type' => 'average_score', 'target_value' => 75, 'created_at' => now()]);
        ($this->out)("Demo candidate created: $email / $password (change or delete this account in production).");
    }
}
