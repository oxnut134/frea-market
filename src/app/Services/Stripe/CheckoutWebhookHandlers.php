<?php

namespace App\Services\Stripe;

use App\Services\Stripe\Data\CheckoutExpiredData;
use App\Services\Stripe\Data\CheckoutPaymentFailedData;
use App\Services\Stripe\Data\CheckoutPaymentPendingData;
use App\Services\Stripe\Data\CheckoutPaymentSucceededData;

/**
 * 単発決済（Checkout Session）の Webhook イベントごとのハンドラ
 *
 * サブスク用の kit の onPaymentFailed などと衝突しないよう、
 * 名前に Checkout を含めている。
 */
interface CheckoutWebhookHandlers
{
    // 支払いが完了した（カードの即時決済、またはコンビニ払いの入金）
    public function onCheckoutPaymentSucceeded(CheckoutPaymentSucceededData $data): void;

    // Checkout は完了したが、支払いはまだ（コンビニ払いの支払い番号の発行）
    public function onCheckoutPaymentPending(CheckoutPaymentPendingData $data): void;

    // 後払いの支払いが失敗した（コンビニ払いの支払期限切れ）
    public function onCheckoutPaymentFailed(CheckoutPaymentFailedData $data): void;

    // Checkout Session が完了しないまま期限切れになった
    public function onCheckoutExpired(CheckoutExpiredData $data): void;
}
