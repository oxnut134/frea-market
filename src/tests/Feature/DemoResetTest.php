<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\Comment;
use App\Models\Item;
use App\Models\Like;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;

// demo:reset（デモ用アカウントの操作を初期状態に戻す）
class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    private $demo;
    private $seller;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->demo = $this->createUser(['name' => config('demo.name'), 'email' => config('demo.email')], config('demo.profile'));
        $this->seller = $this->createUser();
        $this->item = $this->createItem($this->seller);
    }

    private function createUser(array $user = [], array $profile = []): User
    {
        $user = User::factory()->create($user);
        Profile::create(array_merge(['user_id' => $user->id, 'post_code' => '111-1111', 'address' => 'Tokyo'], $profile));

        return $user;
    }

    private function createItem(User $seller, array $overrides = []): Item
    {
        return Item::forceCreate(array_merge([
            'user_id' => $seller->id,
            'item_image' => 'items/watch.jpg',
            'item_name' => '腕時計',
            'price' => 15000,
            'description' => 'スタイリッシュなデザインのメンズ腕時計',
            'condition' => '良好',
        ], $overrides));
    }

    private function addComment(User $user, Item $item): void
    {
        DB::table('comments')->insert(['user_id' => $user->id, 'item_id' => $item->id, 'comment' => 'test', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function reset(): void
    {
        $this->artisan('demo:reset')->assertExitCode(0);
    }

    // 支払い済みの購入を消すと、商品は販売中に戻る
    public function testPaidPurchaseIsDeletedAndItemIsOnSaleAgain(): void
    {
        Purchase::factory()->create(['user_id' => $this->demo->id, 'item_id' => $this->item->id]);
        $this->assertSame('sold', $this->item->fresh()->sale_status);

        $this->reset();

        $this->assertSame(0, Purchase::count());
        $this->assertSame('on_sale', $this->item->fresh()->sale_status);
    }

    // 期限切れ・失敗・期限を過ぎたままの確保は消し、期限内の確保（支払い待ち）だけを残す
    public function testOnlyPendingPurchaseWithinExpiryIsKept(): void
    {
        $items = [$this->item, $this->createItem($this->seller), $this->createItem($this->seller), $this->createItem($this->seller)];
        Purchase::factory()->expired()->create(['user_id' => $this->demo->id, 'item_id' => $items[0]->id]);
        Purchase::factory()->expired()->create(['user_id' => $this->demo->id, 'item_id' => $items[1]->id, 'status' => Purchase::STATUS_FAILED]);
        Purchase::factory()->pendingExpired()->create(['user_id' => $this->demo->id, 'item_id' => $items[2]->id]);
        $waiting = Purchase::factory()->pending()->create(['user_id' => $this->demo->id, 'item_id' => $items[3]->id]);

        $this->reset();

        $this->assertSame([$waiting->id], Purchase::pluck('id')->all());
        $this->assertSame('trading', $items[3]->fresh()->sale_status);
    }

    // いいねとコメントを消す
    public function testLikesAndCommentsAreDeleted(): void
    {
        Like::create(['user_id' => $this->demo->id, 'item_id' => $this->item->id]);
        $this->addComment($this->demo, $this->item);

        $this->reset();

        $this->assertSame(0, Like::count());
        $this->assertSame(0, Comment::count());
    }

    // 出品を、付いているいいね・コメント・購入と画像ごと消す
    public function testExhibitedItemsAreDeletedWithImages(): void
    {
        $image = UploadedFile::fake()->image('bag.jpg')->store('items', 'public');
        $exhibited = $this->createItem($this->demo, ['item_image' => $image]);
        Like::create(['user_id' => $this->seller->id, 'item_id' => $exhibited->id]);
        $this->addComment($this->seller, $exhibited);
        Purchase::factory()->create(['user_id' => $this->seller->id, 'item_id' => $exhibited->id]);

        $this->reset();

        $this->assertNull(Item::find($exhibited->id));
        Storage::disk('public')->assertMissing($image);
        $this->assertSame(0, Like::count());
        $this->assertSame(0, Comment::count());
        $this->assertSame(0, Purchase::count());
        // ほかのユーザーの出品は残る
        $this->assertNotNull(Item::find($this->item->id));
    }

    // 期限内の確保が付いている出品は残す（確保を連鎖で消さない）。確保が片付いたあとの実行で消える
    public function testExhibitedItemWithPendingPurchaseIsKeptUntilSettled(): void
    {
        $image = UploadedFile::fake()->image('bag.jpg')->store('items', 'public');
        $exhibited = $this->createItem($this->demo, ['item_image' => $image]);
        $waiting = Purchase::factory()->pending()->create(['user_id' => $this->seller->id, 'item_id' => $exhibited->id]);

        $this->reset();

        $this->assertNotNull(Item::find($exhibited->id));
        $this->assertNotNull(Purchase::find($waiting->id));
        Storage::disk('public')->assertExists($image);

        $waiting->update(['status' => Purchase::STATUS_PAID, 'paid_at' => now()]);
        $this->reset();

        $this->assertNull(Item::find($exhibited->id));
        Storage::disk('public')->assertMissing($image);
    }

    // 名前とプロフィールを初期値に戻し、アップロードした画像を消す
    public function testProfileIsRestored(): void
    {
        $image = UploadedFile::fake()->image('me.png')->store('profiles', 'public');
        $this->demo->update(['name' => 'changed']);
        Profile::where('user_id', $this->demo->id)->update(['profile_image' => $image, 'post_code' => '999-9999', 'address' => 'changed', 'building' => 'changed']);

        $this->reset();

        $this->assertSame(config('demo.name'), $this->demo->fresh()->name);
        $profile = Profile::where('user_id', $this->demo->id)->firstOrFail();
        $this->assertNull($profile->profile_image);
        $this->assertSame(config('demo.profile'), $profile->only(['post_code', 'address', 'building']));
        Storage::disk('public')->assertMissing($image);
    }

    // デモ用アカウント以外のユーザーの操作には触らない
    public function testOtherUsersDataIsKept(): void
    {
        $other = $this->createUser(['name' => 'other']);
        $image = UploadedFile::fake()->image('other.png')->store('profiles', 'public');
        Profile::where('user_id', $other->id)->update(['profile_image' => $image]);
        $second = $this->createItem($this->seller);
        Purchase::factory()->create(['user_id' => $other->id, 'item_id' => $this->item->id]);
        Purchase::factory()->expired()->create(['user_id' => $other->id, 'item_id' => $second->id]);
        Like::create(['user_id' => $other->id, 'item_id' => $this->item->id]);
        $this->addComment($other, $this->item);

        $this->reset();

        $this->assertSame(2, Purchase::where('user_id', $other->id)->count());
        $this->assertSame(1, Like::where('user_id', $other->id)->count());
        $this->assertSame(1, Comment::where('user_id', $other->id)->count());
        $this->assertSame(2, Item::where('user_id', $this->seller->id)->count());
        $this->assertSame('other', $other->fresh()->name);
        $this->assertSame($image, Profile::where('user_id', $other->id)->value('profile_image'));
        Storage::disk('public')->assertExists($image);
    }

    // 続けて実行しても、デモ用アカウントがいなくても、エラーにならない
    public function testResetIsRepeatableAndToleratesMissingDemoAccount(): void
    {
        $this->reset();
        $this->reset();
        $this->assertSame(1, Profile::where('user_id', $this->demo->id)->count());

        $this->demo->delete();
        $this->reset();
    }

    // 毎日 4:00（日本時間）に実行するように登録されていて、実行すると初期化される
    public function testResetIsScheduledDailyAtFourInTokyo(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())->where('description', 'demo:reset');

        $this->assertCount(1, $events);
        $this->assertSame('0 4 * * *', $events->first()->expression);
        $this->assertSame('Asia/Tokyo', $events->first()->timezone);

        Like::create(['user_id' => $this->demo->id, 'item_id' => $this->item->id]);
        $events->first()->run($this->app);

        $this->assertSame(0, Like::count());
    }
}
