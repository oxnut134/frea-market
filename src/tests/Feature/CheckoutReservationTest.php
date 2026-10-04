<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Exception\ApiConnectionException;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;
use App\Models\Purchase;

// 商品の確保と Checkout Session の作成・キャンセル
class CheckoutReservationTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $seller;
    private $buyer;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = $this->createUserWithProfile();
        $this->buyer = $this->createUserWithProfile(['post_code' => '222-2222', 'address' => 'Osaka', 'building' => 'Bldg']);
        $this->item = $this->createItem($this->seller);
    }

    protected function tearDown(): void
    {
        $this->resetStripe();

        parent::tearDown();
    }

    private function checkout(array $input = ['payment_method' => 'card'], $item = null, $user = null)
    {
        return $this->actingAs($user ?? $this->buyer)
            ->post('/purchase/' . ($item ?? $this->item)->id . '/checkout', $input);
    }

    // Stripe が受け付けなかったときの応答（すでに完了・期限切れの Session など）
    private function rejectedResponse(): array
    {
        return [400, ['error' => ['type' => 'invalid_request_error', 'message' => 'Only Checkout Sessions with a status of open can be expired.']]];
    }

    // ---------------- 確保と Checkout Session ----------------

    // 確保（pending）を作り、Stripe の決済画面へリダイレクトする
    public function testCheckoutReservesItemAndRedirectsToStripe(): void
    {
        $this->travelTo(now()->startOfSecond());
        $stripe_expires_at = now()->addMinutes(31)->timestamp;
        $http = $this->fakeStripe([$this->checkoutSessionResponse(['expires_at' => $stripe_expires_at])]);

        $this->checkout()->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

        $purchase = Purchase::firstOrFail();
        $this->assertSame(Purchase::STATUS_PENDING, $purchase->status);
        $this->assertSame($this->buyer->id, $purchase->user_id);
        $this->assertSame('cs_test_123', $purchase->stripe_checkout_session_id);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_123', $purchase->stripe_checkout_url);
        $this->assertSame('222-2222OsakaBldg', $purchase->delivery_address);
        $this->assertNull($purchase->paid_at);
        // 期限は Stripe が返したものに合わせる
        $this->assertSame($stripe_expires_at, $purchase->expires_at->timestamp);
        $this->assertSame('trading', $this->item->fresh()->sale_status);

        // Stripe に送る内容
        $this->assertCount(1, $http->requests);
        $params = $http->requests[0]['params'];
        $this->assertStringEndsWith('/v1/checkout/sessions', $http->requests[0]['url']);
        $this->assertSame((string) $purchase->id, $params['metadata']['purchase_id']);
        $this->assertSame($this->buyer->email, $params['customer_email']);
        $this->assertSame('革靴', $params['line_items'][0]['price_data']['product_data']['name']);
        $this->assertSame('http://localhost/purchase/complete?session_id={CHECKOUT_SESSION_ID}', $params['success_url']);
        $this->assertSame('http://localhost/purchase/' . $this->item->id . '?checkout=canceled', $params['cancel_url']);
        // Stripe には「30 分 + 余裕 60 秒」後を送る
        $this->assertSame(now()->timestamp + 30 * 60 + 60, $params['expires_at']);
    }

    // 金額はリクエストから受け取らず、商品の価格を使う
    public function testAmountIsTakenFromItemPrice(): void
    {
        $http = $this->fakeStripe([$this->checkoutSessionResponse()]);

        $this->checkout(['payment_method' => 'card', 'price' => 1, 'amount' => 1]);

        $this->assertSame(4000, Purchase::firstOrFail()->amount);
        $this->assertSame(4000, $http->requests[0]['params']['line_items'][0]['price_data']['unit_amount']);
    }

    // 未ログインでは購入できない
    public function testGuestCannotCheckout(): void
    {
        $http = $this->fakeStripe();

        $this->post('/purchase/' . $this->item->id . '/checkout', ['payment_method' => 'card'])->assertRedirect('/login');

        $this->assertSame(0, Purchase::count());
        $this->assertSame([], $http->requests);
    }

    // 自分の出品は購入できない（購入画面・決済の両方）
    public function testSellerCannotPurchaseOwnItem(): void
    {
        $http = $this->fakeStripe();

        $this->actingAs($this->seller)->get('/purchase/' . $this->item->id)
            ->assertRedirect('/item/' . $this->item->id)
            ->assertSessionHas('message', '自分が出品した商品は購入できません。');
        $this->checkout(['payment_method' => 'card'], null, $this->seller)
            ->assertRedirect('/item/' . $this->item->id)
            ->assertSessionHas('message', '自分が出品した商品は購入できません。');

        $this->assertSame(0, Purchase::count());
        $this->assertSame([], $http->requests);
    }

    // 詳細画面に戻されたとき、メッセージが表示される
    public function testDetailPageShowsMessageAfterRejection(): void
    {
        $this->actingAs($this->seller)
            ->followingRedirects()
            ->get('/purchase/' . $this->item->id)
            ->assertSee('自分が出品した商品は購入できません。');
    }

    // 売り切れ・他の人が確保中の商品は、購入画面を開けない
    public function testPurchasePageIsNotShownForUnavailableItem(): void
    {
        $other = $this->createUserWithProfile();
        $sold = $this->createItem($this->seller);
        Purchase::factory()->create(['user_id' => $other->id, 'item_id' => $sold->id]);
        $trading = $this->createItem($this->seller);
        Purchase::factory()->pending()->create(['user_id' => $other->id, 'item_id' => $trading->id]);

        foreach ([$sold, $trading] as $item) {
            $this->actingAs($this->buyer)->get('/purchase/' . $item->id)
                ->assertRedirect('/item/' . $item->id)
                ->assertSessionHas('message', 'この商品は現在購入できません。');
        }
    }

    // 他の人が確保中なら、確保の INSERT が部分ユニークインデックスに弾かれて購入できない
    public function testCheckoutIsRejectedWhileAnotherUserIsReserving(): void
    {
        $other = $this->createUserWithProfile();
        Purchase::factory()->pending()->create(['user_id' => $other->id, 'item_id' => $this->item->id]);
        $http = $this->fakeStripe();

        // INSERT が弾かれるとテスト用のトランザクションは使えなくなるので、この後は DB を確認しない
        // （2 件目が入らないこと自体は ItemSaleStatusTest で確認している）
        $this->checkout()
            ->assertRedirect('/item/' . $this->item->id)
            ->assertSessionHas('message', '他の方が購入手続き中です。');

        $this->assertSame([], $http->requests);
    }

    // 期限切れの確保は expired にして、新しく確保できる
    public function testExpiredReservationIsCleanedUpBeforeReserving(): void
    {
        $other = $this->createUserWithProfile();
        $stale = Purchase::factory()->pendingExpired()->create(['user_id' => $other->id, 'item_id' => $this->item->id]);
        $this->fakeStripe([$this->checkoutSessionResponse()]);

        $this->checkout()->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

        $this->assertSame(Purchase::STATUS_EXPIRED, $stale->fresh()->status);
        $this->assertDatabaseHas('purchases', [
            'item_id' => $this->item->id,
            'user_id' => $this->buyer->id,
            'status' => Purchase::STATUS_PENDING,
        ]);
    }

    // Checkout Session を作れなかったら、確保を解除してエラーを表示する
    public function testReservationIsReleasedWhenSessionCreationFails(): void
    {
        foreach ([
            [500, ['error' => ['type' => 'api_error', 'message' => 'Something went wrong']]],
            new ApiConnectionException('Could not connect to Stripe'),
            $this->checkoutSessionResponse(['url' => null]),
        ] as $response) {
            $item = $this->createItem($this->seller);
            $this->fakeStripe([$response]);

            $this->checkout(['payment_method' => 'card'], $item)
                ->assertRedirect('/purchase/' . $item->id)
                ->assertSessionHasErrors(['checkout' => '決済を開始できませんでした。時間をおいてもう一度お試しください。']);

            $this->assertDatabaseHas('purchases', ['item_id' => $item->id, 'status' => Purchase::STATUS_EXPIRED]);
            $this->assertSame('on_sale', $item->fresh()->sale_status);
        }
    }

    // コンビニ払いは 300,000 円まで
    public function testKonbiniIsRejectedAboveLimit(): void
    {
        $http = $this->fakeStripe([$this->checkoutSessionResponse(), $this->checkoutSessionResponse(['id' => 'cs_test_456'])]);
        $over = $this->createItem($this->seller, ['price' => 300001]);
        $limit = $this->createItem($this->seller, ['price' => 300000]);

        $this->checkout(['payment_method' => 'konbini'], $over)
            ->assertRedirect('/purchase/' . $over->id)
            ->assertSessionHasErrors(['payment_method' => 'コンビニ払いは300,000円までです。']);
        $this->assertSame(0, Purchase::count());
        $this->assertSame([], $http->requests);

        // 上限ちょうどのコンビニ払いと、上限を超えるカード払いはできる
        $this->checkout(['payment_method' => 'konbini'], $limit)->assertSessionHasNoErrors();
        $this->checkout(['payment_method' => 'card'], $over)->assertSessionHasNoErrors();
        $this->assertSame(2, Purchase::count());
    }

    // ---------------- 自分の確保中にもう一度購入しようとした場合 ----------------

    // 決済がまだ終わっていなければ、同じ決済画面に戻す（新しい確保や Session は作らない）
    public function testCheckoutAgainResumesOpenSession(): void
    {
        $http = $this->fakeStripe([$this->checkoutSessionResponse()]);
        $this->checkout();

        $this->checkout(['payment_method' => 'konbini'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

        $this->assertSame(1, Purchase::count());
        $this->assertCount(1, $http->requests);
        $this->assertSame('card', Purchase::firstOrFail()->payment_method);
    }

    // 購入画面には「決済を続ける」を表示する
    public function testPurchasePageOffersToResumeOpenSession(): void
    {
        $this->fakeStripe([$this->checkoutSessionResponse()]);
        $this->checkout();

        $response = $this->get('/purchase/' . $this->item->id);

        $response->assertStatus(200);
        $response->assertSee('購入手続きの途中です。');
        $response->assertSee('決済を続ける');
        $response->assertSee('href="https://checkout.stripe.com/c/pay/cs_test_123"', false);
        $response->assertDontSee('購入する');
    }

    // コンビニ払いの支払い番号を発行済みなら、決済画面には戻さず、支払いを待っていることを案内する
    public function testCheckoutAgainShowsKonbiniGuidanceAfterVoucherIsIssued(): void
    {
        $http = $this->fakeStripe([$this->checkoutSessionResponse()]);
        $this->checkout(['payment_method' => 'konbini']);
        $purchase = Purchase::firstOrFail();
        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase, ['payment_status' => 'unpaid']))->assertOk();

        $this->checkout(['payment_method' => 'card'])->assertRedirect('/purchase/' . $this->item->id);

        $response = $this->get('/purchase/' . $this->item->id);
        $response->assertSee('コンビニでのお支払いをお待ちしています。支払い方法は Stripe からのメールをご確認ください。');
        $response->assertDontSee('決済を続ける');
        $response->assertDontSee('購入する');
        $this->assertSame(1, Purchase::count());
        $this->assertCount(1, $http->requests);
    }

    // ---------------- キャンセル ----------------

    // 決済画面から戻ったら、Checkout Session を期限切れにして確保を解除する
    public function testCancelExpiresSessionAndReleasesReservation(): void
    {
        $http = $this->fakeStripe([$this->checkoutSessionResponse(), $this->checkoutSessionResponse(['status' => 'expired'])]);
        $this->checkout();

        $response = $this->get('/purchase/' . $this->item->id . '?checkout=canceled');

        $response->assertStatus(200);
        $response->assertSee('決済をキャンセルしました。');
        $response->assertSee('購入する');
        $this->assertStringEndsWith('/v1/checkout/sessions/cs_test_123/expire', $http->requests[1]['url']);
        $this->assertSame(Purchase::STATUS_EXPIRED, Purchase::firstOrFail()->status);
        $this->assertSame('on_sale', $this->item->fresh()->sale_status);
    }

    // Stripe が期限切れを受け付けなかった（すでに完了など）場合は、確保をそのままにして Webhook に任せる
    public function testCancelKeepsReservationWhenStripeRejectsExpiry(): void
    {
        $this->fakeStripe([$this->checkoutSessionResponse(), $this->rejectedResponse()]);
        $this->checkout();

        $response = $this->get('/purchase/' . $this->item->id . '?checkout=canceled');

        $response->assertStatus(200);
        $response->assertDontSee('決済をキャンセルしました。');
        $this->assertSame(Purchase::STATUS_PENDING, Purchase::firstOrFail()->status);
    }

    // 通信エラーで期限切れにできなかった場合も、確保はそのまま（期限で掃除される）
    public function testCancelKeepsReservationOnConnectionError(): void
    {
        $this->fakeStripe([$this->checkoutSessionResponse(), new ApiConnectionException('Could not connect to Stripe')]);
        $this->checkout();

        $response = $this->get('/purchase/' . $this->item->id . '?checkout=canceled');

        $response->assertStatus(200);
        $response->assertDontSee('決済をキャンセルしました。');
        $this->assertSame(Purchase::STATUS_PENDING, Purchase::firstOrFail()->status);
    }

    // 確保していない人が ?checkout=canceled を開いても、他の人の確保は解除されない
    public function testCancelDoesNotReleaseAnotherUsersReservation(): void
    {
        $http = $this->fakeStripe([$this->checkoutSessionResponse()]);
        $this->checkout();
        $other = $this->createUserWithProfile();

        $this->actingAs($other)->get('/purchase/' . $this->item->id . '?checkout=canceled')
            ->assertRedirect('/item/' . $this->item->id);

        $this->assertSame(Purchase::STATUS_PENDING, Purchase::firstOrFail()->status);
        $this->assertCount(1, $http->requests);
    }
}
