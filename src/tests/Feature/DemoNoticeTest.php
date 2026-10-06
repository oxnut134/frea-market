<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Item;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

// デモ用アカウントとその案内（DEMO_MODE）
class DemoNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'このデモ環境では、架空のメールアドレス（例：yourname@example.com）と、普段使っていないパスワードで登録してください。';
    private const LOGIN_LABEL = 'デモアカウントでログイン';

    // 既定（無効）では、どの画面にも表示しない
    public function testNoticeIsHiddenByDefault(): void
    {
        $this->assertFalse(config('demo.enabled'));

        $this->get('/login')->assertStatus(200)
            ->assertDontSee(self::LOGIN_LABEL)
            ->assertDontSee('js-demo-login')
            ->assertDontSee('demo-login.js')
            ->assertDontSee(config('demo.email'))
            ->assertDontSee(config('demo.password'));
        $this->get('/register')->assertStatus(200)->assertDontSee(self::MESSAGE);
    }

    // ログイン画面：デモ用アカウントのアドレスとパスワードを持つボタンを 1 つだけ表示する
    public function testLoginPageShowsDemoLoginButton(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->get('/login')->assertStatus(200)
            ->assertSee(
                '<button type="button" class="demo-login_button js-demo-login" data-email="demo@test.com" data-password="'
                    . config('demo.password') . '">' . self::LOGIN_LABEL . '</button>',
                false
            )
            ->assertSee('js/demo-login.js', false)
            ->assertSee('css/demo-notice.css', false)
            ->assertDontSee(self::MESSAGE);

        $this->assertSame(1, substr_count($response->getContent(), 'js-demo-login'));
    }

    // 会員登録画面：架空のアドレスで登録すること、メールを送らないこと、毎日削除されることを案内する
    public function testRegisterPageShowsHowToRegister(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/register')->assertStatus(200)
            ->assertSee(self::MESSAGE)
            ->assertSee('認証メールは送信しません。登録したデータは毎日 4:00（日本時間）に削除されます。')
            ->assertDontSee('js-demo-login')
            ->assertDontSee(config('demo.password'));
    }

    // 認証待ち画面には、デモ用の表示を出さない（デモ環境では登録と同時に認証済みになり、この画面は使わない）
    public function testVerifyPageHasNoDemoNotice(): void
    {
        config(['demo.enabled' => true]);

        $this->actingAs(User::factory()->unverified()->create())->get('/email/verify')->assertStatus(200)
            ->assertSee('認証メールを再送する')
            ->assertDontSee('demo-notice')
            ->assertDontSee('js-demo-login');
    }

    // シードのデモ用アカウント：メール認証済み、プロフィール登録済みで、商品を持たない
    public function testSeededDemoAccountIsReadyToUse(): void
    {
        $this->seed(DatabaseSeeder::class);

        $demo = User::where('email', config('demo.email'))->firstOrFail();
        $this->assertNotNull($demo->email_verified_at);
        $this->assertTrue(Profile::where('user_id', $demo->id)->exists());
        $this->assertSame(0, Item::where('user_id', $demo->id)->count());
    }

    // ボタンが送るのと同じ内容（通常の POST /login）でログインでき、一覧を開ける
    public function testDemoAccountCanLogInThroughNormalLoginForm(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', ['email' => config('demo.email'), 'password' => config('demo.password')])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('email', config('demo.email'))->firstOrFail());
        $this->get('/')->assertStatus(200);
    }
}
