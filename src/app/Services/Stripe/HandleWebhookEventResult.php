<?php

namespace App\Services\Stripe;

/**
 * Webhook イベントの振り分け結果
 */
class HandleWebhookEventResult
{
    public function __construct(
        public readonly bool $handled,
        public readonly string $type,
    ) {
    }
}
