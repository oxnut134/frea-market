<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        // Webhook の署名検証用シークレット（whsec_...）
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // 購入手続き中の商品を確保しておく時間（Checkout Session の有効期限、30分以上）
        'checkout_expires_minutes' => 30,
        // Stripe に送る期限に足す余裕（秒）。Stripe は「作成から 30 分以上」を要求するので、通信の遅れで下回らないようにする
        'checkout_expiry_buffer_seconds' => 60,
        // コンビニ払いの支払期限（日数）
        'konbini_expires_after_days' => 3,
        // コンビニ払いの支払期限（最終日の 23:59:59）の後、商品の確保を続ける時間（分）。期限間際の入金の通知を待つため
        'konbini_expiry_grace_minutes' => 1440,
    ],
];
