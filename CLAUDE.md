# frea-market

COACHTECH の模擬案件のフリマアプリ（Laravel）。ポートフォリオ用に仕上げ中。アプリ名は「Flea Market」（リポジトリと URL の名前は `frea-market` のまま）。
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
  - DB は SSL ありで立て、`DB_SSLMODE=require` でつなぐ（`postgres:15` の中で `openssl` で自己署名の証明書を作り、`-c ssl=on -c ssl_cert_file=... -c ssl_key_file=...` で起動する）— 本番の Neon は SSL 必須で、SSL なしでは、スケジューラーが DB につなげない不具合を再現できなかったため
  - スケジュールのジョブは、4:00 を待たずに `php artisan schedule:test` で実行できる。スケジューラーと同じ条件にするには、www-data で、動いているスケジューラーの環境（`/proc/{pid}/environ`）を使う。ほかのユーザーのプロセスの環境は root でも読めないので、www-data として読む

## 作業ルール

- 着手前に方針・設計を提案し、了承を得てから実装する
- コミットは明示的な承認を得てから行う（「続けて」などは承認とみなさない）
- コミット前に差分を見せる
- コミットメッセージは英語で簡潔に 1 行のみ（本文なし、Co-Authored-By なし）
- 各コミットの前後で `php artisan test` を実行し、件数を報告する
- 画面の動き（ユーザーから見える仕様）は `main` に合わせる。ただし、セキュリティやバグの修正（配送先をサーバー側で組み立てる、プロフィールを `user_id` で引く、など）は、`main` と実装が違っても残す
  - 例外：配送先は `main` と違う。購入画面の「変更する」で入力した住所は、プロフィールを書き換えず、その商品の購入にだけ使う（「決済」の決定事項）
  - 例外：未ログインの商品詳細の表示は `main` と違う。コメントは、見出しとすべてのコメントを未ログインでも表示し（`main` は、ログイン中だけ最新の 1 件）、入力欄の代わりに「ログインしてコメントする」を出す。詳細からログインすると、同じ商品に戻る（「未ログインでの閲覧」の決定事項）
  - 例外：マイページの既定の表示は `main` と違う。タブの指定なしで開いたら「出品した商品」を表示し、並べるのは自分の出品と自分の購入だけにする（`main` は、ほかの人の商品をすべて並べる）。タブの見た目、0 件のときの案内、検索欄、ロゴのリンク、全体の大きさも `main` と違う（「マイページと一覧の表示」の決定事項）
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
- 未ログインでは、いいねのアイコンを、ログインして同じ商品に戻るリンク（`/item/{item_id}/login`）にする。押した時点でログインが切れていた場合は、401 と 419 の両方で同じ URL へ移す（`data-login-url`）— ログアウトで CSRF トークンが作り直され、`auth` より先に CSRF の検証で止まるため
- CSRF トークンはレイアウトの `<meta name="csrf-token">` から取る（`/sanctum/csrf-cookie` は呼ばない）
- CSRF の検証そのものはテストで確かめられない（Laravel がテスト実行時に検証を省くため）。ブラウザか curl で確認する

## デモ（完了）

本番ではメールを送らない（`MAIL_MAILER=log`）。見に来た人は、デモ用アカウントでログインするか、架空のアドレスで会員登録して使う。

### 決定事項

- `DEMO_MODE`（`config('demo.enabled')`、既定は `false`）が、デモ環境の動きをまとめて切り替える：案内の表示、会員登録でのメール認証の省略、シードのユーザーのログイン拒否、登録と出品の上限、初期化。本番だけ `true`。`phpunit.xml` で `false` に固定（ローカルの `.env` に左右されないように）
- デモ用アカウントは 1 つ（ID 5、`demo@test.com`、メール認証済み、プロフィールと画像あり、商品なし）。アドレス・パスワード・プロフィールと画像の初期値は `config/demo.php`。`UsersTableSeeder` と `ProfilesTableSeeder` が作る
  - 画像は `database/seeders/images/profiles/demo.png`（256×256 の PNG。淡い緑の円に、縁取りのある再生ボタン）— ほかの 4 人（動物の顔）と違う「お試し用のアカウント」だとわかる絵にした。描き方は 4 枚とそろえてある。php コンテナの GD で、4 倍の大きさで描いて縮小した（描画のスクリプトは、リポジトリに入れていない。GD に FreeType がなく、文字は描けない）
- ログイン画面には「デモアカウントでログイン」のボタンを置く。`public/js/demo-login.js` がログインフォームに値を入れて送信する。サーバー側に専用のログインの入口は作らない（通常の `POST /login`）
- 会員登録：デモ環境では、登録と同時に `email_verified_at` を入れる（`CreateNewUser`）。認証メールは未認証のユーザーにだけ送られるので、作られなくなる。登録画面には「架空のアドレスと、普段使っていないパスワードで」「認証メールは送信しません。毎日 4:00 に削除」の案内を出す（`partials/demo_notice.blade.php`）。認証待ち画面には、デモ用の表示を出さない
- 「デモで登録した」印：デモ環境で登録したユーザーにだけ `users.registered_in_demo = true` を付ける（既定は `false`、`$fillable` に入れない）。初期化での削除、人数の上限、出品数の上限（5 件）の対象は、この印があるユーザーだけ（`App\Services\Demo\DemoLimits`）— メールアドレスからの推測で決めると、`DEMO_MODE` を誤って有効にしたときに本物の利用者が全員消えるため。印の付け忘れ、シーディングの失敗、設定の誤りがあっても、誰も消えない側に倒れる
- ログインの制限：デモ環境では、シードの 4 人（`config('demo.seeded_emails')`）のログインを拒否する — パスワードがリポジトリで公開されているため。デモ用アカウントと、登録したユーザーはログインできる。Fortify のログインの処理（`Fortify::authenticateThrough`）の中で、パスワードの照合より前に判定する（`RejectSeededAccountsInDemoMode`）。`seeded_emails` を使うのはここだけ。シーダーと一覧の一致はテストで確かめている
- 上限（デモ環境だけ。`config('demo.limits')`）：同じ IP アドレスからの登録は 1 時間に 5 回（登録できた回数だけ数える）、登録したユーザーは 100 人まで、出品は登録したユーザーが 5 件・デモ用アカウントが 20 件まで — メール認証という関門がなくなり、画像でディスク（1 GB）が埋まりうるため
- 利用者の IP アドレスは、`DEMO_CLIENT_IP_HEADER`（本番は `CF-Connecting-IP`）のヘッダーから取る。なければ `$request->ip()` — Render の手前に Cloudflare があり、`X-Forwarded-For` が「利用者, プロキシ」の形だと、`$request->ip()` はプロキシのアドレスになるため（`TrustProxies` は接続元だけを信頼する）。Render の公式ドキュメントでは確かめられず、ほかの開発者の報告による。ローカルでは設定しない（利用者がヘッダーを偽れるため）。既存のログインの回数制限は `$request->ip()` のまま
- 初期化は `php artisan demo:reset`。起動時（`entrypoint.sh`。シーディングより前）と、毎日 4:00（Asia/Tokyo）に実行する。`DEMO_MODE` が無効なら何もしない
  - デモ用アカウント：購入（`paid` / `expired` / `failed` と、期限を過ぎた `pending`）、いいね、コメント、出品（付いているいいね・コメント・購入・画像ごと）を消す。名前とプロフィールは初期値に戻す
  - デモ用アカウントの画像は、初期の画像（`config('demo.profile_image')`）に戻す。アップロードされた画像は消すが、初期の画像そのものは消さない。ディスクになければ、シーダーと同じ `seedImage()` でコピーし直す — 見に来た人が画像を差し替えると、プロフィールの更新が、初期の画像のファイルを「古い画像」として消すため
  - 起動時は、シーディングより前に実行する — シーダーが先に Demo の `profile_image` を初期値で上書きすると、`demo:reset` は消すべきファイルの名前がわからず、アップロードされた画像がディスクに残り続けるため（本番用のイメージで再現して確かめた）。DB が空の最初の起動では、Demo がまだいないので何も消さず、そのあとのシーディングが Demo と画像を作る
  - デモで登録したユーザー：同じものを消したうえで、ユーザーごと削除する（プロフィール、画像、セッションも）
  - 残すもの：期限内の `pending`（あとから支払いの通知が届くため）と、それが付いている出品、それを持つユーザー。片付いたあとの初期化で消える
  - 印のないユーザー（シードのユーザー、デモ環境でない時期に登録したユーザー、以前の認証待ちで止まったユーザー）には触らない。Stripe は呼ばない。トランザクションは使わない
  - 出力は件数だけ（`Demo data was reset: {...,"users":n}`）。メールアドレスは出さない
- スケジュールは `$schedule->call()` で登録する — `$schedule->command()` は別プロセスで実行して出力を `/dev/null` に捨てるので、実行結果がログに残らないため。結果の 1 行は標準出力に出し、`LOG_LEVEL` に関係なく Render のログで確かめられる
- スケジューラーは `docker/render/scheduler.sh`（supervisord から www-data で起動。1 分ごとに `schedule:run`）— `schedule:work` は毎分「No scheduled commands are ready to run.」を出してログを埋めるため。その 1 行だけを落とし、ほかの出力とエラーは通す。`schedule:run -q` は、中で呼ぶコマンドの出力まで消えるので使わない
- スケジューラーには `HOME` と `USER` も指定する（`supervisord.conf` の `environment=HOME="/var/www",USER="www-data"`）— supervisord の `user=` は uid を変えるだけで、`HOME` は root の `/root` のまま引き継がれる。SSL で DB につなぐとき、libpq は `$HOME/.postgresql/postgresql.crt` を探し、www-data は `/root` を読めないので、接続に失敗する（`could not open certificate file ... Permission denied`）。2026-10-07 の 4:00 の初期化が、これで失敗した
  - 起動時の初期化は root で動くので通る。Web も動く — php-fpm は、ワーカーの `HOME` を実行ユーザーのホーム（`/var/www`）に設定し直すため（環境変数は引き継いでいる。ベースイメージの `docker.conf` が `clear_env = no`）
  - ジョブが動くと、ログに `[日時] Running scheduled command: demo:reset` と `Demo data was reset: {...}` の 2 行が出る。初期化の中で例外が起きると、1 行目のあとにエラーのログが出る。supervisord 自身の行は UTC（コンテナの OS は UTC。アプリと PHP は Asia/Tokyo）
  - `supervisorctl` は使えない（設定に制御用のソケットがない）。プロセスは `/proc` で見る
  - 確認済み（本番、2026-10-08）：朝 4:00（日本時間）の初期化が、Render のログで確認できた。スケジューラーの `HOME` の修正が、本番で効いている（2026-10-07 は、DB への接続で失敗していた）
  - 参考（使わずに済んだ）：4:00 を待たずに確かめる（Render のシェルで。`runuser` が使えるかは未確認）：スケジューラーの環境は `runuser -u www-data -- sh -c 'for d in /proc/[0-9]*; do grep -q scheduler.sh $d/cmdline 2>/dev/null && tr "\0" "\n" < $d/environ | grep -E "^(HOME|USER)="; done'`。ジョブの実行は `cd /var/www && runuser -u www-data -- env HOME=/var/www php artisan schedule:test`（実際に初期化される）
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
- `entrypoint.sh`：`APP_KEY` が空なら止める。マイグレーションの失敗は止める。`demo:reset` とシーディング（この順）の失敗は警告だけで続ける。`chown` は `storage` と `bootstrap/cache` の全体に、キャッシュ生成のあとで
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

## 未ログインでの閲覧（完了）

### 決定事項

- 未ログインで見られるのは、商品の一覧（`/frea`）と商品の詳細（`/item/{item_id}`）。`/`（一覧、マイリスト）と検索はログインが必要 — `main` からこの形で、`/frea` へのリンクは画面のどこにもない。README の「デモ」で、ログインせずに見る入り口として案内している
  - `/` と `/frea` は同じ `ItemController::index`。未ログインの `/frea` はすべての商品を返し、「おすすめ／マイリスト」のタブは出ない（`?tab=mylist` は 0 件）
  - COACHTECH の要件書は、リポジトリにも `reports/` にもない。未ログインの一覧を要件書がどう書いているかは、確かめていない
- 未ログインの詳細：いいねは件数と灰色のアイコン、コメントは件数・見出し「コメント(n)」・すべてのコメントを表示する。入力欄と送信ボタンの代わりに「ログインしてコメントする」を出す
- コメントは 1 件ごとに、書いた人の名前と画像を付ける（`Comment::user()`、`User::profile()`、`User::profile_image_url`）— 以前は最新の 1 件だけで、その上に、見ている本人の名前と画像が出ていた（`main` も同じ不具合）。プロフィールの画像がなければ既定のアイコン
- コメントはすべて表示する。新しい順（`created_at` の降順、同じ時刻なら `id` の降順 — `created_at` は秒単位で、同じ秒の 2 件はどちらが上か決まらなかったため）
  - 枠（`detail-form_comment_list`）の高さは、上限だけ決める（`max-height: 40vh`）。超えたら枠の中だけスクロールし、画面全体は伸びない。少ないときは、コメントの分だけの高さになる。0 件のときは枠を出さず、「コメントはまだありません。」
  - 本文は、入力した改行を CSS で残す（`white-space: pre-wrap`。HTML に `<br>` は差し込まない）。長い単語や URL も折り返す（`overflow-wrap: anywhere`）。ビューでは、本文のタグの中に余分な空白や改行を入れない（そのまま表示されるため）
  - 取得に上限はない（投稿者とプロフィールは `with('user.profile')` でまとめて読み込む）— 上限を付けると、超えたときの表示と、その先を読む手段が別に要るため。デモ環境では、登録は 100 人までで、コメントは毎日消える
  - 投稿者の画像は、固定の 28.8px（枠の中に何件も並ぶため。正方形でない画像は `object-fit: cover` で切り抜く）
- 詳細からログインしたあとは、同じ商品に戻す。入り口は `GET /item/{item_id}/login`（`auth` の中。詳細へリダイレクトするだけ）— 未ログインで開くと `auth` が URL を覚えて `/login` へ送り、ログイン後にここへ戻す。Laravel の標準の仕組みをそのまま使い、`url.intended` を自分では書かない。「購入手続きへ」が購入画面に戻るのと同じ仕組み
  - 使うのは、いいねのアイコン、「ログインしてコメントする」、詳細画面のヘッダーの「ログイン」、`like.js` の 401 / 419
  - ログイン画面から会員登録に進んだ場合は、元の商品には戻らない（登録後は認証待ちかプロフィールの登録へ送られ、そのあとは `/`）
- 未ログインのヘッダーに「ログイン」を出す（`layouts/header.blade.php`）。リンク先は `@yield('login_url', '/login')` で、詳細画面だけ `/item/{item_id}/login` に差し替える。ログイン画面には出さない — `/frea` を入り口として案内すると、ログインへ進む導線がなかったため
- テストは `ItemDetailCommentTest`、`ReturnToItemAfterLoginTest`、`GuestHeaderLoginLinkTest`。`CommentFunctionTest::testCantPostCommentBeforeLogin` は、コメントアウトを外して生かした（項目名を `comment` に、`item_id` も送る）。同じファイルの `testCantPostCommentAfterLogin` と、`MylistFunctionTest::testCheckNotAuthorizedUser` は、コメントアウトのまま
- 確認済み（本番、ブラウザ）：未ログインの詳細の「ログインしてコメントする」の見た目、未ログインのヘッダーの「ログイン」の位置、ログイン中に別のタブでログアウトしてからいいねを押すと、ログイン後に同じ商品へ戻ること（`like.js` の 401 / 419）

## マイページと一覧の表示（完了）

### 決定事項

- マイページに並べるのは、自分が出品した商品と、自分が購入した商品だけ。タブの指定なしで開いたら「出品した商品」（`?tab=buy` 以外は、知らない値も「出品した商品」）— 以前は、タブの指定なしで、ほかの人の商品がすべて並んでいた。課題のときの勘違いで、一般的なフリマアプリの形に直した
  - ほかの人の商品を並べていた `UserController::getProfile` は削除した。ヘッダーの「マイページ」と、プロフィール更新後の戻り先は、タブの指定なしの `/mypage`
  - `MyPageFunctionTest`（⑬）は、タブの指定なしでほかの人の商品の画像が出ることを確かめていた 1 行だけを、プロフィール画像の確認に直した。同じテストの「出品した商品」の確認は、`Item::where('user_id', $item->id)` と商品の ID を渡していて何も確かめていない（触っていない。`MyPageTabsTest` で補っている）
- タブは、マイページ（出品した商品／購入した商品）とトップページ（おすすめ／マイリスト）で共通のクラス：`tab-nav`、`tab-nav_item`、`tab-nav_item--active`（`header.css`）。選ばれたタブには `aria-current="page"` も付ける
  - 見た目は、ブラウザのタブの形。上の左右の角は丸める（`border-radius`）。選ばれていないタブは薄い灰色で、下は横線にそのまま載る。選ばれたタブは白い背景に、下の横線と同じ太さ・同じ色の線で、上辺だけ赤で太い
  - 選ばれたタブの下の左右は「すそ」（下の横線へ向かって外へ広がる丸み。内側にえぐれた角）。枠線の丸みで描く：タブの箱を、すその高さの分だけ短くして（`margin-bottom`）、左右の線を箱の下端で止める。箱の下の左右に `::before` / `::after` の四角を置き、その「タブ側の縦の枠線」と「下の枠線」を `border-radius` でつなぐ。箱の下の白い帯（下の横線を消す部分）は、白い `box-shadow`（真下と、すその幅だけ左右にずらしたもの）。太さ・半径・色は `--tab-line` / `--tab-flare` / `--tab-edge` / `--tab-fill`。タブの間（`gap`）は、すそが隣に重ならないように、すその半径と同じ
  - すそは、はじめ色の塗り分け（`radial-gradient`）で描いたが、やめた — タブの縦の線と下の横線はブラウザの枠線で、画面の点に合わせて描かれるのに、塗り分けは輪郭がぼけて位置もずれるので、線がぎざぎざし、つなぎ目で太さが変わって見えたため。縦の線・丸み・横線をすべて枠線にすると、太さと濃さがそろう。4 つの描き方（今のまま、枠線の丸み、SVG、すそなし）を並べた `reports/tab-flare-preview.html` で見比べて決めた。トップページとマイページで、ブラウザの倍率 100%・125%・150% で確認済み
  - トップページは、`?tab=mylist` ならマイリスト、それ以外（検索結果の `/search` も）はおすすめが選ばれている。未ログインの `/frea` にはタブを出さない
- 0 件のときの案内（`header.css` の `item-list_empty`）：マイページは「出品した商品はありません。」「購入した商品はありません。」。トップページは、検索の文字があれば「該当する商品はありません。」（おすすめでもマイリストでも）、なければマイリストだけ「いいねした商品はありません。」。未ログインの一覧には出さない
- 検索欄の案内「なにをお探しですか？」は `placeholder`。検索した文字は、検索後も欄に残す（`value="{{ $keyword ?? '' }}"`。コントローラがもともとビューに渡している `keyword` を使う）— `value` に入れていたので、空のまま検索すると、その文字で検索されて 0 件になっていた。空のまま検索すると、すべての商品が並ぶ
- ヘッダーのロゴは、商品一覧へのリンク。ログイン中は `/`、未ログインは `/frea`（`/` はログイン画面に送られるため）。`alt` は「Flea Market」— マイページにほかの人の商品を並べなくなり、商品一覧へ戻る導線がなくなったため。大きさと位置（幅 22%、左の余白 2%）は、リンクの側（`frea-market_header_logo_link`）で決める
- 全体の大きさ：元の数字の 0.9 倍 — 全体に大きすぎて、ブラウザの表示倍率を下げて見ていたため。配置は `%` と `vh` で組まれていて、倍率を下げても変わるのは `px`（ほぼ文字の大きさ）だけなので、`px` の数字を縮めた
  - `html { font-size: 90%; }`（`header.css`）と、CSS とビューの中の `px` を 0.9 倍。枠線（1px、2px、タブの赤い上辺 4px）と、枠線に合わせた重なり（`-2px` の 2 か所）はそのまま。小数は丸めていない
  - 認証待ちの画面（`auth/verify-email`）は `header.css` を読み込まない独立したページなので、その `<style>` にも同じ指定がある。プロフィールの登録・変更と出品のビューは、ファイルの中の `<style>` に `px` を持つ
  - プロフィール画像の幅：マイページ 12.2%、プロフィールの登録・変更 13.6%（元の約 0.68 倍）。商品詳細の投稿者の画像は、コメントをすべて表示するようにしたときに、固定の 28.8px に変えた。商品詳細の画像の枠は 68%（元の 0.85 倍）。購入画面の画像は変えていない
  - 一覧の商品画像は、トップページとマイページで同じ並び：左右の余白 8%（タブの並びと同じ）、4 列で、幅 22.3% × 4 + すき間 3.5% × 3（合計が 100% を超えると 3 列に折り返す）。行の間は `row-gap: 2vw`（`%` にすると、高さの決まっていない入れ物では効かないことがある）
- テストは `MyPageTabsTest`、`SelectedTabTest`、`EmptyListMessageTest`、`SearchFieldTest`、`HeaderLogoLinkTest`。見た目（色、大きさ、並び）は、テストでは確かめられない

## ロゴとアプリ名（完了）

### 決定事項

- ヘッダーのロゴは、自分で作ったもの（`public/images/logo.svg`）— 公開するポートフォリオで、COACHTECH のロゴを使わないため。左に値札のアイコン、右に「Flea Market」の文字。白一色で、背景は透明
  - 大きさと縦横比は、前のロゴと同じ `viewBox="0 0 300 32"` — 差し替えても、ヘッダーの配置が変わらないように。中身は左寄せで、幅は約 264（前のロゴは 300 いっぱい）
  - 文字は Poppins ExtraBold Italic（SIL Open Font License 1.1。Copyright 2020 The Poppins Project Authors）を、パスに変換したもの — フォントが入っていない環境でも、同じ形に見えるように。フォントのファイルは、リポジトリに入れていない。`google/fonts` の `ofl/poppins/` から取得した
  - 斜体で、いちばん太い ExtraBold にした（立体の SemiBold、斜体の SemiBold / Bold と見比べて決めた）。値札は傾けていない（文字の斜体に合わせて傾けた案も作ったが、選ばなかった）
  - 値札は、角を丸めた五角形に穴を 1 つ開け、38 度回したもの。文字の高さは 27、値札の大きさは 30、間隔は 9
  - 作り方：Python だけで TTF を読み（`glyf` の輪郭、`hmtx` の送り幅、`cmap`）、SVG のパスにした。確認用の画像も、自前の塗りつぶしで描いた（ホストに、フォントや画像のツールがなかったため）。スクリプトは、リポジトリに入れていない。案と確認用のページは `reports/logo-proposals/`（Git の管理外）
  - 前のロゴは、Git の履歴には残っている
- ファビコンは、黒い角丸の四角に、ロゴと同じ形の白い値札（`public/favicon.svg` と、16・32・48 を入れた `public/favicon.ico`）— 以前の `favicon.ico` は 0 バイトで、タブに何も出ていなかった。`<link rel="icon">` は、共通のレイアウトと、認証待ちの画面（独立したページ）の両方にある
- アプリ名の表記は「Flea Market」：ロゴの `alt`、`<title>`（認証待ちの画面は「メール認証 | Flea Market」）、`render.yaml` と `.env.example` の `APP_NAME`、`config/app.php` の既定値、README の見出し
  - そのままにしたもの：リポジトリ名と URL（`frea-market`、`/frea`。綴りが `frea` で、変えると公開 URL が変わる）、CSS のクラス名（`frea-market_header` など）、ローカルの `src/.env`（Git の管理外。`APP_NAME` が `Laravel` のままだと、ローカルの認証メールの見出しは Laravel のロゴになる）
  - `render.yaml` の `APP_NAME` は、Blueprint の同期で本番に入る。空白を含む値でも、設定のキャッシュを通って崩れないことを、本番用のイメージで確かめた
- テストは `HeaderLogoLinkTest`（ロゴの大きさと、文字がパスであること）、`FaviconTest`、`AppNameTest`。`render.yaml` は、テストからは見えない（テストは `src/` の中で動く）

## 今後の予定

- 次：見た目の最終調整（文字と画像の大きさ、タブ、0 件の案内、検索欄の薄い文字の色など）。今の大きさは「とりあえず」で、あとでまた調整する
  - 候補：カテゴリーの文字が小さい（10.8px）。商品詳細のカテゴリーの枠だけは直した（`detail-form_Item_category`：中の余白を固定の大きさに＝上下 4.5px・左右 10.8px、外の左右の余白 5.4px、線は出品の画面の選択肢と同じ 2px、角の丸み 22.5px、幅は文字に合わせる）— 中の余白がなく、縦が短くて窮屈に見えたため。文字の大きさは変えていない。出品の画面の選択肢（ビューの中の `<style>` の `toggle_button_category`）は変えていない。共通のクラスにはしていない
  - 済み（商品詳細）：いいね・コメントのアイコンを、固定の 26px にした（`like-icon`、`detail-form_engagement_image`）— 以前は画面の幅の 2.8%（1280px で約 36px、1920px で約 54px）で、広い画面ほどアイコンだけが大きくなり、画面全体とのつり合いが悪かった。まわりの文字（価格、ボタン）と同じく、画面の幅で変わらない大きさにした。並べ方は、左寄せ、いいねとコメントの間 28px、アイコンと件数の間 3px。件数が 4 桁以上になると、コメントのアイコンが少し右へ動く（1 つ分の幅を固定していないため）
  - 済み（商品詳細）：コメントの入力欄の書体と大きさを、まわりの文字と同じにした（`font: inherit`）— 指定がないと、ブラウザの既定の等幅の書体（約 13.3px）になり、投稿後の表示と見た目が違ったため
  - 済み（商品詳細）：赤いボタンの文字の大きさを 18px にそろえた（「購入手続きへ」に、「コメントを送信する」と「ログインしてコメントする」を合わせた。以前は 15.3px）
  - 済み（商品詳細）：使われていなかった `detail-form_item_price_wrapper` の指定を消し、ビューで改行されて 2 つに分かれていたクラス名を、実際に効いていた `detail-form_item_price` だけにした
  - 候補（商品詳細）：「商品へのコメント」（18px）が、見出し（21.6px）と本文（14.4px）の中間の大きさで、入力欄のラベルなのに本文より目立つ
  - 候補（商品詳細）：ブランド名が、本文と同じ大きさ・同じ色で、説明文と区別がつかない
  - 見比べ用のページ：`reports/icon-size-preview.html`（実際の CSS を `http://localhost` から読み込み、アイコンの大きさだけ変えて並べたもの。Git の管理外）— 見た目は、こちらでは確かめられないので、案を並べたページを作って、ブラウザで選んでもらう形が早い
- 次：README にスクリーンショットを 4 枚入れる（`docs/images/` の `items.png`、`item-detail.png`、`purchase.png`、`mypage.png`。README にコメントで場所を確保してある。見た目の最終調整のあと、デモの初期化の直後に撮る）
- 次：デプロイ時のログの「CRIT unknown problem killing scheduler: PermissionError」の調査と修正（supervisord がスケジューラーのプロセスを止めるときのエラー。原因は未調査）
- 次：本番での確認
  - 登録の回数制限が利用者ごとに数えられているか — 同じ回線から 6 回目が弾かれたあと、別の回線（スマートフォンの回線など）から登録できること。別の回線でも弾かれるなら、`CF-Connecting-IP` が届いていない
  - Neon の使用量を公開から 1 週間見る（目安は 1 日あたり約 3.3 CU 時間まで）。Neon がアクセスのない間に眠ること。多ければ、セッションをファイルにして永続ディスクに置く — 今のマウント先（`storage/app/public`）は外から見えるので、マウント先を `storage/app` に変えて、公開しないフォルダに置く必要がある
  - 再デプロイ後に、アップロードした画像とログインが残ること
  - Render のダッシュボードで、`APP_NAME` が「Flea Market」になっていること（Blueprint の同期）。ブラウザのタブに、ファビコンとタイトル「Flea Market」が出ること
  - 永続ディスクに、どこからも参照されていない画像が残っていないか（Render のシェルで `ls /var/www/storage/app/public/profiles`）— 起動時の順番を直す前のデプロイで、Demo がアップロードした画像が残った可能性がある。あれば手で消す
  - 本番でのコンビニ払い（`basil` で届く `async_payment_succeeded` など）
  - Markdown だけの push で、Render がデプロイしないこと
- 最後に `portfolio` を `main` にマージする（プルリクエスト経由）— GitHub の既定のブランチは `main` で、書き直した README は `portfolio` にあるため。それまで README のクローン手順は `-b portfolio` のまま。マージしたら手順から `-b portfolio` を外す
- メールを実際に送る場合：GHSA-5vg9-5847-vvmq を再確認する（今は `MAIL_MAILER=log` で外部に送っていない前提で残している）。`docs/dependency-advisories.md` と `composer.json` の理由も合わせて直す。`DEMO_MODE` を `false` にすると、メール認証が戻り、デモ用の案内・制限・初期化がすべて止まる。印のあるユーザーは残るので、手で消す
- 仕上げ：`ProfileRequest` と `ProfileFirstRequest` の郵便番号の正規表現の `$` を `\z` にする（`$` は末尾の改行を通す。`RedirectRequest` は修正済み）— `TrimStrings` が先に改行を取り除くので、実害はない
- Laravel のメジャーアップグレード（未定）：残る 5 件の勧告と、放棄されたパッケージ 2 つが解消する
- 検討：4:00 の 1 分を逃すと、翌日まで初期化されない（その時刻の再起動や、一時的な接続の失敗など）。今は、次のデプロイか翌日の 4:00 で片付く。直すなら、時刻を変えてもう一度実行する（初期化は何度実行してもよい）か、前回の実行時刻を持って遅れを取り戻す
- 確認待ち：`/search` の要ログインが仕様どおりか（今は触らない）
- 確認待ち：商品一覧（`index`）で自分の出品を除外するか（今は触らない。`IndexFunctionTest::testWithoutMyExhibition` は skip）
- 既知の点：画像の保存後に DB 登録が失敗するとファイルが残る
- 既知の点：商品一覧・マイページの取得に並び順の指定がなく、表示順が変わりうる（テストは並び順に依存させない）
- 既知の点（いいねの調査で見つけた、未対応）：存在しない商品 ID の詳細画面が 500、レイアウトの `<meta name="csrf-token">` が 2 つで `<body>` が入れ子、`LikeFactory.php` のクラス名が `likeFactory`
- 既知の点：コメントの投稿に回数の制限がなく、商品詳細のコメントの取得にも上限がない。同じ商品に大量に書かれると、その商品のページが重くなる

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
- デモ：デモ用アカウントとログイン画面のボタン、会員登録の開放（メール認証の省略、「デモで登録した」印、登録と出品の上限）、シードのユーザーのログイン拒否、毎日 4:00 とデプロイ時の初期化（`demo:reset`）、スケジューラーの `HOME` の修正（4:00 の初期化が DB につなげなかった）
- 配送先：購入画面で変更した住所を、プロフィールではなくセッションに商品ごとに持つ（その購入にだけ使う）、変更画面の入力の検証、入力エラー時に入力した値を残す
- 未ログインでの閲覧：最新のコメントの投稿者の表示の修正、未ログインの詳細でのコメントの表示と「ログインしてコメントする」、詳細からのログイン後に同じ商品へ戻す入り口（`/item/{item_id}/login`）、未ログインのヘッダーの「ログイン」、README での `/frea` の案内
- デモ用アカウントの画像：画像の追加（シーダーと `demo:reset` で初期の画像に戻す）、起動時の順番を `demo:reset` → シーディングに
- テストの並び順：並び順なしの `first()` / `all()` に `orderBy('id')` を足した（8 ファイル、62 か所。コメントアウトされているコードの中も）— テストを足すとテーブルの中の並びが変わり、`MyProfileDisplayedTest` が毎回落ちるようになったため。`where(...)->first()` の形は、1 件に絞っているので触っていない。テストで `first()` / `all()` を使うときは、並び順を指定する
- マイページと一覧の表示：マイページの既定のタブ（出品した商品）、共通のタブの見た目、0 件のときの案内、検索欄の `placeholder`、ロゴのリンクと `alt`、全体の大きさを 0.9 倍に
- ロゴとアプリ名：ヘッダーのロゴを自作のものに差し替え（値札と「Flea Market」。Poppins ExtraBold Italic をパスに変換）、ファビコンの追加、アプリ名を「Flea Market」にそろえた
- タブの角：上の角を丸め、選ばれたタブの下をすそが広がる形に
- 商品詳細のコメント：最新の 1 件から、すべてのコメントを枠の中でスクロールして読める形に（新しい順、1 件ごとに投稿者、改行を残す、0 件の案内）
- デプロイ：Render（有料）と Neon（無料）に公開。起動スクリプトの修正、`sessions` テーブル、`/healthz` と `robots.txt`、`DB_SSLMODE`、php-fpm の待ち受けアドレス、README の全面的な書き直し（アプリそのものの説明として。図は Mermaid）、DB のエラーの記録から値を外す、ビルドフィルター
