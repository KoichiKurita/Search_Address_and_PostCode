#!/bin/sh
# サーバー上で実行される「直前のリリースへ戻す」スクリプト（POSIX sh / FreeBSD 対応）
# 使い方: rollback.sh <APP_DIR>
set -eu

APP_DIR="$1"

[ -f "$APP_DIR/previous_release" ] || { echo "戻し先の記録がありません（初回デプロイ）" >&2; exit 1; }
PREV="$(cat "$APP_DIR/previous_release")"
[ -d "$PREV" ] || { echo "戻し先が見つかりません: $PREV" >&2; exit 1; }

ln -sfn "$PREV" "$APP_DIR/current.tmp"
perl -e 'rename($ARGV[0], $ARGV[1]) or die "rename failed: $!\n"' "$APP_DIR/current.tmp" "$APP_DIR/current"
echo "ロールバックしました: $PREV"
