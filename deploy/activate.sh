#!/usr/bin/env bash
# サーバー上で実行される「リリース切り替え」スクリプト
# 使い方: activate.sh <APP_DIR> <RELEASE> [RELOAD_SERVICE]
#
# ディレクトリ構成:
#   <APP_DIR>/releases/<コミットID>/  … デプロイされたファイル一式
#   <APP_DIR>/shared/                 … config.php / config.js（Git 管理外の設定）
#   <APP_DIR>/current -> releases/xxx … Webサーバーはここを公開する
set -euo pipefail

APP_DIR="$1"
RELEASE="$2"
RELOAD_SERVICE="${3:-}"
KEEP=5                                   # 残しておくリリース数

REL_DIR="$APP_DIR/releases/$RELEASE"
[ -d "$REL_DIR" ] || { echo "リリースが見つかりません: $REL_DIR" >&2; exit 1; }

# 1. Git 管理外の設定ファイルを shared からリンクする
for f in config.php js/config.js; do
    src="$APP_DIR/shared/$(basename "$f")"
    [ -f "$src" ] || { echo "設定ファイルがありません: $src" >&2; exit 1; }
    ln -sfn "$src" "$REL_DIR/$f"
done

# 2. ロールバック用に、今のリリースを記録しておく
if [ -L "$APP_DIR/current" ]; then
    readlink "$APP_DIR/current" > "$APP_DIR/previous_release"
fi

# 3. シンボリックリンクを「作ってから mv で差し替える」ことで、切り替えを一瞬（アトミック）にする
ln -sfn "$REL_DIR" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$APP_DIR/current"
echo "公開中: $REL_DIR"

# 4. PHP の OPcache が古いファイルを使い続けないよう、PHP-FPM（または Apache）をリロード
if [ -n "$RELOAD_SERVICE" ]; then
    sudo -n /usr/bin/systemctl reload "$RELOAD_SERVICE"
fi

# 5. 古いリリースを削除（新しい順に KEEP 件だけ残す。公開中のものは消さない）
cd "$APP_DIR/releases"
current_name="$(basename "$(readlink "$APP_DIR/current")")"
ls -1t | tail -n +$((KEEP + 1)) | while read -r old; do
    [ "$old" = "$current_name" ] && continue
    rm -rf -- "$old"
    echo "削除: $old"
done
