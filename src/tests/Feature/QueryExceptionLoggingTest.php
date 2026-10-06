<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Tests\TestCase;
use App\Models\User;

// DB のエラーをログに記録するとき、SQL に埋め込まれた値（メールアドレス、パスワードのハッシュなど）を出さない
class QueryExceptionLoggingTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'someone-real@example.com';
    private const HASH = '$2y$10$abcdefghijklmnopqrstuv';

    // 記録された内容（メッセージと付随する情報）を、1 つの文字列にして返す
    private function reportAndCapture(QueryException $e): string
    {
        $logged = [];
        Log::shouldReceive('error')->once()->andReturnUsing(function ($message, $context = []) use (&$logged) {
            $logged = [$message, $context];
        });

        app(ExceptionHandler::class)->report($e);

        return json_encode($logged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // 会員登録の INSERT が失敗しても、メールアドレスとパスワードのハッシュはログに出ない
    public function testBindingsAreNotLogged(): void
    {
        $e = new QueryException(
            'insert into "users" ("name", "email", "password") values (?, ?, ?)',
            ['someone', self::EMAIL, self::HASH],
            new PDOException('SQLSTATE[08006] [7] connection to server failed: timeout expired')
        );
        // 既定のメッセージには、値が埋め込まれている（これをそのまま記録しない）
        $this->assertStringContainsString(self::EMAIL, $e->getMessage());

        $logged = $this->reportAndCapture($e);

        $this->assertStringNotContainsString(self::EMAIL, $logged);
        $this->assertStringNotContainsString(self::HASH, $logged);
        // 何が失敗したかは分かる：エラーの種類と、値を ? のままにした SQL
        $this->assertStringContainsString('SQLSTATE[08006]', $logged);
        $this->assertStringContainsString('insert into \\"users\\" (\\"name\\", \\"email\\", \\"password\\") values (?, ?, ?)', $logged);
    }

    // PostgreSQL の DETAIL の行（値が入る）も出さない。実際のユニーク違反で確かめる
    public function testPostgresDetailLineIsNotLogged(): void
    {
        User::factory()->create(['email' => self::EMAIL]);

        try {
            DB::table('users')->insert(['name' => 'again', 'email' => self::EMAIL, 'password' => self::HASH]);
            $this->fail('ユニーク違反になるはず');
        } catch (QueryException $e) {
            // PostgreSQL のメッセージには、DETAIL: Key (email)=(...) が入っている
            $this->assertStringContainsString('DETAIL', $e->getPrevious()->getMessage());
            $this->assertStringContainsString(self::EMAIL, $e->getPrevious()->getMessage());
        }

        $logged = $this->reportAndCapture($e);

        $this->assertStringNotContainsString(self::EMAIL, $logged);
        $this->assertStringNotContainsString(self::HASH, $logged);
        $this->assertStringNotContainsString('DETAIL', $logged);
        $this->assertStringContainsString('users_email_unique', $logged);
        // アプリ側の呼び出し位置は、ファイルと行だけ（このテストは app/ の外なので、空）
        $this->assertStringContainsString('"at":[]', $logged);
    }

    // DB 以外の例外は、今までどおり記録される
    public function testOtherExceptionsAreReportedAsUsual(): void
    {
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) {
            return $message === 'something else' && ($context['exception'] ?? null) instanceof \RuntimeException;
        });

        app(ExceptionHandler::class)->report(new \RuntimeException('something else'));
    }
}
