<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// ヘッダーのロゴは、商品一覧へ戻るリンク
class HeaderLogoLinkTest extends TestCase
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
        return '<a class="frea-market_header_logo_link" href="' . $href . '">';
    }

    // ログイン中：どの画面からも / へ（マイページからも商品一覧に戻れる）
    public function testLoggedInUserGoesToTopPage(): void
    {
        $this->actingAs($this->user);

        foreach (['/mypage', '/mypage?tab=buy', '/mypage/profile', '/sell', '/item/' . $this->item->id, '/'] as $url) {
            $this->get($url)->assertStatus(200)->assertSee($this->link('/'), false);
        }
    }

    // 未ログイン：/ はログイン画面に送られるので、/frea へ
    public function testGuestGoesToGuestItemList(): void
    {
        foreach (['/frea', '/item/' . $this->item->id, '/login', '/register'] as $url) {
            $this->get($url)->assertStatus(200)->assertSee($this->link('/frea'), false);
        }

        // リンク先は、未ログインで開ける
        $this->get('/frea')->assertStatus(200);
        $this->get('/')->assertRedirect('/login');
    }

    // alt は、ロゴに書いてある文字と同じ
    public function testLogoHasMeaningfulAlt(): void
    {
        $response = $this->get('/frea');

        $response->assertSee('alt="Flea Market"', false);
        $response->assertDontSee('alt="error"', false);
    }

    // ロゴの画像：ヘッダーの配置が変わらないように、大きさと縦横比は 300 × 32。
    // 文字はパスにしてあり、フォントに頼らない
    public function testLogoImageKeepsSizeAndHasNoFontDependentText(): void
    {
        $svg = file_get_contents(public_path('images/logo.svg'));

        $this->assertStringContainsString('viewBox="0 0 300 32"', $svg);
        $this->assertStringContainsString('<title>Flea Market</title>', $svg);
        $this->assertStringNotContainsString('<text', $svg);
        $this->assertStringNotContainsString('font-family', $svg);
    }
}
