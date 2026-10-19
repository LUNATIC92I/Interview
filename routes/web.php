<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\Admin;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\InstallController;
use App\Controllers\InterviewController;
use App\Controllers\PageController;
use App\Controllers\PaymentController;
use App\Controllers\ProfessionController;
use App\Controllers\ReportController;
use App\Controllers\ResourceController;
use App\Core\Router;

return static function (Router $r): void {
    // Public pages
    $r->get('/', [PageController::class, 'home']);
    $r->get('/simulations', [PageController::class, 'simulations']);
    $r->get('/features', [PageController::class, 'features']);
    $r->get('/resources', [PageController::class, 'resources']);
    $r->get('/resources/{slug}', [PageController::class, 'resource']);
    $r->get('/privacy', [PageController::class, 'privacy']);
    $r->get('/pricing', [PageController::class, 'pricing']);
    $r->get('/lang/{locale}', [PageController::class, 'switchLocale']);
    $r->get('/professions', [ProfessionController::class, 'index']);
    $r->get('/professions/{slug}', [ProfessionController::class, 'show']);

    // Authentication
    $r->group('', ['guest'], static function (Router $r) {
        $r->get('/login', [AuthController::class, 'showLogin']);
        $r->post('/login', [AuthController::class, 'login']);
        $r->get('/register', [AuthController::class, 'showRegister']);
        $r->post('/register', [AuthController::class, 'register']);
        $r->get('/password/forgot', [AuthController::class, 'showForgot']);
        $r->post('/password/forgot', [AuthController::class, 'forgot']);
        $r->get('/password/reset', [AuthController::class, 'showReset']);
        $r->post('/password/reset', [AuthController::class, 'reset']);
    });
    $r->get('/email/verify', [AuthController::class, 'verifyEmail']);
    $r->post('/webhooks/geniuspay', [PaymentController::class, 'webhook']);
    $r->get('/install', [InstallController::class, 'show']);
    $r->post('/install', [InstallController::class, 'install']);

    // Candidate area
    $r->group('', ['auth'], static function (Router $r) {
        $r->post('/logout', [AuthController::class, 'logout']);
        $r->post('/email/resend', [AuthController::class, 'resendVerification']);
        $r->get('/dashboard', [DashboardController::class, 'index']);
        $r->get('/history', [DashboardController::class, 'history']);
        $r->post('/goals', [DashboardController::class, 'storeGoal']);
        $r->post('/goals/{id}/delete', [DashboardController::class, 'deleteGoal']);

        $r->get('/interview/new', [InterviewController::class, 'setup']);
        $r->post('/interview', [InterviewController::class, 'create'], ['verified']);
        $r->get('/interview/{publicId}', [InterviewController::class, 'run']);
        $r->post('/interview/{publicId}/abandon', [InterviewController::class, 'abandon']);
        $r->get('/api/interview/{publicId}/state', [InterviewController::class, 'state']);
        $r->post('/api/interview/{publicId}/draft', [InterviewController::class, 'draft']);
        $r->post('/api/interview/{publicId}/submit', [InterviewController::class, 'submit']);
        $r->post('/api/interview/{publicId}/goto', [InterviewController::class, 'goTo']);
        $r->post('/api/interview/{publicId}/pause', [InterviewController::class, 'pause']);
        $r->post('/api/interview/{publicId}/resume', [InterviewController::class, 'resume']);
        $r->post('/api/interview/{publicId}/finish', [InterviewController::class, 'finish']);
        $r->post('/api/questions/{id}/report', [InterviewController::class, 'reportQuestion']);

        $r->get('/reports/compare', [ReportController::class, 'compare']);
        $r->get('/reports/{publicId}', [ReportController::class, 'show']);
        $r->get('/reports/{publicId}/pdf', [ReportController::class, 'pdf']);

        $r->get('/checkout/{slug}', [PaymentController::class, 'checkout']);
        $r->post('/checkout/{slug}', [PaymentController::class, 'start'], ['verified']);
        $r->get('/payments/sandbox/{publicId}', [PaymentController::class, 'sandbox']);
        $r->post('/payments/sandbox/{publicId}', [PaymentController::class, 'sandboxSettle']);
        $r->get('/payments/{publicId}/checkout', [PaymentController::class, 'handoff']);
        $r->get('/payments/{publicId}', [PaymentController::class, 'show']);
        $r->get('/my-resources', [ResourceController::class, 'index']);
        $r->get('/my-resources/{slug}', [ResourceController::class, 'show']);

        $r->get('/account', [AccountController::class, 'index']);
        $r->post('/account/profile', [AccountController::class, 'updateProfile']);
        $r->post('/account/preferences', [AccountController::class, 'updatePreferences']);
        $r->post('/account/password', [AccountController::class, 'updatePassword']);
        $r->post('/account/delete', [AccountController::class, 'delete']);
    });

    // Back office
    $r->group('/admin', ['admin'], static function (Router $r) {
        $r->get('', [Admin\DashboardController::class, 'index']);
        $r->get('/users', [Admin\UserController::class, 'index']);
        $r->get('/users/{id}', [Admin\UserController::class, 'show']);
        $r->post('/users/{id}/status', [Admin\UserController::class, 'status']);
        $r->post('/users/{id}/roles', [Admin\UserController::class, 'roles']);
        $r->post('/users/{id}/access', [Admin\PaymentController::class, 'grant']);
        $r->post('/users/{id}/access/{professionId}/revoke', [Admin\PaymentController::class, 'revoke']);
        $r->get('/payments', [Admin\PaymentController::class, 'index']);
        $r->post('/payments/{id}/refresh', [Admin\PaymentController::class, 'refresh']);

        foreach (['categories' => Admin\CategoryController::class, 'professions' => Admin\ProfessionController::class,
                  'questions' => Admin\QuestionController::class, 'scenarios' => Admin\ScenarioController::class,
                  'learning' => Admin\LearningController::class] as $path => $controller) {
            if ($path === 'questions') {
                $r->get('/questions/export', [$controller, 'export']);
                $r->get('/questions/import', [$controller, 'importForm']);
                $r->post('/questions/import', [$controller, 'import']);
            }
            $r->get('/' . $path, [$controller, 'index']);
            $r->get('/' . $path . '/new', [$controller, 'create']);
            $r->post('/' . $path, [$controller, 'store']);
            $r->get('/' . $path . '/{id}/edit', [$controller, 'edit']);
            $r->post('/' . $path . '/{id}', [$controller, 'update']);
            $r->post('/' . $path . '/{id}/delete', [$controller, 'destroy']);
        }

        $r->get('/rubrics', [Admin\RubricController::class, 'index']);
        $r->get('/rubrics/{id}/edit', [Admin\RubricController::class, 'edit']);
        $r->post('/rubrics/{id}', [Admin\RubricController::class, 'update']);
        $r->get('/difficulties', [Admin\SettingsController::class, 'difficulties']);
        $r->post('/difficulties', [Admin\SettingsController::class, 'updateDifficulties']);
        $r->get('/settings', [Admin\SettingsController::class, 'index']);
        $r->post('/settings', [Admin\SettingsController::class, 'update']);
        $r->get('/translations', [Admin\TranslationController::class, 'index']);
        $r->post('/translations', [Admin\TranslationController::class, 'update']);
        $r->get('/reports', [Admin\ModerationController::class, 'index']);
        $r->post('/reports/{id}', [Admin\ModerationController::class, 'update']);
        $r->get('/audit', [Admin\AuditController::class, 'index']);
    });
};
