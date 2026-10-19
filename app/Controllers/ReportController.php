<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLogger;
use App\Services\InterviewEngine;
use App\Services\RateLimiter;
use App\Services\ReportPdf;
use App\Services\ReportService;

final class ReportController extends Controller
{
    private function completedSession(string $publicId): array
    {
        $session = InterviewEngine::load($publicId, (int) $this->user()['id']);
        if ($session['status'] !== 'completed') {
            throw new HttpException(404);
        }
        return $session;
    }

    public function show(Request $request, string $publicId): Response
    {
        $session = $this->completedSession($publicId);
        $report = ReportService::build($session);
        $attempts = Database::fetchAll(
            "SELECT public_id, completed_at, overall_score FROM interview_sessions
             WHERE user_id = :u AND status = 'completed' AND id <> :id AND profession_id = :p AND interview_type = :t
               AND (scenario_id <=> :sc) ORDER BY completed_at DESC LIMIT 5",
            ['u' => $session['user_id'], 'id' => $session['id'], 'p' => $session['profession_id'], 't' => $session['interview_type'], 'sc' => $session['scenario_id']]
        );
        return $this->view('reports/show', ['title' => t('report.title'), 'report' => $report, 'attempts' => $attempts]);
    }

    public function pdf(Request $request, string $publicId): Response
    {
        $session = $this->completedSession($publicId);
        if (!RateLimiter::attempt('pdf:' . $session['user_id'], 30, 3600)) {
            throw new HttpException(429);
        }
        $report = ReportService::build($session);
        AuditLogger::log('report.pdf_exported', 'interview_session', (int) $session['id']);
        $name = 'interviewpro-report-' . substr($publicId, 0, 8) . '-' . gmdate('Ymd', strtotime($session['completed_at'] . ' UTC')) . '.pdf';
        return Response::download(ReportPdf::render($report), $name, 'application/pdf');
    }

    /** Side by side comparison of two completed attempts of the same user. */
    public function compare(Request $request): Response
    {
        $a = $this->completedSession((string) ($request->query['a'] ?? ''));
        $b = $this->completedSession((string) ($request->query['b'] ?? ''));
        $ra = ReportService::build($a);
        $rb = ReportService::build($b);
        $criteria = [];
        foreach ($ra['criteria'] as $c) {
            $criteria[$c['evaluator']] = ['name' => $c['name'], 'a' => $c['score'], 'b' => null];
        }
        foreach ($rb['criteria'] as $c) {
            $criteria[$c['evaluator']] ??= ['name' => $c['name'], 'a' => null, 'b' => null];
            $criteria[$c['evaluator']]['b'] = $c['score'];
        }
        return $this->view('reports/compare', ['title' => t('compare.title'), 'a' => $ra, 'b' => $rb, 'criteria' => $criteria]);
    }
}
