<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** The payment provider could not be reached or refused the request. The message is safe to log, not to display. */
final class PaymentGatewayException extends \RuntimeException
{
}
