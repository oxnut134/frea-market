<?php

namespace App\Services\Stripe\Data;

class CheckoutPaymentSucceededData
{
    public function __construct(
        public readonly string $sessionId,
        public readonly ?string $paymentIntentId,
        public readonly ?int $purchaseId,
        public readonly ?int $amountTotal,
        public readonly ?string $paymentMethodType,
    ) {
    }
}
