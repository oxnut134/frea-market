<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Purchase;
use App\Services\Purchase\PurchaseWebhookHandlers;

// POST /stripe/webhook：署名の検証と、購入の状態への反映
class StripeWebhookEndpointTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $item;
    private $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->createUserWithProfile();
        $this->item = $this->createItem($this->createUserWithProfile());
    }

    private function pendingPurchase(array $overrides = []): Purchase
    {
        return Purchase::factory()->pending()->create(array_merge([
            'user_id' => $this->buyer->id,
            'item_id' => $this->item->id,
            'amount' => 4000,
            'stripe_checkout_session_id' => 'cs_test_123',
            'stripe_checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
        ], $overrides));
    }

    // ---------------- エンドポイント ----------------

    // ログイン不要・CSRF 除外で受け付ける
    public function testWebhookRouteIsOutsideAuthAndCsrf(): void
    {
        $middleware = Route::getRoutes()->getByName('stripe.webhook')->gatherMiddleware();
        $this->assertNotContains('auth', $middleware);
        $this->assertNotContains('verified', $middleware);
        $this->assertNotContains('profile.exists', $middleware);

        $except = (new \ReflectionProperty(VerifyCsrfToken::class, 'except'));
        $except->setAccessible(true);
        $this->assertContains('stripe/webhook', $except->getValue(app(VerifyCsrfToken::class)));
    }

    // 署名がない・正しくない場合は 400 で、何も更新しない
    public function testInvalidSignatureIsRejected(): void
    {
        $purchase = $this->pendingPurchase();
        $session = $this->sessionFor($purchase);

        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertStatus(400);
        $this->postStripeWebhook('checkout.session.completed', $session, 't=' . time() . ',v1=invalid')->assertStatus(400);
        $this->postStripeWebhook('checkout.session.completed', $session, 't=' . time() . ',v1=' . hash_hmac('sha256', 'x', 'whsec_other_secret'))->assertStatus(400);

        $this->assertSame(Purchase::STATUS_PENDING, $purchase->fresh()->status);
    }

    // 対応していないイベントは、何もせず 200 を返す
    public function testUnhandledEventReturnsOk(): void
    {
        $purchase = $this->pendingPurchase();

        $this->postStripeWebhook('customer.created', ['id' => 'cus_test_123', 'object' => 'customer'])
            ->assertOk()
            ->assertJson(['received' => true, 'handled' => false]);
        // completed でも、支払い不要（payment_status が paid / unpaid 以外）は対象外
        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase, ['payment_status' => 'no_payment_required']))
            ->assertOk()
            ->assertJson(['handled' => false]);

        $this->assertSame(Purchase::STATUS_PENDING, $purchase->fresh()->status);
    }

    // 処理中に例外が起きたら 500（Stripe が再送する）
    public function testHandlerExceptionReturns500(): void
    {
        $purchase = $this->pendingPurchase();
        $this->mock(PurchaseWebhookHandlers::class, function ($mock) {
            $mock->shouldReceive('onCheckoutPaymentSucceeded')->andThrow(new \RuntimeException('database is down'));
        });
        Log::shouldReceive('error')->once();

        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase))->assertStatus(500);
    }

    // ---------------- 支払い完了 ----------------

    // カードの即時決済：pending → paid
    public function testCompletedAndPaidMarksPurchasePaid(): void
    {
        $purchase = $this->pendingPurchase();

        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase))
            ->assertOk()
            ->assertJson(['received' => true, 'handled' => true]);

        $purchase->refresh();
        $this->assertSame(Purchase::STATUS_PAID, $purchase->status);
        $this->assertSame('pi_test_123', $purchase->stripe_payment_intent_id);
        $this->assertNotNull($purchase->paid_at);
        $this->assertSame('sold', $this->item->fresh()->sale_status);
    }

    // 同じイベントを重ねて受信しても、二重には更新されない
    public function testDuplicateEventDoesNotUpdateTwice(): void
    {
        $purchase = $this->pendingPurchase();
        Log::shouldReceive('error')->never();

        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase))->assertOk();
        $paid_at = $purchase->fresh()->paid_at;

        $this->travel(10)->minutes();
        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase, ['payment_intent' => 'pi_test_other']))->assertOk();

        $purchase->refresh();
        $this->assertSame(Purchase::STATUS_PAID, $purchase->status);
        $this->assertTrue($paid_at->equalTo($purchase->paid_at));
        $this->assertSame('pi_test_123', $purchase->stripe_payment_intent_id);
    }

    // metadata に purchase_id がなくても、Checkout Session ID で購入を特定する
    public function testPurchaseIsFoundBySessionIdWithoutMetadata(): void
    {
        $purchase = $this->pendingPurchase();

        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase, ['metadata' => []]))->assertOk();

        $this->assertSame(Purchase::STATUS_PAID, $purchase->fresh()->status);
    }

    // 金額が購入の金額と違う場合は、ログに残したうえで支払い済みにする
    public function testAmountMismatchIsLogged(): void
    {
        $purchase = $this->pendingPurchase();
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) use ($purchase) {
            return $context['amount_total'] === 3999 && $context['purchase_amount'] === 4000 && $context['purchase_id'] === $purchase->id;
        });

        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase, ['amount_total' => 3999]))->assertOk();

        $this->assertSame(Purchase::STATUS_PAID, $purchase->fresh()->status);
    }

    // 確保が期限切れになった後に支払いが通った場合は、状態を変えず、Session ID と PaymentIntent ID をログに残す
    public function testPaymentAfterExpiryIsLoggedForManualHandling(): void
    {
        $purchase = $this->pendingPurchase(['status' => Purchase::STATUS_EXPIRED]);
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) use ($purchase) {
            return $context['purchase_id'] === $purchase->id
                && $context['checkout_session_id'] === 'cs_test_123'
                && $context['payment_intent_id'] === 'pi_test_123'
                && $context['purchase_status'] === Purchase::STATUS_EXPIRED;
        });

        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase))->assertOk();

        $purchase->refresh();
        $this->assertSame(Purchase::STATUS_EXPIRED, $purchase->status);
        $this->assertNull($purchase->paid_at);
    }

    // 購入が見つからない支払い完了は、ログに残して 200 を返す
    public function testPaymentForUnknownPurchaseIsLogged(): void
    {
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) {
            return $context['checkout_session_id'] === 'cs_test_unknown' && $context['payment_intent_id'] === 'pi_test_123';
        });

        $this->postStripeWebhook('checkout.session.completed', [
            'id' => 'cs_test_unknown',
            'payment_status' => 'paid',
            'amount_total' => 4000,
            'payment_intent' => 'pi_test_123',
            'payment_method_types' => ['card'],
            'metadata' => [],
        ])->assertOk();
    }

    // ---------------- コンビニ払い ----------------

    // 支払い番号の発行（completed + unpaid）：pending のまま、確保を支払期限まで延ばす
    public function testKonbiniPendingExtendsReservation(): void
    {
        $this->travelTo(now()->setTime(10, 0, 0));
        $purchase = $this->pendingPurchase(['payment_method' => 'konbini']);

        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase, ['payment_status' => 'unpaid']))
            ->assertOk()
            ->assertJson(['handled' => true]);

        $purchase->refresh();
        $this->assertSame(Purchase::STATUS_PENDING, $purchase->status);
        // 3 日後の 23:59:59 に、24 時間の余裕を足した時刻
        $this->assertSame(
            now()->addDays(3)->setTime(23, 59, 59)->addDay()->format('Y-m-d H:i:s'),
            $purchase->expires_at->format('Y-m-d H:i:s')
        );
        $this->assertSame('pi_test_123', $purchase->stripe_payment_intent_id);
        // Checkout Session は完了したので、決済画面の URL は消す
        $this->assertNull($purchase->stripe_checkout_url);
        $this->assertNull($purchase->paid_at);

        // 30 分を過ぎても「取引中」のまま
        $this->travel(2)->days();
        $this->assertSame('trading', $this->item->fresh()->sale_status);
    }

    // コンビニでの入金（async_payment_succeeded）：pending → paid
    public function testKonbiniPaymentSucceededMarksPurchasePaid(): void
    {
        $purchase = $this->pendingPurchase(['payment_method' => 'konbini']);
        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase, ['payment_status' => 'unpaid']))->assertOk();

        $this->travel(2)->days();
        $this->postStripeWebhook('checkout.session.async_payment_succeeded', $this->sessionFor($purchase))->assertOk();

        $purchase->refresh();
        $this->assertSame(Purchase::STATUS_PAID, $purchase->status);
        $this->assertNotNull($purchase->paid_at);
        $this->assertSame('sold', $this->item->fresh()->sale_status);
    }

    // コンビニ払いの支払期限切れ（async_payment_failed）：pending → failed。商品は販売中に戻る
    public function testKonbiniPaymentFailedMarksPurchaseFailed(): void
    {
        $purchase = $this->pendingPurchase(['payment_method' => 'konbini']);

        $this->postStripeWebhook('checkout.session.async_payment_failed', $this->sessionFor($purchase, ['payment_status' => 'unpaid']))
            ->assertOk()
            ->assertJson(['handled' => true]);

        $this->assertSame(Purchase::STATUS_FAILED, $purchase->fresh()->status);
        $this->assertSame('on_sale', $this->item->fresh()->sale_status);
    }

    // ---------------- 期限切れ ----------------

    // Checkout Session の期限切れ（expired）：pending → expired。商品は販売中に戻る
    public function testSessionExpiredReleasesReservation(): void
    {
        $purchase = $this->pendingPurchase();

        $this->postStripeWebhook('checkout.session.expired', $this->sessionFor($purchase, ['payment_status' => 'unpaid']))
            ->assertOk()
            ->assertJson(['handled' => true]);

        $this->assertSame(Purchase::STATUS_EXPIRED, $purchase->fresh()->status);
        $this->assertSame('on_sale', $this->item->fresh()->sale_status);
    }

    // 支払い済みの購入は、後から届いた期限切れ・失敗のイベントで変わらない
    public function testPaidPurchaseIsNotChangedByLaterEvents(): void
    {
        $purchase = $this->pendingPurchase(['status' => Purchase::STATUS_PAID, 'paid_at' => now()]);

        $this->postStripeWebhook('checkout.session.expired', $this->sessionFor($purchase))->assertOk();
        $this->postStripeWebhook('checkout.session.async_payment_failed', $this->sessionFor($purchase))->assertOk();
        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase, ['payment_status' => 'unpaid']))->assertOk();

        $purchase->refresh();
        $this->assertSame(Purchase::STATUS_PAID, $purchase->status);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_123', $purchase->stripe_checkout_url);
    }
}
