<?php

declare(strict_types=1);

/**
 * 郵便番号・住所検索(config_dummy.php)
 * 作成者：栗田幸一
 * 作成日：2023.5.25
 *
 * 設定ファイル（config.php）のひな形
 *   このファイルを config.php という名前でコピーし、DB の接続情報を書き換えて使う。
 *   config.php は Git の管理外（.gitignore）。本番では、サーバーの shared/config.php に置く（DEPLOY.md）。
 *   開発環境（Docker）では docker/app/config.php が使われるので、コピーは不要。
 *
 *   ※ ファイルの最後に「?>」を書かないこと（後ろに改行が入ると、それが画面に出力されてしまい、
 *     ヘッダーを送れなくなる「headers already sent」の原因になる）
 */

define('DB_NAME', 'mysql:host=<DB サーバー>;dbname=<DB 名>;charset=utf8mb4');   // 接続先（PDO の DSN）
define('DB_USER', '<DB ユーザー名>');
define('DB_PASS', '<DB パスワード>');
