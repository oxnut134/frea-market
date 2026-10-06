<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        // DB のエラーは、SQL に埋め込まれた値をログに出さない。
        // Laravel の QueryException のメッセージには、値を埋め込んだ SQL が入る（ログインや会員登録なら
        // メールアドレスやパスワードのハッシュ）。PostgreSQL の DETAIL の行にも値が入る（例：Key (email)=(...)）。
        // SQL は値を ? のままにして、エラーの種類と、アプリ側の呼び出し位置だけを記録する
        $this->reportable(function (QueryException $e) {
            $previous = $e->getPrevious();
            $message = $previous ? $previous->getMessage() : 'Database query failed';

            Log::error(trim(preg_replace('/\s*DETAIL:.*$/s', '', $message)), [
                'sql' => $e->getSql(),
                'at' => $this->applicationFrames($e),
            ]);

            // 既定の記録（値を埋め込んだ SQL とスタックトレース）は行わない
            return false;
        });
    }

    // スタックトレースのうち、アプリのコード（app/）の位置だけを「ファイル:行」で返す（引数の値は含めない）
    private function applicationFrames(Throwable $e): array
    {
        $frames = [];
        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? '';
            if (strpos($file, app_path()) === 0) {
                $frames[] = substr($file, strlen(base_path()) + 1) . ':' . ($frame['line'] ?? '?');
            }
        }

        return array_slice($frames, 0, 5);
    }
}
