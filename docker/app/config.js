/*
    Docker 開発環境用の設定ファイル
    docker-compose.yml で /var/www/html/js/config.js に重ねてマウントされる。
 */

// 市区町村取得処理を行うphpファイルのファイルパス
const PATH_GET_MUNICIPALITY_PROCESS = "get_municipality.php";

// ハイフンのパターンマッチ文字列（js/config_dummy.js と同じもの）
const PATTERN_HYPHEN = /[\u{30FC}\u{2010}-\u{2015}\u{2212}\u{FF70}\u{2053}\u{2E3A}\u{301C}\u{3030}\u{2500}\u{4E00}\u{FE63}\u{FF0D}\u{2043}\u{FE58}\u{23AF}\u{208B}\u{007E}]/u;

// エクスポート
export {PATH_GET_MUNICIPALITY_PROCESS, PATTERN_HYPHEN};
