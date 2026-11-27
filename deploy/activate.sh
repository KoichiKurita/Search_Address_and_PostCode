#!/bin/sh
# サーバー上で実行される「リリース切り替え」スクリプト（さくらのレンタルサーバ / FreeBSD 対応）
# bash や GNU 独自のオプションを使わず、POSIX sh の範囲で書いている。
# 使い方: activate.sh <APP_DIR> <RELEASE>
#
# ディレクトリ構成:
#   <APP_DIR>/releases/<コミットID>/  … デプロイされたファイル一式
#   <APP_DIR>/shared/                 … config.php（Git 管理外の設定）
#   <APP_DIR>/current -> releases/xxx … ~/www/<公開名> からcurrent/public を参照する
#   APP_DIR は公開ディレクトリ（~/www）の外に置くので、設定ファイルは Web から直接見えない
set -eu

APP_DIR="$1"
RELEASE="$2"
KEEP=5                                   # 残しておくリリース数

REL_DIR="$APP_DIR/releases/$RELEASE"
[ -d "$REL_DIR" ] || { echo "リリースが見つかりません: $REL_DIR" >&2; exit 1; }

# 1. Git 管理外の設定ファイルを shared からリンクする（リリースのルート＝public/ の外に置く）
src="$APP_DIR/shared/config.php"
[ -f "$src" ] || { echo "設定ファイルがありません: $src" >&2; exit 1; }
ln -sfn "$src" "$REL_DIR/config.php"

# 2. ロールバック用に、今のリリースを記録しておく
if [ -L "$APP_DIR/current" ]; then
    readlink "$APP_DIR/current" > "$APP_DIR/previous_release"
fi

# 3. 新しいリンクを作ってから rename で差し替える（切り替えが一瞬で終わる＝アトミック）
#    FreeBSD の mv には GNU の -T がないため、rename(2) を直接呼べる perl を使う
ln -sfn "$REL_DIR" "$APP_DIR/current.tmp"
perl -e 'rename($ARGV[0], $ARGV[1]) or die "rename failed: $!\n"' "$APP_DIR/current.tmp" "$APP_DIR/current"
echo "公開中: $REL_DIR"

# 4. 古いリリースを削除（新しい順に KEEP 件だけ残す。公開中のものは消さない）
current_name="$(basename "$(readlink "$APP_DIR/current")")"
cd "$APP_DIR/releases"
ls -1t | tail -n +$((KEEP + 1)) | while read -r old; do
    [ "$old" = "$current_name" ] && continue
    rm -rf -- "$old"
    echo "削除: $old"
done
