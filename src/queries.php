<?php

declare(strict_types=1);

/**
 * 郵便番号・住所検索(src/queries.php)
 * 作成者：栗田幸一
 * 作成日：2026.11.27
 *
 * DB を検索する関数（SQL はここにまとめる）
 * どれも PDO を引数で受け取るので、テストでは SQLite のメモリ上の DB を渡して確かめられる
 */

require_once __DIR__ . '/helpers.php';   // escape_like() を使うため


/**
 * 都道府県を郵便番号データベースから取得する関数
 * @param PDO $pdo データベースに接続した PHP Data Object
 * @return PDOStatement 実行済みの SQL 文（fetch / fetchAll で結果を取り出す）
 */
function get_prefecture(PDO $pdo): PDOStatement
{
    // 郵便番号データベースから都道府県の一覧データを取得
    // 日本郵便のデータは全国地方公共団体コード順（北海道→沖縄県）に並んでおり、
    // id もその順に振られているため、各都道府県の最小 id で並べると都道府県コード順になる
    $statement = $pdo->prepare('SELECT prefecture FROM post_codes GROUP BY prefecture ORDER BY MIN(id)');
    $statement->execute();

    return $statement;
}


/**
 * 市区町村を郵便番号データベースから取得する関数
 * @param PDO $pdo データベースに接続した PHP Data Object
 * @return PDOStatement 実行済みの SQL 文（fetch / fetchAll で結果を取り出す）
 */
function get_municipality(PDO $pdo, string $selected_prefecture): PDOStatement
{
    // 郵便番号データベースから市区町村の一覧データを取得
    // 都道府県と同様に、最小 id で並べて全国地方公共団体コード順にする
    $statement = $pdo->prepare('SELECT municipality FROM post_codes WHERE prefecture = :prefecture GROUP BY municipality ORDER BY MIN(id)');
    $statement->bindValue(':prefecture', $selected_prefecture, PDO::PARAM_STR);
    $statement->execute();

    return $statement;
}


/**
 * 郵便番号をデータベースから検索する関数
 * @param PDO $pdo データベースに接続した PHP Data Object
 * @param string $prefecture 都道府県
 * @param string $municipality 市区町村
 * @param string|null $town_area 町名（null のときは町名で絞り込まない）
 * @return PDOStatement 実行済みの SQL 文（fetch / fetchAll で結果を取り出す）
 */
function search_post_code(PDO $pdo, string $prefecture, string $municipality, ?string $town_area = null): PDOStatement
{
    // 町名（town_area）の有無でSQL文を変える
    if ($town_area === null) {  // 町名がない場合
        // SQL検索結果には結果表示に必要な列のみ取得する（動作の高速化）
        $statement = $pdo->prepare('SELECT post_code, prefecture, municipality, town_area FROM post_codes WHERE prefecture = :prefecture AND municipality = :municipality');
    } else {    // 町名がある場合
        // 入力に含まれる % や _ をワイルドカードとして扱わないよう、escape_like() でエスケープしてから部分一致にする
        $statement = $pdo->prepare("SELECT post_code, prefecture, municipality, town_area FROM post_codes WHERE prefecture = :prefecture AND municipality = :municipality AND town_area LIKE :town_area ESCAPE '!'");
        $statement->bindValue(':town_area', '%' . escape_like($town_area) . '%', PDO::PARAM_STR);
    }

    // SQL文のパラメータをバインド
    $statement->bindValue(':prefecture', $prefecture, PDO::PARAM_STR);
    $statement->bindValue(':municipality', $municipality, PDO::PARAM_STR);

    // 郵便番号検索実行（SQL文を実行）
    $statement->execute();

    return $statement;
}


/**
 * 郵便番号をデータベースから検索する関数
 * @param PDO $pdo データベースに接続した PHP Data Object
 * @param string $post_code 郵便番号
 * @return PDOStatement 実行済みの SQL 文（fetch / fetchAll で結果を取り出す）
 */
function search_address(PDO $pdo, string $post_code): PDOStatement
{
    // ハイフンの有無で検索処理を分ける
    // ^ と $ で、文字列全体が「数字3桁-数字4桁」の形かを判定する（$ が無いと後ろに余計な文字があっても一致する）
    if (preg_match('/^[0-9]{3}-[0-9]{4}$/', $post_code) === 1) { // ハイフンが付いている場合
        $statement = $pdo->prepare('SELECT post_code, prefecture, municipality, town_area FROM post_codes WHERE post_code = :post_code');
    } else {    // ハイフンがついていない場合
        $statement = $pdo->prepare('SELECT post_code, prefecture, municipality, town_area FROM post_codes WHERE post_code_noHyphen = :post_code');
    }

    // SQL文のパラメータをバインド
    $statement->bindValue(':post_code', $post_code, PDO::PARAM_STR);

    // 郵便番号検索実行（SQL文を実行）
    $statement->execute();

    return $statement;
}
