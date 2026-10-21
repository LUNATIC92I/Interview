<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Profession;
use App\Services\AccessService;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\SandboxGateway;
use App\Services\RateLimiter;

/**
 * Unlocking a profession: summary page, start of the hosted checkout, return page and provider webhook.
 */
final class PaymentController extends Controller
{
    private function profession(string $slug): array
    {
        $profession = Profession::findBySlug($slug);
        if (!$profession || !(int) $profession['is_active']) {
            throw new HttpException(404);
        }
        return $profession;
    }

    public function checkout(Request $request, string $slug): Response
    {
        $profession = $this->profession($slug);
        $user = $this->user();
        if (AccessService::hasAccess((int) $user['id'], (int) $profession['id'])) {
            return $this->redirect('/my-resources/' . $profession['slug']);
        }
        return $this->view('payments/checkout', [
            'title' => t('checkout.title', ['profession' => $profession['name']]),
            'profession' => $profession,
            'skills' => Profession::skills((int) $profession['id']),
            'libraryCount' => (int) Database::fetchColumn(
                'SELECT COUNT(*) FROM questions WHERE is_active = 1 AND (profession_id = :p OR (profession_id IS NULL AND (category_id = :c OR category_id IS NULL)))',
                ['p' => $profession['id'], 'c' => $profession['category_id']]
            ),
            'price' => format_price(AccessService::price(), AccessService::currency()),
            'trialsLeft' => AccessService::trialsLeft((int) $user['id']),
            'sandbox' => PaymentService::driver() !== 'geniuspay',
        ]);
    }

    public function start(Request $request, string $slug): Response
    {
        $profession = $this->profession($slug);
        $user = $this->user();
        if (!RateLimiter::attempt('checkout:' . $user['id'], 10, 3600)) {
            $this->flash('error', t('checkout.too_many'));
            return $this->redirect('/checkout/' . $slug);
        }
        try {
            $payment = PaymentService::start($user, $profession);
        } catch (\DomainException) {
            return $this->redirect('/my-resources/' . $profession['slug']);
        } catch (PaymentGatewayException) {
            $this->flash('error', t('checkout.provider_error'));
            return $this->redirect('/checkout/' . $slug);
        }
        // A same-origin hop: the CSP form-action directive would block a form POST redirected to the provider.
        return Response::redirect('/payments/' . $payment['public_id'] . '/checkout', 303);
    }

    /** Hand-off page that opens the provider checkout (link, followed automatically by app.js). */
    public function handoff(Request $request, string $publicId): Response
    {
        $payment = PaymentService::findPublic($publicId, (int) $this->user()['id']) ?? throw new HttpException(404);
        if ($payment['status'] !== 'pending' || !$payment['checkout_url']) {
            return $this->redirect('/payments/' . $payment['public_id']);
        }
        return $this->view('payments/handoff', [
            'title' => t('checkout.redirecting'),
            'payment' => $payment,
            'profession' => Profession::find((int) $payment['profession_id']),
        ]);
    }

    /** Return page after the checkout: the status shown is the one confirmed by the provider. */
    public function show(Request $request, string $publicId): Response
    {
        $payment = PaymentService::findPublic($publicId, (int) $this->user()['id']) ?? throw new HttpException(404);
        $payment = PaymentService::refresh($payment);
        $profession = Profession::find((int) $payment['profession_id']);
        return $this->view('payments/show', [
            'title' => t('payment.title'),
            'payment' => $payment,
            'profession' => $profession,
        ]);
    }

    /** Local simulated checkout (development and tests only). */
    public function sandbox(Request $request, string $publicId): Response
    {
        [$payment, $gateway] = $this->sandboxPayment($publicId);
        return $this->view('payments/sandbox', [
            'title' => t('sandbox.title'),
            'payment' => $payment,
            'profession' => Profession::find((int) $payment['profession_id']),
        ]);
    }

    public function sandboxSettle(Request $request, string $publicId): Response
    {
        [$payment, $gateway] = $this->sandboxPayment($publicId);
        $outcome = $request->str('outcome');
        if (!in_array($outcome, ['completed', 'failed', 'cancelled'], true)) {
            throw new HttpException(422);
        }
        $gateway->settle((string) $payment['provider_reference'], $outcome);
        return $this->redirect('/payments/' . $payment['public_id']);
    }

    /** @return array{0: array, 1: SandboxGateway} */
    private function sandboxPayment(string $publicId): array
    {
        $gateway = PaymentService::driver() === 'sandbox' && PaymentService::sandboxAllowed() ? PaymentService::gateway() : null;
        $payment = PaymentService::findPublic($publicId, (int) $this->user()['id']);
        if (!$gateway instanceof SandboxGateway || !$payment || $payment['provider'] !== 'sandbox' || $payment['status'] !== 'pending') {
            throw new HttpException(404);
        }
        return [$payment, $gateway];
    }

    public function webhook(Request $request): Response
    {
        $headers = [];
        foreach (['X-Webhook-Signature', 'X-Webhook-Timestamp', 'X-Webhook-Event', 'X-Webhook-Delivery', 'X-Webhook-Environment'] as $name) {
            $headers[strtolower($name)] = $request->header($name);
        }
        $status = PaymentService::handleWebhook($request->rawBody(), $headers);
        return $this->json(['received' => $status === 200], $status);
    }

    /** Payment history of the signed-in user. */
    public static function history(int $userId): array
    {
        return Database::fetchAll(
            'SELECT pay.*, COALESCE(t1.name, t2.name) AS profession_name
             FROM payments pay
             JOIN professions p ON p.id = pay.profession_id
             LEFT JOIN profession_translations t1 ON t1.profession_id = p.id AND t1.locale = :l
             LEFT JOIN profession_translations t2 ON t2.profession_id = p.id AND t2.locale = :o
             WHERE pay.user_id = :u AND pay.status <> :created ORDER BY pay.id DESC LIMIT 50',
            ['u' => $userId, 'l' => locale(), 'o' => locale() === 'fr' ? 'en' : 'fr', 'created' => 'created']
        );
    }
}
