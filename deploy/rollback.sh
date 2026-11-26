#!/usr/bin/env bash
# サーバー上で実行される「直前のリリースへ戻す」スクリプト
# 使い方: rollback.sh <APP_DIR> [RELOAD_SERVICE]
set -euo pipefail

APP_DIR="$1"
RELOAD_SERVICE="${2:-}"

[ -f "$APP_DIR/previous_release" ] || { echo "戻し先の記録がありません（初回デプロイ）" >&2; exit 1; }
PREV="$(cat "$APP_DIR/previous_release")"
[ -d "$PREV" ] || { echo "戻し先が見つかりません: $PREV" >&2; exit 1; }

ln -sfn "$PREV" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$APP_DIR/current"
echo "ロールバックしました: $PREV"

if [ -n "$RELOAD_SERVICE" ]; then
    sudo -n /usr/bin/systemctl reload "$RELOAD_SERVICE"
fi
