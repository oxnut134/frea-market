<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterEmailControlCharactersTest extends TestCase
{
    use RefreshDatabase;

    public function controlCharacterEmails(): array
    {
        return [
            '引用符の中の CRLF' => ["\"a\r\n b\"@example.com"],
            'NUL' => ["user\0@example.com"],
            'TAB' => ["user\t@example.com"],
        ];
    }

    /**
     * 制御文字を含むメールアドレスでは登録できず、認証メールも送られない
     *
     * @dataProvider controlCharacterEmails
     */
    public function testEmailWithControlCharactersIsRejected(string $email): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'test',
            'email' => $email,
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスは有効なメールアドレス形式で入力してください。']);
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }
}
