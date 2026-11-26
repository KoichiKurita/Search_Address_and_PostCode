# 自動デプロイ（GitHub Actions → さくらのレンタルサーバ）

`master` ブランチに push（Pull Request のマージを含む）すると、GitHub Actions が次の順に処理します。

```
lint（php -l） → rsync でアップロード → シンボリックリンク切り替え → ヘルスチェック
                                                                  └ 失敗したら直前のリリースへ自動ロールバック
```

Pull Request の段階では lint だけが動き、デプロイはされません。
リポジトリの Variable `DEPLOY_ENABLED` が `true` のときだけデプロイします。止めたいときは `gh variable set DEPLOY_ENABLED --body false` を実行します（停止スイッチ）。

前提: さくらのレンタルサーバ **スタンダード以上**（SSH が使えるプラン）

## 仕組み

```
/home/<アカウント名>/
├── apps/postcode/                 ← 公開ディレクトリ（www）の外
│   ├── releases/
│   │   ├── 5bf648f.../            ← コミットごとに丸ごと配置（最新5件を保持）
│   │   └── a1b2c3d.../
│   ├── shared/
│   │   ├── config.php             ← DB接続情報（Git管理外・サーバーにだけ置く）
│   │   └── config.js
│   ├── previous_release           ← ロールバック先の記録
│   └── current -> releases/a1b2c3d...
└── www/
    └── postcode -> ../apps/postcode/current   ← https://<ドメイン>/postcode/
```

- **アトミックな切り替え**: アップロード中の中途半端な状態が公開されることはなく、`current` の付け替えは一瞬で終わる
- **すぐ戻せる**: 古いリリースが残っているので、リンクを戻すだけで元に戻る
- **秘密情報を公開領域に置かない**: `config.php` は `~/www` の外（`shared/`）にあり、GitHub にも入れない

### レンタルサーバならではの制約と対策

| 制約 | 対策 |
|---|---|
| OS が FreeBSD で、bash や GNU 独自のオプションがない | デプロイスクリプトは POSIX sh だけで書き、CI でも `dash -n` で確認している。リンクの差し替えは perl の `rename` で行う |
| 専用のデプロイユーザーを作れない | デプロイ専用の SSH 鍵を使い、`restrict` でポート転送などを無効化する。鍵は定期的に作り直す |
| PHP-FPM などをリロードできない | PHP のパスのキャッシュが切れるまで、切り替え後**最大2分ほど**は旧リリースが表示されることがある。旧リリースも残しているので、エラーにはならない |

---

## 1. サーバー側の準備（初回のみ）

### 1-1. SSH でログインできるようにする

1. コントロールパネルで **アカウント名** と **初期ドメイン**（`<アカウント名>.sakura.ne.jp`）を確認する
2. 自分の PC（WSL / Mac）から、サーバーのパスワードでログインする
   ```bash
   ssh <アカウント名>@<アカウント名>.sakura.ne.jp
   ```
   初回に表示されるホスト鍵の指紋（`SHA256:...`）は、手順 2 で使うので控えておく
3. デプロイに必要なコマンドがあるかを確認する
   ```sh
   which rsync perl sh      # 3つともパスが表示されれば OK
   ```

### 1-2. ディレクトリと設定ファイルを用意する（サーバー上で実行）

```sh
mkdir -p ~/apps/postcode/releases ~/apps/postcode/shared

# 今動いている設定ファイルを shared にコピーする（公開先が違う場合は読み替え）
cp ~/www/postcode/config.php   ~/apps/postcode/shared/config.php
cp ~/www/postcode/js/config.js ~/apps/postcode/shared/config.js

# config.php は自分だけが読めるようにする（DB パスワードが入っているため）
chmod 600 ~/apps/postcode/shared/config.php
chmod 644 ~/apps/postcode/shared/config.js
```

> `chmod 600` で画面にエラーが出る場合は、PHP が別のユーザーで動いています。`chmod 640` → `644` の順に試してください。

---

## 2. デプロイ用 SSH 鍵を作る（自分の PC で実行）

```bash
cd ~
ssh-keygen -t ed25519 -f ~/deploy_key -C "github-actions-deploy" -N ""
```

- `deploy_key.pub`（公開鍵）→ サーバーに登録する
- `deploy_key`（秘密鍵）→ GitHub の Secrets に登録し、**登録後は PC から削除する**

公開鍵をサーバーに登録します。先頭に `restrict` を付けると、ポート転送などの余計な機能が無効になります。

```bash
echo "restrict $(cat ~/deploy_key.pub)" | ssh <アカウント名>@<アカウント名>.sakura.ne.jp \
  'mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys'

# 鍵でログインできるかを確認（パスワードを聞かれなければ成功）
ssh -i ~/deploy_key <アカウント名>@<アカウント名>.sakura.ne.jp 'echo OK'
```

ホスト鍵（なりすまし防止用）を取得し、指紋が 1-1 で控えたものと同じかを確認します。

```bash
ssh-keyscan <アカウント名>.sakura.ne.jp 2>/dev/null > ~/deploy_known_hosts
ssh-keygen -lf ~/deploy_known_hosts
```

---

## 3. GitHub に登録する（自分の PC で実行）

`production` Environment とブランチ保護は、`make gh-setup` ですでに作成済みです。残りは Secrets と Variables の登録だけです。

```bash
cd ~/dev/Search_Address_and_PostCode
cp scripts/github/deploy.env.example scripts/github/deploy.env
# deploy.env の <アカウント名> を書き換える（エディタで開く: code scripts/github/deploy.env）

python3 scripts/github/setup.py secrets --dry-run   # 登録内容の確認（Secret の値は表示されない）
python3 scripts/github/setup.py secrets             # 登録

# 登録できたら、秘密鍵を PC から削除する
rm ~/deploy_key
```

| 種類 | 名前 | 値の例 |
|---|---|---|
| Secret | `SSH_PRIVATE_KEY` | `~/deploy_key` の中身 |
| Secret | `SSH_KNOWN_HOSTS` | `~/deploy_known_hosts` の中身 |
| Secret | `DEPLOY_HOST` | `<アカウント名>.sakura.ne.jp` |
| Secret | `DEPLOY_USER` | `<アカウント名>` |
| Variable | `DEPLOY_PORT` | `22` |
| Variable | `APP_DIR` | `/home/<アカウント名>/apps/postcode` |
| Variable | `APP_URL` | `https://<アカウント名>.sakura.ne.jp/postcode`（末尾の `/` なし） |
| Variable（リポジトリ全体） | `DEPLOY_ENABLED` | `true`（`false` でデプロイ停止） |

---

## 4. 初回デプロイと公開先の切り替え

1. GitHub の **Actions → CI/CD → Run workflow**（または `gh workflow run deploy.yml --ref master`）で、デプロイを実行する
   - この時点では `~/www/postcode` は旧ファイルのままなので、ヘルスチェックは旧サイトに対して行われる
2. サーバーで、リリースが配置されたかを確認する
   ```sh
   ls -l ~/apps/postcode/current
   ls ~/apps/postcode/current/
   ```
3. 公開先を `current` へのリンクに切り替える（数秒だけ表示されない時間がある）
   ```sh
   mv ~/www/postcode ~/www/postcode.bak
   ln -s ~/apps/postcode/current ~/www/postcode
   ```
4. ブラウザで `https://<アカウント名>.sakura.ne.jp/postcode/search_address.html` を開いて、動作を確認する
   - **403 Forbidden になる場合**は、シンボリックリンクがたどれていません。すぐに `rm ~/www/postcode && mv ~/www/postcode.bak ~/www/postcode` で元に戻し、表示されたエラーを共有してください
5. 問題がなければ、バックアップを消す
   ```sh
   rm -rf ~/www/postcode.bak
   ```

以降は、`master` にマージするだけで自動的にデプロイされます。

## 5. ロールバック

- **自動**: ヘルスチェックが失敗すると、直前のリリースに自動で戻ります
- **手動（GitHub から）**: Actions で過去の成功したデプロイを開き、**Re-run all jobs** を実行すると、そのコミットが再デプロイされます
- **手動（サーバーで）**: `current` のリンクを、戻したいリリースに付け替えます
  ```sh
  ls -lt ~/apps/postcode/releases                     # 戻したいコミットIDを確認
  ln -sfn ~/apps/postcode/releases/<コミットID> ~/apps/postcode/current.tmp
  perl -e 'rename $ARGV[0], $ARGV[1] or die $!' ~/apps/postcode/current.tmp ~/apps/postcode/current
  ```

## 6. 鍵の作り直し（年1回程度を推奨）

2 の手順で新しい鍵を作って登録し、サーバーの `~/.ssh/authorized_keys` から古い鍵の行を削除します。
