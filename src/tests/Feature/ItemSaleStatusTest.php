<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\Item;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;

class ItemSaleStatusTest extends TestCase
{
    use RefreshDatabase;

    private $seller;
    private $buyer;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = $this->createUserWithProfile();
        $this->buyer = $this->createUserWithProfile();
        $this->item = $this->createItem();
    }

    private function createUserWithProfile()
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'post_code' => '111-1111',
            'address' => 'Tokyo',
        ]);

        return $user;
    }

    private function createItem()
    {
        return Item::forceCreate([
            'user_id' => $this->seller->id,
            'item_image' => 'items/shoes.jpg',
            'item_name' => '革靴',
            'price' => 4000,
            'description' => 'クラシックなデザインの革靴',
            'condition' => '良好',
        ]);
    }

    private function purchaseAttributes(array $overrides = [])
    {
        return array_merge(['user_id' => $this->buyer->id, 'item_id' => $this->item->id], $overrides);
    }

    // DB が INSERT を弾くことを確認する（セーブポイントまで戻すので、テストのトランザクションは壊れない）
    private function assertRejectedByDatabase(callable $insert)
    {
        try {
            DB::transaction($insert);
        } catch (QueryException $e) {
            $this->assertTrue(true);

            return;
        }

        $this->fail('DB が INSERT を受け付けてしまった');
    }

    // ---------------- sale_status ----------------

    // 購入がなければ販売中
    public function testItemWithoutPurchaseIsOnSale(): void
    {
        $this->assertSame('on_sale', $this->item->sale_status);
        $this->assertSame('', $this->item->sale_status_label);
    }

    // 支払い済みは sold
    public function testPaidPurchaseMakesItemSold(): void
    {
        Purchase::factory()->create($this->purchaseAttributes());

        $this->assertSame('sold', $this->item->sale_status);
        $this->assertSame('SOLD', $this->item->sale_status_label);
    }

    // 期限内の確保中は trading
    public function testPendingPurchaseWithinExpiryMakesItemTrading(): void
    {
        Purchase::factory()->pending()->create($this->purchaseAttributes());

        $this->assertSame('trading', $this->item->sale_status);
        $this->assertSame('取引中', $this->item->sale_status_label);
    }

    // 期限切れ・失敗した購入しかなければ販売中
    public function testExpiredAndFailedPurchasesLeaveItemOnSale(): void
    {
        Purchase::factory()->expired()->create($this->purchaseAttributes());
        Purchase::factory()->expired()->create($this->purchaseAttributes(['status' => Purchase::STATUS_FAILED]));

        $this->assertSame('on_sale', $this->item->sale_status);
        $this->assertCount(2, $this->item->purchases);
    }

    // 期限の直前までは trading、期限ちょうどから on_sale
    public function testSaleStatusSwitchesAtExpiryBoundary(): void
    {
        $expires_at = Carbon::create(2026, 10, 4, 12, 0, 0);
        Purchase::factory()->pending()->create($this->purchaseAttributes(['expires_at' => $expires_at]));

        $this->travelTo($expires_at->copy()->subSecond());
        $this->assertSame('trading', $this->item->fresh()->sale_status);

        $this->travelTo($expires_at);
        $this->assertSame('on_sale', $this->item->fresh()->sale_status);

        $this->travelTo($expires_at->copy()->addSecond());
        $this->assertSame('on_sale', $this->item->fresh()->sale_status);
    }

    // 期限の比較には PHP の now() を使う（DB の現在時刻とずれていても、アプリの時刻で判定する）
    public function testExpiryIsComparedWithApplicationTime(): void
    {
        Purchase::factory()->pending()->create($this->purchaseAttributes(['expires_at' => now()->addMinutes(30)]));

        // DB の現在時刻は進まないまま、アプリの時刻だけを期限の後へ進める
        $this->travel(31)->minutes();

        $this->assertSame('on_sale', $this->item->fresh()->sale_status);
    }

    // ---------------- 詳細画面の出し分け ----------------

    // 販売中：購入ボタンを表示
    public function testDetailShowsPurchaseButtonWhenOnSale(): void
    {
        $response = $this->actingAs($this->buyer)->get('/item/' . $this->item->id);

        $response->assertStatus(200);
        $response->assertSee('購入手続きへ');
        $response->assertDontSee('SOLD');
        $response->assertDontSee('取引中');
        $response->assertDontSee('出品中の商品です');
    }

    // 未ログインでも販売中なら購入ボタンを表示
    public function testDetailShowsPurchaseButtonToGuest(): void
    {
        $response = $this->get('/item/' . $this->item->id);

        $response->assertStatus(200);
        $response->assertSee('購入手続きへ');
    }

    // 支払い済み：ボタンの代わりに「SOLD」
    public function testDetailShowsSoldInsteadOfButton(): void
    {
        Purchase::factory()->create($this->purchaseAttributes());

        $response = $this->actingAs($this->createUserWithProfile())->get('/item/' . $this->item->id);

        $response->assertSee('SOLD');
        $response->assertDontSee('購入手続きへ');
    }

    // 確保中：ボタンの代わりに「取引中」
    public function testDetailShowsTradingInsteadOfButton(): void
    {
        Purchase::factory()->pending()->create($this->purchaseAttributes());

        $response = $this->actingAs($this->createUserWithProfile())->get('/item/' . $this->item->id);

        $response->assertSee('取引中');
        $response->assertDontSee('購入手続きへ');
    }

    // 自分の出品：ボタンの代わりに「出品中の商品です」
    public function testDetailHidesButtonOnOwnItem(): void
    {
        $response = $this->actingAs($this->seller)->get('/item/' . $this->item->id);

        $response->assertSee('出品中の商品です');
        $response->assertDontSee('購入手続きへ');
    }

    // 自分の出品でも、売れていれば「SOLD」を優先
    public function testDetailShowsSoldOnOwnSoldItem(): void
    {
        Purchase::factory()->create($this->purchaseAttributes());

        $response = $this->actingAs($this->seller)->get('/item/' . $this->item->id);

        $response->assertSee('SOLD');
        $response->assertDontSee('出品中の商品です');
        $response->assertDontSee('購入手続きへ');
    }

    // ---------------- DB の制約 ----------------

    // 同じ商品に、確保中・支払い済みの購入は 2 件作れない
    public function testSecondActivePurchaseForSameItemIsRejected(): void
    {
        Purchase::factory()->pending()->create($this->purchaseAttributes());
        $other = User::factory()->create();

        $this->assertRejectedByDatabase(function () use ($other) {
            Purchase::factory()->pending()->create($this->purchaseAttributes(['user_id' => $other->id]));
        });
        $this->assertRejectedByDatabase(function () use ($other) {
            Purchase::factory()->create($this->purchaseAttributes(['user_id' => $other->id]));
        });
        $this->assertSame(1, Purchase::count());
    }

    // 期限が切れていても pending のままなら、まだ次の確保はできない（expired に更新してから）
    public function testPendingPurchasePastExpiryStillBlocksUntilMarkedExpired(): void
    {
        $stale = Purchase::factory()->pendingExpired()->create($this->purchaseAttributes());

        $this->assertRejectedByDatabase(function () {
            Purchase::factory()->pending()->create($this->purchaseAttributes());
        });

        $stale->update(['status' => Purchase::STATUS_EXPIRED]);
        Purchase::factory()->pending()->create($this->purchaseAttributes());

        $this->assertSame('trading', $this->item->fresh()->sale_status);
    }

    // 期限切れ・失敗の購入は何件あってもよい
    public function testExpiredAndFailedPurchasesDoNotBlockNewPurchase(): void
    {
        Purchase::factory()->expired()->create($this->purchaseAttributes());
        Purchase::factory()->expired()->create($this->purchaseAttributes(['status' => Purchase::STATUS_FAILED]));
        Purchase::factory()->create($this->purchaseAttributes());

        $this->assertSame(3, Purchase::count());
        $this->assertSame('sold', $this->item->sale_status);
    }

    // 想定外の status / payment_method と、0 円以下の amount は弾かれる
    public function testCheckConstraintsRejectInvalidValues(): void
    {
        $this->assertRejectedByDatabase(function () {
            Purchase::factory()->create($this->purchaseAttributes(['status' => 'canceled']));
        });
        $this->assertRejectedByDatabase(function () {
            Purchase::factory()->create($this->purchaseAttributes(['payment_method' => 'コンビニ払い']));
        });
        $this->assertRejectedByDatabase(function () {
            Purchase::factory()->create($this->purchaseAttributes(['amount' => 0]));
        });
        $this->assertSame(0, Purchase::count());
    }
}
