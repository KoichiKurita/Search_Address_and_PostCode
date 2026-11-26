/*
    Docker 開発環境用の設定ファイル
    docker-compose.yml で /var/www/html/js/config.js に重ねてマウントされる。
 */

// 市区町村取得処理を行うphpファイルのファイルパス
const PATH_GET_MUNICIPALITY_PROCESS = "get_municipality.php";

// エクスポート
export {PATH_GET_MUNICIPALITY_PROCESS};
