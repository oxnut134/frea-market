<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// 未ログインの商品詳細からログインすると、同じ商品に戻る（GET /item/{item_id}/login）
class ReturnToItemAfterLoginTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $user;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        // UserFactory のパスワードは "password"
        $this->user = $this->createUserWithProfile();
        $this->item = $this->createItem($this->createUserWithProfile());
    }

    private function login()
    {
        return $this->post('/login', ['email' => $this->user->email, 'password' => 'password']);
    }

    // 未ログインではログイン画面へ送られ、ログインすると元の商品の詳細に着く
    public function testGuestReturnsToItemAfterLogin(): void
    {
        $entry = '/item/' . $this->item->id . '/login';

        $this->get($entry)->assertRedirect('/login');
        $this->assertGuest();

        $this->login()->assertRedirect($entry);
        $this->assertAuthenticatedAs($this->user);

        $this->get($entry)->assertRedirect('/item/' . $this->item->id);
        $this->get('/item/' . $this->item->id)->assertStatus(200)->assertSee($this->item->item_name);
    }

    // この入り口を通らずにログインした場合は、今までどおり商品一覧へ
    public function testLoginWithoutEntryGoesToHome(): void
    {
        $this->get('/item/' . $this->item->id)->assertStatus(200);

        $this->login()->assertRedirect('/');
    }

    // ログイン済みなら、そのまま詳細へ
    public function testLoggedInUserIsSentStraightToItem(): void
    {
        $this->actingAs($this->user)
            ->get('/item/' . $this->item->id . '/login')
            ->assertRedirect('/item/' . $this->item->id);
    }

    // 詳細画面のいいねは、未ログインではこの入り口へのリンク、ログイン済みではボタン（ログイン切れのときの移動先を持つ）
    public function testLikeIconPointsToEntry(): void
    {
        $entry = '/item/' . $this->item->id . '/login';

        $this->get('/item/' . $this->item->id)
            ->assertSee('<a class="detail-form_engagement_image_wrapper" href="' . $entry . '">', false)
            ->assertDontSee('data-login-url', false);

        $this->actingAs($this->user)->get('/item/' . $this->item->id)
            ->assertSee('data-login-url="' . $entry . '"', false)
            ->assertDontSee('href="' . $entry . '"', false);
    }

    // 存在しない商品、数値でない ID は 404
    public function testUnknownItemIsNotFound(): void
    {
        $this->actingAs($this->user);

        $this->get('/item/' . ($this->item->id + 1000) . '/login')->assertNotFound();
        $this->get('/item/abc/login')->assertNotFound();
    }
}
