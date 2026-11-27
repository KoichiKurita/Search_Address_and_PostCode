<?php
declare(strict_types=1);

/**
 * Webアプリ実践課題
 * 郵便番号検索(search_postcode.php)
 * 作成者：リンクス新越谷 栗田幸一
 * 作成日：2023.4.7
 * 修正日：2026.11.27
 *
 * 住所（都道府県・市区町村・町名）を入力するフォームを表示する
 * ※ ステータスコードを正しく返せるよう、HTML を出力する前にすべての処理を終える
 */

require_once dirname(__FILE__) . '/functions.php';   // 外部関数読み込み

// DBに接続し、都道府県の一覧を取得する
try {
    $prefectures = post_code_repository()->prefectures();
} catch (PDOException $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());   // 詳細はサーバーログにのみ記録する
    render_error_page(500, '都道府県データの取得に失敗しました。時間をおいて再度お試しください。');
}
?>

<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>郵便番号検索</title>
        <link rel="stylesheet" href="css/style.css">
    </head>

    <body>
        <h1>郵便番号検索</h1>
        <p id="direction">都道府県, 市区町村, 町名を入力してください。住所検索は<a href="search_address.html">こちら</a></p>
        <form name="search-post-code" action="result_postcode.php" method="GET">
            <div class="address-input-wrapper">
                <div class="address-input">
                    <label for="prefecture">都道府県<span class="required">（必須）</span>：
                        <select name="prefecture" id="prefecture" required>
                            <option value="">選択してください</option>
                            <?php foreach ($prefectures as $prefecture): ?>
                                <option value="<?=escape($prefecture)?>"><?=escape($prefecture)?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="address-input">
                    <label for="municipality">市区町村<span class="required">（必須）</span>：
                        <select name="municipality" id="municipality" disabled required>
                            <option value="">選択してください</option>
                        </select>
                    </label>
                </div>

                <div class="address-input">
                    <label for="town_area">町名：
                        <!-- maxlength はブラウザ側の制限。サーバー側でも同じ文字数で検証している -->
                        <input type="text" name="town_area" id="town_area" maxlength="<?=TOWN_AREA_MAX_LENGTH?>">
                    </label>
                </div>
            </div>

            <button type="submit" name="operation" value="search">検索開始</button>
        </form>

        <!-- 市区町村の取得に失敗したときに、エラーメッセージを表示する部分 -->
        <p id="error-message" role="alert"></p>
        
        <!-- JavaScript -->
        <!-- .js間のモジュール読み込みはサーバにアップロードした状態でないと実行できない。（ローカルではエラーになる。）
        また、.jsファイルを読み込む.jsファイルに対して、html側でtype="module"で読み込まないといけない。 -->
        <!-- Javascript直下にコメント入れるとページロード完了後に動作しないことがあるので注意する -->
        <script src="js/search_postcode_script.js" type="module"></script>
    </body>
</html>
