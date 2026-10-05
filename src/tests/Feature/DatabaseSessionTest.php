<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Profile;

// SESSION_DRIVER=database（本番）でセッションを DB に保存できる
class DatabaseSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    // 画面を開くと sessions に 1 行でき、同じ Cookie なら行は増えない
    public function testSessionIsStoredInSessionsTable(): void
    {
        $response = $this->get('/login')->assertStatus(200);
        $this->assertSame(1, DB::table('sessions')->count());

        $cookie = $response->getCookie(config('session.cookie'), false);
        $this->withCookie(config('session.cookie'), $cookie->getValue())->get('/login')->assertStatus(200);
        $this->assertSame(1, DB::table('sessions')->count());
    }

    // ログインすると、セッションの行にユーザーの ID が入る
    public function testLoginIsKeptInSessionsTable(): void
    {
        $user = User::factory()->create(['password' => bcrypt('abc12345')]);
        Profile::create(['user_id' => $user->id, 'post_code' => '111-1111', 'address' => 'Tokyo']);

        $this->post('/login', ['email' => $user->email, 'password' => 'abc12345'])->assertRedirect('/');

        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}
