<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;
use App\Models\Purchase;

// 商品購入機能（「購入する」→ Stripe の決済画面 → Webhook で購入確定）
class PurchaseFunctionTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $seller;
    private $buyer;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = $this->createUserWithProfile();
        $this->buyer = $this->createUserWithProfile();
        $this->item = $this->createItem($this->seller, ['item_name' => '腕時計', 'price' => 15000]);
    }

    protected function tearDown(): void
    {
        $this->resetStripe();

        parent::tearDown();
    }

    // 「購入する」を押してカードで支払い、Stripe から支払い完了の通知が届くまで
    private function purchaseWithCard(): Purchase
    {
        $this->fakeStripe([$this->checkoutSessionResponse()]);

        $this->actingAs($this->buyer)
            ->post('/purchase/' . $this->item->id . '/checkout', ['payment_method' => 'card'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

        $purchase = Purchase::where('item_id', $this->item->id)->firstOrFail();
        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase))->assertOk();

        return $purchase->fresh();
    }

    // 「購入する」ボタンを押下すると購入が完了する
    public function testPurchaseIsCompletedAfterPayment(): void
    {
        $this->actingAs($this->buyer)->get('/purchase/' . $this->item->id)
            ->assertStatus(200)
            ->assertSee('購入する');

        $purchase = $this->purchaseWithCard();

        $this->assertSame(Purchase::STATUS_PAID, $purchase->status);
        $this->assertSame($this->buyer->id, $purchase->user_id);
        $this->assertSame(15000, $purchase->amount);
        $this->assertSame('card', $purchase->payment_method);
        $this->assertNotNull($purchase->paid_at);
    }

    // 「購入する」を押しただけ（支払い前）では、購入は確定しない
    public function testPurchaseIsNotCompletedBeforePayment(): void
    {
        $this->fakeStripe([$this->checkoutSessionResponse()]);

        $this->actingAs($this->buyer)->post('/purchase/' . $this->item->id . '/checkout', ['payment_method' => 'card']);

        $this->assertDatabaseHas('purchases', ['item_id' => $this->item->id, 'status' => Purchase::STATUS_PENDING]);
        $this->get('/')->assertSee('取引中')->assertDontSee('SOLD');
    }

    // 購入した商品は商品一覧画面にて「SOLD」と表示される
    public function testPurchasedItemIsShownAsSoldInItemList(): void
    {
        $this->purchaseWithCard();

        $response = $this->actingAs($this->buyer)->get('/');

        $response->assertSee('腕時計');
        $response->assertSee('SOLD');
    }

    // 「プロフィール/購入した商品一覧」に追加されている
    public function testPurchasedItemIsListedInProfile(): void
    {
        $this->actingAs($this->buyer)->get('/mypage/?tab=buy')->assertDontSee('腕時計');

        $this->purchaseWithCard();

        $response = $this->actingAs($this->buyer)->get('/mypage/?tab=buy');

        $response->assertSee('腕時計');
        $response->assertSee('SOLD');
    }
}
