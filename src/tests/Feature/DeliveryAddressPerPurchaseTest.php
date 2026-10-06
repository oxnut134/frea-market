<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// 購入画面で変更した配送先は、プロフィールを書き換えず、その商品の購入にだけ使う
class DeliveryAddressPerPurchaseTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $buyer;
    private $item;
    private $other_item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->createUserWithProfile(['post_code' => '222-2222', 'address' => 'Osaka', 'building' => 'Bldg']);
        $seller = $this->createUserWithProfile();
        $this->item = $this->createItem($seller);
        $this->other_item = $this->createItem($seller, ['item_name' => '腕時計']);
    }

    protected function tearDown(): void
    {
        $this->resetStripe();

        parent::tearDown();
    }

    private function changeAddress(array $overrides = [])
    {
        return $this->actingAs($this->buyer)->post(route('purchase.redirect'), array_merge([
            'item_id' => $this->item->id,
            'post_code' => '123-1234',
            'address' => 'newAddress',
            'building' => 'newBuilding',
        ], $overrides));
    }

    private function checkout($item, string $payment_method = 'card')
    {
        return $this->actingAs($this->buyer)->post('/purchase/' . $item->id . '/checkout', [
            'payment_method' => $payment_method,
        ]);
    }

    public function testChangingAddressDoesNotRewriteProfile(): void
    {
        $this->changeAddress()->assertRedirect('/purchase/' . $this->item->id);

        $profile = Profile::where('user_id', $this->buyer->id)->first();
        $this->assertSame(
            ['post_code' => '222-2222', 'address' => 'Osaka', 'building' => 'Bldg'],
            $profile->only(['post_code', 'address', 'building'])
        );
    }

    public function testChangedAddressIsNotUsedForOtherItems(): void
    {
        $this->changeAddress();

        $response = $this->get('/purchase/' . $this->other_item->id);
        $response->assertSee('222-2222');
        $response->assertSee('Osaka Bldg');
        $response->assertDontSee('newAddress');

        $this->fakeStripe([$this->checkoutSessionResponse()]);
        $this->checkout($this->other_item);

        $this->assertSame('222-2222OsakaBldg', Purchase::where('item_id', $this->other_item->id)->value('delivery_address'));
    }

    public function testAddressFormShowsChangedAddress(): void
    {
        $response = $this->actingAs($this->buyer)->get('/purchase/address/' . $this->item->id);
        $response->assertSee('value="222-2222"', false);
        $response->assertSee('value="Osaka"', false);
        $response->assertSee('value="Bldg"', false);

        $this->changeAddress();

        $response = $this->get('/purchase/address/' . $this->item->id);
        $response->assertSee('value="123-1234"', false);
        $response->assertSee('value="newAddress"', false);
        $response->assertSee('value="newBuilding"', false);
    }

    public function testLatestChangeReplacesEarlierOne(): void
    {
        $this->changeAddress();
        $this->changeAddress(['post_code' => '987-6543', 'address' => 'latestAddress', 'building' => 'latestBuilding']);

        $response = $this->get('/purchase/' . $this->item->id);
        $response->assertSee('987-6543');
        $response->assertSee('latestAddress latestBuilding');
        $response->assertDontSee('newAddress');
    }

    // 決済の開始に失敗してやり直すときも、変更した配送先が使われる
    public function testChangedAddressIsKeptWhenCheckoutFailsToStart(): void
    {
        $this->changeAddress();

        $this->fakeStripe([new RuntimeException('connection failed')]);
        $this->checkout($this->item)->assertRedirect('/purchase/' . $this->item->id);
        $this->assertSame(Purchase::STATUS_EXPIRED, Purchase::where('item_id', $this->item->id)->value('status'));

        $this->get('/purchase/' . $this->item->id)->assertSee('newAddress newBuilding');

        $this->fakeStripe([$this->checkoutSessionResponse()]);
        $this->checkout($this->item);

        $this->assertSame(
            '123-1234newAddressnewBuilding',
            Purchase::where('item_id', $this->item->id)->where('status', Purchase::STATUS_PENDING)->value('delivery_address')
        );
    }

    // Stripe の決済画面から戻ってやり直すときも、変更した配送先が使われる
    public function testChangedAddressIsKeptAfterCancel(): void
    {
        $this->changeAddress();

        $this->fakeStripe([
            $this->checkoutSessionResponse(),
            $this->checkoutSessionResponse(['status' => 'expired']),
        ]);
        $this->checkout($this->item);

        $response = $this->get('/purchase/' . $this->item->id . '?checkout=canceled');
        $response->assertSee('決済をキャンセルしました。');
        $response->assertSee('newAddress newBuilding');
    }

    public function testCompletePageForgetsChangedAddressOfThatItemOnly(): void
    {
        $this->changeAddress();
        $this->changeAddress(['item_id' => $this->other_item->id, 'address' => 'otherAddress']);

        $this->fakeStripe([$this->checkoutSessionResponse()]);
        $this->checkout($this->item);

        // 確保した時点では消さない
        $this->assertTrue(session()->has('delivery_addresses.' . $this->item->id));

        $this->get('/purchase/complete?session_id=cs_test_123')->assertStatus(200);

        $this->assertFalse(session()->has('delivery_addresses.' . $this->item->id));
        $this->assertSame('otherAddress', session('delivery_addresses.' . $this->other_item->id . '.address'));
    }

    // 自分の確保中は、配送先を変えられない（確保した購入の配送先が表示される）
    public function testAddressCannotBeChangedWhileOwnReservationIsPending(): void
    {
        $this->fakeStripe([$this->checkoutSessionResponse()]);
        $this->checkout($this->item);

        $this->get('/purchase/address/' . $this->item->id)->assertRedirect('/purchase/' . $this->item->id);
        $this->changeAddress()->assertRedirect('/purchase/' . $this->item->id);

        $this->assertFalse(session()->has('delivery_addresses.' . $this->item->id));
        $this->get('/purchase/' . $this->item->id)->assertSee('222-2222OsakaBldg');
    }

    // 期限が切れた確保は、配送先の変更を止めない
    public function testAddressCanBeChangedAfterOwnReservationExpired(): void
    {
        Purchase::factory()->pending()->create([
            'user_id' => $this->buyer->id,
            'item_id' => $this->item->id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->changeAddress()->assertRedirect('/purchase/' . $this->item->id);

        $this->get('/purchase/' . $this->item->id)->assertSee('newAddress newBuilding');
    }
}
