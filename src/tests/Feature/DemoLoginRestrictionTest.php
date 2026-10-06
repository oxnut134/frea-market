<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

// デモ環境（DEMO_MODE=true）では、シードのユーザーはログインできない。
// デモ用アカウントと、新しく登録したユーザーはログインできる
class DemoLoginRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'このアカウントは、デモ環境ではログインできません';
    private const FAILED = 'ログイン情報が登録されていません。';
    private const CAT = 'cat@test.com';
    private const CAT_PASSWORD = 'cat-password';
    private const REGISTERED = 'yourname@example.com';
    private const REGISTERED_PASSWORD = 'registered-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        // Cat のパスワードはシーダーの値に依存させず、テスト用に入れ直す
        User::where('email', self::CAT)->update(['password' => bcrypt(self::CAT_PASSWORD)]);

        // 新しく登録したユーザー（シードにいないアドレス）
        $registered = User::factory()->create(['email' => self::REGISTERED, 'password' => bcrypt(self::REGISTERED_PASSWORD)]);
        Profile::create(['user_id' => $registered->id, 'post_code' => '111-1111', 'address' => 'Tokyo']);
    }

    private function login(string $email, string $password)
    {
        return $this->from('/login')->post('/login', ['email' => $email, 'password' => $password]);
    }

    // シーダーが作るユーザーは、設定の一覧（seeded_emails）とデモ用アカウントで全部（一覧とシーダーがずれると、判定が狂う）
    public function testSeededEmailsMatchUsersCreatedBySeeder(): void
    {
        $expected = array_merge(config('demo.seeded_emails'), [config('demo.email')]);
        sort($expected);

        $this->assertSame($expected, User::where('email', '!=', self::REGISTERED)->orderBy('email')->pluck('email')->all());
    }

    // 有効：シードのユーザーは、正しいパスワードでもログインできない
    public function testSeededAccountIsRejectedInDemoMode(): void
    {
        config(['demo.enabled' => true]);

        foreach (config('demo.seeded_emails') as $email) {
            $this->login($email, self::CAT_PASSWORD)
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['email' => self::MESSAGE]);
            $this->assertGuest();
        }

        // ログイン画面にメッセージが表示される
        $this->get('/login')->assertSee(self::MESSAGE);
    }

    // 有効：パスワードが違っても同じメッセージ（照合より前に判定する）。アドレスの大文字と小文字は区別しない
    public function testRejectionDoesNotDependOnPasswordOrCase(): void
    {
        config(['demo.enabled' => true]);

        $this->login(self::CAT, 'wrong-password')->assertSessionHasErrors(['email' => self::MESSAGE]);
        $this->login('Cat@Test.com', self::CAT_PASSWORD)->assertSessionHasErrors(['email' => self::MESSAGE]);
        $this->assertGuest();
    }

    // 有効：デモ用アカウントはログインできる
    public function testDemoAccountCanLogInInDemoMode(): void
    {
        config(['demo.enabled' => true]);

        $this->login(config('demo.email'), config('demo.password'))->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('email', config('demo.email'))->firstOrFail());
    }

    // 有効：新しく登録したユーザーはログインできる
    public function testRegisteredUserCanLogInInDemoMode(): void
    {
        config(['demo.enabled' => true]);

        $this->login(self::REGISTERED, self::REGISTERED_PASSWORD)->assertRedirect('/');
        $this->assertAuthenticatedAs(User::where('email', self::REGISTERED)->firstOrFail());
    }

    // 有効：パスワードが違う場合や、存在しないアドレスは、通常のログイン失敗になる
    public function testWrongPasswordAndUnknownAddressFailAsUsual(): void
    {
        config(['demo.enabled' => true]);

        $this->login(config('demo.email'), 'wrong-password')->assertSessionHasErrors(['email' => self::FAILED]);
        $this->login(self::REGISTERED, 'wrong-password')->assertSessionHasErrors(['email' => self::FAILED]);
        $this->login('nobody@example.com', 'wrong-password')->assertSessionHasErrors(['email' => self::FAILED]);
        $this->assertGuest();
    }

    // 無効（ローカル）：今までどおり、シードのユーザーもログインできる
    public function testSeededAccountCanLogInWhenDemoModeIsOff(): void
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
