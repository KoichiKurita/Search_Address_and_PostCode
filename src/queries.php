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

use App\Domain\PostalCode;

require_once __DIR__ . '/helpers.php';   // escape_like() を使うため


/**
 * 都道府県の一覧を取得する関数（都道府県マスタから）
 * @param PDO $pdo データベースに接続した PHP Data Object
 * @return PDOStatement 実行済みの SQL 文（fetch / fetchAll で結果を取り出す）
 */
function get_prefecture(PDO $pdo): PDOStatement
{
    // 都道府県マスタ（47件）から取得する。sort_order の順に並べると全国地方公共団体コード順（北海道→沖縄県）になる
    // ※ 以前は post_codes（約12万件）を毎回 GROUP BY して集計していた（db/refresh_masters.sql を参照）
    $statement = $pdo->prepare('SELECT name FROM prefectures ORDER BY sort_order');
    $statement->execute();

    return $statement;
}


/**
 * 指定した都道府県の市区町村の一覧を取得する関数（市区町村マスタから）
 * @param PDO $pdo データベースに接続した PHP Data Object
 * @return PDOStatement 実行済みの SQL 文（fetch / fetchAll で結果を取り出す）
 */
function get_municipality(PDO $pdo, string $selected_prefecture): PDOStatement
{
    // 市区町村マスタから、指定した都道府県の市区町村を全国地方公共団体コード順に取得する
    $statement = $pdo->prepare(
        'SELECT m.name FROM municipalities m'
        . ' INNER JOIN prefectures p ON p.id = m.prefecture_id'
        . ' WHERE p.name = :prefecture ORDER BY m.sort_order'
    );
    $statement->bindValue(':prefecture', $selected_prefecture, PDO::PARAM_STR);
    $statement->execute();

    return $statement;
}


/**
 * 住所から郵便番号を検索するときの WHERE 句を作る（件数の取得と、1ページ分の取得で共通に使う）
 * @param string|null $town_area 町名（null のときは町名で絞り込まない）
 * @return string WHERE 句（WHERE は含まない）
 */
function post_code_condition(?string $town_area): string
{
    $condition = 'prefecture = :prefecture AND municipality = :municipality';
    if ($town_area !== null) {
        // 入力に含まれる % や _ をワイルドカードとして扱わないよう、escape_like() でエスケープしてから部分一致にする
        $condition .= " AND town_area LIKE :town_area ESCAPE '!'";
    }
    return $condition;
}


/**
 * post_code_condition() で作った WHERE 句のパラメータに値を割り当てる
 * @param PDOStatement $statement prepare 済みの SQL 文
 * @param string $prefecture 都道府県
 * @param string $municipality 市区町村
 * @param string|null $town_area 町名（null のときは町名で絞り込まない）
 */
function bind_post_code_condition(PDOStatement $statement, string $prefecture, string $municipality, ?string $town_area): void
{
    $statement->bindValue(':prefecture', $prefecture, PDO::PARAM_STR);
    $statement->bindValue(':municipality', $municipality, PDO::PARAM_STR);
    if ($town_area !== null) {
        $statement->bindValue(':town_area', '%' . escape_like($town_area) . '%', PDO::PARAM_STR);
    }
}


/**
 * 住所に一致する郵便番号の件数を数える関数（ページングの総件数に使う）
 * @param PDO $pdo データベースに接続した PHP Data Object
 * @param string $prefecture 都道府県
 * @param string $municipality 市区町村
 * @param string|null $town_area 町名（null のときは町名で絞り込まない）
 * @return int 件数
 */
function count_post_code(PDO $pdo, string $prefecture, string $municipality, ?string $town_area = null): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM post_codes WHERE ' . post_code_condition($town_area));
    bind_post_code_condition($statement, $prefecture, $municipality, $town_area);
    $statement->execute();

    $count = $statement->fetchColumn();
    return is_numeric($count) ? (int)$count : 0;
}


/**
 * 住所から郵便番号を検索する関数（1ページ分だけ取得する）
 * @param PDO $pdo データベースに接続した PHP Data Object
 * @param string $prefecture 都道府県
 * @param string $municipality 市区町村
 * @param string|null $town_area 町名（null のときは町名で絞り込まない）
 * @param int $limit 取得する最大件数（1ページの件数）
 * @param int $offset 先頭から何件飛ばすか（(ページ番号 - 1) × 1ページの件数）
 * @return PDOStatement 実行済みの SQL 文（fetch / fetchAll で結果を取り出す）
 */
function search_post_code(
    PDO $pdo,
    string $prefecture,
    string $municipality,
    ?string $town_area = null,
    int $limit = POST_CODE_PAGE_SIZE,
    int $offset = 0
): PDOStatement {
    // ORDER BY id で並び順を固定する（全国地方公共団体コード順）
    // 並び順が決まっていないと、ページを切り替えたときに同じ行が2回出たり、出ない行ができたりする
    $statement = $pdo->prepare(
        'SELECT post_code, prefecture, municipality, town_area FROM post_codes'
        . ' WHERE ' . post_code_condition($town_area)
        . ' ORDER BY id LIMIT :limit OFFSET :offset'
    );
    bind_post_code_condition($statement, $prefecture, $municipality, $town_area);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

    $statement->execute();

    return $statement;
}


/**
 * 郵便番号から住所を検索する関数
 * 引数を PostalCode 型にしているので、正規化・検証されていない文字列は渡せない（型で守る）
 * @param PDO $pdo データベースに接続した PHP Data Object
 * @param PostalCode $postal_code 郵便番号
 * @return PDOStatement 実行済みの SQL 文（fetch / fetchAll で結果を取り出す）
 */
function search_address(PDO $pdo, PostalCode $postal_code): PDOStatement
{
    // PostalCode は常に「ハイフンなしの7桁」を取り出せるので、ハイフンの有無で SQL を分ける必要がない
    $statement = $pdo->prepare(
        'SELECT post_code, prefecture, municipality, town_area FROM post_codes'
        . ' WHERE post_code_noHyphen = :post_code ORDER BY id'
    );
    $statement->bindValue(':post_code', $postal_code->digits(), PDO::PARAM_STR);
    $statement->execute();

    return $statement;
}
