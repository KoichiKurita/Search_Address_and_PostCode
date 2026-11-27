<?php
declare(strict_types=1);

/**
 * Webアプリ実践課題
 * 郵便番号検索(result_postcode.php)
 * 作成者：リンクス新越谷 栗田幸一
 * 作成日：2023.3.31
 * 修正日：2026.11.27
 *
 * 住所（都道府県・市区町村・町名）から郵便番号を検索し、結果を表示する
 * ※ ステータスコードを正しく返せるよう、HTML を出力する前にすべての処理を終える
 */

require_once dirname(__FILE__) . '/functions.php';   // 外部関数読み込み

// 検索条件（未入力や、配列が送られた場合は空文字にする）
$prefecture   = query_string($_GET, 'prefecture');
$municipality = query_string($_GET, 'municipality');
$town_area    = trim(query_string($_GET, 'town_area'));
$page         = parse_page(query_string($_GET, 'page'));   // 表示するページ番号（不正な値は 1）

$rows_postcode = [];    // 検索結果（表示するページの分だけ）
$total = 0;             // 検索結果の総件数
$last_page = 1;         // 最後のページ番号
$offset = 0;            // 表示するページの先頭が、全体の何件目の次か（0 から数える）
$error_message = '';    // 入力エラーのメッセージ（空ならエラーなし）
$is_fallback = false;   // 町名で見つからず、市区町村だけで検索し直したかどうか

try {
    $pdo = connect();   // DB接続

    // 都道府県の一覧（プルダウンの表示と、入力値の検証の両方に使う）
    $prefectures = get_prefecture($pdo)->fetchAll(PDO::FETCH_COLUMN);

    // 入力値の検証：都道府県・市区町村は「DB に存在する値か」で確かめる（ホワイトリスト方式）
    // in_array の第3引数 true は、型まで含めて厳密に比較する指定
    if (!in_array($prefecture, $prefectures, true)) {
        $error_message = '都道府県を選択してください。';
    } elseif (!in_array($municipality, get_municipality($pdo, $prefecture)->fetchAll(PDO::FETCH_COLUMN), true)) {
        $error_message = '市区町村を選択してください。';
    } elseif (mb_strlen($town_area) > TOWN_AREA_MAX_LENGTH) {
        $error_message = '町名は' . TOWN_AREA_MAX_LENGTH . '文字以内で入力してください。';
    }

    if ($error_message !== '') {
        // 入力値が正しくない場合は検索せず、400（Bad Request）を返す
        http_response_code(400);
    } else {
        // 町名が入力されていれば町名でも絞り込む（未入力なら null で、町名の条件を付けない）
        $town_condition = $town_area !== '' ? $town_area : null;

        // まず件数を数える（ページングの総件数に使う）
        $total = count_post_code($pdo, $prefecture, $municipality, $town_condition);

        // 町名で0件だった場合は、都道府県+市区町村で検索し直す
        if ($total === 0 && $town_condition !== null) {
            $town_condition = null;
            $total = count_post_code($pdo, $prefecture, $municipality);
            $is_fallback = $total > 0;
        }

        // 最後のページより大きい番号が指定された場合は、最後のページを表示する
        $last_page = last_page($total, POST_CODE_PAGE_SIZE);
        $page = min($page, $last_page);

        // 表示するページの分だけ取得する（例: 2ページ目なら 51〜100件目）
        $offset = ($page - 1) * POST_CODE_PAGE_SIZE;
        $rows_postcode = search_post_code($pdo, $prefecture, $municipality, $town_condition, POST_CODE_PAGE_SIZE, $offset)
            ->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log(basename(__FILE__) . ': ' . $e->getMessage());   // 詳細はサーバーログにのみ記録する
    render_error_page(500, '郵便番号の検索に失敗しました。時間をおいて再度お試しください。');
}

/**
 * 同じ検索条件で、指定したページを表示する URL（クエリ部分）を作る
 * http_build_query は、値の URL エンコードもまとめて行う
 */
$page_url = fn (int $number): string => '?' . http_build_query([
    'prefecture'   => $prefecture,
    'municipality' => $municipality,
    'town_area'    => $town_area,
    'page'         => $number,
]);

?>
<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>郵便番号検索結果</title>
        <link rel="stylesheet" href="css/style.css">
    </head>

    <body>
        <!-- 結果画面にも検索フォームを表示する -->
        <h1>郵便番号検索</h1>
        <p id="direction">都道府県, 市区町村, 町名を入力してください。住所検索は<a href="search_address.html">こちら</a></p>
        <form name="search-post-code" action="result_postcode.php" method="GET">
            <div class="address-input-wrapper">
                <div class="address-input">
                    <label for="prefecture">都道府県<span class="required">（必須）</span>：
                        <select name="prefecture" id="prefecture" required>
                            <option value="">選択してください</option>
                            <?php foreach ($prefectures as $name): ?>
                                <!-- 検索した都道府県を初期表示する -->
                                <option value="<?=escape($name)?>" <?=$name === $prefecture ? 'selected' : ''?>><?=escape($name)?></option>
                            <?php endforeach; ?>
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
                        <!-- maxlength はブラウザ側の制限。サーバー側でも同じ文字数で検証している -->
                        <input type="text" name="town_area" id="town_area" value="<?=escape($town_area)?>" maxlength="<?=TOWN_AREA_MAX_LENGTH?>">
                    </label>
                </div>
            </div>

            <button type="submit" name="operation" value="search">検索開始</button>
        </form>

        <!-- 入力エラーや、市区町村の取得に失敗したときに、エラーメッセージを表示する部分（JS とサーバーの両方が使う） -->
        <p id="error-message" role="alert"><?=escape($error_message)?></p>

        <?php if ($error_message === ''): ?>
            <hr class="divider">

            <!-- フォームに入力された住所とデータベースで検索した結果を表形式で表示する -->
            <h1 id="result_heading">住所「<?=escape($prefecture . $municipality . $town_area)?>」の郵便番号の検索結果：<?=$total?>件</h1>

            <?php if ($total > 0): ?>
                <!-- 何件目から何件目までを表示しているか -->
                <p class="result-range"><?=$offset + 1?>〜<?=$offset + count($rows_postcode)?>件目を表示（<?=$page?> / <?=$last_page?> ページ）</p>
            <?php endif; ?>
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
            
            <?php if ($last_page > 1): ?>
                <!-- ページ切り替え（現在のページはリンクにせず、aria-current で読み上げソフトにも伝える） -->
                <nav class="pagination" aria-label="検索結果のページ">
                    <?php if ($page > 1): ?>
                        <a href="<?=escape($page_url($page - 1))?>" rel="prev">前へ</a>
                    <?php endif; ?>

                    <?php foreach (pagination_pages($page, $last_page) as $number): ?>
                        <?php if ($number === null): ?>
                            <span class="ellipsis">…</span>
                        <?php elseif ($number === $page): ?>
                            <span aria-current="page"><?=$number?></span>
                        <?php else: ?>
                            <a href="<?=escape($page_url($number))?>"><?=$number?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($page < $last_page): ?>
                        <a href="<?=escape($page_url($page + 1))?>" rel="next">次へ</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

        <a href="search_postcode.php">郵便番号検索トップへ戻る</a>

        <!-- JavaScript -->
        <script src="js/result_postcode_script.js" type="module"></script>
    </body>
</html>
