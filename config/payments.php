<?php

use App\Core\Env;

return [
    // "geniuspay" for real payments, "sandbox" for a local simulated checkout (refused in production).
    'driver' => (string) Env::get('PAYMENT_DRIVER', 'sandbox'),
    'geniuspay' => [
        'base_url' => rtrim((string) Env::get('GENIUSPAY_BASE_URL', 'https://geniuspay.ci/api/v1/merchant'), '/'),
        'api_key' => (string) Env::get('GENIUSPAY_API_KEY', ''),
        'api_secret' => (string) Env::get('GENIUSPAY_API_SECRET', ''),
        'webhook_secret' => (string) Env::get('GENIUSPAY_WEBHOOK_SECRET', ''),
        'timeout' => (int) Env::get('GENIUSPAY_TIMEOUT_SECONDS', 20),
        // Optional: force a payment method (wave, orange_money, mtn_money, card...). Empty shows the GeniusPay checkout page.
        'payment_method' => (string) Env::get('GENIUSPAY_PAYMENT_METHOD', ''),
    ],
];
