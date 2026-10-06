<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\Category;
use App\Models\Item;
use App\Models\Profile;
use App\Models\User;

// デモ環境（DEMO_MODE=true）の上限：登録の回数（IP アドレスごと）、登録したユーザーの人数、1 人あたりの出品数
class DemoLimitsTest extends TestCase
{
    use RefreshDatabase;

    private const THROTTLED = '登録の回数が上限に達しました。しばらくしてからお試しいただくか、デモアカウントをご利用ください。';
    private const FULL = 'ただいま新規登録を受け付けていません。デモアカウントをご利用ください。';
    private const ITEM_LIMIT = 'このデモ環境では、出品は 1 アカウント 5 件までです。';
    private const DEMO_ITEM_LIMIT = 'このデモ環境では、出品は 1 アカウント 20 件までです。';

    private $serial = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // 別のアドレスで登録する（登録するとログインした状態になるので、先にログアウトする）
    private function register(array $server = [])
    {
        $this->post('/logout');

        return $this->withServerVariables($server)->post('/register', [
            'name' => 'test',
            'email' => 'user' . (++$this->serial) . '@example.com',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ]);
    }

    private function loginWithProfile(string $email, bool $registered_in_demo = false): User
    {
        $user = User::factory()->create(['email' => $email, 'registered_in_demo' => $registered_in_demo]);
        Profile::create(['user_id' => $user->id, 'post_code' => '111-1111', 'address' => 'Tokyo']);
        $this->actingAs($user);

        return $user;
    }

    private function exhibit()
    {
        $category = Category::firstOrCreate(['category' => 'ファッション']);

        return $this->post('/sell', [
            'item_image' => UploadedFile::fake()->image('shoes.jpg'),
            'item_name' => '革靴',
            'brand_name' => 'regal',
            'price' => 4000,
            'description' => 'クラシックなデザインの革靴',
            'condition' => '良好',
            'categories' => [$category->category],
        ]);
    }

    // ---------------- 登録の回数（IP アドレスごと、1 時間に 5 回） ----------------

    // 同じ IP アドレスからは 5 回まで。6 回目は登録されず、メッセージを表示する。別の IP アドレスは登録できる
    public function testRegistrationIsLimitedPerIpAddress(): void
    {
        config(['demo.enabled' => true]);
        $first = ['REMOTE_ADDR' => '203.0.113.1'];

        for ($i = 0; $i < 5; $i++) {
            $this->register($first)->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(5, User::count());

        $this->register($first)->assertSessionHasErrors(['email' => self::THROTTLED]);
        $this->assertSame(5, User::count());
        $this->assertGuest();

        $this->register(['REMOTE_ADDR' => '203.0.113.2'])->assertSessionDoesntHaveErrors();
        $this->assertSame(6, User::count());
    }

    // 1 時間たつと、また登録できる
    public function testRegistrationLimitResetsAfterAnHour(): void
    {
        config(['demo.enabled' => true]);
        $server = ['REMOTE_ADDR' => '203.0.113.1'];

        for ($i = 0; $i < 5; $i++) {
            $this->register($server);
        }
        $this->register($server)->assertSessionHasErrors(['email' => self::THROTTLED]);

        $this->travel(61)->minutes();
        $this->register($server)->assertSessionDoesntHaveErrors();
    }

    // 入力エラーで登録できなかった分は、回数に数えない
    public function testFailedRegistrationIsNotCounted(): void
    {
        config(['demo.enabled' => true]);
        $server = ['REMOTE_ADDR' => '203.0.113.1'];

        for ($i = 0; $i < 6; $i++) {
            $this->withServerVariables($server)->post('/register', ['name' => '', 'email' => 'x', 'password' => 'a'])
                ->assertSessionHasErrors(['name']);
        }
        $this->register($server)->assertSessionDoesntHaveErrors();
    }

    // プロキシが利用者の IP アドレスを入れるヘッダーを設定した場合は、そのヘッダーで数える（接続元はプロキシで全員同じ）
    public function testRegistrationIsCountedByConfiguredClientIpHeader(): void
    {
        config(['demo.enabled' => true, 'demo.client_ip_header' => 'CF-Connecting-IP']);
        $visitor = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7'];

        for ($i = 0; $i < 5; $i++) {
            $this->register($visitor)->assertSessionDoesntHaveErrors();
        }
        // 同じ利用者は、プロキシのアドレスが変わっても同じ人として数える
        $this->register(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7'])
            ->assertSessionHasErrors(['email' => self::THROTTLED]);
        // 別の利用者は、プロキシのアドレスが同じでも登録できる
        $this->register(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.8'])
            ->assertSessionDoesntHaveErrors();
        // ヘッダーがない・IP アドレスでない場合は、接続元のアドレスで数える
        $this->register(['REMOTE_ADDR' => '10.0.0.3', 'HTTP_CF_CONNECTING_IP' => 'not-an-ip'])
            ->assertSessionDoesntHaveErrors();
    }

    // ヘッダーを設定していなければ、利用者が送ったヘッダーは使わない（偽って制限を逃れられないように）
    public function testClientIpHeaderIsIgnoredUnlessConfigured(): void
    {
        config(['demo.enabled' => true, 'demo.client_ip_header' => null]);

        for ($i = 0; $i < 5; $i++) {
            $this->register(['REMOTE_ADDR' => '203.0.113.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.' . $i]);
        }
        $this->register(['REMOTE_ADDR' => '203.0.113.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.99'])
            ->assertSessionHasErrors(['email' => self::THROTTLED]);
    }

    // ヘッダーが要る理由：X-Forwarded-For が「利用者, プロキシ」の形で届くと、$request->ip() はプロキシのアドレスになる
    // （TrustProxies は接続元だけを信頼し、信頼していない右端のアドレスを返すため）。利用者ごとに数えられない
    public function testRequestIpIsTheLastProxyWhenForwardedForHasTwoAddresses(): void
    {
        config(['demo.enabled' => true, 'demo.client_ip_header' => null]);
        $proxy = ['REMOTE_ADDR' => '10.0.0.1'];

        for ($i = 0; $i < 5; $i++) {
            $this->register($proxy + ['HTTP_X_FORWARDED_FOR' => '198.51.100.' . $i . ', 172.70.0.1']);
        }
        // 利用者は別々なのに、同じプロキシ（172.70.0.1）として数えられ、6 人目が弾かれる
        $this->register($proxy + ['HTTP_X_FORWARDED_FOR' => '198.51.100.99, 172.70.0.1'])
            ->assertSessionHasErrors(['email' => self::THROTTLED]);
    }

    // ---------------- 登録したユーザーの人数 ----------------

    // デモで登録したユーザーが上限に達したら、受け付けない。印のないユーザー（デモ用アカウント、シードのユーザーなど）は数えない
    public function testRegistrationStopsWhenUserLimitIsReached(): void
    {
        config(['demo.enabled' => true, 'demo.limits.users' => 2]);
        User::factory()->create(['email' => config('demo.email')]);
        User::factory()->create(['email' => 'cat@test.com']);
        User::factory()->create(['email' => 'real-user@example.com']);

        $this->register(['REMOTE_ADDR' => '203.0.113.1'])->assertSessionDoesntHaveErrors();
        $this->register(['REMOTE_ADDR' => '203.0.113.2'])->assertSessionDoesntHaveErrors();
        $this->register(['REMOTE_ADDR' => '203.0.113.3'])->assertSessionHasErrors(['email' => self::FULL]);

        $this->assertSame(5, User::count());
        $this->assertSame(2, User::where('registered_in_demo', true)->count());
    }

    // 無効（ローカル）：回数にも人数にも上限はない
    public function testRegistrationIsNotLimitedWhenDemoModeIsOff(): void
    {
        config(['demo.limits.users' => 2]);

        for ($i = 0; $i < 7; $i++) {
            $this->register(['REMOTE_ADDR' => '203.0.113.1'])->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(7, User::count());
    }

    // ---------------- 1 人あたりの出品数 ----------------

    // デモで登録したユーザーの出品は 5 件まで。6 件目は保存されず、画像も残らない
    public function testRegisteredUserCanExhibitUpToLimit(): void
    {
        config(['demo.enabled' => true]);
        $user = $this->loginWithProfile('yourname@example.com', true);

        for ($i = 0; $i < 5; $i++) {
            $this->exhibit()->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(5, Item::where('user_id', $user->id)->count());

        $this->from('/sell')->exhibit()->assertRedirect('/sell')->assertSessionHasErrors(['item_image' => self::ITEM_LIMIT]);
        $this->assertSame(5, Item::where('user_id', $user->id)->count());
        $this->assertCount(5, Storage::disk('public')->files('items'));

        // 出品画面にメッセージが表示される
        $this->get('/sell')->assertSee(self::ITEM_LIMIT);
    }

    // デモ用アカウントの出品は 20 件まで（全員が共有するので、登録したユーザーより多い）
    public function testDemoAccountCanExhibitUpToItsOwnLimit(): void
    {
        config(['demo.enabled' => true]);
        $user = $this->loginWithProfile(config('demo.email'));

        for ($i = 0; $i < 20; $i++) {
            $this->exhibit()->assertSessionDoesntHaveErrors();
        }
        $this->exhibit()->assertSessionHasErrors(['item_image' => self::DEMO_ITEM_LIMIT]);

        $this->assertSame(20, Item::where('user_id', $user->id)->count());
    }

    // 印のないユーザー（シードのユーザーや、デモ環境でない時期に登録したユーザー）には、出品数の上限はない
    public function testUsersWithoutMarkAreNotLimited(): void
    {
        config(['demo.enabled' => true]);

        foreach (['cat@test.com', 'real-user@example.com'] as $email) {
            $user = $this->loginWithProfile($email);
            for ($i = 0; $i < 6; $i++) {
                $this->exhibit()->assertSessionDoesntHaveErrors();
            }
            $this->assertSame(6, Item::where('user_id', $user->id)->count());
        }
    }

    // 無効（ローカル）：出品数に上限はない
    public function testItemsAreNotLimitedWhenDemoModeIsOff(): void
    {
        $user = $this->loginWithProfile('yourname@example.com', true);

        for ($i = 0; $i < 6; $i++) {
            $this->exhibit()->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(6, Item::where('user_id', $user->id)->count());
    }
}
