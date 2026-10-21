<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\AccessService;
use App\Services\PaymentService;
use App\Services\Payments\GeniusPayGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Settings;
use App\Services\UserService;
use Tests\Support\TestCase;

/** GeniusPay client, webhook signatures and the payment state machine, with a stubbed HTTP transport. */
final class PaymentTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';

    private array $requests = [];
    /** @var array<string, array{0: int, 1: array}> */
    private array $responses = [];
    private int $userId;
    private int $professionId;

    public function setUpClass(): void
    {
        $this->userId = UserService::create('pay-unit-' . uniqid() . '@test.local', 'Payer', 'Password123', 'fr');
        $this->professionId = (int) Database::fetchColumn("SELECT id FROM professions WHERE slug = 'data-analyst'");
        Settings::set('paywall_enabled', '1');
    }

    public function __destruct()
    {
        PaymentService::setGateway(null);
        Settings::set('paywall_enabled', '0');
    }

    private function gateway(string $method = ''): GeniusPayGateway
    {
        return new GeniusPayGateway('https://geniuspay.test/api/v1/merchant', 'pk_sandbox_x', 'sk_sandbox_y', self::SECRET, 5, $method,
            function (string $httpMethod, string $url, array $headers, ?string $body) {
                $this->requests[] = compact('httpMethod', 'url', 'headers', 'body');
                [$status, $json] = $this->responses[$httpMethod . ' ' . parse_url($url, PHP_URL_PATH)] ?? [404, ['success' => false]];
                return [$status, json_encode($json)];
            });
    }

    private function payment(string $reference, int $amount = 6550, string $status = 'pending'): array
    {
        $id = Database::insert('payments', [
            'public_id' => bin2hex(random_bytes(16)), 'user_id' => $this->userId, 'profession_id' => $this->professionId,
            'provider' => 'geniuspay', 'provider_reference' => $reference, 'amount' => $amount, 'currency' => 'XOF',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return Database::fetch('SELECT * FROM payments WHERE id = :id', ['id' => $id]);
    }

    private function hasAccess(): bool
    {
        return AccessService::hasAccess($this->userId, $this->professionId);
    }

    public function testCreateCheckoutSendsAuthenticatedRequest(): void
    {
        $this->responses['POST /api/v1/merchant/payments'] = [201, ['success' => true, 'data' => [
            'reference' => 'MTX-A1B2C3D4E5', 'status' => 'pending', 'checkout_url' => 'https://geniuspay.ci/checkout/MTX-A1B2C3D4E5',
        ]]];
        $out = $this->gateway()->createCheckout([
            'public_id' => 'abc', 'amount' => 6550, 'currency' => 'XOF', 'description' => 'InterviewPro - Data Analyst',
            'customer' => ['name' => 'Payer', 'email' => 'payer@test.local'], 'success_url' => 'https://app.test/payments/abc', 'error_url' => 'https://app.test/payments/abc?from=error',
        ]);
        $this->assertSame('MTX-A1B2C3D4E5', $out['reference']);
        $this->assertSame('https://geniuspay.ci/checkout/MTX-A1B2C3D4E5', $out['checkout_url']);
        $req = end($this->requests);
        $this->assertSame('pk_sandbox_x', $req['headers']['X-API-Key']);
        $this->assertSame('sk_sandbox_y', $req['headers']['X-API-Secret']);
        $body = json_decode($req['body'], true);
        $this->assertSame(6550, $body['amount']);
        $this->assertSame('XOF', $body['currency']);
        $this->assertSame('abc', $body['metadata']['payment_id']);
        $this->assertFalse(isset($body['payment_method']), 'hosted checkout when no method is forced');
        $this->gateway('wave')->createCheckout(['public_id' => 'x', 'amount' => 6550, 'currency' => 'XOF', 'description' => 'd', 'customer' => [], 'success_url' => 'u', 'error_url' => 'u']);
        $this->assertSame('wave', json_decode(end($this->requests)['body'], true)['payment_method'], 'configured payment method forwarded');
    }

    public function testCheckoutRejectsUnsafeOrFailedResponses(): void
    {
        $this->responses['POST /api/v1/merchant/payments'] = [201, ['success' => true, 'data' => ['reference' => 'MTX-1', 'checkout_url' => 'http://insecure.test/pay']]];
        $this->expectException(PaymentGatewayException::class, fn () => $this->gateway()->createCheckout(['public_id' => 'a', 'amount' => 6550, 'currency' => 'XOF', 'description' => 'd', 'customer' => [], 'success_url' => 'u', 'error_url' => 'u']), 'plain http checkout refused');
        $this->responses['POST /api/v1/merchant/payments'] = [401, ['success' => false, 'error' => ['code' => 'INVALID_API_KEY']]];
        $this->expectException(PaymentGatewayException::class, fn () => $this->gateway()->createCheckout(['public_id' => 'a', 'amount' => 6550, 'currency' => 'XOF', 'description' => 'd', 'customer' => [], 'success_url' => 'u', 'error_url' => 'u']), 'API error');
        $unconfigured = new GeniusPayGateway('https://geniuspay.test', '', '', '', 5, '', fn () => [200, '{}']);
        $this->assertFalse($unconfigured->configured());
        $this->expectException(PaymentGatewayException::class, fn () => $unconfigured->fetch('MTX-1'), 'missing keys');
    }

    public function testStatusMappingAndFetch(): void
    {
        foreach (['completed' => 'completed', 'success' => 'completed', 'PAID' => 'completed', 'processing' => 'processing', 'failed' => 'failed',
                  'canceled' => 'cancelled', 'expired' => 'expired', 'refunded' => 'refunded', 'weird' => 'pending'] as $remote => $local) {
            $this->assertSame($local, GeniusPayGateway::status($remote), $remote);
        }
        $this->responses['GET /api/v1/merchant/payments/MTX-FETCH1'] = [200, ['success' => true, 'data' => [
            'reference' => 'MTX-FETCH1', 'amount' => '6550.00', 'currency' => 'xof', 'status' => 'completed', 'payment_method' => 'wave',
        ]]];
        $r = $this->gateway()->fetch('MTX-FETCH1');
        $this->assertSame(['reference' => 'MTX-FETCH1', 'status' => 'completed', 'amount' => 6550, 'currency' => 'XOF', 'payment_method' => 'wave'], $r);
        $this->expectException(PaymentGatewayException::class, fn () => $this->gateway()->fetch('../admin'), 'reference format checked');
    }

    public function testWebhookSignatureVerification(): void
    {
        $g = $this->gateway();
        $body = json_encode(['id' => 'evt-1', 'event' => 'payment.success', 'data' => ['reference' => 'MTX-1']]);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, self::SECRET);
        $this->assertTrue(is_array($g->verifyWebhook($body, ['x-webhook-signature' => $sig, 'x-webhook-timestamp' => $ts])), 'valid signature');
        $this->assertSame(null, $g->verifyWebhook($body, ['x-webhook-signature' => hash_hmac('sha256', $ts . '.' . $body, 'other'), 'x-webhook-timestamp' => $ts]), 'wrong secret');
        $tampered = str_replace('MTX-1', 'MTX-2', $body);
        $this->assertSame(null, $g->verifyWebhook($tampered, ['x-webhook-signature' => $sig, 'x-webhook-timestamp' => $ts]), 'tampered body');
        $this->assertSame(null, $g->verifyWebhook($body, ['x-webhook-signature' => $sig, 'x-webhook-timestamp' => (string) ((int) $ts + 1)]), 'timestamp is signed');
        $old = (string) (time() - 600);
        $this->assertSame(null, $g->verifyWebhook($body, ['x-webhook-signature' => hash_hmac('sha256', $old . '.' . $body, self::SECRET), 'x-webhook-timestamp' => $old]), 'replayed after 5 minutes');
        $this->assertSame(null, $g->verifyWebhook($body, ['x-webhook-timestamp' => $ts]), 'missing signature');
        $noSecret = new GeniusPayGateway('https://geniuspay.test', 'k', 's', '', 5, '', fn () => [200, '{}']);
        $this->assertSame(null, $noSecret->verifyWebhook($body, ['x-webhook-signature' => $sig, 'x-webhook-timestamp' => $ts]), 'no secret configured, nothing accepted');
    }

    public function testCompletedPaymentGrantsAccessOnceAndRefundRevokes(): void
    {
        $p = $this->payment('MTX-OK1');
        $this->assertFalse($this->hasAccess());
        $done = PaymentService::apply((int) $p['id'], ['reference' => 'MTX-OK1', 'status' => 'completed', 'amount' => 6550, 'currency' => 'XOF', 'payment_method' => 'wave']);
        $this->assertSame('completed', $done['status']);
        $this->assertTrue($this->hasAccess(), 'access granted');
        PaymentService::apply((int) $p['id'], ['reference' => 'MTX-OK1', 'status' => 'completed', 'amount' => 6550, 'currency' => 'XOF', 'payment_method' => 'wave']);
        $this->assertSame(1, (int) Database::fetchColumn('SELECT COUNT(*) FROM profession_access WHERE user_id = :u', ['u' => $this->userId]), 'idempotent');
        $refunded = PaymentService::apply((int) $p['id'], ['reference' => 'MTX-OK1', 'status' => 'refunded', 'amount' => 6550, 'currency' => 'XOF', 'payment_method' => null]);
        $this->assertSame('refunded', $refunded['status']);
        $this->assertFalse($this->hasAccess(), 'refund removes access');
    }

    public function testWrongAmountOrReferenceNeverGrantsAccess(): void
    {
        $p = $this->payment('MTX-LOW1');
        $r = PaymentService::apply((int) $p['id'], ['reference' => 'MTX-LOW1', 'status' => 'completed', 'amount' => 200, 'currency' => 'XOF', 'payment_method' => 'wave']);
        $this->assertSame('failed', $r['status']);
        $this->assertSame('amount_mismatch', $r['failure_reason']);
        $p2 = $this->payment('MTX-EUR1');
        $this->assertSame('failed', PaymentService::apply((int) $p2['id'], ['reference' => 'MTX-EUR1', 'status' => 'completed', 'amount' => 6550, 'currency' => 'EUR', 'payment_method' => null])['status'], 'currency checked');
        $p3 = $this->payment('MTX-REF1');
        $this->assertSame('pending', PaymentService::apply((int) $p3['id'], ['reference' => 'MTX-OTHER', 'status' => 'completed', 'amount' => 6550, 'currency' => 'XOF', 'payment_method' => null])['status'], 'other reference ignored');
        $this->assertFalse($this->hasAccess());
    }

    public function testWebhookTriggersServerSideCheck(): void
    {
        PaymentService::setGateway($this->gateway());
        $p = $this->payment('MTX-HOOK1');
        $body = json_encode(['id' => 'evt-' . uniqid(), 'event' => 'payment.success', 'data' => ['reference' => 'MTX-HOOK1', 'status' => 'completed', 'amount' => 6550]]);
        $ts = (string) time();
        $headers = ['x-webhook-signature' => hash_hmac('sha256', $ts . '.' . $body, self::SECRET), 'x-webhook-timestamp' => $ts];

        // The provider still reports the payment as pending: the payload alone grants nothing.
        $this->responses['GET /api/v1/merchant/payments/MTX-HOOK1'] = [200, ['success' => true, 'data' => ['reference' => 'MTX-HOOK1', 'amount' => 6550, 'currency' => 'XOF', 'status' => 'pending']]];
        $this->assertSame(200, PaymentService::handleWebhook($body, $headers));
        $this->assertFalse($this->hasAccess(), 'webhook payload not trusted');
        $this->assertSame(200, PaymentService::handleWebhook($body, $headers), 'duplicate delivery accepted');
        $this->assertSame(1, (int) Database::fetchColumn("SELECT COUNT(*) FROM payment_notifications WHERE provider_reference = 'MTX-HOOK1'"), 'stored once');

        $this->responses['GET /api/v1/merchant/payments/MTX-HOOK1'] = [200, ['success' => true, 'data' => ['reference' => 'MTX-HOOK1', 'amount' => 6550, 'currency' => 'XOF', 'status' => 'completed']]];
        $body2 = json_encode(['id' => 'evt-' . uniqid(), 'event' => 'payment.success', 'data' => ['reference' => 'MTX-HOOK1']]);
        $this->assertSame(200, PaymentService::handleWebhook($body2, ['x-webhook-signature' => hash_hmac('sha256', $ts . '.' . $body2, self::SECRET), 'x-webhook-timestamp' => $ts]));
        $this->assertSame('completed', Database::fetchColumn('SELECT status FROM payments WHERE id = :id', ['id' => $p['id']]));
        $this->assertTrue($this->hasAccess(), 'access granted after the provider confirmed');

        $this->assertSame(401, PaymentService::handleWebhook($body2, ['x-webhook-signature' => 'bad', 'x-webhook-timestamp' => $ts]), 'bad signature rejected');
        PaymentService::setGateway(null);
    }

    public function testTrialAndAdminRules(): void
    {
        $uid = UserService::create('trial-' . uniqid() . '@test.local', 'Trial', 'Password123', 'fr');
        $this->assertSame(['allowed' => true, 'trial' => true], AccessService::decide($uid, $this->professionId));
        Database::insert('interview_sessions', [
            'public_id' => bin2hex(random_bytes(16)), 'user_id' => $uid, 'profession_id' => $this->professionId, 'experience_level' => 2,
            'interview_type' => 'hr', 'locale' => 'fr', 'difficulty' => 2, 'duration_minutes' => 10, 'question_count' => 3, 'mode' => 'training',
            'is_trial' => 1, 'status' => 'completed', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(['allowed' => false, 'trial' => false], AccessService::decide($uid, $this->professionId), 'one trial only');
        AccessService::grant($uid, $this->professionId, 'admin');
        $this->assertSame(['allowed' => true, 'trial' => false], AccessService::decide($uid, $this->professionId));
        Settings::set('paywall_enabled', '0');
        $this->assertTrue(AccessService::hasAccess($uid, 1), 'everything open when the paywall is off');
        Settings::set('paywall_enabled', '1');
    }
}
