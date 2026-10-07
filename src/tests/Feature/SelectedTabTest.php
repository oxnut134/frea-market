<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// 選ばれているタブの表示（マイページとトップページで共通の tab-nav）
class SelectedTabTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUserWithProfile();
        $this->createItem($this->createUserWithProfile());
    }

    private function selected(string $href, string $label): string
    {
        return '<a class="tab-nav_item tab-nav_item--active" href="' . $href . '" aria-current="page">' . $label . '</a>';
    }

    private function notSelected(string $href, string $label): string
    {
        return '<a class="tab-nav_item" href="' . $href . '">' . $label . '</a>';
    }

    // 選ばれているタブは、どの画面でも 1 つだけ
    private function assertOneTabIsSelected($response): void
    {
        $this->assertSame(1, substr_count($response->getContent(), 'tab-nav_item--active'));
        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
    }

    /**
     * @dataProvider myPageUrlsForSellTab
     */
    public function testMyPageSelectsSellTabByDefault(string $url): void
    {
        $response = $this->actingAs($this->user)->get($url);

        $response->assertStatus(200);
        $response->assertSee($this->selected('/mypage/?tab=sell', '出品した商品'), false);
        $response->assertSee($this->notSelected('/mypage/?tab=buy', '購入した商品'), false);
        $this->assertOneTabIsSelected($response);
    }

    public function myPageUrlsForSellTab(): array
    {
        return [
            'タブの指定なし' => ['/mypage'],
            'sell' => ['/mypage?tab=sell'],
            '知らない値' => ['/mypage?tab=unknown'],
        ];
    }

    public function testMyPageSelectsBuyTab(): void
    {
        $response = $this->actingAs($this->user)->get('/mypage?tab=buy');

        $response->assertSee($this->selected('/mypage/?tab=buy', '購入した商品'), false);
        $response->assertSee($this->notSelected('/mypage/?tab=sell', '出品した商品'), false);
        $this->assertOneTabIsSelected($response);
    }

    public function testTopPageSelectsRecommendTabByDefault(): void
    {
        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $response->assertSee($this->selected('/', 'おすすめ'), false);
        $response->assertSee($this->notSelected('/?tab=mylist', 'マイリスト'), false);
        $this->assertOneTabIsSelected($response);
    }

    public function testTopPageSelectsMylistTab(): void
    {
        $response = $this->actingAs($this->user)->get('/?tab=mylist');

        $response->assertSee($this->selected('/?tab=mylist', 'マイリスト'), false);
        $response->assertSee($this->notSelected('/', 'おすすめ'), false);
        $this->assertOneTabIsSelected($response);
    }

    // 検索結果は、おすすめが選ばれた状態
    public function testSearchResultSelectsRecommendTab(): void
    {
        $response = $this->actingAs($this->user)->post('/search', ['keyword' => '靴']);

        $response->assertStatus(200);
        $response->assertSee($this->selected('/', 'おすすめ'), false);
        $this->assertOneTabIsSelected($response);
    }

    // 未ログインの一覧（/frea）には、タブを出さない
    public function testGuestItemListHasNoTabs(): void
    {
        $this->get('/frea')->assertStatus(200)->assertDontSee('tab-nav', false);
    }
}
