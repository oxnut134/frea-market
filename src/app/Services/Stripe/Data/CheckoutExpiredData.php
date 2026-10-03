<?php

namespace App\Services\Stripe\Data;

class CheckoutExpiredData
{
    public function __construct(
        public readonly string $sessionId,
        public readonly ?int $purchaseId,
    ) {
    }
}
