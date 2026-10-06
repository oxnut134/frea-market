<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Models\User;

// デモ環境（DEMO_MODE=true）の会員登録：メール認証を省いて、すぐ使えるようにする
class DemoRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function register()
    {
        return $this->post('/register', [
            'name' => 'test',
            'email' => 'yourname@example.com',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ]);
    }

    // 登録と同時に認証済みになり、認証メールは送らない
    public function testRegistrationVerifiesUserWithoutSendingMail(): void
    {
        config(['demo.enabled' => true]);
        Notification::fake();

        // 登録後の行き先は通常と同じ（認証待ち画面）。認証済みなので、そこからすぐ先へ送られる
        $this->register()->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertRedirect('/');

        $user = User::where('email', 'yourname@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        Notification::assertNothingSent();
    }

    // 認証待ち画面を通らずにプロフィールの登録へ進み、登録すると一覧を開ける
    public function testRegisteredUserGoesStraightToProfileAndCanUseSite(): void
    {
        config(['demo.enabled' => true]);
        Notification::fake();

        $this->register();
        $this->get('/')->assertRedirect(route('profile.first'));

        $this->post('/profile/first', ['user_name' => 'test', 'post_code' => '111-1111', 'address' => 'Tokyo'])
            ->assertSessionDoesntHaveErrors();
        $this->get('/')->assertStatus(200);
    }

    // 無効（ローカル）：今までどおり未認証で作られ、認証メールが送られる
    public function testRegistrationRequiresVerificationWhenDemoModeIsOff(): void
    {
        $this->assertFalse(config('demo.enabled'));
        Notification::fake();

        $this->register()->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'yourname@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
