<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Local stand-in for the payment provider, used in development and automated tests. The checkout is
 * an InterviewPro page where the outcome is chosen by hand; it moves no money and is refused when
 * APP_ENV is production (see PaymentService::gateway()).
 */
final class SandboxGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly string $directory)
    {
    }

    public function name(): string
    {
        return 'sandbox';
    }

    public function createCheckout(array $payment): array
    {
        $reference = 'SBX-' . strtoupper(bin2hex(random_bytes(6)));
        $this->write($reference, ['status' => 'pending', 'amount' => $payment['amount'], 'currency' => $payment['currency']]);
        return ['reference' => $reference, 'checkout_url' => absolute_url('/payments/sandbox/' . $payment['public_id']), 'status' => 'pending'];
    }

    public function fetch(string $reference): array
    {
        $data = $this->read($reference) ?? throw new PaymentGatewayException('Unknown sandbox payment');
        return ['reference' => $reference, 'status' => $data['status'], 'amount' => (int) $data['amount'], 'currency' => $data['currency'], 'payment_method' => 'sandbox'];
    }

    /** Record the outcome chosen on the sandbox checkout page. */
    public function settle(string $reference, string $status, ?int $amount = null): void
    {
        $data = $this->read($reference) ?? throw new PaymentGatewayException('Unknown sandbox payment');
        $data['status'] = $status;
        if ($amount !== null) {
            $data['amount'] = $amount;
        }
        $this->write($reference, $data);
    }

    private function file(string $reference): string
    {
        if (!preg_match('/^SBX-[A-F0-9]{12}$/', $reference)) {
            throw new PaymentGatewayException('Invalid sandbox reference');
        }
        return $this->directory . '/' . $reference . '.json';
    }

    private function read(string $reference): ?array
    {
        $raw = @file_get_contents($this->file($reference));
        return $raw === false ? null : json_decode($raw, true);
    }

    private function write(string $reference, array $data): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0770, true);
        }
        file_put_contents($this->file($reference), json_encode($data), LOCK_EX);
    }
}
