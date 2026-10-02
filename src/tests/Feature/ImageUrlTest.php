<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\Profile;

class ImageUrlTest extends TestCase
{
    use RefreshDatabase;

    // 商品画像のURLは画像用ディスクから生成される
    public function testItemImageUrlComesFromImageDisk(): void
    {
        $item = new Item;
        $item->item_image = 'items/watch.jpg';

        $this->assertSame(
            Storage::disk(config('filesystems.images'))->url('items/watch.jpg'),
            $item->image_url
        );
        $this->assertStringEndsWith('/storage/items/watch.jpg', $item->image_url);
    }

    // プロフィール画像のURLは画像用ディスクから生成される
    public function testProfileImageUrlComesFromImageDisk(): void
    {
        $profile = new Profile;
        $profile->profile_image = 'profiles/person.png';

        $this->assertStringEndsWith('/storage/profiles/person.png', $profile->image_url);
    }

    // プロフィール画像が未設定の場合はデフォルトのアイコンになる
    public function testProfileImageUrlFallsBackToDefaultIcon(): void
    {
        $profile = new Profile;

        $this->assertSame(asset('images/default-profile.svg'), $profile->image_url);
    }

    // プロフィール編集画面はDBの内容を表示する
    public function testProfileEditPageShowsStoredProfile(): void
    {
        $user = User::factory()->create(['name' => 'テスト太郎']);
        Profile::create([
            'user_id' => $user->id,
            'profile_image' => 'profiles/person.png',
            'post_code' => '111-1111',
            'address' => 'Tokyo',
            'building' => 'Building',
        ]);
        $this->actingAs($user);

        // クエリ文字列の値ではなく、DBの値が表示される
        $response = $this->get('/mypage/profile?user_name=other&post_code=999-9999');
        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
        $response->assertSee('111-1111');
        $response->assertSee('/storage/profiles/person.png');
        $response->assertDontSee('999-9999');
    }
}
