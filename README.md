# quolim-ec

ささいずみの Web 予約・EC サイト（Laravel 13 / PHP 8.5）。

| パス | 機能 | 状態 |
| --- | --- | --- |
| `/seasonal/{campaign}` | 季節商品予約（クリスマス・おせち等） | 公開対象 |
| `/shop` | EC（quolim-shop から移植予定） | 未実装 |
| — | Booking / Takeout / Delivery | 未実装 |

予約は POS の予約台帳（`P_SLIP_TRN` ほか）へ直接登録する。アプリ独自のテーブルは持たない（セッション・キャッシュはファイル）。管理画面は既存の `erp/ERP_HTTPS` を使う。

## ローカル開発

```bash
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
```

http://localhost:8090 で表示される。

POS の DB（RDS `dev_erp`）へは SSH トンネル経由で接続する。ホスト側でトンネルを張り、`.env` の `POS_DB_USERNAME` / `POS_DB_PASSWORD` を設定する。

```bash
ssh -N -L 3307:<RDS エンドポイント>:3306 <踏み台>
```

受付期間外は商品一覧が「受付期間外」表示になる。期間前に画面を確認したい場合は `.env` で開始日時を上書きする。

```dotenv
SEASONAL_XMAS_2026_OPENS_AT="2026-10-01 00:00:00"
```

## テスト

```bash
docker compose exec app php artisan test
```

テストは `mysql-test` コンテナの `pos_test` に POS テーブルの DDL（`tests/Fixtures/pos_schema.sql`）を毎回流し込んで実行する。`dev_erp` には接続しない。

## エラー通知（LINE WORKS）

ERROR 以上のログ（POS 登録の失敗、想定外の例外）を LINE WORKS のチャネルに通知する。同じエラーは 5 分に 1 回まで。お客様の個人情報や SQL は送らない。

- `.env` の `LOG_STACK` に `lineworks` を含め、`LINEWORKS_*` に erp-quolim と同じ Bot の認証情報を設定する
- 通知先は `LINEWORKS_ERROR_CHANNEL_ID`（Bot がそのチャネルに参加している必要がある）
- 設定の確認：`php artisan lineworks:test-notification`

## キャンペーンの追加・変更

キャンペーンは `config/seasonal/{slug}.php` を 1 ファイル追加すると `/seasonal/{slug}` で公開される。期間が重なるキャンペーンも同時に公開できる。

- 商品・価格・受取店舗・日時枠・受付期間はこのファイルで管理する（登録される金額はここの価格）
- キャンペーンの説明文は `resources/views/seasonal/campaigns/{slug}/description.blade.php`
- 店舗ごとの POS 側設定（部屋 ID・グループ商品）は `config/pos.php`

本番で設定を変えたら `php artisan config:cache` を実行し直す。

## 本番デプロイ

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

### チェックリスト

- [ ] `.env` を本番用に作成した（リポジトリには含めない）
  - `APP_ENV=production` / `APP_DEBUG=false` / `APP_URL=https://...`
  - `APP_KEY` を生成した
  - `POS_DB_*` を本番 DB に向けた（接続ユーザーは対象テーブルへの SELECT / INSERT / UPDATE のみ）
  - `TRUSTED_PROXIES=*`（ALB で HTTPS を終端するため。EC2 には ALB からしか到達できないこと）
  - `SESSION_SECURE_COOKIE=true`
  - `LOG_LEVEL=info`
  - `LOG_STACK=daily,lineworks` と `LINEWORKS_*`（`php artisan lineworks:test-notification` で届くことを確認）
- [ ] HTTPS で配信されている（ALB のヘルスチェックは `/up`）
- [ ] 受付期間（`opens_at` / `closes_at`）が正しい
- [ ] 各店舗で 1 件テスト予約し、POS の予約台帳に表示されることを確認した（確認後に削除）
- [ ] `storage/logs` に個人情報が出ていないことを確認した
