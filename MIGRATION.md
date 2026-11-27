# さくらのレンタルサーバへの移設手順

郵便番号・住所検索アプリを、さくらのレンタルサーバ（スタンダード）に移設する手順です。
PHP 8.3 で動作確認済みです（2026年10月の PHP 脆弱性 CVE-2026-109618 / 109619 の修正版を前提）。

## 1. 契約と PHP バージョンの設定

1. さくらのレンタルサーバ「スタンダード」を申し込む（お試し期間あり）
2. コントロールパネル →「スクリプト設定」→「言語バージョン設定」で **PHP 8.3** を選ぶ
3. 同じ画面か `phpinfo()` で、PHP が 8.3.36 以降（または事業者が修正済みと告知している版）であることを確認する

## 2. データベースの作成

1. コントロールパネル →「Webサイト/データ」→「データベース」→「新規追加」
   - 種類: MySQL 8.0 / 文字コード: UTF-8（utf8mb4）
2. 作成後に表示される **ホスト名・データベース名・ユーザー名・パスワード** を控える
3. 「管理ツール（phpMyAdmin）」を開き、`db/schema.sql` の内容を「SQL」タブで実行する

## 3. 郵便番号データの投入

1. 日本郵便の「郵便番号データダウンロード」ページから
   **住所の郵便番号（1レコード1行、UTF-8形式）** の `utf_ken_all.zip` をダウンロードして展開
2. 手元の PC で変換する

   ```
   python3 db/ken_all_to_sql.py utf_ken_all.csv out
   ```

3. phpMyAdmin の「インポート」で `out/post_codes_001.sql` から番号順にすべて取り込む
4. 続けて `db/refresh_masters.sql` を実行し、都道府県・市区町村のマスタを作り直す（データを取り込むたびに必要）
   （1ファイル目の先頭で既存データを消すので、やり直すときも 001 から）
5. 「SQL」タブで `SELECT COUNT(*) FROM post_codes;` を実行し、件数が変換時の表示と一致することを確認する

## 4. 設定ファイルの作成

`config.php` と `js/config.js` は `.gitignore` 対象なので、ダミーをコピーして作ります。

**config.php**（`config_dummy.php` をコピー）

```php
define("DB_NAME", "mysql:host=<ホスト名>;dbname=<データベース名>;charset=utf8mb4");
define("DB_USER", "<ユーザー名>");
define("DB_PASS", "<パスワード>");
```

**js/config.js**（`js/config_dummy.js` をコピー）

```js
const PATH_GET_MUNICIPALITY_PROCESS = "get_municipality.php";
```

## 5. アップロード

1. ファイルマネージャーか FTP で `/home/<アカウント名>/www/postcode/` などに配置する
   - アップロード不要: `.git/`, `db/`, `MIGRATION.md`, `config_dummy.php`, `js/config_dummy.js`
2. コントロールパネルで独自ドメイン（または初期ドメイン）の **無料SSL** を有効にする

## 6. 動作確認

- `https://<ドメイン>/postcode/search_address.html` で `330-0063` を検索し、住所が出る
- 全角ハイフン（`330ー0063`）やハイフンなし（`3300063`）でも同じ結果になる
- `search_postcode.php` で都道府県を選ぶと市区町村の選択肢が出る

## 今回のコード修正（PHP 8.3 対応）

- `search_post_code()` の引数を `?string` に変更（PHP 8.4 の非推奨警告対策）
- GET パラメータが無いときに `strict_types` 下で TypeError になる箇所を修正
- `get_municipality.php` で文字列以外が送られたときの TypeError を防止
- `config_dummy.php` のハイフン正規表現を JavaScript 記法（`\u{...}`）から PCRE 記法（`\x{...}`）に修正
  （従来は正規表現のコンパイルに失敗し、全角ハイフンが正規化されていなかった）
- DB エラーの詳細を画面に出さず、サーバーログにのみ記録するよう変更
