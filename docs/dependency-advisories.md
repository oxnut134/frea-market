# 依存パッケージの脆弱性

このアプリは Laravel 8 を使っています。`composer audit` が報告する勧告のうち、修正版が Laravel 9 以上にしかない 5 件は、
Laravel 8 のままでは解消できません。このアプリで影響するかを 1 件ずつ確認したうえで、残しています。
それ以外の勧告は、パッケージを更新して解消してあります。

確認コマンド：`docker compose exec php composer audit`
（残した 5 件は「ignored」として、理由とともに表示されます。設定は `src/composer.json` の `config.policy.advisories.ignore-id`）

## 残している勧告

| 勧告 | 重大度 | 修正版 | このアプリでの影響 |
|---|---|---|---|
| [GHSA-5vg9-5847-vvmq](https://github.com/advisories/GHSA-5vg9-5847-vvmq) email ルールの CRLF インジェクション | high | Laravel 12.60 | 条件付きであり（下記） |
| [GHSA-crmm-hgp2-wgrp](https://github.com/advisories/GHSA-crmm-hgp2-wgrp) 一時署名 URL のパス混同 | medium | Laravel 12.61.1 | なし。ローカルディスクの一時 URL（`Storage::temporaryUrl`）を使っておらず、Laravel 8 にこの機能がない |
| [GHSA-78fx-h6xr-vch4](https://github.com/advisories/GHSA-78fx-h6xr-vch4) ファイル検証の回避（CVE-2025-27515） | medium | Laravel 10.48.29 | なし。ワイルドカード（`files.*`）での検証を使っておらず、画像は単一フィールドで検証している |
| [GHSA-jh5r-qr3c-85q8](https://github.com/advisories/GHSA-jh5r-qr3c-85q8) デバッグ画面の XSS（CVE-2026-102279） | low | Laravel 12.69 | なし。`APP_DEBUG=true` のときだけ成立し、本番は `false` |
| [GHSA-cxf4-7mrp-vvpr](https://github.com/advisories/GHSA-cxf4-7mrp-vvpr) flysystem のパス正規化（CVE-2026-102601） | low | flysystem 3.35.3（Laravel 9 以上） | なし。保存先は固定ディレクトリとランダムなファイル名で、利用者はパスを指定できない |

## 残る high（email ルールの CRLF インジェクション）について

会員登録では、入力されたメールアドレスに認証メールを送るため、勧告の前提に当てはまります。
Laravel 8 の `email` ルールは、引用符の中の改行など、一部の制御文字を含むアドレスを通します。

緩和策として、登録時にメールアドレスの制御文字（改行、タブ、NUL など）を弾く検証を足しています
（`app/Actions/Fortify/CreateNewUser.php`、テストは `tests/Feature/RegisterEmailControlCharactersTest.php`）。

これは緩和策で、修正ではありません。

- 勧告は具体的な入力を公開していないため、この検証で塞げるとは断定できません
- 勧告は Symfony Mailer との組み合わせを条件にしています。Laravel 8 のメール送信は SwiftMailer で、同じ経路が成立するかは確認できていません
- 本番は `MAIL_MAILER=log` で、メールを SMTP に送っていません

**本番で SMTP に切り替えるときは、この勧告を再確認してください。** 実際にメールが外部へ送られるようになり、上の前提が変わります。

## 放棄されたパッケージ

`composer audit` は、次の 2 つを「放棄されたパッケージ」として報告し、終了コードが 1 になります。
どちらも Laravel 9 以上で不要になるもので、Laravel 8 では置き換えられないため、設定は変えていません。

- `fruitcake/laravel-cors`（Laravel 9 でフレームワークに統合）
- `swiftmailer/swiftmailer`（Laravel 9 で Symfony Mailer に置き換え）

## 依存を更新するときの注意

`composer update` は、勧告のある版を候補から外します。上の 5 件を `ignore-id` から外すと、Laravel 8 が候補に残らず、更新できなくなります。
新しい勧告が出たときは、影響を確認してから、理由を添えて `ignore-id` に足してください。
