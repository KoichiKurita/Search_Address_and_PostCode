<?php
    declare(strict_types=1);

    /**
     * Webアプリ実践課題
     * 郵便番号・住所検索(functions.php)
     * 作成者：リンクス新越谷 栗田幸一
     * 作成日：2023.3.31
     * 修正日：2023.5.25
     */

    // DBの認証情報の読み込み
    require_once dirname(__FILE__). '/config.php';

    /**
     * PDOインスタンスを取得する関数
     * @return pdo PHP Data Object を返します。
     */
    function connect() :pdo
    {
        $pdo = new PDO(DB_NAME, DB_USER, DB_PASS);    // DB接続
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);  // SQLをプリコンパイルしてから実行。処理速度向上とSQLインジェクション対策
        return $pdo;
    }


    /**
     * 都道府県を郵便番号データベースから取得する関数
     * @param pdo $pdo データベースに接続した PHP Data Object
     * @return PDO::prepare SQL statement を返します
     */
    function get_prefecture(PDO $pdo) :PDOStatement
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
     * @param pdo $pdo データベースに接続した PHP Data Object
     * @return PDO::prepare SQL statement を返します
     */
    function get_municipality(PDO $pdo, string $selected_prefecture) :PDOStatement
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
     * @param pdo $pdo データベースに接続した PHP Data Object
     * @param string $prefecture 都道府県
     * @param string $municipality 市区町村
     * @param string $town_area=null 町名
     * @return PDO::prepare SQL statement を返します
     */
    function search_post_code(PDO $pdo, string $prefecture, string $municipality, ?string $town_area = null) :PDOStatement
    {
        // 町名（town_area）の有無でSQL文を変える
        if ($town_area === null) {  // 町名がない場合
            // SQL検索結果には結果表示に必要な列のみ取得する（動作の高速化）
            $statement = $pdo->prepare('SELECT post_code, prefecture, municipality, town_area FROM post_codes WHERE prefecture = :prefecture AND municipality = :municipality');
        } else {    // 町名がある場合
            $statement = $pdo->prepare('SELECT post_code, prefecture, municipality, town_area FROM post_codes WHERE prefecture = :prefecture AND municipality = :municipality AND town_area LIKE :town_area');
            $statement->bindValue(':town_area', '%'. $town_area. '%', PDO::PARAM_STR);
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
     * @param pdo $pdo データベースに接続した PHP Data Object
     * @param string $post_code 郵便番号
     * @return PDO::prepare SQL statement を返します
     */
    function search_address(PDO $pdo, string $post_code) :PDOStatement
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


    /**
     * HTMLエスケープする関数
     * @param mixed $value
     * @return string エスケープ処理した文字列を返します。
     */
    function escape($value)
    {
        return htmlspecialchars(strval($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
?>
