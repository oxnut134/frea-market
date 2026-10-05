<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

// デモ環境（DEMO_MODE=true）では、デモ用アカウントだけがログインできる
class DemoLoginRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'このデモ環境では、デモアカウントのみ利用できます';
    private const CAT = 'cat@test.com';
    private const CAT_PASSWORD = 'cat-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        // Cat のパスワードはシーダーの値に依存させず、テスト用に入れ直す
        User::where('email', self::CAT)->update(['password' => bcrypt(self::CAT_PASSWORD)]);
    }

    private function login(string $email, string $password)
    {
        return $this->from('/login')->post('/login', ['email' => $email, 'password' => $password]);
    }

    // 有効：デモ用アカウント以外は、正しいパスワードでもログインできない
    public function testOtherAccountIsRejectedInDemoMode(): void
    {
        config(['demo.enabled' => true]);

        $this->login(self::CAT, self::CAT_PASSWORD)
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => self::MESSAGE]);
        $this->assertGuest();

        // ログイン画面にメッセージが表示される
        $this->get('/login')->assertSee(self::MESSAGE);
    }

    // 有効：パスワードが違っても同じメッセージ（照合より前に判定する）。存在しないアドレスも同じ
    public function testRejectionDoesNotDependOnPassword(): void
    {
        config(['demo.enabled' => true]);

        $this->login(self::CAT, 'wrong-password')->assertSessionHasErrors(['email' => self::MESSAGE]);
        $this->login('nobody@test.com', 'wrong-password')->assertSessionHasErrors(['email' => self::MESSAGE]);
        $this->assertGuest();
    }

    // 有効：デモ用アカウントはログインできる（アドレスの大文字と小文字は区別しない）
    public function testDemoAccountCanLogInInDemoMode(): void
    {
        config(['demo.enabled' => true]);

        $this->login('Demo@Test.com', config('demo.password'))->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('email', config('demo.email'))->firstOrFail());
    }

    // 有効：デモ用アカウントでもパスワードが違えば、通常のログイン失敗になる
    public function testDemoAccountWithWrongPasswordFailsAsUsual(): void
    {
        config(['demo.enabled' => true]);

        $this->login(config('demo.email'), 'wrong-password')
            ->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません。']);
        $this->assertGuest();
    }

    // 無効（ローカル）：今までどおり、どのアカウントでもログインできる
    public function testAllAccountsCanLogInWhenDemoModeIsOff(): void
    {
        $this->assertFalse(config('demo.enabled'));

        $this->login(self::CAT, self::CAT_PASSWORD)->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('email', self::CAT)->firstOrFail());

        $this->post('/logout');
        $this->assertGuest();

        $this->login(config('demo.email'), config('demo.password'))->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('email', config('demo.email'))->firstOrFail());
    }
}
