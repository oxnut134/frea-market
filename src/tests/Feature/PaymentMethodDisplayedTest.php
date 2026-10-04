<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;
use App\Models\Purchase;

// 支払い方法選択機能
class PaymentMethodDisplayedTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $buyer;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->createUserWithProfile();
        $this->item = $this->createItem($this->createUserWithProfile());
    }

    protected function tearDown(): void
    {
        $this->resetStripe();

        parent::tearDown();
    }

    // 購入画面で支払い方法を選択できる
    public function testPurchasePageShowsPaymentMethodOptions(): void
    {
        $response = $this->actingAs($this->buyer)->get('/purchase/' . $this->item->id);

        $response->assertStatus(200);
        $response->assertSee('<option value="konbini" >コンビニ払い</option>', false);
        $response->assertSee('<option value="card" >カード支払い</option>', false);
        // 小計の支払い方法（選択すると JavaScript が表示名を入れる）
        $response->assertSee('id="selected-payment-method"', false);
    }

    // 選択した支払い方法が、決済と購入に反映される
    public function testSelectedPaymentMethodIsUsedForCheckout(): void
    {
        foreach (['card', 'konbini'] as $payment_method) {
            $item = $this->createItem($this->createUserWithProfile());
            $http = $this->fakeStripe([$this->checkoutSessionResponse(['id' => 'cs_test_' . $payment_method])]);

            $this->actingAs($this->buyer)
                ->post('/purchase/' . $item->id . '/checkout', ['payment_method' => $payment_method])
                ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

            $this->assertSame([$payment_method], $http->requests[0]['params']['payment_method_types']);
            $this->assertDatabaseHas('purchases', ['item_id' => $item->id, 'payment_method' => $payment_method]);
        }
    }

    // 支払い方法を選ばない、または選択肢以外の値では購入できない
    public function testPaymentMethodMustBeOneOfTheOptions(): void
    {
        $http = $this->fakeStripe();

        foreach ([[], ['payment_method' => ''], ['payment_method' => 'カード支払い'], ['payment_method' => 'paypal']] as $input) {
            $this->actingAs($this->buyer)
                ->post('/purchase/' . $this->item->id . '/checkout', $input)
                ->assertSessionHasErrors(['payment_method' => 'お支払い方法を入力してください。']);
        }

        $this->assertSame(0, Purchase::count());
        $this->assertSame([], $http->requests);
    }

    // 入力エラーで戻ったとき、選んでいた支払い方法が選択されたままになる
    public function testSelectedPaymentMethodIsKeptAfterError(): void
    {
        $item = $this->createItem($this->createUserWithProfile(), ['price' => 300001]);

        $this->actingAs($this->buyer)
            ->followingRedirects()
            ->post('/purchase/' . $item->id . '/checkout', ['payment_method' => 'konbini'])
            ->assertSee('<option value="konbini"  selected >コンビニ払い</option>', false)
            ->assertSee('コンビニ払いは300,000円までです。');
    }
}
