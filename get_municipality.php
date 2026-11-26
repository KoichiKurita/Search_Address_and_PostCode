<?php
    declare(strict_types=1);

    /**
     * Webアプリ実践課題
     * 郵便番号検索(get_municipality.php)
     * 作成者：リンクス新越谷 栗田幸一
     * 作成日：2023.3.31
     * 修正日：2023.4.8
     */

    // javascriptからの要求に応じて、都道府県から対応する市区町村一覧データを返す
    require_once dirname(__FILE__). '/functions.php';   // 外部関数読み込み

    // javascriptからの値の読み込み
    $raw = file_get_contents('php://input');
    $selected_prefecture = json_decode($raw);

    // 文字列以外が送られた場合は空配列を返す（strict_types下でのTypeError防止）
    if (!is_string($selected_prefecture)) {
        echo json_encode(array());
        exit;
    }

    // DBに接続し、市区町村一覧データを取得する
    try {

        $pdo = connect();

        $statement = get_municipality($pdo, $selected_prefecture);

        // 取得した市区町村一覧データを配列化する
        // JSON にはエスケープせずに入れる（HTML エスケープは、表示する側でだけ行う）
        // JS 側は new Option() で文字列として扱うので、ここでエスケープすると「&amp;」のように二重になる
        $municipalities = array();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $municipalities[] = $row['municipality'];
        }

        // 市区町村一覧データをJSON文字列に変換し、javascript 側へ返す
        echo json_encode($municipalities);

    } catch (PDOException $e) {
        // データベースの接続, 検索でエラーが発生した場合、javascript 側にエラーメッセージを返す
        $error_msg = array();
        error_log('get_municipality: '. $e->getMessage());   // 詳細はサーバーログにのみ記録する
        $error_msg['error_msg'] = '市区町村の取得に失敗しました。';
        echo json_encode($error_msg);
    }
?>
