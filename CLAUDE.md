# frea-market

COACHTECH の模擬案件のフリマアプリ（Laravel）。ポートフォリオ用に仕上げ中。
作業ブランチは `portfolio`（main には直接コミットしない）。

## 環境

- 作業フォルダは WSL2 の `~/coachtech/frea-market`。Windows のフォルダ（`/mnt/c/...`）は使わない — Docker のマウントが遅いため。ホストは WSL2 の Ubuntu（bash）
- Laravel 8 / PHP 8.1 / PostgreSQL 15。アプリ本体は `src/`
- Docker Compose：`php`（`docker/php/Dockerfile`）、`nginx`（:80）、`pgsql`（:5432）、`adminer`（:8080）、`mailhog`（:8025 / SMTP :1025）
- artisan と composer は php コンテナ内で実行：`docker compose exec php php artisan ...`
- 新しい環境では、`storage` と `bootstrap/cache` の持ち主を www-data にする：`docker compose exec php chown -R www-data:www-data storage bootstrap/cache` と `docker compose exec php chmod -R ug+rwX storage bootstrap/cache`
- テスト：`docker compose exec php php artisan test`。DB は `phpunit.xml` の `pgsql_test` / `frea_test`。Stripe の鍵と Webhook シークレットも `phpunit.xml` のダミー値を使う
  - `frea_test` は自動では作られない。新しい環境では `docker compose exec pgsql createdb -U laravel_user frea_test` で作る
- メール：ローカルは MailHog（http://localhost:8025）
- 画像：`IMAGE_DISK`（既定 `public`、本番は `s3`）。URL は `Item::image_url` / `Profile::image_url` で生成
  - 新しい環境では、シーディング後に `php artisan storage:link` と `chown -R www-data:www-data storage/app/public`
  - アップロード上限 5MB（`docker/php/php.ini` は 6M。変更したら `docker compose build php && docker compose up -d php`）
- Stripe CLI：WSL2 側の `/usr/bin/stripe`（1.51.1）を使う。Windows 側の `C:\Program Files\Stripe\stripe`（1.43.2）は使わない。ログインは未実施（`stripe login`）。nginx が WSL2 の 80 番なので、`stripe listen --forward-to http://localhost/stripe/webhook`（WSL2 では未確認）。表示された `whsec_...` を `.env` の `STRIPE_WEBHOOK_SECRET` に入れる。`stripe docs` が使えるかは未確認（使えなければ docs.stripe.com の `.md` を直接取得）
- 本番：Render（ルートの `Dockerfile`、`render.yaml`、`docker/render/entrypoint.sh`）。`MAIL_MAILER=log`、`IMAGE_DISK=public` のまま

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
- ビューで CSS / JS を読み込むときは `asset()` ではなく `@versioned()` を使う（URL にファイルの更新時刻が付き、ブラウザが古いキャッシュを使い続けない。`AppServiceProvider` の Blade ディレクティブ）
- CSS ファイルの先頭には `@charset "UTF-8";` を書く

## 決済（完了）

方針：Checkout Session に一本化し、購入確定は Webhook で行う。自作の stripe-subscription-kit（TS）と同じ設計を PHP で実装した。ローカルでの確認手順は README の「1-6 Stripe設定」。

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
- `trading` は期限内（`expires_at > now()`）の `pending` だけ — 期限切れの `pending` が「取引中」のままだと誰も確保を試みず、掃除が走らないため
- `expires_at` と現在時刻の比較は、SQL の `NOW()` ではなく PHP の `now()` をバインドして渡す — アプリは Asia/Tokyo、本番の DB は UTC の可能性があり、`NOW()` だと 9 時間ずれるため（ローカルは DB も Asia/Tokyo）
- `Item` のリレーションは `purchases()`（hasMany、試みも含む全履歴）と `activePurchase()`（hasOne、期限内の `pending` か `paid` の 1 件）。画面や判定では `activePurchase()` を使う。フリマとしての 1:1 は `activePurchase()` と部分ユニークインデックスで表す
- `purchases.payment_method` は `card` / `konbini`（NOT NULL、CHECK 制約）。表示名（カード支払い / コンビニ払い）は `Purchase` モデルの定数
- `purchases` の列：`amount` は NOT NULL で CHECK `amount > 0`（PostgreSQL に unsigned がないため）、`expires_at` は NOT NULL、`stripe_checkout_url` は text / nullable、`delivery_address` は 255 文字
- 詳細画面：`sold` は「SOLD」、`trading` は「取引中」、自分の出品は「出品中の商品です」を購入ボタンの代わりに表示（この順で判定）。それ以外は「購入手続きへ」
- マイページの「購入した商品」は `paid` と、自分の期限内の `pending`（「お支払い待ち」と表示）
- 販売状況は商品画像に斜めの帯で重ねる（一覧・マイリスト・検索・マイページ・詳細）。共通の部品 `partials/sale_status_overlay.blade.php` と `header.css` の `sale-status_*` クラスを使い、販売中の商品には何も重ねない。詳細画面のボタン代わりの表示は別に残す
- nginx は CSS / JS も UTF-8 で返す（`charset utf-8;` と `charset_types`。`docker/nginx/default.conf` と `docker/render/nginx.conf` の両方）— ブラウザが CSS を Shift-JIS と誤認したため
- Checkout Session の期限：Stripe には「30 分 + 余裕 60 秒」を送り、DB の `expires_at` には Stripe が返した期限を入れる — Stripe は「作成から 30 分以上」を要求し、ちょうど 30 分だと通信の遅れで拒否されうるため
- コンビニ払いの `expires_at`：Pending の受信時に「3 日後の 23:59:59 + 余裕 24 時間」へ延ばす — 期限前に払込票を発行していれば期限後も入金でき、Stripe 側のバッファーの長さが不明なため。コンビニ払いは `success_url` に戻らない
- 自分の確保中にもう一度購入しようとした場合：決済画面の URL（`stripe_checkout_url`）が残っていればそこへ戻す。Pending の受信時に URL を消すので、支払い番号の発行後は「コンビニでのお支払いをお待ちしています…」を案内する
- キャンセル（`?checkout=canceled`）：Session を expire できたときだけ `expired` にして「決済をキャンセルしました」を表示。Stripe が受け付けない・通信エラーのときは DB を触らない
- 期限切れ・失敗の後に支払い成功が届いた場合は `paid` に戻さない（上のログで手動対応）。金額が `purchases.amount` と違う場合も error ログ
- 購入できないとき（自分の出品、販売中でない、他の人が確保中）は詳細画面へ戻し、フラッシュメッセージ（`session('message')`）を表示
- 完了画面（`/purchase/complete`）は表示だけ。DB に書かず、Stripe にも問い合わせない。カード払いで Webhook が未着なら「決済を確認しています。しばらくしてから再読み込みしてください」
- コンビニ払いの上限 300,000 円はサーバー側で検証（出品価格に上限はない）。`/purchase/{item_id}` は数値だけに制約
- ユニーク違反のテストは応答だけを確認する — INSERT が弾かれると、PostgreSQL ではテスト用のトランザクションがそれ以降使えないため
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
- 設定：`config/services.php` の `stripe.webhook_secret` / `checkout_expires_minutes`（30）/ `checkout_expiry_buffer_seconds`（60）/ `konbini_expires_after_days`（3）/ `konbini_expiry_grace_minutes`（1440）
- 購入フロー：`PurchaseController`（`checkout` / `complete` / キャンセル）、`StripeWebhookController`（`POST /stripe/webhook`、auth 外・CSRF 除外、署名不正は 400、処理中の例外は 500）、`app/Services/Purchase/PurchaseWebhookHandlers`
- テストの補助：`tests/Support/InteractsWithCheckout`（偽の Stripe クライアント、署名付き Webhook の送信）

## 依存パッケージ（完了）

`composer audit` の 41 件のうち 36 件を更新で解消。残る 5 件（`laravel/framework` 4 件、`league/flysystem` 1 件）は Laravel 9 以上が必要で、メジャーアップグレードは範囲外。理由は README の「依存パッケージの脆弱性」と `composer.json` に記録した。

### 決定事項

- `minimum-stability` は `stable` — Composer 2.9 以降は勧告のある版を候補から外すため、`dev` のままだと `composer update` が `laravel/framework` を `8.x-dev` に切り替える（脆弱性は直らず、audit の件数だけ減る）
- 残す勧告は `composer.json` の `config.policy.advisories.ignore-id` に「GHSA の ID: 理由」で登録する（旧 `audit.ignore` は非推奨）。理由のない登録はしない。外すと Laravel 8 が候補に残らず、`composer update` が解決できなくなる
- 登録するのは、入っている版に該当する勧告だけ — 古い版だけが対象の勧告（framework 8.83.28 未満、flysystem 1.1.4 未満）は、古い版へ下がるのを止めるために残す
- 更新は、対象のパッケージを名指しして `--with-dependencies` を付ける（全体の `composer update` はしない）— 変更を小さくし、原因を絞りやすくするため。本番用と開発用はコミットを分ける
- `composer audit` の終了コードは 1 のまま — 放棄されたパッケージ（`fruitcake/laravel-cors`、`swiftmailer/swiftmailer`）が失敗扱いになるため。Laravel 9 以上で不要になるもので、設定は変えない
- 会員登録のメールアドレスは `not_regex:/[\x00-\x1F\x7F]/` で制御文字を弾く — `email` ルールが引用符内の CRLF、NUL、TAB を通すため。GHSA-5vg9-5847-vvmq（email ルールの CRLF インジェクション、high）への緩和策で、修正ではない
  - メッセージは `validation.php` の `custom.email.not_regex`（共通の `not_regex` に置くと、ほかの項目でも「メールアドレス形式で…」と出るため）
  - テストは `RegisterEmailControlCharactersTest`（`RegisterValidationTest` は COACHTECH の一覧と対応しているので足さない）
- Composer の設定の書式は、同梱のスキーマで確認する（コンテナ内で `composer` を `.phar` の名前でコピーし、`phar://.../res/composer-schema.json` を読む）

## 今後の予定

- 次：いいね機能の見直し（調査と方針は `reports/2026-10-04-like-review.md`）：`likes_count` の廃止、POST / DELETE への変更と `insertOrIgnore`、アイコンの判定と連打対策の 3 コミット
- その次：Render へのデプロイ：S3（`IMAGE_DISK=s3`、`AWS_*`、バケットの公開設定）、SMTP、Webhook エンドポイントの登録と `STRIPE_WEBHOOK_SECRET`、DB の作り直し、`entrypoint.sh` の `storage:link` / `chown` の動作確認、`PurchasesTableSeeder` を `DatabaseSeeder` から呼ぶかの判断（今は呼んでいない）
  - SMTP に切り替えるときは GHSA-5vg9-5847-vvmq を再確認する（今は `MAIL_MAILER=log` で外部に送っていない前提で残している）。README と `composer.json` の理由も合わせて直す
- 仕上げ：検索欄の `value`、ロゴの `alt`、README（「利用技術」が古い：PHP 7.4.9、MySQL、stripe-php 9.9 など）
- Laravel のメジャーアップグレード（未定）：残る 5 件の勧告と、放棄されたパッケージ 2 つが解消する
- 確認待ち：`/search` の要ログインが仕様どおりか（今は触らない）
- 確認待ち：商品一覧（`index`）で自分の出品を除外するか（今は触らない。`IndexFunctionTest::testWithoutMyExhibition` は skip）
- 既知の点：画像の保存後に DB 登録が失敗するとファイルが残る
- 既知の点：商品一覧・マイページの取得に並び順の指定がなく、表示順が変わりうる（テストは並び順に依存させない）

## これまでに完了したフェーズ

- 掃除：`password_confirmation` 列の廃止、デッドコード・未使用の import / モデル / ビュー / ルートの削除、コメント投稿者の修正
- テスト修正：PostgreSQL 由来の失敗 8 件を修正（GD を追加）、`PurchaseController` のプロフィール取得を `user_id` で
- メール認証：Fortify の標準の流れに統一（`verified` ミドルウェア、認証待ち画面と再送、プロフィール未登録なら `/profile/first`）、不要な Fortify 機能とメール関連コードを削除
- 送信中表示：`public/js/submit-guard.js` で二重送信防止と「処理中…」
- 決済：Cashier の削除、stripe-php v21、`StripeCheckoutService`、購入状態の管理（`purchases.status`）と販売状況の表示、Checkout と Webhook への切り替え、README の手順
- 画像：Storage のディスクに保存（ランダムなファイル名）、シード画像はシーディング時にディスクへコピー、`public/storage` はシンボリックリンクに、`laravel-lang/lang` を削除（Packagist のマルウェア報告。手元の版はクリーンと確認済み）
- 依存パッケージ：脆弱な 10 パッケージを更新（35 パッケージ）、`minimum-stability` を `stable` に、残る 5 件の勧告を理由付きで登録、登録メールアドレスの制御文字の検証、README への記録
