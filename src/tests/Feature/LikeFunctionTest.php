<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\Like;
use App\Models\Profile;

// いいねの追加・削除（POST / DELETE /like/{id}）
class LikeFunctionTest extends TestCase
{
    use RefreshDatabase;

    private $seller;
    private $user;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = $this->createUserWithProfile();
        $this->user = $this->createUserWithProfile();
        $this->item = Item::forceCreate([
            'user_id' => $this->seller->id,
            'item_image' => 'items/watch.jpg',
            'item_name' => '腕時計',
            'price' => 15000,
            'description' => 'スタイリッシュなデザインのメンズ腕時計',
            'condition' => '良好',
        ]);
    }

    private function createUserWithProfile(): User
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'post_code' => '111-1111', 'address' => 'Tokyo']);

        return $user;
    }

    // @versioned と同じ URL（更新時刻付き）
    private function versioned(string $path): string
    {
        return asset($path) . '?v=' . filemtime(public_path($path));
    }

    private function likeCount(): int
    {
        return Like::where('item_id', $this->item->id)->count();
    }

    // いいねを追加すると 1 件増え、削除すると元に戻る
    public function testAdditionaLikeByClick(): void
    {
        $this->actingAs($this->user)->postJson('/like/' . $this->item->id)
            ->assertStatus(200)
            ->assertExactJson(['liked' => true, 'likes' => 1]);
        $this->assertTrue(Like::where('item_id', $this->item->id)->where('user_id', $this->user->id)->exists());

        $this->actingAs($this->user)->deleteJson('/like/' . $this->item->id)
            ->assertStatus(200)
            ->assertExactJson(['liked' => false, 'likes' => 0]);
        $this->assertSame(0, $this->likeCount());
    }

    // 同じ商品に 2 回追加しても 1 件のままで、エラーにならない
    public function testAddingTwiceKeepsSingleLike(): void
    {
        $this->actingAs($this->user)->postJson('/like/' . $this->item->id)->assertStatus(200);
        $this->actingAs($this->user)->postJson('/like/' . $this->item->id)
            ->assertStatus(200)
            ->assertExactJson(['liked' => true, 'likes' => 1]);

        $this->assertSame(1, $this->likeCount());
    }

    // いいねしていない商品の削除も 200 で、件数は変わらない
    public function testRemovingWithoutLikeChangesNothing(): void
    {
        Like::create(['item_id' => $this->item->id, 'user_id' => $this->seller->id]);

        $this->actingAs($this->user)->deleteJson('/like/' . $this->item->id)
            ->assertStatus(200)
            ->assertExactJson(['liked' => false, 'likes' => 1]);

        $this->assertSame(1, $this->likeCount());
    }

    // 応答と詳細画面の件数は、likes テーブルの行数と一致する（ほかの人のいいねも数える）
    public function testCountMatchesRowsInLikesTable(): void
    {
        Like::create(['item_id' => $this->item->id, 'user_id' => $this->seller->id]);

        $this->actingAs($this->user)->postJson('/like/' . $this->item->id)
            ->assertExactJson(['liked' => true, 'likes' => 2]);
        $this->assertSame(2, $this->likeCount());

        $this->actingAs($this->user)->get('/item/' . $this->item->id)
            ->assertStatus(200)
            ->assertSee('<div id="count" class="detail-form_engagement_count">2</div>', false);
    }

    // アイコンは「自分がいいねしているか」で決まる（ほかの人のいいねだけでは赤くならない）
    public function testIconReflectsOwnLikeState(): void
    {
        $liked_icon = 'class="like-icon" src="' . $this->versioned('images/liked.png') . '"';
        $not_liked_icon = 'class="like-icon" src="' . $this->versioned('images/not-liked.png') . '"';
        Like::create(['item_id' => $this->item->id, 'user_id' => $this->seller->id]);

        $this->actingAs($this->user)->get('/item/' . $this->item->id)
            ->assertSee($not_liked_icon, false)
            ->assertSee('data-liked="0"', false)
            ->assertDontSee($liked_icon, false);

        Like::create(['item_id' => $this->item->id, 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/item/' . $this->item->id)
            ->assertSee($liked_icon, false)
            ->assertSee('data-liked="1"', false)
            ->assertSee('js/like.js', false)
            ->assertDontSee($not_liked_icon, false);
    }

    // 未ログインでは、件数があっても灰色のアイコンで、ログインして同じ商品に戻るリンクになる
    public function testGuestSeesLoginLinkInsteadOfLikeButton(): void
    {
        Like::create(['item_id' => $this->item->id, 'user_id' => $this->seller->id]);

        $this->get('/item/' . $this->item->id)
            ->assertStatus(200)
            ->assertSee('<a class="detail-form_engagement_image_wrapper" href="/item/' . $this->item->id . '/login">', false)
            ->assertSee('class="like-icon" src="' . $this->versioned('images/not-liked.png') . '"', false)
            ->assertDontSee('js-like-button', false)
            ->assertDontSee('data-like-url', false);
    }

    // GET では追加も削除もできない
    public function testGetRequestIsNotAllowed(): void
    {
        $this->actingAs($this->user)->get('/like/' . $this->item->id)->assertStatus(405);
        $this->actingAs($this->user)->get('/like/' . $this->item->id . '/add')->assertStatus(404);
        $this->actingAs($this->user)->get('/like/' . $this->item->id . '/remove')->assertStatus(404);

        $this->assertSame(0, $this->likeCount());
    }

    // 未ログインは 401 で、いいねは増えない
    public function testGuestCannotLike(): void
    {
        $this->postJson('/like/' . $this->item->id)->assertStatus(401);
        $this->deleteJson('/like/' . $this->item->id)->assertStatus(401);

        $this->assertSame(0, $this->likeCount());
    }

    // 存在しない商品、数値でない ID は 404
    public function testUnknownItemReturnsNotFound(): void
    {
        $this->actingAs($this->user)->postJson('/like/' . ($this->item->id + 1000))->assertStatus(404);
        $this->actingAs($this->user)->postJson('/like/abc')->assertStatus(404);
    }
}
