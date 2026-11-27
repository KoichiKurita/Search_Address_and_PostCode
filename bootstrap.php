<?php

declare(strict_types=1);

/**
 * 郵便番号・住所検索(bootstrap.php)
 * 作成者：栗田幸一
 * 作成日：2023.3.31（functions.php として作成）
 * 修正日：2026.11.27
 *
 * アプリを動かす準備（設定・オートローダー・DB 接続）
 * フロントコントローラ（public/index.php）が最初に読み込む。
 * 公開ルート（public/）の外にあるので、URL から直接開かれることはない。
 */

// DB の認証情報の読み込み（Git 管理外。本番ではデプロイのときに shared/config.php へのリンクが作られる）
require_once __DIR__ . '/config.php';

// Composer のオートローダー（src/ のクラスを、use で書くだけで読み込めるようにする）
require_once __DIR__ . '/vendor/autoload.php';

// DB や画面に依存しない関数の読み込み
require_once __DIR__ . '/src/helpers.php';


/**
 * DB に接続する
 * @param bool $readOnly 読み取り専用のセッションにするか（画面と API は true。書き込むのは郵便番号データの取り込みだけ）
 * @return PDO DB への接続
 * @throws PDOException DB に接続できない場合
 */
function connect(bool $readOnly = true): PDO
{
    $pdo = new PDO(DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);  // SQL をプリコンパイルしてから実行する（SQL インジェクション対策）

    // この接続を読み取り専用にする（このアプリは検索だけで、DB に書き込まない）
    //   本番のレンタルサーバでは SELECT だけの DB ユーザーを作れないため、その代わりの安全策として、
    //   バグなどで書き込み系の SQL が実行されても、DB 側で拒否されるようにする
    if ($readOnly) {
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    }

    return $pdo;
}
