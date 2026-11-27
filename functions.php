<?php

declare(strict_types=1);

/**
 * Webアプリ実践課題
 * 郵便番号・住所検索(functions.php)
 * 作成者：リンクス新越谷 栗田幸一
 * 作成日：2023.3.31
 * 修正日：2026.11.27
 *
 * DB への接続と、画面・API の応答（エラーページ・JSON）を行う関数
 *
 * ※ PHP だけのファイルなので、最後の「?>」は書かない（PSR-12）。
 *   「?>」の後ろに改行や空白があると、それが画面に出力されてしまい、
 *   ステータスコードやヘッダーを送れなくなる原因になるため。
 */

// DBの認証情報の読み込み
require_once dirname(__FILE__) . '/config.php';

// DB や画面に依存しない関数（src/helpers.php）と、DB を検索する関数（src/queries.php）の読み込み
// テストしやすいように、ファイルを分けている
require_once dirname(__FILE__) . '/src/helpers.php';
require_once dirname(__FILE__) . '/src/queries.php';


/**
 * PDOインスタンスを取得する関数
 * @return pdo PHP Data Object を返します。
 */
function connect(): pdo
{
    $pdo = new PDO(DB_NAME, DB_USER, DB_PASS);    // DB接続
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);  // SQLをプリコンパイルしてから実行。処理速度向上とSQLインジェクション対策

    // この接続を読み取り専用にする（このアプリは検索だけで、DB に書き込まない）
    //   本番のレンタルサーバでは SELECT だけの DB ユーザーを作れないため、その代わりの安全策として、
    //   バグなどで書き込み系の SQL（INSERT・UPDATE・DELETE・DROP など）が実行されても、DB 側で拒否されるようにする
    //   ※ 権限そのものを制限するわけではないので、プリペアドステートメントと組み合わせて使う
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');

    return $pdo;
}


/**
 * エラーページを表示して、処理を終了する
 * ステータスコードを正しく返すため、画面に何か出力する前に呼び出すこと
 * （一度でも出力すると、そのあとでステータスコードを変えられなくなる）
 * @param int $status HTTP ステータスコード（例: 500）
 * @param string $message 利用者に表示するメッセージ（エラーの詳細は書かない）
 * @return never この関数から戻ることはない
 */
function render_error_page(int $status, string $message): never
{
    http_response_code($status);
    $status_html  = escape($status);
    $message_html = escape($message);

    echo <<<HTML
        <!DOCTYPE html>
        <html lang="ja">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>エラー（{$status_html}）</title>
                <link rel="stylesheet" href="css/style.css">
            </head>
            <body>
                <h1>エラーが発生しました</h1>
                <p id="error-message" role="alert">{$message_html}</p>
                <p><a href="search_address.html">住所検索へ</a> ／ <a href="search_postcode.php">郵便番号検索へ</a></p>
            </body>
        </html>
        HTML;
    exit;
}


/**
 * JSON を返して、処理を終了する（get_municipality.php などの API 用）
 * @param int $status HTTP ステータスコード
 * @param mixed $data JSON にする値
 * @return never この関数から戻ることはない
 */
function send_json(int $status, mixed $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    // JSON_THROW_ON_ERROR: 変換に失敗したら false ではなく例外にする
    // JSON_UNESCAPED_UNICODE: 日本語を \u3042 のような形にせず、そのまま出力する
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}
