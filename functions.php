<?php
    declare(strict_types=1);

    /**
     * Webアプリ実践課題
     * 郵便番号・住所検索(functions.php)
     * 作成者：リンクス新越谷 栗田幸一
     * 作成日：2023.3.31
     * 修正日：2026.11.27
     *
     * ※ PHP だけのファイルなので、最後の「?>」は書かない（PSR-12）。
     *   「?>」の後ろに改行や空白があると、それが画面に出力されてしまい、
     *   ステータスコードやヘッダーを送れなくなる原因になるため。
     */

    // DBの認証情報の読み込み
    require_once dirname(__FILE__). '/config.php';

    /**
     * 町名の入力の最大文字数（これより長い入力は不正な値として扱う）
     */
    const TOWN_AREA_MAX_LENGTH = 50;

    /**
     * 郵便番号の入力で「ハイフン」として扱う文字の正規表現
     * 環境によって変わらない値なので、config.php ではなくコードに置く
     *
     * U+30FC（ー 長音記号）、U+2010〜U+2015（各種ダッシュ）、U+2212（− マイナス）、U+FF0D（－ 全角ハイフン）など
     * U+4E00（一 漢数字のいち）は意図的に含めている。
     *   日本語入力で「-」のつもりで「一」に変換されてしまうことがあり、
     *   郵便番号は数字だけなので「一」を「-」に置き換えても困ることがないため。
     *
     * 参考:
     * https://qiita.com/ryounagaoka/items/4cf5191d1a2763667add
     * https://hydrocul.github.io/wiki/blog/2014/1101-hyphen-minus-wave-tilde.html
     */
    const POST_CODE_HYPHEN_PATTERN = '/[\x{30FC}\x{2010}-\x{2015}\x{2212}\x{FF70}\x{2053}\x{2E3A}\x{301C}\x{3030}\x{2500}\x{4E00}\x{FE63}\x{FF0D}\x{2043}\x{FE58}\x{23AF}\x{208B}\x{007E}]/u';


    /**
     * 入力された郵便番号を正規化する
     * 全角数字・全角スペースを半角にし、前後の空白を取り除き、各種ハイフンを半角「-」にそろえる
     * 例: 「３３０ー００６３」→「330-0063」
     * @param string $value 入力された郵便番号
     * @return string 正規化した郵便番号
     */
    function normalize_post_code(string $value): string
    {
        // mb_convert_kana の n は「全角数字→半角」、s は「全角スペース→半角」
        $value = trim(mb_convert_kana($value, 'ns', 'UTF-8'));

        // preg_replace は一致した箇所をすべて置換する。正規表現の実行に失敗したときは null が返るので空文字にする
        return preg_replace(POST_CODE_HYPHEN_PATTERN, '-', $value) ?? '';
    }


    /**
     * 郵便番号の書式が正しいかを判定する（数字3桁 + ハイフン（省略可）+ 数字4桁）
     * JS 側でも同じチェックをしているが、URL を直接書き換えれば JS のチェックは通らずに送れるため、
     * サーバー側でも必ず検証する
     * @param string $post_code 正規化済みの郵便番号
     * @return bool 正しい書式なら true
     */
    function is_valid_post_code(string $post_code): bool
    {
        return preg_match('/^[0-9]{3}-?[0-9]{4}$/', $post_code) === 1;
    }


    /**
     * LIKE 検索で使う文字列のワイルドカード（% と _）を、普通の文字として扱えるようにエスケープする
     * エスケープ文字には「!」を使い、SQL 側で ESCAPE '!' を指定する
     * （バックスラッシュは、DB の設定や PHP の文字列でも特別な意味を持ち、間違えやすいため使わない）
     * 例: 「10%」→「10!%」
     * @param string $value 検索する文字列
     * @return string エスケープした文字列
     */
    function escape_like(string $value): string
    {
        // 先にエスケープ文字「!」自体をエスケープしてから、% と _ をエスケープする
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

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
            // 入力に含まれる % や _ をワイルドカードとして扱わないよう、escape_like() でエスケープしてから部分一致にする
            $statement = $pdo->prepare("SELECT post_code, prefecture, municipality, town_area FROM post_codes WHERE prefecture = :prefecture AND municipality = :municipality AND town_area LIKE :town_area ESCAPE '!'");
            $statement->bindValue(':town_area', '%'. escape_like($town_area). '%', PDO::PARAM_STR);
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
