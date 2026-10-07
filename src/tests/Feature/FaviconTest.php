<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ファビコン（ロゴと同じ値札のアイコン）
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    private function assertHasFaviconLinks($response): void
    {
        foreach (['favicon.ico' => 'sizes="any"', 'favicon.svg' => 'type="image/svg+xml"'] as $file => $attribute) {
            $url = asset($file) . '?v=' . filemtime(public_path($file));
            $response->assertSee('<link rel="icon" href="' . $url . '" ' . $attribute . '>', false);
        }
    }

    // 共通のレイアウトを使う画面
    public function testPagesWithHeaderLinkToFavicon(): void
    {
        foreach (['/login', '/register', '/frea'] as $url) {
            $this->assertHasFaviconLinks($this->get($url)->assertStatus(200));
        }
    }

    // 認証待ちの画面は、共通のレイアウトを使わない独立したページ
    public function testVerifyEmailPageLinksToFavicon(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->assertHasFaviconLinks($this->actingAs($user)->get('/email/verify')->assertStatus(200));
    }

    // .ico は空ではなく、16・32・48 の 3 つの大きさを持つ
    public function testIcoFileHasThreeSizes(): void
    {
        $ico = file_get_contents(public_path('favicon.ico'));

        $header = unpack('vreserved/vtype/vcount', substr($ico, 0, 6));
        $this->assertSame(['reserved' => 0, 'type' => 1, 'count' => 3], $header);

        $sizes = [];
        for ($i = 0; $i < 3; $i++) {
            $sizes[] = ord($ico[6 + 16 * $i]);
        }
        $this->assertSame([16, 32, 48], $sizes);
    }

    // .svg は 32 × 32 で、文字（フォント）を含まない
    public function testSvgFileIsSquareWithoutText(): void
    {
        $svg = file_get_contents(public_path('favicon.svg'));

        $this->assertStringContainsString('viewBox="0 0 32 32"', $svg);
        $this->assertStringNotContainsString('<text', $svg);
    }
}
