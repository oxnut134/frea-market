<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Profile;

// プロフィールの変更・登録の画面：エラーで戻ったときに入力値を残すこと、画像の欄で選べる形式
class ProfileFormTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '登録済みの名前']);
        Profile::create([
            'user_id' => $this->user->id,
            'post_code' => '111-1111',
            'address' => '登録済みの住所',
            'building' => '登録済みの建物',
        ]);
    }

    // エラーがなければ、登録済みの値を表示する
    public function testCurrentValuesAreShown(): void
    {
        $this->actingAs($this->user)
            ->get('/mypage/profile')
            ->assertOk()
            ->assertSee('name="user_name" value="登録済みの名前"', false)
            ->assertSee('name="post_code" value="111-1111"', false)
            ->assertSee('name="address" value="登録済みの住所"', false)
            ->assertSee('name="building" value="登録済みの建物"', false);
    }

    // エラーで戻ったときは、登録済みの値ではなく、入力した値を表示する（空にした欄も空のまま）
    public function testInputIsKeptWhenValidationFails(): void
    {
        $this->actingAs($this->user)
            ->from('/mypage/profile')
            ->post('/mypage/profile', [
                'user_name' => '',
                'post_code' => '1234567',
                'address' => '入力した住所',
                'building' => '',
            ])
            ->assertRedirect('/mypage/profile')
            ->assertSessionHasErrors(['user_name', 'post_code']);

        $this->get('/mypage/profile')
            ->assertOk()
            ->assertSee('name="user_name" value=""', false)
            ->assertSee('name="post_code" value="1234567"', false)
            ->assertSee('name="address" value="入力した住所"', false)
            ->assertSee('name="building" value=""', false)
            ->assertDontSee('登録済みの名前')
            ->assertDontSee('登録済みの住所');

        // プロフィールは書き換わらない
        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'name' => '登録済みの名前']);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $this->user->id,
            'post_code' => '111-1111',
            'address' => '登録済みの住所',
            'building' => '登録済みの建物',
        ]);
    }

    // プロフィールの登録：エラーがなければ、ユーザー名は会員登録の名前、ほかは空で表示する
    public function testFirstProfileFormShowsRegisteredNameAndEmptyFields(): void
    {
        $new_user = User::factory()->create(['name' => '会員登録の名前']);

        $this->actingAs($new_user)
            ->get('/profile/first')
            ->assertOk()
            ->assertSee('name="user_name" value="会員登録の名前"', false)
            ->assertSee('name="post_code" value=""', false)
            ->assertSee('name="address" value=""', false)
            ->assertSee('name="building" value=""', false);
    }

    // プロフィールの登録：エラーで戻ったときは、入力した値を表示する（空にしたユーザー名も空のまま）
    public function testFirstProfileInputIsKeptWhenValidationFails(): void
    {
        $new_user = User::factory()->create(['name' => '会員登録の名前']);

        $this->actingAs($new_user)
            ->from('/profile/first')
            ->post('/profile/first', [
                'user_name' => '',
                'post_code' => '1234567',
                'address' => '入力した住所',
                'building' => '入力した建物',
            ])
            ->assertRedirect('/profile/first')
            ->assertSessionHasErrors(['user_name', 'post_code']);

        $this->get('/profile/first')
            ->assertOk()
            ->assertSee('name="user_name" value=""', false)
            ->assertSee('name="post_code" value="1234567"', false)
            ->assertSee('name="address" value="入力した住所"', false)
            ->assertSee('name="building" value="入力した建物"', false)
            ->assertDontSee('会員登録の名前');

        // プロフィールは作られない
        $this->assertDatabaseMissing('profiles', ['user_id' => $new_user->id]);
    }

    // プロフィールの変更と登録の画面で、画像の欄は JPEG と PNG だけを選ばせる
    public function testImageInputAcceptsOnlyJpegAndPng(): void
    {
        $this->actingAs($this->user)
            ->get('/mypage/profile')
            ->assertOk()
            ->assertSee('name="profile_image" accept="image/jpeg,image/png"', false);

        $new_user = User::factory()->create();
        $this->actingAs($new_user)
            ->get('/profile/first')
            ->assertOk()
            ->assertSee('accept="image/jpeg,image/png"', false);
    }
}
