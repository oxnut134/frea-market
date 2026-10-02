<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\Category;
use App\Models\Profile;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 画像の保存先をテスト用のディスクに差し替える
        Storage::fake('public');
    }

    // プロフィール登録済みのユーザーでログインする
    private function loginWithProfile(?string $profile_image = null): User
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'profile_image' => $profile_image,
            'post_code' => '111-1111',
            'address' => 'Tokyo',
        ]);
        $this->actingAs($user);

        return $user;
    }

    private function exhibitInput(array $overrides = []): array
    {
        $category = Category::create(['category' => 'ファッション']);

        return array_merge([
            'item_image' => UploadedFile::fake()->image('shoes.jpg'),
            'item_name' => '革靴',
            'brand_name' => 'regal',
            'price' => 4000,
            'description' => 'クラシックなデザインの革靴',
            'condition' => '良好',
            'categories' => [$category->category],
        ], $overrides);
    }

    private function profileInput(array $overrides = []): array
    {
        return array_merge([
            'user_name' => 'test',
            'post_code' => '222-2222',
            'address' => 'Osaka',
            'building' => 'Building',
        ], $overrides);
    }

    // 出品すると画像がディスクに保存され、DBには保存先のパスが入る
    public function testExhibitStoresItemImage(): void
    {
        $user = $this->loginWithProfile();

        $response = $this->post('/sell', $this->exhibitInput());
        $response->assertSessionHasNoErrors();

        $item = Item::where('user_id', $user->id)->first();
        $this->assertMatchesRegularExpression('#^items/[A-Za-z0-9]{40}\.jpg$#', $item->item_image);
        Storage::disk('public')->assertExists($item->item_image);

        // 一覧に保存した画像のURLが表示される
        $this->get('/mypage?tab=sell')->assertSee($item->image_url);
    }

    // 出品画像は必須
    public function testExhibitRequiresItemImage(): void
    {
        $this->loginWithProfile();

        $response = $this->post('/sell', $this->exhibitInput(['item_image' => null]));
        $response->assertSessionHasErrors(['item_image' => '画像ファイルを選択してください。']);
        $this->assertDatabaseCount('items', 0);
    }

    // jpeg / png 以外の画像は保存されない
    public function testExhibitRejectsOtherFileTypes(): void
    {
        $this->loginWithProfile();

        $response = $this->post('/sell', $this->exhibitInput([
            'item_image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]));
        $response->assertSessionHasErrors(['item_image' => '画像ファイルはjpegかpngの型式にしてください。']);
        $this->assertDatabaseCount('items', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // 5MBを超える画像は保存されない
    public function testExhibitRejectsTooLargeImage(): void
    {
        $this->loginWithProfile();

        $response = $this->post('/sell', $this->exhibitInput([
            'item_image' => UploadedFile::fake()->create('large.jpg', 5121, 'image/jpeg'),
        ]));
        $response->assertSessionHasErrors(['item_image' => '画像ファイルは5MB以下にしてください。']);
        $this->assertDatabaseCount('items', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // 初回プロフィール登録：画像ありの場合はディスクに保存される
    public function testFirstProfileStoresImage(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/profile/first', $this->profileInput([
            'profile_image' => UploadedFile::fake()->image('me.png'),
        ]));
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');

        $profile = Profile::where('user_id', $user->id)->first();
        $this->assertMatchesRegularExpression('#^profiles/[A-Za-z0-9]{40}\.png$#', $profile->profile_image);
        Storage::disk('public')->assertExists($profile->profile_image);
    }

    // 初回プロフィール登録：画像なしでも登録でき、デフォルトのアイコンが表示される
    public function testFirstProfileWithoutImageUsesDefaultIcon(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/profile/first', $this->profileInput());
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');

        $profile = Profile::where('user_id', $user->id)->first();
        $this->assertNull($profile->profile_image);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->get('/mypage')->assertStatus(200)->assertSee('images/default-profile.svg');
    }

    // プロフィール編集：画像を差し替えると新しい画像が保存され、古い画像は削除される
    public function testProfileUpdateReplacesImageAndDeletesOldOne(): void
    {
        $old_image = UploadedFile::fake()->image('old.png')->store('profiles', 'public');
        $user = $this->loginWithProfile($old_image);

        $response = $this->post('/mypage/profile', $this->profileInput([
            'profile_image' => UploadedFile::fake()->image('new.jpg'),
        ]));
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('mypage'));

        $profile = Profile::where('user_id', $user->id)->first();
        $this->assertNotSame($old_image, $profile->profile_image);
        Storage::disk('public')->assertExists($profile->profile_image);
        Storage::disk('public')->assertMissing($old_image);
        $this->assertSame('Osaka', $profile->address);
    }

    // プロフィール編集：画像を選択しない場合は現在の画像のまま
    public function testProfileUpdateWithoutImageKeepsCurrentImage(): void
    {
        $old_image = UploadedFile::fake()->image('old.png')->store('profiles', 'public');
        $user = $this->loginWithProfile($old_image);

        $response = $this->post('/mypage/profile', $this->profileInput());
        $response->assertSessionHasNoErrors();

        $profile = Profile::where('user_id', $user->id)->first();
        $this->assertSame($old_image, $profile->profile_image);
        Storage::disk('public')->assertExists($old_image);
        $this->assertSame('Osaka', $profile->address);
    }

    // プロフィール編集：5MBを超える画像は保存されず、現在の画像も残る
    public function testProfileUpdateRejectsTooLargeImage(): void
    {
        $old_image = UploadedFile::fake()->image('old.png')->store('profiles', 'public');
        $user = $this->loginWithProfile($old_image);

        $response = $this->post('/mypage/profile', $this->profileInput([
            'profile_image' => UploadedFile::fake()->create('large.png', 5121, 'image/png'),
        ]));
        $response->assertSessionHasErrors(['profile_image' => '画像ファイルは5MB以下にしてください。']);

        $this->assertSame($old_image, Profile::where('user_id', $user->id)->first()->profile_image);
        $this->assertSame([$old_image], Storage::disk('public')->allFiles());
    }
}
