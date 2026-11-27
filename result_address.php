<?php
declare(strict_types=1);

/**
 * Webアプリ実践課題
 * 住所検索(result_address.php)
 * 作成者：リンクス新越谷 栗田幸一
 * 作成日：2023.3.31
 * 修正日：2026.11.27
 *
 * 郵便番号から住所を検索し、結果を表示する
 * ※ ステータスコードを正しく返せるよう、HTML を出力する前にすべての処理を終える
 */

use App\Domain\PostalCode;

require_once dirname(__FILE__) . '/functions.php';   // 外部関数読み込み（config.php も functions.php の中で読み込む）

// 入力された郵便番号（画面に入力値を残すため、正規化した文字列も持っておく）
$input = PostalCode::normalize(query_string($_GET, 'post-code'));

// PostalCode に変換する。正しい郵便番号でなければ null になる（このあと検索できるのは PostalCode だけ）
$postal_code = PostalCode::tryFromString($input);

$rows = [];             // 検索結果
$error_message = '';    // 入力エラーのメッセージ（空ならエラーなし）

if ($postal_code === null) {
    // 書式が正しくない場合は DB を検索せず、400（Bad Request）とエラーメッセージを返す
    // JS のチェックは URL を直接書き換えれば通らずに送れるので、サーバー側でも必ず検証する
    http_response_code(400);
    $error_message = '郵便番号は7桁の数字で入力してください（例：330-0063）';
} else {
    try {
        $pdo = connect();   // DB接続

        // 郵便番号から住所を検索する
        // fetchAll で配列として受け取り、件数は count() で数える
        // （SELECT 文に対する rowCount() は、DB の種類によっては正しい件数を返さないため）
        $rows = search_address($pdo, $postal_code)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log(basename(__FILE__) . ': ' . $e->getMessage());   // 詳細はサーバーログにのみ記録する
        render_error_page(500, '住所の検索に失敗しました。時間をおいて再度お試しください。');
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>住所検索結果</title>
        <link rel="stylesheet" href="css/style.css">
    </head>

    <body>
        <!-- 結果画面にも検索フォームを表示する -->
        <h1>住所検索</h1>
        <p id="direction">郵便番号を入力してください。郵便番号検索は<a href="search_postcode.php">こちら</a></p>
        <p class="note">注：7桁の数字で入力してください。ハイフン（-）は有り・無しどちらでも検索可能です。</p>
        <form name="search-address" action="result_address.php" method="GET">
            <div class="post-code-input-wrapper">
                <div class="post-code-input">
                    <label for="post-code">郵便番号<span class="required">（必須）</span>：
                        <!-- 検索した郵便番号を入力欄に残す（JS ではなく PHP で値を埋め込む） -->
                        <input type="text" name="post-code" id="post-code" value="<?=escape($input)?>" required>
                    </label>
                </div>
            </div>

            <button type="submit" name="operation" value="search" id="button">検索開始</button>
        </form>

        <!-- 入力された郵便番号の書式が誤っている場合にエラーメッセージを表示する部分（JS とサーバーの両方が使う） -->
        <p id="error-message" role="alert"><?=escape($error_message)?></p>

        <?php if ($error_message === ''): ?>
            <hr class="divider">

            <!-- フォームに入力された郵便番号とデータベースで検索した結果を表形式で表示する -->
            <!-- 見出しは、入力の形（ハイフンの有無）に関係なく「330-0063」の形にそろえる -->
            <!-- エラーがないときは $postal_code が必ず作られているので、null の考慮はいらない（PHPStan も確認している） -->
            <h1 id="result_heading">郵便番号「<?=escape($postal_code->withHyphen())?>」の住所の検索結果：<?=count($rows)?>件</h1>            <table class="result-table">
                <tr>
                    <th>郵便番号</th>
                    <th>住所</th>
                </tr>

                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?=escape($row['post_code'])?></td>
                    <td><?=escape($row['prefecture'] . $row['municipality'] . $row['town_area'])?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <a href="search_address.html">住所検索トップへ戻る</a>

        <!-- JavaScript -->
        <script src="js/result_address_script.js" type="module"></script>
    </body>
</html>
