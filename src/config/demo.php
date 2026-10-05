<?php

return [

    // デモ用アカウントの案内を表示するか（メールを送信しない公開環境で true にする）
    'notice' => (bool) env('DEMO_NOTICE', false),

    // デモ用アカウント（UsersTableSeeder と ProfilesTableSeeder が作る。商品は持たない）。
    // ログイン画面の「デモアカウントでログイン」が、この値でログインフォームを送信する
    'name' => 'Demo',
    'email' => 'demo@test.com',
    'password' => 'demo12345',

    // プロフィールの初期値（ProfilesTableSeeder と demo:reset が使う）
    'profile' => [
        'post_code' => '555-5555',
        'address' => 'ueno',
        'building' => 'zoo',
    ],

];
