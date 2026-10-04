<?php

namespace App\Services\Purchase;

use App\Models\Purchase;
use App\Services\Stripe\CheckoutWebhookHandlers;
use App\Services\Stripe\Data\CheckoutExpiredData;
use App\Services\Stripe\Data\CheckoutPaymentFailedData;
use App\Services\Stripe\Data\CheckoutPaymentPendingData;
use App\Services\Stripe\Data\CheckoutPaymentSucceededData;
use Illuminate\Support\Facades\Log;

/**
 * Checkout の Webhook イベントを購入（purchases）の状態に反映する
 *
 * どの更新も「status = 'pending' のときだけ」の条件付き UPDATE 1 文で行う。
 * 同じイベントを重ねて受信しても、二重には更新されない。
 */
class PurchaseWebhookHandlers implements CheckoutWebhookHandlers
{
    // 支払いが完了した：pending → paid
    public function onCheckoutPaymentSucceeded(CheckoutPaymentSucceededData $data): void
    {
        $context = [
            'purchase_id' => $data->purchaseId,
            'checkout_session_id' => $data->sessionId,
            'payment_intent_id' => $data->paymentIntentId,
        ];

        $purchase = $this->findPurchase($data->purchaseId, $data->sessionId);
        if (!$purchase) {
            Log::error('Stripe webhook: payment succeeded but purchase was not found (manual handling required)', $context);

            return;
        }

        if ($data->amountTotal !== null && $data->amountTotal !== $purchase->amount) {
            Log::error('Stripe webhook: paid amount differs from purchase amount', $context + [
                'amount_total' => $data->amountTotal,
                'purchase_amount' => $purchase->amount,
            ]);
        }

        $updated = Purchase::where('id', $purchase->id)
            ->where('status', Purchase::STATUS_PENDING)
            ->update([
                'status' => Purchase::STATUS_PAID,
                'paid_at' => now(),
                'stripe_payment_intent_id' => $data->paymentIntentId,
            ]);
        if ($updated > 0) {
            return;
        }

        $status = Purchase::where('id', $purchase->id)->value('status');
        if ($status === Purchase::STATUS_PAID) {
            // 同じイベントの重複受信
            return;
        }

        // 確保が期限切れ・失敗になった後に支払いが通った。自動では返金せず、手動で対応する
        Log::error('Stripe webhook: payment succeeded for a purchase that is no longer pending (manual refund required)', $context + [
            'purchase_status' => $status,
        ]);
    }

    // コンビニ払いの支払い番号が発行された：確保をコンビニ払いの支払期限まで延ばす
    public function onCheckoutPaymentPending(CheckoutPaymentPendingData $data): void
    {
        $purchase = $this->findPurchase($data->purchaseId, $data->sessionId);
        if (!$purchase) {
            $this->logNotFound('payment pending', $data->purchaseId, $data->sessionId);

            return;
        }

        // 支払期限は、指定した日数後の 23:59:59。期限間際の入金の通知を待つため、少し余裕を足す
        $expires_at = now()
            ->addDays((int) config('services.stripe.konbini_expires_after_days'))
            ->endOfDay()
            ->addMinutes((int) config('services.stripe.konbini_expiry_grace_minutes'));

        Purchase::where('id', $purchase->id)
            ->where('status', Purchase::STATUS_PENDING)
            ->update([
                'expires_at' => $expires_at,
                'stripe_payment_intent_id' => $data->paymentIntentId,
                // Checkout Session は完了したので、決済画面には戻れない
                'stripe_checkout_url' => null,
            ]);
    }

    // コンビニ払いの支払期限が切れた、または支払いが失敗した：pending → failed
    public function onCheckoutPaymentFailed(CheckoutPaymentFailedData $data): void
    {
        $purchase = $this->findPurchase($data->purchaseId, $data->sessionId);
        if (!$purchase) {
            $this->logNotFound('payment failed', $data->purchaseId, $data->sessionId);

            return;
        }

        Purchase::where('id', $purchase->id)
            ->where('status', Purchase::STATUS_PENDING)
            ->update(['status' => Purchase::STATUS_FAILED]);
    }

    // Checkout Session が完了しないまま期限切れになった：pending → expired
    public function onCheckoutExpired(CheckoutExpiredData $data): void
    {
        $purchase = $this->findPurchase($data->purchaseId, $data->sessionId);
        if (!$purchase) {
            $this->logNotFound('session expired', $data->purchaseId, $data->sessionId);

            return;
        }

        Purchase::where('id', $purchase->id)
            ->where('status', Purchase::STATUS_PENDING)
            ->update(['status' => Purchase::STATUS_EXPIRED]);
    }

    // metadata の purchase_id で探し、なければ Checkout Session ID で探す
    private function findPurchase(?int $purchaseId, string $sessionId): ?Purchase
    {
        if ($purchaseId !== null) {
            return Purchase::find($purchaseId);
        }

        return Purchase::where('stripe_checkout_session_id', $sessionId)->first();
    }

    private function logNotFound(string $event, ?int $purchaseId, string $sessionId): void
    {
        Log::warning('Stripe webhook: purchase was not found for ' . $event, [
            'purchase_id' => $purchaseId,
            'checkout_session_id' => $sessionId,
        ]);
    }
}
