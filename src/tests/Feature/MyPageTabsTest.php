<?php

namespace Tests\Feature;

use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// マイページに並ぶ商品（出品した商品／購入した商品）
class MyPageTabsTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $user;
    private $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUserWithProfile();
        $this->other = $this->createUserWithProfile();

        // 自分の出品、自分が購入したほかの人の商品、どちらでもないほかの人の商品
        $this->createItem($this->user, ['item_name' => 'myListing']);
        $bought = $this->createItem($this->other, ['item_name' => 'myPurchase']);
        $this->createItem($this->other, ['item_name' => 'othersListing']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'item_id' => $bought->id]);
    }

    private function getMyPage(string $query = '')
    {
        return $this->actingAs($this->user)->get('/mypage' . $query);
    }

    // タブの指定がなければ、出品した商品だけが並ぶ（ほかの人の商品は並ばない）
    public function testDefaultShowsOwnListingsOnly(): void
    {
        $response = $this->getMyPage();

        $response->assertStatus(200);
        $response->assertSee('myListing');
        $response->assertDontSee('myPurchase');
        $response->assertDontSee('othersListing');
    }

    public function testSellTabShowsOwnListingsOnly(): void
    {
        $response = $this->getMyPage('?tab=sell');

        $response->assertSee('myListing');
        $response->assertDontSee('myPurchase');
        $response->assertDontSee('othersListing');
    }

    public function testBuyTabShowsOwnPurchasesOnly(): void
    {
        $response = $this->getMyPage('?tab=buy');

        $response->assertSee('myPurchase');
        $response->assertDontSee('myListing');
        $response->assertDontSee('othersListing');
    }

    // 知らない値は、出品した商品として扱う
    public function testUnknownTabFallsBackToOwnListings(): void
    {
        $response = $this->getMyPage('?tab=unknown');

        $response->assertStatus(200);
        $response->assertSee('myListing');
        $response->assertDontSee('myPurchase');
        $response->assertDontSee('othersListing');
    }

    // プロフィールを更新したあとの戻り先（タブの指定なし）でも同じ
    public function testProfileUpdateReturnsToOwnListings(): void
    {
        $this->actingAs($this->user)->post('/mypage/profile', [
            'user_name' => 'renamed',
            'post_code' => '111-1111',
            'address' => 'Tokyo',
        ])->assertRedirect('/mypage');

        $this->getMyPage()->assertSee('renamed')->assertSee('myListing')->assertDontSee('othersListing');
    }
}
