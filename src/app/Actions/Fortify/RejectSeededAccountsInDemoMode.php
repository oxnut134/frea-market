<?php

namespace App\Actions\Fortify;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

// デモ環境（DEMO_MODE=true）では、シードのユーザー（config('demo.seeded_emails')）のログインを受け付けない。
// パスワードがリポジトリで公開されているため。デモ用アカウントと、新しく登録したユーザーはログインできる。
// ログインの処理（FortifyServiceProvider のパイプライン）の中で、パスワードの照合より前に判定する
class RejectSeededAccountsInDemoMode
{
    public function handle($request, $next)
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        $email = Str::lower((string) $request->input(Fortify::username()));
        if (in_array($email, array_map([Str::class, 'lower'], config('demo.seeded_emails')), true)) {
            throw ValidationException::withMessages([
                Fortify::username() => [trans('auth.demo_unavailable')],
            ]);
        }

        return $next($request);
    }
}
