<?php

namespace Tests\Unit\Services\Stripe;

use App\Services\Stripe\CheckoutWebhookHandlers;
use App\Services\Stripe\Data\CheckoutExpiredData;
use App\Services\Stripe\Data\CheckoutPaymentFailedData;
use App\Services\Stripe\Data\CheckoutPaymentPendingData;
use App\Services\Stripe\Data\CheckoutPaymentSucceededData;
use App\Services\Stripe\StripeCheckoutService;
use PHPUnit\Framework\TestCase;
use Stripe\Event;
use Stripe\StripeClient;

class HandleWebhookEventTest extends TestCase
{
    /** @var array<int, array{0: string, 1: object}> 呼ばれたハンドラと渡されたデータ */
    private array $calls = [];

    private function handlers(): CheckoutWebhookHandlers
    {
        $calls = &$this->calls;

        return new class($calls) implements CheckoutWebhookHandlers {
            public function __construct(private array &$calls)
            {
            }

            public function onCheckoutPaymentSucceeded(CheckoutPaymentSucceededData $data): void
            {
                $this->calls[] = ['succeeded', $data];
            }

            public function onCheckoutPaymentPending(CheckoutPaymentPendingData $data): void
            {
                $this->calls[] = ['pending', $data];
            }

            public function onCheckoutPaymentFailed(CheckoutPaymentFailedData $data): void
            {
                $this->calls[] = ['failed', $data];
            }

            public function onCheckoutExpired(CheckoutExpiredData $data): void
            {
                $this->calls[] = ['expired', $data];
            }
        };
    }

    private function handle(Event $event)
    {
        $service = new StripeCheckoutService(new StripeClient('sk_test_dummy'), 'whsec_test_dummy');

        return $service->handleWebhookEvent($event, $this->handlers());
    }

    private function event(string $type, array $session = []): Event
    {
        return Event::constructFrom([
            'id' => 'evt_test_123',
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => array_merge([
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_123',
                    'amount_total' => 15000,
                    'payment_method_types' => ['card'],
                    'metadata' => ['purchase_id' => '12', 'item_id' => '3', 'user_id' => '7'],
                ], $session),
            ],
        ]);
    }

    // カードの即時決済（completed かつ paid）は支払い成功
    public function testCompletedAndPaidCallsSucceeded(): void
    {
        $result = $this->handle($this->event('checkout.session.completed'));

        $this->assertTrue($result->handled);
        $this->assertSame('checkout.session.completed', $result->type);
        $this->assertCount(1, $this->calls);
        [$handler, $data] = $this->calls[0];
        $this->assertSame('succeeded', $handler);
        $this->assertInstanceOf(CheckoutPaymentSucceededData::class, $data);
        $this->assertSame('cs_test_123', $data->sessionId);
        $this->assertSame('pi_test_123', $data->paymentIntentId);
        $this->assertSame(12, $data->purchaseId);
        $this->assertSame(15000, $data->amountTotal);
        $this->assertSame('card', $data->paymentMethodType);
    }

    // コンビニ払いの支払い番号発行（completed かつ unpaid）は支払い待ち
    public function testCompletedAndUnpaidCallsPending(): void
    {
        $result = $this->handle($this->event('checkout.session.completed', [
            'payment_status' => 'unpaid',
            'payment_method_types' => ['konbini'],
        ]));

        $this->assertTrue($result->handled);
        [$handler, $data] = $this->calls[0];
        $this->assertSame('pending', $handler);
        $this->assertInstanceOf(CheckoutPaymentPendingData::class, $data);
        $this->assertSame('cs_test_123', $data->sessionId);
        $this->assertSame('pi_test_123', $data->paymentIntentId);
        $this->assertSame(12, $data->purchaseId);
        $this->assertSame('konbini', $data->paymentMethodType);
    }

    // コンビニ払いの入金は支払い成功
    public function testAsyncPaymentSucceededCallsSucceeded(): void
    {
        $result = $this->handle($this->event('checkout.session.async_payment_succeeded', [
            'payment_method_types' => ['konbini'],
        ]));

        $this->assertTrue($result->handled);
        [$handler, $data] = $this->calls[0];
        $this->assertSame('succeeded', $handler);
        $this->assertSame('konbini', $data->paymentMethodType);
    }

    // コンビニ払いの支払期限切れは支払い失敗
    public function testAsyncPaymentFailedCallsFailed(): void
    {
        $result = $this->handle($this->event('checkout.session.async_payment_failed', [
            'payment_status' => 'unpaid',
        ]));

        $this->assertTrue($result->handled);
        [$handler, $data] = $this->calls[0];
        $this->assertSame('failed', $handler);
        $this->assertInstanceOf(CheckoutPaymentFailedData::class, $data);
        $this->assertSame('cs_test_123', $data->sessionId);
        $this->assertSame('pi_test_123', $data->paymentIntentId);
        $this->assertSame(12, $data->purchaseId);
    }

    // 決済画面の期限切れ
    public function testExpiredCallsExpired(): void
    {
        $result = $this->handle($this->event('checkout.session.expired', [
            'payment_status' => 'unpaid',
            'payment_intent' => null,
        ]));

        $this->assertTrue($result->handled);
        [$handler, $data] = $this->calls[0];
        $this->assertSame('expired', $handler);
        $this->assertInstanceOf(CheckoutExpiredData::class, $data);
        $this->assertSame('cs_test_123', $data->sessionId);
        $this->assertSame(12, $data->purchaseId);
    }

    // metadata に購入 ID がない Session（stripe trigger で作られたものなど）は purchaseId が null
    public function testPurchaseIdIsNullWithoutMetadata(): void
    {
        $this->handle($this->event('checkout.session.completed', ['metadata' => []]));

        [, $data] = $this->calls[0];
        $this->assertNull($data->purchaseId);
    }

    // 展開された PaymentIntent オブジェクトからも ID を取り出す
    public function testPaymentIntentIdFromExpandedObject(): void
    {
        $this->handle($this->event('checkout.session.completed', [
            'payment_intent' => ['id' => 'pi_test_expanded', 'object' => 'payment_intent'],
        ]));

        [, $data] = $this->calls[0];
        $this->assertSame('pi_test_expanded', $data->paymentIntentId);
    }

    // 支払いが不要な completed など、想定外の支払い状態は扱わない
    public function testCompletedWithOtherPaymentStatusIsNotHandled(): void
    {
        $result = $this->handle($this->event('checkout.session.completed', [
            'payment_status' => 'no_payment_required',
        ]));

        $this->assertFalse($result->handled);
        $this->assertSame([], $this->calls);
    }

    // 対象外のイベントは扱わない
    public function testOtherEventsAreNotHandled(): void
    {
        $event = Event::constructFrom([
            'id' => 'evt_test_456',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test_123', 'object' => 'payment_intent']],
        ]);

        $result = $this->handle($event);

        $this->assertFalse($result->handled);
        $this->assertSame('payment_intent.succeeded', $result->type);
        $this->assertSame([], $this->calls);
    }
}
