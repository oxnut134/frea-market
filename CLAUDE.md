# frea-market

COACHTECH の模擬案件のフリマアプリ（Laravel）。ポートフォリオ用に仕上げ中。
作業ブランチは `portfolio`（main には直接コミットしない）。

## 環境

- Laravel 8 / PHP 8.1 / PostgreSQL 15。アプリ本体は `src/`
- Docker Compose：`php`（`docker/php/Dockerfile`）、`nginx`（:80）、`pgsql`（:5432）、`adminer`（:8080）、`mailhog`（:8025 / SMTP :1025）
- artisan と composer は php コンテナ内で実行：`docker compose exec php php artisan ...`
- テスト：`docker compose exec php php artisan test`。DB は `phpunit.xml` の `pgsql_test` / `frea_test`。Stripe の鍵と Webhook シークレットも `phpunit.xml` のダミー値を使う
- メール：ローカルは MailHog（http://localhost:8025）
- 画像：`IMAGE_DISK`（既定 `public`、本番は `s3`）。URL は `Item::image_url` / `Profile::image_url` で生成
  - 新しい環境では、シーディング後に `php artisan storage:link` と `chown -R www-data:www-data storage/app/public`
  - アップロード上限 5MB（`docker/php/php.ini` は 6M。変更したら `docker compose build php && docker compose up -d php`）
- Stripe CLI：ホストの `C:\Program Files\Stripe\stripe`（1.43.2）。nginx がホストの 80 番なので、ホストから `stripe listen --forward-to http://localhost/...`
- 本番：Render（ルートの `Dockerfile`、`render.yaml`、`docker/render/entrypoint.sh`）。`MAIL_MAILER=log`、`IMAGE_DISK=public` のまま
- ホストは Windows + Git Bash：sed でバックスラッシュを含む置換をすると崩れるので Edit を使う。curl で日本語を送ると Shift-JIS になるので UTF-8 のファイル経由で送る

## 作業ルール

- 着手前に方針・設計を提案し、了承を得てから実装する
- コミットは明示的な承認を得てから行う（「続けて」などは承認とみなさない）
- コミット前に差分を見せる
- コミットメッセージは英語で簡潔に 1 行のみ（本文なし、Co-Authored-By なし）
- 各コミットの前後で `php artisan test` を実行し、件数を報告する
- 依頼範囲外の変更が必要な場合は、実施前に理由を説明する
- `migrate:fresh` やイメージの再ビルドが必要な場合は明示する（既存マイグレーションの修正で対応してよい。本番データは作り直せる）
- 危険の兆候（不審なコード、想定外のパッケージなど）や判断に迷う点があれば、中断して報告する
- 長い報告は `reports/` に Markdown で書き出す（`reports/` は Git 管理外）
- push は origin のみ。org には push しない
- 決済まわりのテストで Stripe の API に実際のリクエストを送らない（`tests/Support/FakeStripeHttpClient` かサービスのモックを使う）
- 秘密情報（.env の鍵、パスワードなど）の実際の値を、報告・reports/・コミットに書き出さない

## 決済フェーズ（進行中）

方針：Checkout Session に一本化し、購入確定は Webhook で行う。自作の stripe-subscription-kit（TS）と同じ設計を PHP で実装する。

### 決定事項

- 商品の確保は Checkout Session の作成時に行う。期限 30 分、`checkout.session.expired` で解除 — 二重購入を防ぐため
- コンビニ払いの支払期限は 3 日。その間は「取引中」
- 確保中（`pending`）の表示は「取引中」、支払い済み（`paid`）は「SOLD」 — 期限切れで販売中に戻ることがあるため
- 自分の出品は購入不可。購入画面・決済の前に確認する
- トランザクションは使わない — 各書き込みは 1 文で完結し、同時購入は部分ユニークインデックスで止めるため
  - `purchases(item_id) WHERE status IN ('pending','paid')` の部分ユニークインデックス（`DB::statement` で作成）
  - 確保の INSERT がインデックスに弾かれたら例外を受け止め、「他の方が購入手続き中です」を表示
  - Webhook の状態更新は `WHERE ... AND status = 'pending'` の条件付き UPDATE。重複受信でも二重に更新されない
- 確保の INSERT 時に `expires_at` へ仮の期限（30 分後）を入れる — Stripe の呼び出しと解除が両方失敗しても、掃除の対象になるように
- 期限切れの `pending` は、次に誰かが確保するときに `expired` にする（定期実行は使わない）
- 2 人目の決済が通った場合も自動返金しない。Session ID と PaymentIntent ID を含めてログに残し、手動対応
- 金額はリクエストから受け取らず `items.price` から取る
- `items.status` は廃止し、`purchases` の状態から判定する（`Item::sale_status`：`on_sale` / `trading` / `sold`）
- `purchases.status` は `pending` / `paid` / `expired` / `failed`（CHECK 制約）。キャンセルは `expired`、`async_payment_failed` は `failed`
- `trading` は期限内（`expires_at > now()`）の `pending` だけ — 期限切れの `pending` が「取引中」のままだと誰も確保を試みず、掃除が走らないため。コンビニ払いは Pending の受信時に `expires_at` を 3 日後へ延ばす
- `expires_at` と現在時刻の比較は、SQL の `NOW()` ではなく PHP の `now()` をバインドして渡す — アプリは Asia/Tokyo、本番の DB は UTC の可能性があり、`NOW()` だと 9 時間ずれるため（ローカルは DB も Asia/Tokyo）
- `Item` のリレーションは `purchases()`（hasMany、試みも含む全履歴）と `activePurchase()`（hasOne、期限内の `pending` か `paid` の 1 件）。画面や判定では `activePurchase()` を使う。フリマとしての 1:1 は `activePurchase()` と部分ユニークインデックスで表す
- `purchases.payment_method` は `card` / `konbini`（NOT NULL、CHECK 制約）。表示名（カード支払い / コンビニ払い）は `Purchase` モデルの定数
- `purchases` の列：`amount` は NOT NULL で CHECK `amount > 0`（PostgreSQL に unsigned がないため）、`expires_at` は NOT NULL、`stripe_checkout_url` は text / nullable、`delivery_address` は 255 文字
- 詳細画面：`sold` は「SOLD」、`trading` は「取引中」、自分の出品は「出品中の商品です」を購入ボタンの代わりに表示（この順で判定）。それ以外は「購入手続きへ」
- マイページの「購入した商品」は `paid` と、自分の期限内の `pending`（「お支払い待ち」と表示）
- 一覧・マイページの「SOLD」「取引中」は、以前の `sold` と同じ位置・同じスタイル
- Laravel Cashier は外した — 使っておらず、署名検証なしの `/stripe/webhook` が開いていたため
- stripe-php は `^21.3`（21.3.2、API バージョン `2026-08-26.dahlia`）— v22.0.0 はリリース直後でパッチ版がなく、決済は枯れた版を優先。v22 では Checkout の `payment_method_types` が廃止される
- ハンドラ名は `onCheckoutPaymentSucceeded` / `onCheckoutPaymentPending` / `onCheckoutPaymentFailed` / `onCheckoutExpired` — kit のサブスク用 `onPaymentFailed` などと衝突させないため
- `expireCheckoutSession` は Stripe が受け付けなかった場合（`InvalidRequestException`）だけ `false`。通信エラー・認証エラー・429 は上に伝える
- 出品の最低価格は 120 円 — Stripe のコンビニ払いの下限（120〜300,000 円）。範囲外はコンビニ払いを選べない
- 作り直すテストはファイル名を残して中身を全面的に書き直す — COACHTECH のテストケース一覧との対応を保つため

### 設計（`src/app/Services/Stripe/`）

- `StripeCheckoutService`：`createCheckoutSession` / `expireCheckoutSession` / `verifyWebhookEvent` / `handleWebhookEvent`。`AppServiceProvider` で singleton
- 結果オブジェクト（`VerifyWebhookEventResult`、`HandleWebhookEventResult`、`CheckoutSessionResult`）と入力（`CreateCheckoutSessionParams`）、DTO（`Data/`）、インターフェース `CheckoutWebhookHandlers`
- イベント：`completed` + `paid` → Succeeded、`completed` + `unpaid` → Pending、`async_payment_succeeded` → Succeeded、`async_payment_failed` → Failed、`expired` → Expired、それ以外は `handled: false`
- 設定：`config/services.php` の `stripe.webhook_secret` / `checkout_expires_minutes`（30）/ `konbini_expires_after_days`（3）

### 完了済みのコミット

- `c9ae579` Remove Laravel Cashier
- `9ff7719` Upgrade stripe-php to v21
- `d9d513f` Add Stripe checkout service
- `9def7a2` Require minimum item price of 120 yen
- `3e0e173` Propagate Stripe errors other than rejected session expiry

### 残りのコミット計画

- **4. Track purchase status and derive item sale status**（`migrate:fresh` が必要）
  - `purchases` に `status` / `payment_method`（`card` / `konbini`）/ `amount` / `stripe_checkout_session_id`（unique）/ `stripe_checkout_url` / `stripe_payment_intent_id` / `expires_at` / `paid_at`、部分ユニークインデックス、CHECK 制約（`status`、`payment_method`、`amount > 0`）、`delivery_address` を 255 文字に
  - `items.status` 列と `2025_07_10_073430_create_add_nullable_status_table.php` を削除、`ItemFactory` / `$fillable` から `status` を削除、`add_likes_count` の `->after('status')` を削除
  - `Purchase`：状態・支払い方法の定数、表示名と日本語からの逆引き、`scopeActive`
  - `Item`：`purchases()` / `activePurchase()`（既存の `purchase()` は削除）、`sale_status` / `sale_status_label`
  - 一覧・詳細・マイページの「取引中」「SOLD」表示とボタンの出し分け、「購入した商品」の「お支払い待ち」。一覧・マイリスト・検索・マイページは `with('activePurchase')`
  - `ItemController::index` / `UserController::getProfile` の status 再計算ループを削除
  - 旧フローの `store` / `directPay` のつなぎの修正（5 で削除）：判定を `sale_status` に置き換え、常に新しい行を INSERT（`status = paid`、変換した `payment_method`、`amount = items.price`、`expires_at` / `paid_at` は現在時刻）。ユニーク違反の 500 は 5 までは許容
  - `PurchasesTableSeeder`（購入者は出品者以外、`paid` / `card` / 商品の価格。`DatabaseSeeder` からは呼ばないまま — 呼ぶかはデプロイ時に決める）、`PurchaseFactory`（既定は `paid` / `card`、期限内の `pending`・期限切れの `pending`・`expired` の state）
  - テスト：`MylistFunctionTest` / `IndexFunctionTest` はコメントアウトされた sold のテストを「SOLD」「取引中」の検証として復活、`MyPageFunctionTest` は「購入した商品」の範囲と「お支払い待ち」を検証
  - `IndexFunctionTest::testWithoutMyExhibition` は `$new_user->save()` の誤りを直したうえで `markTestSkipped`（`index` の要件確認待ち）。`MylistFunctionTest` の有効なテストは中身に合う名前に変える
  - `PurchaseFunctionTest` / `PaymentMethodDisplayedTest` / `RedirectDeliveryAddressTest` は最小限の修正だけ（`items.status` の検証を外し、`sold` を `SOLD` に、`payment_method` の検証を `konbini` に）。書き直しは 5
  - `ItemSaleStatusTest` を追加：状態ごとの `sale_status`、期限の境界（直前・直後）での `trading` / `on_sale` の切り替え、詳細画面の出し分け、部分ユニークインデックスと CHECK 制約が違反を弾くこと
- **5. Switch purchase flow to Checkout and webhooks**
  - `POST /purchase/{item_id}/checkout`（確保 → Checkout Session）、成功画面 `/purchase/complete?session_id=...`（表示だけ、DB に書かない）、キャンセルは購入画面に `?checkout=canceled` で戻り `expire`
  - `POST /stripe/webhook`（auth 外、CSRF 除外、署名不正は 400、処理中の例外は 500）、`PurchaseWebhookHandlers` が `CheckoutWebhookHandlers` を実装
  - コンビニ払いの Pending 受信時に `expires_at` を 3 日後へ延ばす
  - 自分の出品に対するサーバー側の拒否（購入画面・決済の前）
  - 確保の INSERT のユニーク違反を受け止める（4 のつなぎでは 500 のまま）
  - 購入画面は支払い方法だけを送る（hidden の price / email などを廃止）。`PurchaseRequest` は `in:card,konbini`
  - 削除：ルート `/stripe`、`/payment`、`/payment/direct`、`/checkout`、`/checkout/success`、`/checkout/cancel`、`PaymentController`、`payment/index.blade.php`、`temporary_message.blade.php`
  - テスト：`PurchaseFunctionTest` / `PaymentMethodDisplayedTest` / `RedirectDeliveryAddressTest` を書き直し、`StripeWebhookEndpointTest` / `CheckoutReservationTest` / `PurchaseCompletePageTest` を追加
- **6. Document local Stripe webhook setup**：README に Stripe CLI の手順、`STRIPE_WEBHOOK_SECRET`、ダッシュボードの Webhook の API バージョンを `2026-08-26.dahlia` にすること

## 今後の予定

- 決済フェーズの後：依存パッケージの脆弱性対応（`composer audit`：12 パッケージ 41 件、Laravel 8 のサポート終了が根本原因）
- その後、Render へのデプロイ：S3（`IMAGE_DISK=s3`、`AWS_*`、バケットの公開設定）、SMTP、Webhook エンドポイントの登録と `STRIPE_WEBHOOK_SECRET`、DB の作り直し、`entrypoint.sh` の `storage:link` / `chown` の動作確認
- 仕上げ：いいね（連打対策、アイコンの切り替え条件）、検索欄の `value`、ロゴの `alt`、README
- 確認待ち：`/search` の要ログインが仕様どおりか（今は触らない）
- 確認待ち：商品一覧（`index`）で自分の出品を除外するか（今は触らない。`IndexFunctionTest::testWithoutMyExhibition` は skip）
- 既知の点：画像の保存後に DB 登録が失敗するとファイルが残る

## これまでに完了したフェーズ

- 掃除：`password_confirmation` 列の廃止、デッドコード・未使用の import / モデル / ビュー / ルートの削除、コメント投稿者の修正
- テスト修正：PostgreSQL 由来の失敗 8 件を修正（GD を追加）、`PurchaseController` のプロフィール取得を `user_id` で
- メール認証：Fortify の標準の流れに統一（`verified` ミドルウェア、認証待ち画面と再送、プロフィール未登録なら `/profile/first`）、不要な Fortify 機能とメール関連コードを削除
- 送信中表示：`public/js/submit-guard.js` で二重送信防止と「処理中…」
- 画像：Storage のディスクに保存（ランダムなファイル名）、シード画像はシーディング時にディスクへコピー、`public/storage` はシンボリックリンクに、`laravel-lang/lang` を削除（Packagist のマルウェア報告。手元の版はクリーンと確認済み）
