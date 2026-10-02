<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use App\Models\User;
use App\Models\Profile;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    // 認証リンクのURLを生成する
    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
    }

    // 会員登録すると認証メールが送信され、認証待ち画面に誘導される
    public function testRegisterSendsVerificationMailAndShowsNotice(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'test',
            'email' => 'test@test.com',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ]);
        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'test@test.com')->first();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get('/')->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))
            ->assertStatus(200)
            ->assertSee('認証メールを再送する');
    }

    // 未認証ユーザーは保護されたページを使えない
    public function testUnverifiedUserCannotUseProtectedPages(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->get('/')->assertRedirect(route('verification.notice'));
        $this->get('/mypage')->assertRedirect(route('verification.notice'));
        $this->get('/sell')->assertRedirect(route('verification.notice'));
        $this->get('/profile/first')->assertRedirect(route('verification.notice'));
        $this->post('/item/comment', ['item_id' => 1, 'comment' => 'test'])
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('comments', 0);
    }

    // 未認証ユーザーもログインでき、認証待ち画面に誘導される
    public function testUnverifiedUserCanLoginAndSeeNotice(): void
    {
        User::factory()->unverified()->create([
            'email' => 'unverified@test.com',
            'password' => bcrypt('abc12345'),
        ]);

        $response = $this->post('/login', [
            'email' => 'unverified@test.com',
            'password' => 'abc12345',
        ]);
        $response->assertSessionDoesntHaveErrors();
        $this->assertAuthenticated();

        $this->get('/')->assertRedirect(route('verification.notice'));
    }

    // 認証待ち画面から認証メールを再送できる
    public function testVerificationMailCanBeResent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->from(route('verification.notice'))
            ->post(route('verification.send'));
        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    // 認証リンクをクリックすると認証済みになり、プロフィール入力画面に誘導される
    public function testVerificationLinkVerifiesUserAndLeadsToProfileFirst(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get($this->verificationUrl($user));
        $response->assertRedirect('/?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->get('/?verified=1')->assertRedirect(route('profile.first'));
        $this->get('/profile/first')->assertStatus(200);
    }

    // ログアウト状態で認証リンクを開いても、ログイン後に認証が完了する
    public function testVerificationLinkWorksAfterLogin(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@test.com',
            'password' => bcrypt('abc12345'),
        ]);
        $url = $this->verificationUrl($user);

        $this->get($url)->assertRedirect(route('login'));

        $response = $this->post('/login', [
            'email' => 'unverified@test.com',
            'password' => 'abc12345',
        ]);
        $response->assertRedirect($url);

        $this->get($url)->assertRedirect('/?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    // 認証済みユーザーはプロフィールを登録すると商品一覧を表示できる
    public function testVerifiedUserCanSeeIndexAfterFirstProfile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/profile/first', [
            'profile_image' => UploadedFile::fake()->image('person.png'),
            'user_name' => 'test',
            'post_code' => '111-1111',
            'address' => 'Tokyo',
            'building' => 'Building',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'address' => 'Tokyo']);

        $this->get('/')->assertStatus(200);
    }
}
