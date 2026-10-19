<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\Payments\GeniusPayGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\SandboxGateway;

/**
 * Payment lifecycle for profession access.
 *
 * The amount always comes from the settings, never from the browser. Access is granted only after the
 * provider itself reports the payment as completed for the exact amount and currency: the return URL
 * and the webhook only trigger a server-side status check (refresh()), they are never trusted as such.
 * Every transition is idempotent so the redirect, the webhook and manual checks can race safely.
 */
final class PaymentService
{
    public const FINAL = ['completed', 'failed', 'cancelled', 'expired', 'refunded'];

    private static ?PaymentGatewayInterface $gateway = null;

    public static function setGateway(?PaymentGatewayInterface $gateway): void
    {
        self::$gateway = $gateway;
    }

    public static function driver(): string
    {
        return (string) Config::get('payments.driver', 'sandbox');
    }

    public static function sandboxAllowed(): bool
    {
        return Config::get('app.env') !== 'production';
    }

    public static function gateway(): PaymentGatewayInterface
    {
        if (self::$gateway) {
            return self::$gateway;
        }
        if (self::driver() === 'geniuspay') {
            return self::$gateway = self::geniusPay();
        }
        if (self::driver() === 'sandbox' && self::sandboxAllowed()) {
            return self::$gateway = new SandboxGateway(BASE_PATH . '/storage/cache/sandbox-payments');
        }
        throw new PaymentGatewayException('No usable payment driver (the sandbox is disabled in production)');
    }

    public static function geniusPay(): GeniusPayGateway
    {
        return new GeniusPayGateway(
            (string) Config::get('payments.geniuspay.base_url'),
            (string) Config::get('payments.geniuspay.api_key'),
            (string) Config::get('payments.geniuspay.api_secret'),
            (string) Config::get('payments.geniuspay.webhook_secret'),
            (int) Config::get('payments.geniuspay.timeout', 20),
            (string) Config::get('payments.geniuspay.payment_method', ''),
        );
    }

    public static function findPublic(string $publicId, int $userId): ?array
    {
        return Database::fetch('SELECT * FROM payments WHERE public_id = :p AND user_id = :u', ['p' => $publicId, 'u' => $userId]);
    }

    /**
     * Create a payment and open the provider checkout. A recent unfinished checkout for the same
     * profession is reused instead of creating a second one.
     *
     * @throws \DomainException when the profession is already unlocked
     * @throws PaymentGatewayException when the provider fails
     */
    public static function start(array $user, array $profession): array
    {
        if (AccessService::hasAccess((int) $user['id'], (int) $profession['id'])) {
            throw new \DomainException('already_unlocked');
        }
        $gateway = self::gateway();
        $amount = AccessService::price();
        $currency = AccessService::currency();
        $open = Database::fetch(
            "SELECT * FROM payments WHERE user_id = :u AND profession_id = :p AND provider = :g AND status = 'pending'
                AND amount = :a AND currency = :c AND checkout_url IS NOT NULL AND created_at >= :since ORDER BY id DESC LIMIT 1",
            ['u' => $user['id'], 'p' => $profession['id'], 'g' => $gateway->name(), 'a' => $amount, 'c' => $currency, 'since' => gmdate('Y-m-d H:i:s', time() - 6 * 3600)]
        );
        if ($open) {
            return $open;
        }
        $publicId = bin2hex(random_bytes(16));
        $id = Database::insert('payments', [
            'public_id' => $publicId, 'user_id' => $user['id'], 'profession_id' => $profession['id'],
            'provider' => $gateway->name(), 'amount' => $amount, 'currency' => $currency, 'status' => 'created',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $return = absolute_url('/payments/' . $publicId);
        try {
            $checkout = $gateway->createCheckout([
                'public_id' => $publicId,
                'amount' => $amount,
                'currency' => $currency,
                'description' => 'InterviewPro - ' . $profession['name'],
                'customer' => ['name' => (string) $user['display_name'], 'email' => (string) $user['email']],
                'success_url' => $return,
                'error_url' => $return . '?from=error',
            ]);
        } catch (PaymentGatewayException $e) {
            Database::update('payments', ['status' => 'failed', 'failure_reason' => 'checkout_error', 'updated_at' => now()], ['id' => $id]);
            Logger::error('Payment checkout failed', ['payment' => $publicId, 'error' => $e->getMessage()]);
            throw $e;
        }
        Database::update('payments', [
            'provider_reference' => $checkout['reference'], 'checkout_url' => $checkout['checkout_url'],
            'status' => in_array($checkout['status'], ['pending', 'processing'], true) ? $checkout['status'] : 'pending', 'updated_at' => now(),
        ], ['id' => $id]);
        AuditLogger::log('payment.started', 'payment', $id, ['profession_id' => (int) $profession['id'], 'amount' => $amount, 'currency' => $currency]);
        return Database::fetch('SELECT * FROM payments WHERE id = :id', ['id' => $id]);
    }

    /**
     * Ask the provider for the current status and apply it. Returns the up to date payment row.
     * Provider errors leave the payment unchanged (it is checked again later).
     */
    public static function refresh(array $payment): array
    {
        // Nothing to ask for a payment that never reached the provider; failures and refunds are final.
        if (!$payment['provider_reference'] || in_array($payment['status'], ['failed', 'cancelled', 'expired', 'refunded'], true)) {
            return $payment;
        }
        try {
            $remote = self::gateway()->fetch((string) $payment['provider_reference']);
        } catch (PaymentGatewayException $e) {
            Logger::error('Payment status check failed', ['payment' => $payment['public_id'], 'error' => $e->getMessage()]);
            return $payment;
        }
        return self::apply((int) $payment['id'], $remote);
    }

    /** Apply a provider status to a payment, granting or revoking access as needed. */
    public static function apply(int $paymentId, array $remote): array
    {
        return Database::transaction(static function () use ($paymentId, $remote) {
            $p = Database::fetch('SELECT * FROM payments WHERE id = :id FOR UPDATE', ['id' => $paymentId]);
            if ($remote['reference'] !== '' && $remote['reference'] !== $p['provider_reference']) {
                Logger::security('Payment reference mismatch', ['payment' => $p['public_id']]);
                return $p;
            }
            $status = $remote['status'];
            if ($p['status'] === 'completed') {
                if ($status === 'refunded') {
                    Database::update('payments', ['status' => 'refunded', 'updated_at' => now()], ['id' => $p['id']]);
                    if ($p['user_id']) {
                        AccessService::revoke((int) $p['user_id'], (int) $p['profession_id'], null, 'refund');
                    }
                    AuditLogger::log('payment.refunded', 'payment', (int) $p['id'], [], null);
                }
                return Database::fetch('SELECT * FROM payments WHERE id = :id', ['id' => $p['id']]);
            }
            if ($status === 'completed') {
                if ((int) $remote['amount'] !== (int) $p['amount'] || strtoupper($remote['currency']) !== $p['currency']) {
                    Database::update('payments', ['status' => 'failed', 'failure_reason' => 'amount_mismatch', 'updated_at' => now()], ['id' => $p['id']]);
                    Logger::security('Payment amount mismatch', ['payment' => $p['public_id'], 'expected' => $p['amount'] . ' ' . $p['currency'], 'received' => $remote['amount'] . ' ' . $remote['currency']]);
                    return Database::fetch('SELECT * FROM payments WHERE id = :id', ['id' => $p['id']]);
                }
                Database::update('payments', [
                    'status' => 'completed', 'completed_at' => now(), 'updated_at' => now(), 'failure_reason' => null,
                    'payment_method' => $remote['payment_method'] ?? null,
                ], ['id' => $p['id']]);
                if ($p['user_id']) {
                    AccessService::grant((int) $p['user_id'], (int) $p['profession_id'], 'payment', (int) $p['id']);
                }
                AuditLogger::log('payment.completed', 'payment', (int) $p['id'], ['amount' => (int) $p['amount'], 'currency' => $p['currency']], $p['user_id'] ? (int) $p['user_id'] : null);
            } elseif ($status !== $p['status'] && $status !== 'refunded') {
                Database::update('payments', ['status' => $status, 'updated_at' => now()], ['id' => $p['id']]);
            }
            return Database::fetch('SELECT * FROM payments WHERE id = :id', ['id' => $p['id']]);
        });
    }

    /**
     * Handle a GeniusPay webhook. The payload only says which payment changed: its status is read
     * again from the API before anything is granted. Returns an HTTP status code.
     *
     * @param array<string, string> $headers lower-case header names
     */
    public static function handleWebhook(string $rawBody, array $headers): int
    {
        try {
            $gateway = self::gateway();
        } catch (PaymentGatewayException) {
            return 404;
        }
        if (!$gateway instanceof GeniusPayGateway) {
            return 404; // webhooks are only meaningful with the real provider
        }
        $payload = $gateway->verifyWebhook($rawBody, $headers);
        if ($payload === null) {
            Logger::security('Invalid payment webhook signature');
            return 401;
        }
        $event = mb_substr((string) ($payload['event'] ?? $headers['x-webhook-event'] ?? ''), 0, 60);
        $eventId = mb_substr((string) ($payload['id'] ?? $headers['x-webhook-delivery'] ?? hash('sha256', $rawBody)), 0, 80);
        $reference = isset($payload['data']['reference']) ? mb_substr((string) $payload['data']['reference'], 0, 64) : null;
        try {
            $notificationId = Database::insert('payment_notifications', [
                'provider' => 'geniuspay', 'event_id' => $eventId, 'event_type' => $event, 'provider_reference' => $reference,
                'environment' => mb_substr((string) ($payload['environment'] ?? $headers['x-webhook-environment'] ?? ''), 0, 20), 'received_at' => now(),
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return 200; // already received
            }
            throw $e;
        }
        if ($reference !== null) {
            $payment = Database::fetch('SELECT * FROM payments WHERE provider = :g AND provider_reference = :r', ['g' => 'geniuspay', 'r' => $reference]);
            if ($payment) {
                self::refresh($payment);
            }
        }
        Database::update('payment_notifications', ['processed_at' => now()], ['id' => $notificationId]);
        return 200;
    }
}
