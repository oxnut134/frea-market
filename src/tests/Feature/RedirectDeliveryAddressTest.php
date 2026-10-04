<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;
use App\Models\Purchase;

// 配送先変更機能
class RedirectDeliveryAddressTest extends TestCase
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

    private function changeAddress()
    {
        return $this->actingAs($this->buyer)->post(route('purchase.redirect'), [
            'item_id' => $this->item->id,
            'post_code' => '123-1234',
            'address' => 'newAddress',
            'building' => 'newBuilding',
        ]);
    }

    // 送付先住所変更画面にて登録した住所が商品購入画面に反映されている
    public function testChangedAddressIsShownOnPurchasePage(): void
    {
        $this->actingAs($this->buyer)->get('/purchase/address/' . $this->item->id)->assertStatus(200);

        $this->changeAddress()->assertRedirect('/purchase/' . $this->item->id);

        $response = $this->get('/purchase/' . $this->item->id);
        $response->assertSee('123-1234');
        $response->assertSee('newAddress');
        $response->assertSee('newBuilding');
    }

    // 購入した商品に送付先住所が紐づいて登録される
    public function testPurchaseIsLinkedToChangedAddress(): void
    {
        $this->changeAddress();
        $this->fakeStripe([$this->checkoutSessionResponse()]);

        // 送付先はリクエストから受け取らない（送っても無視される）
        $this->post('/purchase/' . $this->item->id . '/checkout', [
            'payment_method' => 'konbini',
            'delivery_address' => 'someone else',
        ]);

        $purchase = Purchase::where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame($this->buyer->id, $purchase->user_id);
        $this->assertSame('123-1234newAddressnewBuilding', $purchase->delivery_address);

        // 支払い完了後も、購入に紐づいた送付先は変わらない
        $this->postStripeWebhook('checkout.session.completed', $this->sessionFor($purchase))->assertOk();
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => Purchase::STATUS_PAID,
            'delivery_address' => '123-1234newAddressnewBuilding',
        ]);
    }
}
