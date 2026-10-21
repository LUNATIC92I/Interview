<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * A payment provider. Amounts are whole units of the currency (FCFA has no decimals).
 * Implementations never decide access themselves: PaymentService checks the returned data.
 */
interface PaymentGatewayInterface
{
    public function name(): string;

    /**
     * Open a hosted checkout for a stored payment.
     *
     * @param array{public_id: string, amount: int, currency: string, description: string,
     *              customer: array{name: string, email: string}, success_url: string, error_url: string} $payment
     * @return array{reference: string, checkout_url: string, status: string}
     */
    public function createCheckout(array $payment): array;

    /**
     * Read the payment as known by the provider.
     *
     * @return array{reference: string, status: string, amount: int, currency: string, payment_method: ?string}
     */
    public function fetch(string $reference): array;
}
