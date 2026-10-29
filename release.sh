#!/bin/sh

set -e

FEATURE_BRANCH="$1"

if [ -z "$FEATURE_BRANCH" ]; then
    echo "Usage: release.sh <feature-branch>"
    exit 1
fi

echo "=== staging を更新 ==="
git checkout staging
git pull origin staging

echo "=== $FEATURE_BRANCH を staging にマージ ==="
git merge "$FEATURE_BRANCH"
git push origin staging

echo "=== master を更新 ==="
git checkout master
git pull origin master

echo "=== staging を master にマージ ==="
git merge staging
git push origin master

echo "=== Release completed ==="
