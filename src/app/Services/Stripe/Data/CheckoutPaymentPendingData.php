<?php

namespace App\Services\Stripe\Data;

class CheckoutPaymentPendingData
{
    public function __construct(
        public readonly string $sessionId,
        public readonly ?string $paymentIntentId,
        public readonly ?int $purchaseId,
        public readonly ?string $paymentMethodType,
    ) {
    }
}
