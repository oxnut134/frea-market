<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// 未ログインのヘッダーに出る「ログイン」のリンク
class GuestHeaderLoginLinkTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $user;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUserWithProfile();
        $this->item = $this->createItem($this->createUserWithProfile());
    }

    private function link(string $href): string
    {
        return '<a class="frea-market_header_login_link" href="' . $href . '">ログイン</a>';
    }

    // 未ログインの商品一覧（/frea）：ログイン画面へ
    public function testGuestItemListHasLoginLink(): void
    {
        $this->get('/frea')->assertStatus(200)->assertSee($this->link('/login'), false);
    }

    // 未ログインの商品詳細：ログイン後に、その商品へ戻る
    public function testGuestItemDetailLinkReturnsToTheItem(): void
    {
        $this->get('/item/' . $this->item->id)
            ->assertStatus(200)
            ->assertSee($this->link('/item/' . $this->item->id . '/login'), false);
    }

    // 会員登録の画面にも出る
    public function testRegisterPageHasLoginLink(): void
    {
        $this->get('/register')->assertStatus(200)->assertSee($this->link('/login'), false);
    }

    // ログイン画面には出さない
    public function testLoginPageHasNoLoginLink(): void
    {
        $this->get('/login')->assertStatus(200)->assertDontSee('frea-market_header_login_link', false);
    }

    // ログイン済みでは出さない（ログアウトのボタンなどが出る）
    public function testLoggedInUserDoesNotSeeLoginLink(): void
    {
        $this->actingAs($this->user);

        foreach (['/', '/frea', '/item/' . $this->item->id] as $url) {
            $this->get($url)
                ->assertStatus(200)
                ->assertDontSee('frea-market_header_login_link', false)
                ->assertSee('ログアウト');
        }
    }
}
