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

// demo:reset（デモ用アカウントの操作を初期状態に戻し、新しく登録したユーザーを削除する）
class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    private $demo;
    private $seller; // シードのユーザー（Cat）。初期化の対象外
    private $item;   // Cat の出品

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['demo.enabled' => true]);

        $this->demo = $this->createUser(['name' => config('demo.name'), 'email' => config('demo.email')], config('demo.profile'));
        $this->seller = $this->createUser(['email' => 'cat@test.com']);
        $this->item = $this->createItem($this->seller);
    }

    // デモで登録したユーザー（「デモで登録した」印がある）
    private function createRegisteredUser(array $user = []): User
    {
        return $this->createUser(array_merge(['email' => 'yourname@example.com', 'registered_in_demo' => true], $user));
    }

    // 印のないユーザーに、購入・いいね・コメント・出品・プロフィール画像を持たせる
    private function giveEverything(User $user): array
    {
        $profile_image = UploadedFile::fake()->image('me.png')->store('profiles', 'public');
        Profile::where('user_id', $user->id)->update(['profile_image' => $profile_image]);
        $item_image = UploadedFile::fake()->image('bag.jpg')->store('items', 'public');
        $exhibited = $this->createItem($user, ['item_image' => $item_image]);
        $bought = $this->createItem($this->seller);
        Purchase::factory()->create(['user_id' => $user->id, 'item_id' => $bought->id]);
        Like::create(['user_id' => $user->id, 'item_id' => $this->item->id]);
        $this->addComment($user, $this->item);

        return [$profile_image, $item_image, $exhibited];
    }

    private function assertEverythingIsKept(User $user, array $data): void
    {
        [$profile_image, $item_image, $exhibited] = $data;

        $this->assertNotNull(User::find($user->id));
        $this->assertSame($profile_image, Profile::where('user_id', $user->id)->value('profile_image'));
        $this->assertNotNull(Item::find($exhibited->id));
        $this->assertSame(1, Purchase::where('user_id', $user->id)->count());
        $this->assertSame(1, Like::where('user_id', $user->id)->count());
        $this->assertSame(1, Comment::where('user_id', $user->id)->count());
        Storage::disk('public')->assertExists($profile_image);
        Storage::disk('public')->assertExists($item_image);
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

    // 名前とプロフィールを初期値に戻し、アップロードした画像を消す。画像は初期の画像に戻る
    public function testProfileIsRestored(): void
    {
        $image = UploadedFile::fake()->image('me.png')->store('profiles', 'public');
        $this->demo->update(['name' => 'changed']);
        Profile::where('user_id', $this->demo->id)->update(['profile_image' => $image, 'post_code' => '999-9999', 'address' => 'changed', 'building' => 'changed']);

        $this->reset();

        $this->assertSame(config('demo.name'), $this->demo->fresh()->name);
        $profile = Profile::where('user_id', $this->demo->id)->firstOrFail();
        $this->assertSame(config('demo.profile_image'), $profile->profile_image);
        $this->assertSame(config('demo.profile'), $profile->only(['post_code', 'address', 'building']));
        Storage::disk('public')->assertMissing($image);
        // 初期の画像は、ディスクになければコピーされる
        Storage::disk('public')->assertExists(config('demo.profile_image'));
    }

    // 初期の画像のままなら、その画像は消さない（何度実行しても残る）
    public function testInitialProfileImageIsKept(): void
    {
        $this->reset();
        Storage::disk('public')->put(config('demo.profile_image'), 'marker');

        $this->reset();
        $this->reset();

        $this->assertSame(config('demo.profile_image'), Profile::where('user_id', $this->demo->id)->value('profile_image'));
        // コピーし直してもいない
        $this->assertSame('marker', Storage::disk('public')->get(config('demo.profile_image')));
    }

    // 画面から画像を差し替えると、初期の画像のファイルは消される。初期化で、ファイルごと元に戻る
    public function testInitialProfileImageIsRestoredAfterReplacedOnProfilePage(): void
    {
        $this->reset();
        Storage::disk('public')->assertExists(config('demo.profile_image'));

        $this->actingAs($this->demo)->post('/mypage/profile', [
            'user_name' => config('demo.name'),
            'post_code' => '555-5555',
            'address' => 'ueno',
            'building' => 'zoo',
            'profile_image' => UploadedFile::fake()->image('me.png'),
        ])->assertRedirect(route('mypage'));
        $uploaded = Profile::where('user_id', $this->demo->id)->value('profile_image');
        $this->assertNotSame(config('demo.profile_image'), $uploaded);
        Storage::disk('public')->assertMissing(config('demo.profile_image'));

        $this->reset();

        $this->assertSame(config('demo.profile_image'), Profile::where('user_id', $this->demo->id)->value('profile_image'));
        Storage::disk('public')->assertExists(config('demo.profile_image'));
        Storage::disk('public')->assertMissing($uploaded);
    }

    // シードのユーザーの操作には触らない
    public function testSeededUsersDataIsKept(): void
    {
        $other = $this->createUser(['name' => 'other', 'email' => 'dog@test.com']);
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

    // デモで登録したユーザーは、購入・いいね・コメント・出品・プロフィール・画像・セッションごと削除する
    public function testRegisteredUserIsDeletedWithEverything(): void
    {
        $user = $this->createRegisteredUser();
        $profile_image = UploadedFile::fake()->image('me.png')->store('profiles', 'public');
        Profile::where('user_id', $user->id)->update(['profile_image' => $profile_image]);
        $item_image = UploadedFile::fake()->image('bag.jpg')->store('items', 'public');
        $exhibited = $this->createItem($user, ['item_image' => $item_image]);
        Purchase::factory()->create(['user_id' => $user->id, 'item_id' => $this->item->id]);
        Like::create(['user_id' => $user->id, 'item_id' => $this->item->id]);
        $this->addComment($user, $this->item);
        DB::table('sessions')->insert([
            ['id' => 'registered-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'seller-session', 'user_id' => $this->seller->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->reset();

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, Profile::where('user_id', $user->id)->count());
        $this->assertNull(Item::find($exhibited->id));
        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, Like::count());
        $this->assertSame(0, Comment::count());
        Storage::disk('public')->assertMissing($profile_image);
        Storage::disk('public')->assertMissing($item_image);
        $this->assertSame(['seller-session'], DB::table('sessions')->pluck('id')->all());
        // 購入が消えたので、商品は販売中に戻る
        $this->assertSame('on_sale', $this->item->fresh()->sale_status);
    }

    // ---------------- 印のないユーザーは、誰も消えない ----------------

    // 印のないユーザーは、シードのアドレスでなくても削除しない。操作にも触らない
    // （デモ環境でない時期に登録した利用者を、DEMO_MODE を誤って有効にしても消さないため）
    public function testUserWithoutMarkIsKeptWithEverything(): void
    {
        $user = $this->createUser(['email' => 'real-user@example.com']);
        $this->assertFalse($user->fresh()->registered_in_demo);
        $data = $this->giveEverything($user);

        $this->reset();

        $this->assertEverythingIsKept($user, $data);
    }

    // 認証待ちで止まったユーザー（印がない）も削除しない
    public function testUnverifiedUserWithoutMarkIsKept(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'stuck@example.com']);

        $this->reset();

        $this->assertNotNull(User::find($user->id));
    }

    // シーディングが失敗してシードのユーザーが 1 人もいなくても、印のないユーザーは誰も消えない
    public function testNobodyIsDeletedWhenSeededUsersAreMissing(): void
    {
        $user = $this->createUser(['email' => 'real-user@example.com']);
        $data = $this->giveEverything($user);
        // デモ用アカウントを消す。Cat は「シードのアドレスを持つ」こと以外、ふつうのユーザーと同じ
        $this->demo->delete();
        $before = User::orderBy('id')->pluck('id')->all();

        $this->reset();

        $this->assertSame($before, User::orderBy('id')->pluck('id')->all());
        $this->assertEverythingIsKept($user, $data);
    }

    // シードのアドレスの一覧が空でも（設定の誤り）、印のないユーザーは誰も消えない
    public function testNobodyIsDeletedWhenSeededEmailListIsEmpty(): void
    {
        config(['demo.seeded_emails' => []]);
        $user = $this->createUser(['email' => 'real-user@example.com']);
        $data = $this->giveEverything($user);
        $before = User::orderBy('id')->pluck('id')->all();

        $this->reset();

        $this->assertSame($before, User::orderBy('id')->pluck('id')->all());
        $this->assertEverythingIsKept($user, $data);
        // シードのユーザー（Cat）の出品も残る
        $this->assertNotNull(Item::find($this->item->id));
    }

    // デモ環境でない時期に登録したユーザーは、あとで DEMO_MODE を有効にして実行しても消えない
    public function testUserRegisteredOutsideDemoModeSurvivesReset(): void
    {
        config(['demo.enabled' => false]);
        $this->post('/register', [
            'name' => 'real',
            'email' => 'real-user@example.com',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ]);
        $this->post('/logout');
        $user = User::where('email', 'real-user@example.com')->firstOrFail();

        config(['demo.enabled' => true]);
        $this->reset();

        $this->assertNotNull(User::find($user->id));
    }

    // デモ環境で登録したユーザーは、次の初期化で消える（登録から削除までの流れ）
    public function testUserRegisteredInDemoModeIsDeletedByReset(): void
    {
        $this->post('/register', [
            'name' => 'visitor',
            'email' => 'visitor@example.com',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ]);
        $this->post('/logout');
        $this->assertTrue(User::where('email', 'visitor@example.com')->exists());

        $this->reset();

        $this->assertFalse(User::where('email', 'visitor@example.com')->exists());
    }

    // ---------------- 期限内の確保 ----------------

    // 期限内の確保がある、デモで登録したユーザーは、確保ごと残す。ほかの操作は消し、確保が片付いたあとの実行で削除する
    public function testRegisteredUserWithPendingPurchaseIsKeptUntilSettled(): void
    {
        $user = $this->createRegisteredUser();
        $profile_image = UploadedFile::fake()->image('me.png')->store('profiles', 'public');
        Profile::where('user_id', $user->id)->update(['profile_image' => $profile_image]);
        $waiting = Purchase::factory()->pending()->create(['user_id' => $user->id, 'item_id' => $this->item->id]);
        Like::create(['user_id' => $user->id, 'item_id' => $this->item->id]);

        $this->reset();

        $this->assertNotNull(User::find($user->id));
        $this->assertNotNull(Purchase::find($waiting->id));
        $this->assertSame(1, Profile::where('user_id', $user->id)->count());
        Storage::disk('public')->assertExists($profile_image);
        $this->assertSame(0, Like::count());

        $waiting->update(['status' => Purchase::STATUS_PAID, 'paid_at' => now()]);
        $this->reset();

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, Purchase::count());
        Storage::disk('public')->assertMissing($profile_image);
    }

    // 出品に、ほかの人の期限内の確保が付いている、デモで登録したユーザーは、出品ごと残す
    public function testRegisteredUserWhoseItemHasPendingPurchaseIsKept(): void
    {
        $user = $this->createRegisteredUser();
        $item_image = UploadedFile::fake()->image('bag.jpg')->store('items', 'public');
        $exhibited = $this->createItem($user, ['item_image' => $item_image]);
        $waiting = Purchase::factory()->pending()->create(['user_id' => $this->demo->id, 'item_id' => $exhibited->id]);

        $this->reset();

        $this->assertNotNull(User::find($user->id));
        $this->assertNotNull(Item::find($exhibited->id));
        $this->assertNotNull(Purchase::find($waiting->id));
        Storage::disk('public')->assertExists($item_image);

        $waiting->update(['status' => Purchase::STATUS_EXPIRED]);
        $this->reset();

        $this->assertNull(User::find($user->id));
        $this->assertNull(Item::find($exhibited->id));
        Storage::disk('public')->assertMissing($item_image);
    }

    // デモ用アカウントとシードのユーザーは、ユーザーとしては削除しない
    public function testDemoAndSeededUsersAreNotDeleted(): void
    {
        foreach (array_slice(config('demo.seeded_emails'), 1) as $email) {
            $this->createUser(['email' => $email]);
        }
        $this->createRegisteredUser();

        $this->reset();

        $expected = array_merge(config('demo.seeded_emails'), [config('demo.email')]);
        sort($expected);
        $this->assertSame($expected, User::orderBy('email')->pluck('email')->all());
    }

    // デモ環境でなければ、何も消さない（通常の環境で、登録したユーザーを消さないように）
    public function testNothingIsResetWhenDemoModeIsOff(): void
    {
        config(['demo.enabled' => false]);
        $user = $this->createRegisteredUser();
        Like::create(['user_id' => $this->demo->id, 'item_id' => $this->item->id]);
        $this->demo->update(['name' => 'changed']);

        $this->reset();

        $this->assertNotNull(User::find($user->id));
        $this->assertSame(1, Like::count());
        $this->assertSame('changed', $this->demo->fresh()->name);
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
