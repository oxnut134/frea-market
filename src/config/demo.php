<?php

return [

    // デモ環境として動かすか（メールを送信しない公開環境で true にする）。
    // true のとき、デモ用アカウントの案内を表示し、シードのユーザー（seeded_emails）のログインを受け付けない。
    // 会員登録では、メール認証を省いてすぐ使えるようにする
    'enabled' => (bool) env('DEMO_MODE', false),

    // デモ用アカウント（UsersTableSeeder と ProfilesTableSeeder が作る。商品は持たない）。
    // ログイン画面の「デモアカウントでログイン」が、この値でログインフォームを送信する
    'name' => 'Demo',
    'email' => 'demo@test.com',
    'password' => 'demo12345',

    // シードのユーザー（UsersTableSeeder が作る、デモ用アカウント以外のユーザー）。
    // パスワードがリポジトリで公開されているので、デモ環境ではログインさせない。
    // この一覧にもデモ用アカウントにも当たらないユーザーを、「新しく登録したユーザー」として扱う
    'seeded_emails' => [
        'cat@test.com',
        'dog@test.com',
        'tiger@test.com',
        'wolf@test.com',
    ],

    // 登録を開放したことへの歯止め（デモ環境でだけ効く。App\Services\Demo\DemoLimits）
    'limits' => [
        // 同じ IP アドレスから登録できる回数（1 時間あたり）
        'registrations_per_hour' => 5,
        // 新しく登録したユーザーの人数の上限（毎日の初期化で 0 に戻る）
        'users' => 100,
        // 新しく登録したユーザー 1 人あたりの出品数の上限
        'items_per_user' => 5,
        // デモ用アカウントの出品数の上限（全員が共有するので多め。毎日の初期化で 0 に戻る）
        'items_for_demo_account' => 20,
    ],

    // 利用者の IP アドレスが入るヘッダー。公開環境の手前のプロキシが設定するもの（Render では CF-Connecting-IP）。
    // 未設定なら $request->ip() を使う。プロキシのない環境で設定すると、利用者が自由に偽れるので設定しない
    'client_ip_header' => env('DEMO_CLIENT_IP_HEADER'),

    // プロフィールの初期値（ProfilesTableSeeder と demo:reset が使う）
    'profile' => [
        'post_code' => '555-5555',
        'address' => 'ueno',
        'building' => 'zoo',
    ],

];
