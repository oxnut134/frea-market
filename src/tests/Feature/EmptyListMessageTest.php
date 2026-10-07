<?php

namespace Tests\Feature;

use App\Models\Like;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// 商品の並びが 0 件のときの案内
class EmptyListMessageTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private const NO_LISTINGS = '出品した商品はありません。';
    private const NO_PURCHASES = '購入した商品はありません。';
    private const NO_LIKES = 'いいねした商品はありません。';
    private const NO_MATCHES = '該当する商品はありません。';

    private $user;
    private $others_item;

    protected function setUp(): void
    {
        parent::setUp();

        // 出品も購入もいいねもしていないユーザーと、ほかの人の商品
        $this->user = $this->createUserWithProfile();
        $this->others_item = $this->createItem($this->createUserWithProfile());
    }

    public function testMyPageShowsMessageWithoutListings(): void
    {
        foreach (['/mypage', '/mypage?tab=sell'] as $url) {
            $this->actingAs($this->user)->get($url)
                ->assertStatus(200)
                ->assertSee('<p class="item-list_empty">' . self::NO_LISTINGS . '</p>', false)
                ->assertDontSee(self::NO_PURCHASES);
        }
    }

    public function testMyPageShowsMessageWithoutPurchases(): void
    {
        $this->actingAs($this->user)->get('/mypage?tab=buy')
            ->assertStatus(200)
            ->assertSee('<p class="item-list_empty">' . self::NO_PURCHASES . '</p>', false)
            ->assertDontSee(self::NO_LISTINGS);
    }

    // 商品があるタブには出さない
    public function testMyPageHidesMessageWhenItemsExist(): void
    {
        $this->createItem($this->user, ['item_name' => 'myListing']);
        Purchase::factory()->create(['user_id' => $this->user->id, 'item_id' => $this->others_item->id]);

        $this->actingAs($this->user)->get('/mypage')->assertSee('myListing')->assertDontSee('item-list_empty', false);
        $this->get('/mypage?tab=buy')->assertSee($this->others_item->item_name)->assertDontSee('item-list_empty', false);
    }

    public function testMylistShowsMessageWithoutLikes(): void
    {
        $this->actingAs($this->user)->get('/?tab=mylist')
            ->assertStatus(200)
            ->assertSee('<p class="item-list_empty">' . self::NO_LIKES . '</p>', false);
    }

    public function testMylistHidesMessageWhenLikedItemsExist(): void
    {
        Like::create(['user_id' => $this->user->id, 'item_id' => $this->others_item->id]);

        $this->actingAs($this->user)->get('/?tab=mylist')
            ->assertSee($this->others_item->item_name)
            ->assertDontSee('item-list_empty', false);
    }

    // おすすめには出さない（検索していないとき）
    public function testRecommendTabHasNoMessage(): void
    {
        $this->actingAs($this->user)->get('/')->assertStatus(200)->assertDontSee('item-list_empty', false);
    }

    // 検索して 1 件も見つからなかったとき
    public function testSearchShowsMessageWithoutMatches(): void
    {
        $this->actingAs($this->user)->post('/search', ['keyword' => 'no-such-item'])
            ->assertStatus(200)
            ->assertSee('<p class="item-list_empty">' . self::NO_MATCHES . '</p>', false)
            ->assertDontSee(self::NO_LIKES);
    }

    public function testSearchHidesMessageWhenItemsMatch(): void
    {
        $this->actingAs($this->user)->post('/search', ['keyword' => mb_substr($this->others_item->item_name, 0, 1)])
            ->assertStatus(200)
            ->assertSee($this->others_item->item_name)
            ->assertDontSee('item-list_empty', false);
    }

    // 検索の文字が空なら、すべての商品が並ぶので出さない
    public function testSearchWithoutKeywordHasNoMessage(): void
    {
        $this->actingAs($this->user)->post('/search', ['keyword' => ''])
            ->assertStatus(200)
            ->assertSee($this->others_item->item_name)
            ->assertDontSee('item-list_empty', false);
    }

    // マイリストを検索の文字で絞り込んで 0 件：いいねした商品があってもなくても、検索の結果として案内する
    public function testMylistWithKeywordShowsNoMatches(): void
    {
        $this->actingAs($this->user)->get('/?tab=mylist&keyword=no-such-item')
            ->assertSee('<p class="item-list_empty">' . self::NO_MATCHES . '</p>', false)
            ->assertDontSee(self::NO_LIKES);

        Like::create(['user_id' => $this->user->id, 'item_id' => $this->others_item->id]);

        $this->get('/?tab=mylist&keyword=no-such-item')
            ->assertSee('<p class="item-list_empty">' . self::NO_MATCHES . '</p>', false)
            ->assertDontSee(self::NO_LIKES)
            ->assertDontSee($this->others_item->item_name);
    }

    // マイリストで、検索の文字が空のまま 0 件なら、マイリストが空として案内する
    public function testMylistWithEmptyKeywordShowsNoLikes(): void
    {
        $this->actingAs($this->user)->get('/?tab=mylist&keyword=')
            ->assertSee('<p class="item-list_empty">' . self::NO_LIKES . '</p>', false)
            ->assertDontSee(self::NO_MATCHES);
    }

    // 未ログインの一覧には、?tab=mylist や検索の文字を付けても出さない
    public function testGuestItemListHasNoMessage(): void
    {
        $this->get('/frea?tab=mylist')->assertStatus(200)->assertDontSee('item-list_empty', false);
        $this->get('/frea?tab=mylist&keyword=no-such-item')->assertStatus(200)->assertDontSee('item-list_empty', false);
        $this->get('/frea')->assertStatus(200)->assertDontSee('item-list_empty', false);
    }
}
