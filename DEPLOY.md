# 自動デプロイ（GitHub Actions → さくらのクラウド）

`master` ブランチに push（Pull Request のマージを含む）すると、GitHub Actions が次の順に処理します。

```
lint（php -l） → rsync でアップロード → シンボリックリンク切り替え → ヘルスチェック
                                                                  └ 失敗したら直前のリリースへ自動ロールバック
```

Pull Request の段階では lint だけが動き、デプロイはされません。

## 仕組み

```
/var/www/postcode/
├── releases/
│   ├── 5bf648f.../        ← コミットごとに丸ごと配置（最新5件を保持）
│   └── a1b2c3d.../
├── shared/
│   ├── config.php         ← DB接続情報（Git管理外・サーバーにだけ置く）
│   └── config.js
├── previous_release       ← ロールバック先の記録
└── current -> releases/a1b2c3d...   ← Webサーバーはここを公開
```

- **アトミックな切り替え**: アップロード中のファイルが見えることはなく、`current` の付け替えは一瞬で終わる
- **すぐ戻せる**: 古いリリースが残っているので、リンクを戻すだけで元に戻る
- **秘密情報を Git に入れない**: DB パスワードは `shared/config.php` にだけ置き、GitHub には SSH 鍵だけを登録する

---

## 1. サーバー側の準備（初回のみ）

以下は Ubuntu の例です。AlmaLinux / Rocky Linux の場合は、`www-data` を `apache`、`apt` を `dnf` に読み替えてください。

### 1-1. デプロイ専用ユーザーを作る

```bash
sudo apt install -y rsync
sudo adduser --disabled-password --gecos "" deploy
```

### 1-2. ディレクトリと設定ファイルを用意する

```bash
sudo mkdir -p /var/www/postcode/{releases,shared}
sudo chown -R deploy:deploy /var/www/postcode

# 現在動いている config.php / js/config.js を shared にコピーする
sudo cp <今の公開ディレクトリ>/config.php     /var/www/postcode/shared/config.php
sudo cp <今の公開ディレクトリ>/js/config.js   /var/www/postcode/shared/config.js

# config.php は Webサーバーだけが読めるようにする（DBパスワードが入っているため）
sudo chown deploy:www-data /var/www/postcode/shared/config.php
sudo chmod 640             /var/www/postcode/shared/config.php
sudo chmod 644             /var/www/postcode/shared/config.js
```

### 1-3. Webサーバーの公開先を `current` に向ける

Apache の例（`/etc/apache2/sites-available/` 配下の設定ファイル）:

```apache
Alias /postcode /var/www/postcode/current
<Directory /var/www/postcode/current>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

```bash
sudo apachectl configtest && sudo systemctl reload apache2
```

### 1-4. リロードだけを sudo で許可する（OPcache 対策）

PHP の OPcache はファイルの実体パスでキャッシュするため、リンクを切り替えたあとに PHP-FPM をリロードします。
ここでは、そのリロードだけを `deploy` ユーザーに許可します（それ以外の sudo はできません）。

```bash
sudo visudo -f /etc/sudoers.d/deploy
```

```
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
```

> mod_php（PHP-FPM を使っていない）場合は `php8.3-fpm` を `apache2` にしてください。

### 1-5. SSH の安全設定

`/etc/ssh/sshd_config` で、パスワードログインと root ログインを禁止します。

```
PasswordAuthentication no
PermitRootLogin no
```

```bash
sudo systemctl reload ssh      # AlmaLinux 系は sshd
```

さくらのクラウドの **パケットフィルタ** で TCP 22（または変更したポート）を許可します。
GitHub Actions の接続元 IP は固定できないため、**鍵認証のみ・パスワード禁止・fail2ban** で守る構成にしています。

```bash
sudo apt install -y fail2ban     # SSH への総当たり攻撃を自動でブロックする
sudo systemctl enable --now fail2ban
```

---

## 2. デプロイ用 SSH 鍵を作る（自分の PC で実行）

```bash
ssh-keygen -t ed25519 -f ./deploy_key -C "github-actions-deploy" -N ""
```

- `deploy_key.pub`（公開鍵）→ サーバーに登録する
- `deploy_key`（秘密鍵）→ GitHub の Secrets に登録し、**登録後は PC から削除する**

サーバーに公開鍵を登録します。先頭に `restrict` を付けると、ポートフォワーディングなどの余計な機能が無効になります。

```bash
sudo -u deploy mkdir -p -m 700 /home/deploy/.ssh
echo "restrict $(cat deploy_key.pub)" | sudo -u deploy tee -a /home/deploy/.ssh/authorized_keys
sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

ホスト鍵（なりすまし防止用）を取得します。

```bash
ssh-keyscan -p 22 <サーバーのIPアドレス> 2>/dev/null
```

> 取得した鍵が本物かを、サーバー上の `ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` の指紋と見比べて確認してください。

---

## 3. GitHub の設定

> **コードで行う場合（推奨）**: 3-1〜3-3 はすべて `scripts/github/setup.py` で設定できます。
> ```bash
> cp scripts/github/deploy.env.example scripts/github/deploy.env   # 値を記入
> python3 scripts/github/setup.py environment secrets ruleset --dry-run
> python3 scripts/github/setup.py environment secrets ruleset
> ```
> 以下は、画面から設定する場合の手順と、各設定の意味です。

### 3-1. Environment を作る

リポジトリの **Settings → Environments → New environment** で `production` を作ります。

- **Deployment branches and tags**: `Selected branches` → `master` だけを許可
- （任意）**Required reviewers** に自分を入れると、デプロイ前に承認ボタンが出る

### 3-2. Secrets と Variables を `production` に登録する

| 種類 | 名前 | 値の例 |
|---|---|---|
| Secret | `SSH_PRIVATE_KEY` | `deploy_key` の中身（`-----BEGIN` から `END-----` まで全部） |
| Secret | `SSH_KNOWN_HOSTS` | `ssh-keyscan` の出力 |
| Secret | `DEPLOY_HOST` | サーバーの IP アドレス |
| Secret | `DEPLOY_USER` | `deploy` |
| Variable | `DEPLOY_PORT` | `22` |
| Variable | `APP_DIR` | `/var/www/postcode` |
| Variable | `APP_URL` | `https://example.com/postcode`（末尾の `/` なし） |
| Variable | `RELOAD_SERVICE` | `php8.3-fpm`（リロード不要なら空のまま） |

### 3-3. master を保護する（推奨）

**Settings → Branches → Add branch ruleset** で `master` に次を設定します。

- Require a pull request before merging
- Require status checks to pass → `PHP 構文チェック`

これで「lint が通らないコードは master に入らない＝本番にも出ない」状態になります。

---

## 4. 動作確認

1. ブランチを切って小さな変更を入れ、Pull Request を作る → **lint だけ** が動く
2. マージする → **Actions** タブで `さくらのクラウドへデプロイ` が緑になる
3. サーバーで `ls -l /var/www/postcode/current` を実行し、最新のコミットIDを指していることを確認する
4. ブラウザで動作を確認する

## 5. ロールバック

- **自動**: ヘルスチェックが失敗すると、直前のリリースに自動で戻ります
- **手動（GitHub から）**: Actions で過去の成功したデプロイを開き、**Re-run all jobs** を実行するとそのコミットが再デプロイされます
- **手動（サーバーで）**: `current` のリンクを戻したいリリースに付け替えます
  ```bash
  ls -lt /var/www/postcode/releases            # 戻したいコミットIDを確認
  sudo -u deploy ln -sfn /var/www/postcode/releases/<コミットID> /var/www/postcode/current.tmp
  sudo -u deploy mv -Tf /var/www/postcode/current.tmp /var/www/postcode/current
  sudo systemctl reload php8.3-fpm
  ```
