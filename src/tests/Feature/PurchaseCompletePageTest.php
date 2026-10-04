<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;
use App\Models\Purchase;

// 決済後に戻ってくる画面（/purchase/complete）。表示するだけで、DB には書かない
class PurchaseCompletePageTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $buyer;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->createUserWithProfile();
        $this->item = $this->createItem($this->createUserWithProfile(), ['item_name' => '腕時計', 'price' => 15000]);
    }

    protected function tearDown(): void
    {
        $this->resetStripe();

        parent::tearDown();
    }

    private function purchase(array $overrides = [], string $state = 'pending'): Purchase
    {
        $factory = $state === 'paid' ? Purchase::factory() : Purchase::factory()->{$state}();

        return $factory->create(array_merge([
            'user_id' => $this->buyer->id,
            'item_id' => $this->item->id,
            'amount' => 15000,
            'stripe_checkout_session_id' => 'cs_test_123',
        ], $overrides));
    }

    private function getComplete(string $session_id = 'cs_test_123')
    {
        return $this->actingAs($this->buyer)->get('/purchase/complete?session_id=' . $session_id);
    }

    // 支払い済み：購入完了を表示
    public function testShowsCompletedForPaidPurchase(): void
    {
        $this->purchase([], 'paid');

        $response = $this->getComplete();

        $response->assertStatus(200);
        $response->assertSee('購入が完了しました。');
        $response->assertSee('腕時計');
        $response->assertSee('15,000');
        $response->assertSee('カード支払い');
    }

    // カード払いで Webhook がまだ届いていない：確認中を表示
    public function testShowsConfirmingForPendingCardPurchase(): void
    {
        $this->purchase();

        $response = $this->getComplete();

        $response->assertStatus(200);
        $response->assertSee('決済を確認しています。');
        $response->assertSee('しばらくしてから再読み込みしてください。');
        $response->assertDontSee('購入が完了しました。');
    }

    // コンビニ払いで支払い待ち：支払いを待っていることを表示
    public function testShowsAwaitingPaymentForPendingKonbiniPurchase(): void
    {
        $this->purchase(['payment_method' => 'konbini']);

        $response = $this->getComplete();

        $response->assertStatus(200);
        $response->assertSee('コンビニでのお支払いをお待ちしています。');
        $response->assertSee('支払い方法は Stripe からのメールをご確認ください。');
        $response->assertDontSee('購入が完了しました。');
    }

    // 期限切れ・失敗：完了していないことを表示
    public function testShowsNotCompletedForExpiredOrFailedPurchase(): void
    {
        $this->purchase([], 'expired');
        $this->purchase(['status' => Purchase::STATUS_FAILED, 'stripe_checkout_session_id' => 'cs_test_456'], 'expired');

        foreach (['cs_test_123', 'cs_test_456'] as $session_id) {
            $response = $this->getComplete($session_id);

            $response->assertStatus(200);
            $response->assertSee('購入は完了していません。');
            $response->assertDontSee('購入が完了しました。');
        }
    }

    // 表示するだけで、購入の状態を変えず、Stripe にも問い合わせない
    public function testDoesNotWriteToDatabaseOrCallStripe(): void
    {
        $http = $this->fakeStripe();
        $purchase = $this->purchase();
        $before = $purchase->fresh()->getAttributes();

        $this->getComplete()->assertStatus(200);

        $this->assertSame($before, $purchase->fresh()->getAttributes());
        $this->assertSame(1, Purchase::count());
        $this->assertSame([], $http->requests);
    }

    // 他の人の Session ID、存在しない Session ID、session_id なしは 404
    public function testReturns404ForUnknownOrOthersSession(): void
    {
        $other = $this->createUserWithProfile();
        $this->purchase(['user_id' => $other->id], 'paid');

        $this->getComplete()->assertStatus(404);
        $this->getComplete('cs_test_unknown')->assertStatus(404);
        $this->actingAs($this->buyer)->get('/purchase/complete')->assertStatus(404);
    }

    // 未ログインではログイン画面へ
    public function testGuestIsRedirectedToLogin(): void
    {
        $this->purchase([], 'paid');

        $this->get('/purchase/complete?session_id=cs_test_123')->assertRedirect('/login');
    }
}
