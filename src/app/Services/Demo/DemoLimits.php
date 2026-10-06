<?php

namespace App\Services\Demo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\Item;
use App\Models\User;

// デモ環境（DEMO_MODE=true）で、誰でも登録できるようにしたことへの歯止め。
// 「デモで登録したユーザー」の定義と、登録の回数・人数・出品数の上限をまとめる
class DemoLimits
{
    // デモで登録したユーザー：デモ環境での会員登録のときに印（users.registered_in_demo）を付けたもの。
    // 毎日の初期化で削除する対象なので、メールアドレスなどからの推測では決めない。
    // 印がなければ対象にしない（シーディングの失敗や設定の誤りがあっても、誰も消えない側に倒す）
    public static function registeredUsers()
    {
        return User::where('registered_in_demo', true);
    }

    public static function isRegisteredUser(User $user): bool
    {
        return $user->registered_in_demo === true;
    }

    // 利用者の IP アドレス。公開環境の手前にはプロキシがあり、$request->ip() が利用者のものとは限らないので、
    // プロキシが利用者のアドレスを入れるヘッダー（config('demo.client_ip_header')）があれば、そちらを使う
    public static function clientIp(Request $request): string
    {
        $header = config('demo.client_ip_header');
        $ip = $header ? trim((string) $request->headers->get($header)) : '';

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : (string) $request->ip();
    }

    // 登録を受け付けられるか。受け付けられなければ、入力エラーとして返す
    public static function ensureCanRegister(Request $request): void
    {
        if (self::registeredUsers()->count() >= config('demo.limits.users')) {
            throw ValidationException::withMessages(['email' => [trans('demo.registration_full')]]);
        }

        if (RateLimiter::tooManyAttempts(self::registrationKey($request), config('demo.limits.registrations_per_hour'))) {
            throw ValidationException::withMessages(['email' => [trans('demo.registration_throttled')]]);
        }
    }

    // 登録できた回数を、IP アドレスごとに 1 時間数える
    public static function recordRegistration(Request $request): void
    {
        RateLimiter::hit(self::registrationKey($request), 3600);
    }

    // 出品数の上限（画像でディスクが埋まるのを防ぐ）。それ以外のユーザーには上限がない（null）
    public static function itemLimitFor(User $user): ?int
    {
        if (Str::lower($user->email) === Str::lower(config('demo.email'))) {
            return config('demo.limits.items_for_demo_account');
        }

        return self::isRegisteredUser($user) ? config('demo.limits.items_per_user') : null;
    }

    public static function hasReachedItemLimit(User $user): bool
    {
        $limit = self::itemLimitFor($user);

        return $limit !== null && Item::where('user_id', $user->id)->count() >= $limit;
    }

    private static function registrationKey(Request $request): string
    {
        return 'demo-registration:' . self::clientIp($request);
    }
}
