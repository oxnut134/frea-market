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
- 画像：`IMAGE_DISK`（既定 `public`。本番も `public` で、永続ディスクに保存）。URL は `Item::image_url` / `Profile::image_url` で生成
  - 新しい環境では、シーディング後に `php artisan storage:link` と `chown -R www-data:www-data storage/app/public`
  - アップロード上限 5MB（`docker/php/php.ini` は 6M。変更したら `docker compose build php && docker compose up -d php`）
- Stripe CLI：WSL2 側の `/usr/bin/stripe`（1.51.1）を使う。Windows 側の `C:\Program Files\Stripe\stripe`（1.43.2）は使わない。ログインは未実施（`stripe login`）。nginx が WSL2 の 80 番なので、`stripe listen --forward-to http://localhost/stripe/webhook`（WSL2 では未確認）。表示された `whsec_...` を `.env` の `STRIPE_WEBHOOK_SECRET` に入れる。`stripe docs` が使えるかは未確認（使えなければ docs.stripe.com の `.md` を直接取得）
- 本番：https://frea-market.onrender.com 。Render（Docker、Starter、シンガポール、永続ディスク 1 GB を `/var/www/storage/app/public` に）と Neon（無料プラン、PostgreSQL 15、シンガポール、`-pooler` の付かない直接接続）
  - 設定はルートの `Dockerfile`、`render.yaml`、`docker/render/`（`entrypoint.sh`、`nginx.conf`、`php-fpm.conf`、`supervisord.conf`、`scheduler.sh`）。`portfolio` への push で自動デプロイ
  - 手入力の環境変数は 6 つ（`APP_KEY`、`APP_URL`、`DATABASE_URL`、`STRIPE_PUBLIC_KEY`、`STRIPE_SECRET_KEY`、`STRIPE_WEBHOOK_SECRET`）。値は Render のダッシュボードにだけ置く
  - `MAIL_MAILER=log`、`DEMO_MODE=true`、`DEMO_CLIENT_IP_HEADER=CF-Connecting-IP`、`SESSION_DRIVER=database`、`LOG_CHANNEL=stderr`、`DB_SSLMODE=require`
  - Markdown だけの push ではデプロイされない（ビルドフィルター）
- 本番用のイメージの確認：ローカルで `docker build` して、使い捨ての `postgres:15` と空のボリューム（`/var/www/storage/app/public`）を別のネットワークに立てて起動する。開発用の DB には触れない。`render.yaml`、`entrypoint.sh`、nginx・php-fpm・supervisord の設定はテストで確かめられないので、変えたらこの方法で確かめる

## 作業ルール

- 着手前に方針・設計を提案し、了承を得てから実装する
- コミットは明示的な承認を得てから行う（「続けて」などは承認とみなさない）
- コミット前に差分を見せる
- コミットメッセージは英語で簡潔に 1 行のみ（本文なし、Co-Authored-By なし）
- 各コミットの前後で `php artisan test` を実行し、件数を報告する
- 画面の動き（ユーザーから見える仕様）は `main` に合わせる。ただし、セキュリティやバグの修正（配送先をサーバー側で組み立てる、プロフィールを `user_id` で引く、など）は、`main` と実装が違っても残す
  - 例外：配送先は `main` と違う。購入画面の「変更する」で入力した住所は、プロフィールを書き換えず、その商品の購入にだけ使う（「決済」の決定事項）
- 依頼範囲外の変更が必要な場合は、実施前に理由を説明する
- `migrate:fresh` やイメージの再ビルドが必要な場合は明示する（既存マイグレーションの修正で対応してよい。本番データは作り直せる）
- 危険の兆候（不審なコード、想定外のパッケージなど）や判断に迷う点があれば、中断して報告する
- 長い報告は `reports/` に Markdown で書き出す（`reports/` は Git 管理外）
- push は origin のみ。org には push しない
- 決済まわりのテストで Stripe の API に実際のリクエストを送らない（`tests/Support/FakeStripeHttpClient` かサービスのモックを使う）
- 秘密情報（.env の鍵、パスワードなど）の実際の値を、報告・reports/・コミットに書き出さない
- ビューで CSS / JS / `public/images` の画像を読み込むときは `asset()` ではなく `@versioned()` を使う（URL にファイルの更新時刻が付き、ブラウザが古いキャッシュを使い続けない。`AppServiceProvider` の Blade ディレクティブ）
- CSS ファイルの先頭には `@charset "UTF-8";` を書く

## README の書き方

- このアプリそのものの説明として、今の仕様を現在形で書く（概要、デモ、画面と操作、仕様、技術構成、ER 図、環境構築、テスト、本番の構成、既知の点）。「作り直した」「修正した」「以前は〜だった」のような、変更の経緯は書かない — 読む人は、初めてこのアプリを見る採用担当者やエンジニア
- 仕様は、コードとテストで確かめてから書く
- テストを足したら、README のテストの件数も直す
- 依存パッケージの勧告の詳しい説明は `docs/dependency-advisories.md`。README の「既知の点」からリンクする
- COACHTECH の模擬案件が土台であることは、末尾の一文だけ
- ER 図と構成図、購入の流れの図は Mermaid。スキーマや構成を変えたら、図も直す

### COACHTECH のテストケース一覧との対応

README には載せない。ファイル名を残してきたのは、この対応を保つため（作り直すテストは、ファイル名を残して中身を書き直す）。

| # | テストケース | テストファイル（`src/tests/Feature/`） |
|---|---|---|
| ① | 会員登録機能 | `RegisterValidationTest.php` |
| ② | ログイン機能 | `LoginValidationTest.php` |
| ③ | ログアウト機能 | `LogoutValidationTest.php` |
| ④ | 商品一覧取得 | `IndexFunctionTest.php` |
| ⑤ | マイリスト一覧取得 | `MylistFunctionTest.php` |
| ⑥ | 商品検索機能 | `SearchItemsTest.php` |
| ⑦ | 商品詳細情報取得 | `ShowItemDetailTest.php` |
| ⑧ | いいね機能 | `LikeFunctionTest.php` |
| ⑨ | コメント送信機能 | `CommentFunctionTest.php` |
| ⑩ | 商品購入機能 | `PurchaseFunctionTest.php` |
| ⑪ | 支払い方法選択機能 | `PaymentMethodDisplayedTest.php` |
| ⑫ | 配送先変更機能 | `RedirectDeliveryAddressTest.php` |
| ⑬ | ユーザー情報取得 | `MyPageFunctionTest.php` |
| ⑭ | ユーザー情報変更 | `MyProfileDisplayedTest.php` |
| ⑮ | 出品商品情報登録 | `RegisterForExhibitionTest.php` |

## 決済（完了）

方針：Checkout Session に一本化し、購入確定は Webhook で行う。自作の stripe-subscription-kit（TS）と同じ設計を PHP で実装した。ローカルでの確認手順は README の「Stripe の設定」。

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
- 本番の Webhook エンドポイントの API バージョンは `2025-05-28.basil` — Stripe のダッシュボードで `dahlia` を選べなかったため。カード払い（`checkout.session.completed`）は本番で正常に処理できた。アプリから Stripe への呼び出しは、stripe-php が固定する `2026-08-26.dahlia` のまま
- ハンドラ名は `onCheckoutPaymentSucceeded` / `onCheckoutPaymentPending` / `onCheckoutPaymentFailed` / `onCheckoutExpired` — kit のサブスク用 `onPaymentFailed` などと衝突させないため
- `expireCheckoutSession` は Stripe が受け付けなかった場合（`InvalidRequestException`）だけ `false`。通信エラー・認証エラー・429 は上に伝える
- 出品の最低価格は 120 円 — Stripe のコンビニ払いの下限（120〜300,000 円）。範囲外はコンビニ払いを選べない
- 作り直すテストはファイル名を残して中身を全面的に書き直す — COACHTECH のテストケース一覧との対応を保つため
- 配送先：購入画面の「変更する」で入力した住所は、プロフィールを書き換えず、セッションの `delivery_addresses.{item_id}`（`post_code` / `address` / `building`）に入れる。購入画面、変更画面の初期値、確保時の `delivery_address` は、セッションにあればそれを、なければプロフィールを使う（`PurchaseController::deliveryAddress`）— 一般的な通販サイトと同じく、その注文だけ別の住所に送れるようにするため。プロフィールを書き換えると、共有のデモ用アカウントでは、ほかの人が入力した住所も見えてしまう
  - セッションの住所は、完了画面（`complete`）で消す。確保時には消さない — キャンセルや決済の開始の失敗でやり直すときに、プロフィールの住所に戻ってしまうため。Webhook には購入者のセッションがないので、そこでは消せない
  - コンビニ払いは完了画面に戻らないので、セッションが切れる（ログアウト、120 分）まで残る。支払い済みの商品は購入画面に入れないので、使われることはない。期限切れのあとに同じ商品をやり直すと、変更した住所が使われる
  - 自分の確保中（期限内の `pending`）は、変更画面の表示も保存も購入画面へ戻す — 確保した購入の `delivery_address` は変わらないので、画面の表示と食い違うため
  - 変更画面の検証（`RedirectRequest`）：郵便番号は `123-4567` の形、住所と建物名は各 100 文字まで、3 つとも必須（プロフィールでは建物名は任意）— 合計 208 文字で、`purchases.delivery_address`（255 文字）に収まる。存在しない・数値でない `item_id` は 404
  - テストは `DeliveryAddressPerPurchaseTest` と `DeliveryAddressValidationTest`（`RedirectDeliveryAddressTest` は COACHTECH の一覧と対応しているので足さない。中身は変えずに通る）
  - `TrimStrings` が検証より先に前後の空白と改行を取り除く。末尾の改行のような入力は、画面からの送信では確かめられないので、ルールを直接 `Validator` にかけて確かめる

### 設計（`src/app/Services/Stripe/`）

- `StripeCheckoutService`：`createCheckoutSession` / `expireCheckoutSession` / `verifyWebhookEvent` / `handleWebhookEvent`。`AppServiceProvider` で singleton
- 結果オブジェクト（`VerifyWebhookEventResult`、`HandleWebhookEventResult`、`CheckoutSessionResult`）と入力（`CreateCheckoutSessionParams`）、DTO（`Data/`）、インターフェース `CheckoutWebhookHandlers`
- イベント：`completed` + `paid` → Succeeded、`completed` + `unpaid` → Pending、`async_payment_succeeded` → Succeeded、`async_payment_failed` → Failed、`expired` → Expired、それ以外は `handled: false`
- 設定：`config/services.php` の `stripe.webhook_secret` / `checkout_expires_minutes`（30）/ `checkout_expiry_buffer_seconds`（60）/ `konbini_expires_after_days`（3）/ `konbini_expiry_grace_minutes`（1440）
- 購入フロー：`PurchaseController`（`checkout` / `complete` / キャンセル）、`StripeWebhookController`（`POST /stripe/webhook`、auth 外・CSRF 除外、署名不正は 400、処理中の例外は 500）、`app/Services/Purchase/PurchaseWebhookHandlers`
- テストの補助：`tests/Support/InteractsWithCheckout`（偽の Stripe クライアント、署名付き Webhook の送信）

## 依存パッケージ（完了）

`composer audit` の 41 件のうち 36 件を更新で解消。残る 5 件（`laravel/framework` 4 件、`league/flysystem` 1 件）は Laravel 9 以上が必要で、メジャーアップグレードは範囲外。理由は `docs/dependency-advisories.md` と `composer.json` に記録した。

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

## いいね（完了）

調査と方針は `reports/2026-10-04-like-review.md`。

### 決定事項

- いいね数は `likes` から数える。`items.likes_count` は廃止 — 同じ事実を 2 か所に持つと、ユーザーの削除などでずれるため（`items.status` の廃止と同じ考え方）
- 追加は `POST /like/{id}`、削除は `DELETE /like/{id}`（数値だけに制約）— GET だと CSRF の検証がかからず、リンクを踏ませるだけで操作できたため
- 追加は `insertOrIgnore`（`ON CONFLICT DO NOTHING`）の 1 文 — `firstOrCreate` は同時に届くとユニーク違反で 500 になるため。重複は `likes(item_id, user_id)` のユニークインデックスで止める
- 応答は `{"liked": true/false, "likes": n}`。画面のアイコンと件数は、この応答で更新する（画面側で ±1 しない）
- アイコンは「自分がいいねしているか」で決める（初期表示は Blade で `$my_like` から）。件数では判定しない
- 連打対策：送信中は次のクリックを無視し、半透明にする（`public/js/like.js`、`detail.css` の `is-sending`）。失敗時は表示を変えず、メッセージも出さない
- 未ログインでは、いいねのアイコンを `/login` へのリンクにする。押した時点でログインが切れていた場合は、401 と 419 の両方で `/login` へ移す — ログアウトで CSRF トークンが作り直され、`auth` より先に CSRF の検証で止まるため
- CSRF トークンはレイアウトの `<meta name="csrf-token">` から取る（`/sanctum/csrf-cookie` は呼ばない）
- CSRF の検証そのものはテストで確かめられない（Laravel がテスト実行時に検証を省くため）。ブラウザか curl で確認する

## デモ（完了）

本番ではメールを送らない（`MAIL_MAILER=log`）。見に来た人は、デモ用アカウントでログインするか、架空のアドレスで会員登録して使う。

### 決定事項

- `DEMO_MODE`（`config('demo.enabled')`、既定は `false`）が、デモ環境の動きをまとめて切り替える：案内の表示、会員登録でのメール認証の省略、シードのユーザーのログイン拒否、登録と出品の上限、初期化。本番だけ `true`。`phpunit.xml` で `false` に固定（ローカルの `.env` に左右されないように）
- デモ用アカウントは 1 つ（ID 5、`demo@test.com`、メール認証済み、プロフィールあり、商品なし）。アドレス・パスワード・プロフィールの初期値は `config/demo.php`。`UsersTableSeeder` と `ProfilesTableSeeder` が作る
- ログイン画面には「デモアカウントでログイン」のボタンを置く。`public/js/demo-login.js` がログインフォームに値を入れて送信する。サーバー側に専用のログインの入口は作らない（通常の `POST /login`）
- 会員登録：デモ環境では、登録と同時に `email_verified_at` を入れる（`CreateNewUser`）。認証メールは未認証のユーザーにだけ送られるので、作られなくなる。登録画面には「架空のアドレスと、普段使っていないパスワードで」「認証メールは送信しません。毎日 4:00 に削除」の案内を出す（`partials/demo_notice.blade.php`）。認証待ち画面には、デモ用の表示を出さない
- 「デモで登録した」印：デモ環境で登録したユーザーにだけ `users.registered_in_demo = true` を付ける（既定は `false`、`$fillable` に入れない）。初期化での削除、人数の上限、出品数の上限（5 件）の対象は、この印があるユーザーだけ（`App\Services\Demo\DemoLimits`）— メールアドレスからの推測で決めると、`DEMO_MODE` を誤って有効にしたときに本物の利用者が全員消えるため。印の付け忘れ、シーディングの失敗、設定の誤りがあっても、誰も消えない側に倒れる
- ログインの制限：デモ環境では、シードの 4 人（`config('demo.seeded_emails')`）のログインを拒否する — パスワードがリポジトリで公開されているため。デモ用アカウントと、登録したユーザーはログインできる。Fortify のログインの処理（`Fortify::authenticateThrough`）の中で、パスワードの照合より前に判定する（`RejectSeededAccountsInDemoMode`）。`seeded_emails` を使うのはここだけ。シーダーと一覧の一致はテストで確かめている
- 上限（デモ環境だけ。`config('demo.limits')`）：同じ IP アドレスからの登録は 1 時間に 5 回（登録できた回数だけ数える）、登録したユーザーは 100 人まで、出品は登録したユーザーが 5 件・デモ用アカウントが 20 件まで — メール認証という関門がなくなり、画像でディスク（1 GB）が埋まりうるため
- 利用者の IP アドレスは、`DEMO_CLIENT_IP_HEADER`（本番は `CF-Connecting-IP`）のヘッダーから取る。なければ `$request->ip()` — Render の手前に Cloudflare があり、`X-Forwarded-For` が「利用者, プロキシ」の形だと、`$request->ip()` はプロキシのアドレスになるため（`TrustProxies` は接続元だけを信頼する）。Render の公式ドキュメントでは確かめられず、ほかの開発者の報告による。ローカルでは設定しない（利用者がヘッダーを偽れるため）。既存のログインの回数制限は `$request->ip()` のまま
- 初期化は `php artisan demo:reset`。起動時（`entrypoint.sh`）と、毎日 4:00（Asia/Tokyo）に実行する。`DEMO_MODE` が無効なら何もしない
  - デモ用アカウント：購入（`paid` / `expired` / `failed` と、期限を過ぎた `pending`）、いいね、コメント、出品（付いているいいね・コメント・購入・画像ごと）を消す。名前とプロフィールは初期値に戻す
  - デモで登録したユーザー：同じものを消したうえで、ユーザーごと削除する（プロフィール、画像、セッションも）
  - 残すもの：期限内の `pending`（あとから支払いの通知が届くため）と、それが付いている出品、それを持つユーザー。片付いたあとの初期化で消える
  - 印のないユーザー（シードのユーザー、デモ環境でない時期に登録したユーザー、以前の認証待ちで止まったユーザー）には触らない。Stripe は呼ばない。トランザクションは使わない
  - 出力は件数だけ（`Demo data was reset: {...,"users":n}`）。メールアドレスは出さない
- スケジュールは `$schedule->call()` で登録する — `$schedule->command()` は別プロセスで実行して出力を `/dev/null` に捨てるので、実行結果がログに残らないため。結果の 1 行は標準出力に出し、`LOG_LEVEL` に関係なく Render のログで確かめられる
- スケジューラーは `docker/render/scheduler.sh`（supervisord から www-data で起動。1 分ごとに `schedule:run`）— `schedule:work` は毎分「No scheduled commands are ready to run.」を出してログを埋めるため。その 1 行だけを落とし、ほかの出力とエラーは通す。`schedule:run -q` は、中で呼ぶコマンドの出力まで消えるので使わない
- `PurchasesTableSeeder` は `DatabaseSeeder` から呼ばない — ID 1 の購入を上書きするので、利用者の購入を壊すか、部分ユニークインデックスに弾かれてシーディングが失敗するため
- 購入のとき、ユーザーのメールアドレスを `customer_email` として Stripe に渡している（デモ環境でもそのまま）。本物のアドレスで登録して購入すると、Stripe のテスト環境に記録される。README に書いてある

## デプロイ（完了）

調査と計画は `reports/2026-10-05-render-paid-deploy-plan.md`（Supabase と Northflank の調査は `reports/2026-10-05-render-supabase-deploy-plan.md`）。

### 決定事項

- 公開先は Render の有料（Web）と Neon の無料（DB）— Render の無料は 15 分で止まり、Supabase の無料は使わないと一時停止されて手で再開が必要なため。Neon は眠っても、アクセスがあれば自動で起きる
- Neon の無料枠は月 100 CU 時間（0.25 CU で 400 時間）で、超えると次の請求期間まで DB が止まる。5 分アクセスがないと眠る。計算サイズの上限は、Neon の画面で 0.25 CU に固定する
- ヘルスチェックは `/healthz`（`docker/render/nginx.conf` で nginx が直接 200 を返す）— Laravel を通すとセッションで DB に触れ、数秒ごとのヘルスチェックで DB が眠れなくなるため。`robots.txt` はすべてのクローラーを断る
- DB を起こさないもの：ヘルスチェック、`robots.txt`、スケジューラーの毎分の実行、存在しないパスへのアクセス。起こすもの：Laravel の画面を通るアクセス（未ログインでもセッションで触る）、毎日 4:00 の初期化、デプロイ
- `sslmode` は `env('DB_SSLMODE', 'prefer')`（`pgsql` だけ）。本番は `require`。`DATABASE_URL` の `?sslmode=` は設定より優先される
- セッションは `database`（`sessions` テーブル）— `file` はデプロイのたびに消え、`cookie` は入力エラー時の入力値で 4KB を超えうるため。`SESSION_SECURE_COOKIE=true`（Laravel 8 は未設定だと `secure` を付けない）
- `APP_KEY` は手入力（`php artisan key:generate --show` の値）— Render の `generateValue` は `base64:` が付かず、Laravel が受け付けないため
- `APP_URL` も手入力 — 画像の URL（`public` ディスク）に使われる。`render.yaml` に固定の値を書くと、Blueprint の同期で戻るため
- `entrypoint.sh`：`APP_KEY` が空なら止める。マイグレーションの失敗は止める。シーディングと `demo:reset` の失敗は警告だけで続ける。`chown` は `storage` と `bootstrap/cache` の全体に、キャッシュ生成のあとで
- 永続ディスク：デプロイのたびに短い停止がある。残るのはマウント先の下だけ。ビルドとデプロイ前コマンドからは見えない（シーディングは起動時のまま）
- シード画像は、ディスクにあればコピーされない。リポジトリの画像を差し替えたら、Render のシェルでディスク上の古い画像を消してから再デプロイする
- php-fpm は `127.0.0.1:9000` だけで待ち受ける（`docker/render/php-fpm.conf` を `zzz-render.conf` としてコピー）— ベースイメージの `zz-docker.conf` がすべてのアドレスで待ち受けるため。開発用は別コンテナの nginx からつなぐので変えない
- `.dockerignore` で、ローカルの `.env`、`vendor`、DB のデータ、`storage` の中身をイメージに入れない
- DB のエラー（`QueryException`）は、値を埋め込んだ SQL とスタックトレースをログに出さない（`app/Exceptions/Handler.php`）— ログインや会員登録の SQL には、メールアドレスやパスワードのハッシュが入るため。PostgreSQL の `DETAIL:` の行（`Key (email)=(...)`）も落とす。記録するのは、DB のエラーの文、値を `?` のままにした SQL、`app/` の呼び出し位置（ファイルと行）だけ
- ビルドフィルター：Markdown だけの変更（`*.md`、`**/*.md`）では、Render はデプロイしない（`render.yaml` の `buildFilter.ignoredPaths`）— デプロイのたびに短い停止があり、デモのデータも初期化されるため。手動のデプロイは、フィルターに関係なく動く
- Render は `X-Forwarded-Proto` を付ける（`TrustProxies` は `*` で設定済み。本番で CSS と画像が https で読めている）

### デプロイ後に確認したこと

- 確認済み：デモ用アカウントでのログイン、カード払い（4242）、Webhook が 200、SOLD の表示、CSS と画像の表示（https）、再デプロイ後もシード画像が表示されること、「Detected a new open port TCP:9000」が php-fpm の修正後に出なくなったこと
- 確認済み（登録の開放後）：会員登録画面の案内、架空のアドレスでの登録（認証待ちを通らずにプロフィールの登録へ）、ログアウト後の再ログイン、`DEMO_CLIENT_IP_HEADER` と Build Filters が Blueprint の同期で入ったこと、デプロイのログのマイグレーションと `Demo data was reset`
- 未確認：下の「今後の予定」の「次」に挙げたもの

## 今後の予定

- 次：README にスクリーンショットを 4 枚入れる（`docs/images/` の `items.png`、`item-detail.png`、`purchase.png`、`mypage.png`。README にコメントで場所を確保してある。デモの初期化の直後に撮る）
- 次：デプロイ時のログの「CRIT unknown problem killing scheduler: PermissionError」の調査と修正（supervisord がスケジューラーのプロセスを止めるときのエラー。原因は未調査）
- 次：本番での確認
  - 登録の回数制限が利用者ごとに数えられているか — 同じ回線から 6 回目が弾かれたあと、別の回線（スマートフォンの回線など）から登録できること。別の回線でも弾かれるなら、`CF-Connecting-IP` が届いていない
  - 翌朝 4:00 過ぎのログに `Demo data was reset` が出ること（デモで登録したユーザーが消えること）
  - Neon の使用量を公開から 1 週間見る（目安は 1 日あたり約 3.3 CU 時間まで）。Neon がアクセスのない間に眠ること。多ければ、セッションをファイルにして永続ディスクに置く — 今のマウント先（`storage/app/public`）は外から見えるので、マウント先を `storage/app` に変えて、公開しないフォルダに置く必要がある
  - 再デプロイ後に、アップロードした画像とログインが残ること
  - 本番でのコンビニ払い（`basil` で届く `async_payment_succeeded` など）
  - Markdown だけの push で、Render がデプロイしないこと
- 最後に `portfolio` を `main` にマージする（プルリクエスト経由）— GitHub の既定のブランチは `main` で、書き直した README は `portfolio` にあるため。それまで README のクローン手順は `-b portfolio` のまま。マージしたら手順から `-b portfolio` を外す
- メールを実際に送る場合：GHSA-5vg9-5847-vvmq を再確認する（今は `MAIL_MAILER=log` で外部に送っていない前提で残している）。`docs/dependency-advisories.md` と `composer.json` の理由も合わせて直す。`DEMO_MODE` を `false` にすると、メール認証が戻り、デモ用の案内・制限・初期化がすべて止まる。印のあるユーザーは残るので、手で消す
- 仕上げ：検索欄の `value`、ロゴの `alt`
- 仕上げ：`ProfileRequest` と `ProfileFirstRequest` の郵便番号の正規表現の `$` を `\z` にする（`$` は末尾の改行を通す。`RedirectRequest` は修正済み）— `TrimStrings` が先に改行を取り除くので、実害はない
- 仕上げ：テストの並び順への依存をまとめて直す — 並び順なしの `first()` / `all()` が 7 ファイルに残っている（`CommentFunctionTest`、`RegisterForExhibitionTest`、`MyPageFunctionTest`、`MyProfileDisplayedTest`、`SearchItemsTest`、`IndexFunctionTest`、`LoginValidationTest`）。テスト中に VACUUM が走ると ID 順に返らず、まれに失敗する（`MylistFunctionTest` と `ShowItemDetailTest` で発生し、`orderBy('id')` で修正済み。テストを足すと、テーブルの中の並びが変わって表に出ることがある）
- Laravel のメジャーアップグレード（未定）：残る 5 件の勧告と、放棄されたパッケージ 2 つが解消する
- 検討：未ログインで `/frea` を開いたときの動きの確認（`auth` の外にあり、`ItemController::index` を呼ぶ。一覧が出るなら、仕様の抜け道）
- 確認待ち：`/search` の要ログインが仕様どおりか（今は触らない）
- 確認待ち：商品一覧（`index`）で自分の出品を除外するか（今は触らない。`IndexFunctionTest::testWithoutMyExhibition` は skip）
- 既知の点：画像の保存後に DB 登録が失敗するとファイルが残る
- 既知の点：商品一覧・マイページの取得に並び順の指定がなく、表示順が変わりうる（テストは並び順に依存させない）
- 既知の点（いいねの調査で見つけた、未対応）：存在しない商品 ID の詳細画面が 500、レイアウトの `<meta name="csrf-token">` が 2 つで `<body>` が入れ子、`LikeFactory.php` のクラス名が `likeFactory`、詳細画面で `users` と `profiles` の取得が 2 回ずつ

## これまでに完了したフェーズ

- 掃除：`password_confirmation` 列の廃止、デッドコード・未使用の import / モデル / ビュー / ルートの削除、コメント投稿者の修正
- テスト修正：PostgreSQL 由来の失敗 8 件を修正（GD を追加）、`PurchaseController` のプロフィール取得を `user_id` で
- メール認証：Fortify の標準の流れに統一（`verified` ミドルウェア、認証待ち画面と再送、プロフィール未登録なら `/profile/first`）、不要な Fortify 機能とメール関連コードを削除
- 送信中表示：`public/js/submit-guard.js` で二重送信防止と「処理中…」
- 決済：Cashier の削除、stripe-php v21、`StripeCheckoutService`、購入状態の管理（`purchases.status`）と販売状況の表示、Checkout と Webhook への切り替え、README の手順
- 画像：Storage のディスクに保存（ランダムなファイル名）、シード画像はシーディング時にディスクへコピー、`public/storage` はシンボリックリンクに、`laravel-lang/lang` を削除（Packagist のマルウェア報告。手元の版はクリーンと確認済み）
- 依存パッケージ：脆弱な 10 パッケージを更新（35 パッケージ）、`minimum-stability` を `stable` に、残る 5 件の勧告を理由付きで登録、登録メールアドレスの制御文字の検証、README への記録
- いいね：`likes_count` の廃止、POST / DELETE と `insertOrIgnore`、自分の状態でのアイコン表示と連打対策（`like.js`）、`LikeFunctionTest` の書き直し
- 環境：作業フォルダを WSL2 に移行（Windows のマウント越しだと 1 リクエストに約 1 秒かかったため）
- 不要ファイルの整理：Git に入っていた MySQL のデータフォルダ（`docker/mysql/data/`）の追跡除外、入れ子のリポジトリ・CSS のバックアップ・画像の複製などの削除、`.dockerignore`
- デモ：デモ用アカウントとログイン画面のボタン、会員登録の開放（メール認証の省略、「デモで登録した」印、登録と出品の上限）、シードのユーザーのログイン拒否、毎日 4:00 とデプロイ時の初期化（`demo:reset`）
- 配送先：購入画面で変更した住所を、プロフィールではなくセッションに商品ごとに持つ（その購入にだけ使う）、変更画面の入力の検証、入力エラー時に入力した値を残す
- デプロイ：Render（有料）と Neon（無料）に公開。起動スクリプトの修正、`sessions` テーブル、`/healthz` と `robots.txt`、`DB_SSLMODE`、php-fpm の待ち受けアドレス、README の全面的な書き直し（アプリそのものの説明として。図は Mermaid）、DB のエラーの記録から値を外す、ビルドフィルター
