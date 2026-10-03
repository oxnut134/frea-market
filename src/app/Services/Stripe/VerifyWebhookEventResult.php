<?php

namespace App\Services\Stripe;

use Stripe\Event;

/**
 * Webhook の署名検証の結果（例外ではなく成功／失敗を値で返す）
 */
class VerifyWebhookEventResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?Event $event,
        public readonly ?string $error,
    ) {
    }

    public static function ok(Event $event): self
    {
        return new self(true, $event, null);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, $error);
    }
}
