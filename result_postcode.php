<?php declare(strict_types=1); ?>

<!--
    Webアプリ実践課題
    郵便番号検索(result_postcode.php)
    作成者：リンクス新越谷 栗田幸一
    作成日：2023.3.31
    修正日：2026.11.27
-->

<!-- メインルーチン -->
<!-- 住所フォームの入力内容から郵便番号を検索する -->
<?php
    require_once dirname(__FILE__). '/functions.php';   // 外部関数読み込み

    // DBに接続する。
    try {
        $pdo = connect();   // DB接続
    } catch (PDOException $e) {
        echo 'データベースの接続に失敗しました。';
        error_log(basename(__FILE__). ': '. $e->getMessage());   // 詳細はサーバーログにのみ記録する
        return;
    }

    // DBに接続し、都道府県データを検索する。
    try {
        // DBから都道府県一覧データを取得する
        // 【メモ】PHPの変数は関数以外のブロックで宣言されたものはブロック外でも有効
        // 上記try文内で宣言された$pdoは下のブロックで使用できる
        $statement_prefecture = get_prefecture($pdo);
    } catch (PDOException $e) {
        echo '都道府県データの取得に失敗しました。';
        error_log(basename(__FILE__). ': '. $e->getMessage());   // 詳細はサーバーログにのみ記録する
        return;
    }

    // 検索条件（未入力の場合は空文字にする：strict_types下でのTypeError防止）
    $prefecture   = (string)($_GET['prefecture'] ?? '');
    $municipality = (string)($_GET['municipality'] ?? '');
    $town_area    = trim((string)($_GET['town_area'] ?? ''));

    // 町名で見つからず、市区町村だけで検索し直したかどうか（画面にお知らせを出すために使う）
    $is_fallback = false;

    // DBから郵便番号を検索する。
    try {

        // 町名の入力の有無で検索処理を分ける
        // fetchAll で配列として受け取り、件数は count() で数える
        // （SELECT 文に対する rowCount() は、DB の種類によっては正しい件数を返さないため）
        if ($town_area !== '') { // 町名が入力されている場合
            $rows_postcode = search_post_code($pdo, $prefecture, $municipality, $town_area)->fetchAll(PDO::FETCH_ASSOC);
        } else {    // 町名が入力されていない場合
            $rows_postcode = search_post_code($pdo, $prefecture, $municipality)->fetchAll(PDO::FETCH_ASSOC);
        }

        // 町名で0件だった場合は、都道府県+市区町村で検索し直す
        // （町名が未入力のときは、上ですでに同じ検索をしているので行わない）
        if (count($rows_postcode) === 0 && $town_area !== '') {
            $rows_postcode = search_post_code($pdo, $prefecture, $municipality)->fetchAll(PDO::FETCH_ASSOC);
            $is_fallback = count($rows_postcode) > 0;
        }

    } catch (PDOException $e) {
        echo '郵便番号の検索に失敗しました。';
        error_log(basename(__FILE__). ': '. $e->getMessage());   // 詳細はサーバーログにのみ記録する
        return;
    }

?>

<!-- 以下、結果表示用Webページ -->
<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>郵便番号検索結果</title>
        <link rel="stylesheet" href="css/style.css">
    </head>

    <body>
        <!-- result.phpにも検索フォームを表示する -->
        <h1>郵便番号検索</h1>
        <p id="direction">都道府県, 市区町村, 町名を入力してください。住所検索は<a href="search_address.html">こちら</a></p>
        <form name="search-post-code" action="result_postcode.php" method="GET">
            <div class="address-input-wrapper">
                <div class="address-input">
                    <label for="prefecture">都道府県<span class="required">（必須）</span>：
                        <select name="prefecture" id="prefecture" required>
                            <option value="">選択してください</option>
                            <?php while ($row_prefecture = $statement_prefecture->fetch(PDO::FETCH_ASSOC)): ?>
                                <?php
                                    // 検索ページで選択した都道府県を初期表示する
                                    $selected_flag = '';
                                    if($row_prefecture['prefecture'] === $prefecture) {
                                        $selected_flag = 'selected';
                                    }
                                ?>
                                <option value="<?=escape($row_prefecture['prefecture'])?>" <?=$selected_flag?>><?=escape($row_prefecture['prefecture'])?></option>
                            <?php endwhile; ?>
                        </select>
                    </label>
                </div>

                <div class="address-input">
                    <label for="municipality">市区町村<span class="required">（必須）</span>：
                        <!-- 検索した市区町村を data-selected に埋め込み、JS で選択肢を作るときに初期選択にする -->
                        <select name="municipality" id="municipality" data-selected="<?=escape($municipality)?>" required>
                            <option value="">選択してください</option>
                        </select>
                    </label>
                </div>

                <div class="address-input">
                    <label for="town_area">町名：
                        <input type="text" name="town_area" id="town_area" value="<?=escape($town_area)?>">
                    </label>
                </div>
            </div>

            <button type="submit" name="operation" value="search">検索開始</button>
        </form>

        <!-- 市区町村の取得に失敗したときに、エラーメッセージを表示する部分 -->
        <p id="error-message" role="alert"></p>

        <hr class="divider">

        <!-- フォームに入力された住所とデータベースで検索した結果を表形式で表示する -->
        <h1 id="result_heading">住所「<?=escape($prefecture. $municipality. $town_area)?>」の郵便番号の検索結果：<?=count($rows_postcode)?>件</h1>

        <?php if ($is_fallback): ?>
            <!-- 町名では見つからず、市区町村全体の結果を表示していることを利用者に知らせる -->
            <p class="notice">町名「<?=escape($town_area)?>」に一致する住所が見つからなかったため、<?=escape($municipality)?>全体の結果を表示しています。</p>
        <?php endif; ?>

        <table class="result-table">
            <tr>
                <th>郵便番号</th>
                <th>都道府県</th>
                <th>市区町村</th>
                <th>町名</th>
            </tr>

            <?php foreach ($rows_postcode as $row_postcode): ?>
            <tr>
                <td><?=escape($row_postcode['post_code'])?></td>
                <td><?=escape($row_postcode['prefecture'])?></td>
                <td><?=escape($row_postcode['municipality'])?></td>
                <td><?=escape($row_postcode['town_area'])?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <a href='search_postcode.php'>郵便番号検索トップへ戻る</a>

        <!-- JavaScript -->
        <script src="js/result_postcode_script.js" type="module"></script>
    </body>
</html>
