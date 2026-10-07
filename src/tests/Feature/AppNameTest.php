<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// アプリ名の表記は「Flea Market」
class AppNameTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    // 共通のレイアウトを使う画面のタイトル
    public function testPageTitleIsAppName(): void
    {
        $user = $this->createUserWithProfile();

        foreach (['/login', '/register', '/frea'] as $url) {
            $this->get($url)->assertStatus(200)->assertSee('<title>Flea Market</title>', false);
        }

        $this->actingAs($user);
        foreach (['/', '/mypage', '/sell'] as $url) {
            $this->get($url)->assertStatus(200)
                ->assertSee('<title>Flea Market</title>', false)
                ->assertDontSee('フリマアプリ');
        }
    }

    // 認証待ちの画面（独立したページ）のタイトル
    public function testVerifyEmailPageTitleHasAppName(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)->get('/email/verify')
            ->assertStatus(200)
            ->assertSee('<title>メール認証 | Flea Market</title>', false);
    }

    // 設定の見本（.env.example）のアプリ名。空白を含むので、引用符で囲む
    public function testEnvExampleUsesAppName(): void
    {
        $this->assertStringContainsString("\nAPP_NAME=\"Flea Market\"\n", "\n" . file_get_contents(base_path('.env.example')));
    }

    // APP_NAME が設定されていない環境での既定値
    public function testDefaultAppNameInConfig(): void
    {
        $this->assertStringContainsString("'name' => env('APP_NAME', 'Flea Market'),", file_get_contents(config_path('app.php')));
    }
}
