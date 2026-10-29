#!/bin/sh
#
# 本番（EC2）デプロイ
#
#   ./deploy.sh            origin/master の最新をデプロイ
#   ./deploy.sh <commit>   指定したコミット・タグをデプロイ（切り戻し用）
#
# ビルド中は旧コンテナが動き続ける。止まるのは入れ替えの数秒のみ。

set -eu

cd "$(dirname "$0")"

REF="${1:-origin/master}"
COMPOSE="${DOCKER_COMPOSE:-docker-compose} -f docker-compose.prod.yml"
HEALTH_TIMEOUT=60

echo "=== 事前チェック ==="
if [ ! -f .env ]; then
    echo ".env がありません。" >&2
    exit 1
fi
if ! grep -q '^APP_KEY=..*' .env; then
    echo ".env の APP_KEY が空です。" >&2
    exit 1
fi
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "サーバー上で変更されたファイルがあります。確認してから実行してください。" >&2
    git status --short --untracked-files=no >&2
    exit 1
fi

echo "=== ソースを取得（$REF） ==="
PREVIOUS="$(git rev-parse --short HEAD)"
git fetch --prune origin
git checkout --quiet --detach "$REF"
CURRENT="$(git rev-parse --short HEAD)"
echo "$PREVIOUS -> $CURRENT  $(git log -1 --format=%s)"

echo "=== ビルド（旧コンテナは稼働中） ==="
$COMPOSE build app

echo "=== コンテナを入れ替え ==="
mkdir -p storage/logs storage/framework/sessions storage/framework/cache
$COMPOSE up -d app

echo "=== 起動を確認 ==="
PORT="$($COMPOSE port app 80 | sed 's/.*://')"
elapsed=0
until curl -fsS -o /dev/null "http://127.0.0.1:$PORT/up"; do
    elapsed=$((elapsed + 2))
    if [ "$elapsed" -ge "$HEALTH_TIMEOUT" ]; then
        echo "起動を確認できませんでした。直近のログ:" >&2
        $COMPOSE logs --tail=50 app >&2
        echo "切り戻す場合: ./deploy.sh $PREVIOUS" >&2
        exit 1
    fi
    sleep 2
done

echo "=== キャッシュを作成 ==="
$COMPOSE exec -T -u www-data app php artisan config:cache
$COMPOSE exec -T -u www-data app php artisan route:cache
$COMPOSE exec -T -u www-data app php artisan view:cache

echo "=== 不要なイメージを削除 ==="
docker image prune -f

echo "=== デプロイ完了（$CURRENT） ==="
echo "切り戻す場合: ./deploy.sh $PREVIOUS"
