<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * GeniusPay merchant API (https://geniuspay.ci/docs/api).
 *
 * Requests are authenticated with the X-API-Key / X-API-Secret headers and only ever sent from the
 * server. Webhooks carry an HMAC-SHA256 signature of "timestamp.payload" computed with the webhook
 * secret (whsec_...), in the X-Webhook-Signature header, with X-Webhook-Timestamp.
 */
final class GeniusPayGateway implements PaymentGatewayInterface
{
    public const SIGNATURE_TOLERANCE = 300;

    /** GeniusPay status => local status. */
    private const STATUSES = [
        'pending' => 'pending', 'initiated' => 'pending', 'processing' => 'processing',
        'completed' => 'completed', 'success' => 'completed', 'successful' => 'completed', 'paid' => 'completed',
        'failed' => 'failed', 'cancelled' => 'cancelled', 'canceled' => 'cancelled',
        'expired' => 'expired', 'refunded' => 'refunded',
    ];

    /** @var callable(string, string, array<string, string>, ?string): array{0: int, 1: string} */
    private $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $webhookSecret = '',
        private readonly int $timeout = 20,
        private readonly string $paymentMethod = '',
        ?callable $transport = null,
    ) {
        $this->transport = $transport ?? [$this, 'curl'];
    }

    public function name(): string
    {
        return 'geniuspay';
    }

    public function configured(): bool
    {
        return $this->apiKey !== '' && $this->apiSecret !== '';
    }

    public function createCheckout(array $payment): array
    {
        $body = [
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'description' => mb_substr($payment['description'], 0, 500),
            'customer' => array_filter([
                'name' => $payment['customer']['name'] ?? '',
                'email' => $payment['customer']['email'] ?? '',
            ]),
            'success_url' => $payment['success_url'],
            'error_url' => $payment['error_url'],
            'metadata' => ['payment_id' => $payment['public_id']],
        ];
        if ($this->paymentMethod !== '') {
            $body['payment_method'] = $this->paymentMethod;
        }
        $data = $this->request('POST', '/payments', $body);
        $reference = (string) ($data['reference'] ?? '');
        $url = (string) ($data['checkout_url'] ?? $data['payment_url'] ?? '');
        if ($reference === '' || !preg_match('#^https://#i', $url)) {
            throw new PaymentGatewayException('GeniusPay response without reference or secure checkout URL');
        }
        return ['reference' => $reference, 'checkout_url' => $url, 'status' => self::status((string) ($data['status'] ?? 'pending'))];
    }

    public function fetch(string $reference): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $reference)) {
            throw new PaymentGatewayException('Invalid reference');
        }
        $data = $this->request('GET', '/payments/' . rawurlencode($reference));
        return [
            'reference' => (string) ($data['reference'] ?? ''),
            'status' => self::status((string) ($data['status'] ?? '')),
            'amount' => (int) round((float) ($data['amount'] ?? 0)),
            'currency' => strtoupper((string) ($data['currency'] ?? 'XOF')),
            'payment_method' => isset($data['payment_method']) ? mb_substr((string) $data['payment_method'], 0, 40) : null,
        ];
    }

    public static function status(string $remote): string
    {
        return self::STATUSES[strtolower(trim($remote))] ?? 'pending';
    }

    /**
     * Check a webhook and return its decoded payload, or null when the signature, the timestamp or the
     * body is not valid. The documentation signs "timestamp.json_payload"; both the raw body and its
     * re-encoded form are accepted because the provider's samples re-serialise the payload.
     *
     * @param array<string, string> $headers lower-case header names
     */
    public function verifyWebhook(string $rawBody, array $headers, ?int $now = null): ?array
    {
        $signature = strtolower(trim((string) ($headers['x-webhook-signature'] ?? '')));
        $timestamp = trim((string) ($headers['x-webhook-timestamp'] ?? ''));
        if ($this->webhookSecret === '' || $signature === '' || !ctype_digit($timestamp)) {
            return null;
        }
        if (abs(($now ?? time()) - (int) $timestamp) > self::SIGNATURE_TOLERANCE) {
            return null;
        }
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return null;
        }
        $candidates = [$rawBody, (string) json_encode($payload), (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
        foreach (array_unique($candidates) as $signed) {
            if (hash_equals(hash_hmac('sha256', $timestamp . '.' . $signed, $this->webhookSecret), $signature)) {
                return $payload;
            }
        }
        return null;
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        if (!$this->configured()) {
            throw new PaymentGatewayException('GeniusPay keys are not configured');
        }
        $headers = [
            'X-API-Key' => $this->apiKey,
            'X-API-Secret' => $this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        [$status, $raw] = ($this->transport)($method, $this->baseUrl . $path, $headers, $body === null ? null : (string) json_encode($body));
        $json = json_decode($raw, true);
        if ($status < 200 || $status >= 300 || !is_array($json) || ($json['success'] ?? false) !== true || !is_array($json['data'] ?? null)) {
            $code = is_array($json) ? (string) ($json['error']['code'] ?? '') : '';
            throw new PaymentGatewayException(sprintf('GeniusPay %s %s failed with HTTP %d %s', $method, $path, $status, $code));
        }
        return $json['data'];
    }

    /** @return array{0: int, 1: string} */
    private function curl(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new PaymentGatewayException('GeniusPay unreachable: ' . $error);
        }
        return [$status, (string) $raw];
    }
}
