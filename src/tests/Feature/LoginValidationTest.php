<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class LoginValidationTest extends TestCase
{

    use RefreshDatabase;

    public function testEmailValidationMessageIsDisplayed(): void
    {
        $response = $this->post('/login', [
            'email' => '', // メールアドレスを空にする
            'password' => 'abc12345', // 必要項目を入力
        ]);
        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください。']);
    }

    public function testPasswordValidationMessageIsDisplayed(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@test.com', // メールアドレスを入力
            'password' => '', // パスワードを空にする
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください。']);
    }
    public function testWrongInputValidationMessageIsDisplayed(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@test.com', // メールアドレスを入力
            'password' => 'abc12344', // パスワードを空にする
        ]);


        //       dd(session()->get('errors'));
        $errors = session()->get('errors');
        $this->assertEquals('ログイン情報が登録されていません。', $errors->get('email')[0]);
        //$response->assertSessionasErrors(['email' => 'ログイン情報が登録されていません。']);
    }

    /**
     * 正しいメールアドレスとパスワードでログインできることを確認するテスト
     *
     * @return void
     */
    public function testSuccessfulLogin(): void
    {
        User::factory()->create([
            'name' => 'PHPtest',
            'email' => 'phptest@test.com',
            'password' => bcrypt('abc12345'),
        ]);

        $user = User::orderBy('id')->get();

        $response = $this->post('/login', [
            'email' => 'phptest@test.com', // 正しいメールアドレス
            'password' => 'abc12345', // 正しいパスワード
        ]);
        //echo "\nレスポンスコード: ", $response->status(), "\n";
        $response->assertStatus(302);
        //dd(session()->get('errors'));
        $response->assertSessionDoesntHaveErrors(['email', 'password']);
    }
}

