<?php

namespace App\Services\Stripe;

/**
 * 作成した Checkout Session
 */
class CheckoutSessionResult
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $url,
        public readonly int $expiresAt,
    ) {
    }
}
