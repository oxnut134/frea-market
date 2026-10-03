<?php

namespace App\Services\Stripe;

use App\Services\Stripe\Data\CheckoutExpiredData;
use App\Services\Stripe\Data\CheckoutPaymentFailedData;
use App\Services\Stripe\Data\CheckoutPaymentPendingData;
use App\Services\Stripe\Data\CheckoutPaymentSucceededData;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe とのやり取り（単発決済の Checkout Session と Webhook）をまとめたサービス
 *
 * 設計は stripe-subscription-kit に合わせている。
 * - createCheckoutSession：Checkout Session の作成
 * - verifyWebhookEvent：署名検証。例外ではなく結果オブジェクトを返す
 * - handleWebhookEvent：イベント種別ごとにデータを整理してハンドラに渡す
 */
class StripeCheckoutService
{
    public function __construct(
        private StripeClient $stripe,
        private string $webhookSecret,
        private int $konbiniExpiresAfterDays = 3,
    ) {
    }

    /**
     * Checkout Session を作成する
     *
     * @throws ApiErrorException Stripe へのリクエストが失敗した場合
     * @throws RuntimeException  Stripe が決済画面の URL を返さなかった場合
     */
    public function createCheckoutSession(CreateCheckoutSessionParams $params): CheckoutSessionResult
    {
        $metadata = [
            'purchase_id' => (string) $params->purchaseId,
            'item_id' => (string) $params->itemId,
            'user_id' => (string) $params->userId,
        ];

        $request = [
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'jpy',
                        'product_data' => ['name' => $params->itemName],
                        'unit_amount' => $params->amount,
                    ],
                    'quantity' => 1,
                ],
            ],
            'payment_method_types' => [$params->paymentMethod],
            'customer_email' => $params->customerEmail,
            'success_url' => $params->successUrl,
            'cancel_url' => $params->cancelUrl,
            'expires_at' => $params->expiresAt,
            'metadata' => $metadata,
            // PaymentIntent 側からも購入を特定できるようにする
            'payment_intent_data' => ['metadata' => $metadata],
        ];

        if ($params->paymentMethod === 'konbini') {
            $request['payment_method_options'] = [
                'konbini' => ['expires_after_days' => $this->konbiniExpiresAfterDays],
            ];
        }

        $session = $this->stripe->checkout->sessions->create($request);

        if (empty($session->url)) {
            throw new RuntimeException('Stripe did not return a checkout session URL');
        }

        return new CheckoutSessionResult($session->id, $session->url, (int) $session->expires_at);
    }

    /**
     * Checkout Session を期限切れにする
     *
     * すでに完了・期限切れの Session など、Stripe が受け付けなかった場合は false を返す。
     */
    public function expireCheckoutSession(string $sessionId): bool
    {
        try {
            $this->stripe->checkout->sessions->expire($sessionId);

            return true;
        } catch (ApiErrorException $e) {
            return false;
        }
    }

    /**
     * Webhook の署名を検証し、イベントを取り出す
     *
     * @param string      $payload   リクエストボディの生の文字列（パース前のもの）
     * @param string|null $signature Stripe-Signature ヘッダーの値
     */
    public function verifyWebhookEvent(string $payload, ?string $signature): VerifyWebhookEventResult
    {
        if ($this->webhookSecret === '') {
            return VerifyWebhookEventResult::fail('Webhook secret is not configured');
        }
        if ($signature === null || $signature === '') {
            return VerifyWebhookEventResult::fail('Missing Stripe-Signature header');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);

            return VerifyWebhookEventResult::ok($event);
        } catch (SignatureVerificationException | UnexpectedValueException $e) {
            return VerifyWebhookEventResult::fail($e->getMessage());
        }
    }

    /**
     * イベント種別ごとにデータを整理し、対応するハンドラを呼ぶ
     */
    public function handleWebhookEvent(Event $event, CheckoutWebhookHandlers $handlers): HandleWebhookEventResult
    {
        $session = $event->data->object;
        if (!$session instanceof Session) {
            return new HandleWebhookEventResult(false, $event->type);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                if ($session->payment_status === 'paid') {
                    $handlers->onCheckoutPaymentSucceeded($this->succeededData($session));

                    return new HandleWebhookEventResult(true, $event->type);
                }
                if ($session->payment_status === 'unpaid') {
                    // コンビニ払いなど、支払い番号の発行時点ではまだ支払われていない
                    $handlers->onCheckoutPaymentPending(new CheckoutPaymentPendingData(
                        $session->id,
                        $this->paymentIntentId($session),
                        $this->purchaseId($session),
                        $this->paymentMethodType($session),
                    ));

                    return new HandleWebhookEventResult(true, $event->type);
                }

                return new HandleWebhookEventResult(false, $event->type);

            case 'checkout.session.async_payment_succeeded':
                $handlers->onCheckoutPaymentSucceeded($this->succeededData($session));

                return new HandleWebhookEventResult(true, $event->type);

            case 'checkout.session.async_payment_failed':
                $handlers->onCheckoutPaymentFailed(new CheckoutPaymentFailedData(
                    $session->id,
                    $this->paymentIntentId($session),
                    $this->purchaseId($session),
                ));

                return new HandleWebhookEventResult(true, $event->type);

            case 'checkout.session.expired':
                $handlers->onCheckoutExpired(new CheckoutExpiredData(
                    $session->id,
                    $this->purchaseId($session),
                ));

                return new HandleWebhookEventResult(true, $event->type);

            default:
                return new HandleWebhookEventResult(false, $event->type);
        }
    }

    private function succeededData(Session $session): CheckoutPaymentSucceededData
    {
        return new CheckoutPaymentSucceededData(
            $session->id,
            $this->paymentIntentId($session),
            $this->purchaseId($session),
            $session->amount_total !== null ? (int) $session->amount_total : null,
            $this->paymentMethodType($session),
        );
    }

    private function purchaseId(Session $session): ?int
    {
        $purchaseId = $session->metadata['purchase_id'] ?? null;

        return is_numeric($purchaseId) ? (int) $purchaseId : null;
    }

    private function paymentIntentId(Session $session): ?string
    {
        $paymentIntent = $session->payment_intent;

        if (is_string($paymentIntent)) {
            return $paymentIntent;
        }

        return $paymentIntent->id ?? null;
    }

    private function paymentMethodType(Session $session): ?string
    {
        return $session->payment_method_types[0] ?? null;
    }
}
