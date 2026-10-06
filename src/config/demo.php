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

    // プロフィールの初期値（ProfilesTableSeeder と demo:reset が使う）
    'profile' => [
        'post_code' => '555-5555',
        'address' => 'ueno',
        'building' => 'zoo',
    ],

];
