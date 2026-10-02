<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\Profile;

class EnsureProfileExistsTest extends TestCase
{
    use RefreshDatabase;

    private function createItem(): Item
    {
        $seller = User::factory()->create();

        $item = new Item;
        $item->user_id = $seller->id;
        $item->item_image = 'Item-Armani+Mens+Clock.jpg';
        $item->item_name = '腕時計';
        $item->brand_name = 'Armani';
        $item->price = 15000;
        $item->description = 'スタイリッシュなデザインのメンズ腕時計';
        $item->condition = '良好';
        $item->save();

        return $item;
    }

    private function createProfile(User $user): Profile
    {
        return Profile::create([
            'user_id' => $user->id,
            'profile_image' => 'person.png',
            'post_code' => '111-1111',
            'address' => 'Tokyo',
        ]);
    }

    // 未ログインユーザーは商品一覧と商品詳細をそのまま表示できる
    public function testGuestCanSeeItemListAndDetail(): void
    {
        $item = $this->createItem();

        $this->get('/frea')->assertStatus(200)->assertSee('腕時計');
        $this->get('/item/' . $item->id)->assertStatus(200)->assertSee('腕時計');
        $this->assertGuest();
    }

    // プロフィール未登録のログインユーザーはプロフィール入力画面に誘導される
    public function testUserWithoutProfileIsRedirectedToProfileFirst(): void
    {
        $item = $this->createItem();
        $this->actingAs(User::factory()->create());

        $this->get('/frea')->assertRedirect(route('profile.first'));
        $this->get('/item/' . $item->id)->assertRedirect(route('profile.first'));
        $this->get('/')->assertRedirect(route('profile.first'));
        $this->get('/mypage')->assertRedirect(route('profile.first'));
        $this->get('/profile/first')->assertStatus(200);
    }

    // プロフィール登録済みのログインユーザーはそのまま表示できる
    public function testUserWithProfileCanSeePages(): void
    {
        $item = $this->createItem();
        $user = User::factory()->create();
        $this->createProfile($user);
        $this->actingAs($user);

        $this->get('/frea')->assertStatus(200);
        $this->get('/item/' . $item->id)->assertStatus(200);
        $this->get('/')->assertStatus(200);
    }

    // プロフィール登録済みでプロフィール入力画面を開くと商品一覧に戻される
    public function testUserWithProfileIsRedirectedFromProfileFirst(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user);
        $this->actingAs($user);

        $this->get('/profile/first')->assertRedirect('/');

        // 再送信してもプロフィールは変更・重複されない
        $this->post('/profile/first', [
            'profile_image' => UploadedFile::fake()->image('other.png'),
            'user_name' => 'test',
            'post_code' => '222-2222',
            'address' => 'Osaka',
        ])->assertRedirect('/');
        $this->assertEquals(1, Profile::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'address' => 'Tokyo']);
    }
}
