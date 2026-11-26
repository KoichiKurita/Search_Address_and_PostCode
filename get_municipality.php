<?php
    declare(strict_types=1);

    /**
     * Webアプリ実践課題
     * 郵便番号検索(get_municipality.php)
     * 作成者：リンクス新越谷 栗田幸一
     * 作成日：2023.3.31
     * 修正日：2026.11.27
     *
     * JavaScript からの要求に応じて、都道府県に対応する市区町村の一覧を JSON で返す API
     *   リクエスト: POST、本文は JSON の文字列（例: "埼玉県"）
     *   レスポンス: 200 … 市区町村名の配列（例: ["さいたま市西区", ...]）
     *               400 … 本文が文字列でない
     *               405 … POST 以外のメソッド
     *               500 … DB エラー
     *   エラー時は {"error_msg": "..."} を返す
     */

    require_once dirname(__FILE__). '/functions.php';   // 外部関数読み込み

    // POST 以外（ブラウザで URL を直接開いた場合など）は受け付けない
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');      // 405 を返すときは、使えるメソッドを Allow ヘッダーで知らせる
        send_json(405, ['error_msg' => 'POST メソッドで送信してください。']);
    }

    // JavaScript から送られた値を読み込む（JSON として解釈できなければ null になる）
    $selected_prefecture = json_decode((string)file_get_contents('php://input'));

    // 文字列以外が送られた場合は、不正なリクエストとして 400 を返す
    if (!is_string($selected_prefecture) || $selected_prefecture === '') {
        send_json(400, ['error_msg' => '都道府県が正しく送信されていません。']);
    }

    // DBに接続し、市区町村一覧データを取得する
    try {
        $pdo = connect();

        // FETCH_COLUMN で、1列目（municipality）だけの配列として受け取る
        // JSON にはエスケープせずに入れる（HTML エスケープは、表示する側でだけ行う）
        $municipalities = get_municipality($pdo, $selected_prefecture)->fetchAll(PDO::FETCH_COLUMN);

    } catch (PDOException $e) {
        // DB の接続・検索でエラーが発生した場合は 500 を返す（詳細はサーバーログにのみ記録する）
        error_log(basename(__FILE__). ': '. $e->getMessage());
        send_json(500, ['error_msg' => '市区町村の取得に失敗しました。']);
    }

    send_json(200, $municipalities);
