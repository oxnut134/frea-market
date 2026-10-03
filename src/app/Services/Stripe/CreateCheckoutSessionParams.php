<?php

namespace App\Services\Stripe;

/**
 * Checkout Session を作成するための入力
 */
class CreateCheckoutSessionParams
{
    /**
     * @param string $paymentMethod 'card' または 'konbini'
     * @param int    $expiresAt     Checkout Session の有効期限（UNIX タイムスタンプ）
     */
    public function __construct(
        public readonly int $purchaseId,
        public readonly int $itemId,
        public readonly int $userId,
        public readonly string $itemName,
        public readonly int $amount,
        public readonly string $paymentMethod,
        public readonly string $customerEmail,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly int $expiresAt,
    ) {
    }
}
