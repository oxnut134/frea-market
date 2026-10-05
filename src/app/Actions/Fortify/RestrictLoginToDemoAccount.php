<?php

namespace App\Actions\Fortify;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

// デモ環境（DEMO_MODE=true）では、デモ用アカウント以外のログインを受け付けない。
// ログインの処理（FortifyServiceProvider のパイプライン）の中で、パスワードの照合より前に判定する
class RestrictLoginToDemoAccount
{
    public function handle($request, $next)
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        $email = Str::lower((string) $request->input(Fortify::username()));
        if ($email !== Str::lower(config('demo.email'))) {
            throw ValidationException::withMessages([
                Fortify::username() => [trans('auth.demo_only')],
            ]);
        }

        return $next($request);
    }
}
