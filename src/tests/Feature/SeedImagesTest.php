<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\Item;
use App\Models\Profile;
use Database\Seeders\DatabaseSeeder;

class SeedImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 画像の保存先をテスト用のディスクに差し替える
        Storage::fake('public');
    }

    // シーディングすると、シード画像が画像用ディスクにコピーされる
    public function testSeedersCopyImagesToImageDisk(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertCount(10, Storage::disk('public')->files('items'));
        $this->assertCount(4, Storage::disk('public')->files('profiles'));

        // DBのパスが指すファイルがすべて存在する
        foreach (Item::all() as $item) {
            $this->assertStringStartsWith('items/', $item->item_image);
            Storage::disk('public')->assertExists($item->item_image);
        }
        foreach (Profile::all() as $profile) {
            $this->assertStringStartsWith('profiles/', $profile->profile_image);
            Storage::disk('public')->assertExists($profile->profile_image);
        }
    }

    // ファイル名はURLで問題にならない文字だけを使う
    public function testSeedImageNamesAreUrlSafe(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Storage::disk('public')->allFiles() as $path) {
            $this->assertMatchesRegularExpression('#^(items|profiles)/[a-z0-9-]+\.(jpg|png)$#', $path);
        }
    }

    // 再度シーディングしても、すでにある画像はコピーし直さない
    public function testSeedersDoNotCopyExistingImagesAgain(): void
    {
        $this->seed(DatabaseSeeder::class);

        Storage::disk('public')->put('items/laptop.jpg', 'marker');
        Storage::disk('public')->delete('items/tumbler.jpg');

        $this->seed(DatabaseSeeder::class);

        // 既存のファイルはそのまま、なくなっていたファイルだけコピーされる
        $this->assertSame('marker', Storage::disk('public')->get('items/laptop.jpg'));
        Storage::disk('public')->assertExists('items/tumbler.jpg');
        $this->assertCount(10, Storage::disk('public')->files('items'));
    }
}
