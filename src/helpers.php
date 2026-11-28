<?php

declare(strict_types=1);

/**
 * 郵便番号・住所検索(src/helpers.php)
 * 作成者：栗田幸一
 * 作成日：2026.11.27
 *
 * DB や画面に依存しない関数と定数（入力の取り出し・エスケープ・ページングの計算など）
 * 郵便番号の正規化・検証は App\Domain\PostalCode が行う
 * 入力と出力だけで結果が決まる「純粋な関数」なので、PHPUnit で単体テストしやすい
 * bootstrap.php とテスト（tests/bootstrap.php）の両方から読み込む
 */

/**
 * 町名の入力の最大文字数（これより長い入力は不正な値として扱う）
 */
const TOWN_AREA_MAX_LENGTH = 50;

/**
 * 郵便番号検索の結果を、1ページに何件ずつ表示するか
 */
const POST_CODE_PAGE_SIZE = 50;

/**
 * クエリパラメータ（$_GET など）から、文字列の値を取り出す
 * 「?post-code[]=1」のように配列が送られた場合や、パラメータが無い場合は空文字を返す
 * （(string) で型変換すると、配列のときに警告が出て「Array」という文字列になってしまうため）
 * @param array<mixed> $query クエリパラメータの配列（通常は $_GET）
 * @param string $name パラメータ名
 * @return string パラメータの値（文字列でなければ空文字）
 */
function query_string(array $query, string $name): string
{
    $value = $query[$name] ?? '';
    return is_string($value) ? $value : '';
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
 * ひらがな・半角カタカナを、全角カタカナにする（フリガナで検索するときに、入力の揺れをそろえる）
 * 例: 「たかさご」「ﾀｶｻｺﾞ」→「タカサゴ」
 *   K: 半角カタカナ → 全角カタカナ / V: 濁点・半濁点を1文字にまとめる（ｺﾞ → ゴ） / C: ひらがな → カタカナ
 * @param string $value 入力された文字列
 * @return string 全角カタカナにそろえた文字列（漢字・英数字はそのまま）
 */
function to_katakana(string $value): string
{
    return mb_convert_kana($value, 'KVC', 'UTF-8');
}


/**
 * HTMLエスケープする関数（画面に出力する直前に使う）
 * @param mixed $value 出力する値（数値なども文字列にしてからエスケープする）
 * @return string エスケープ処理した文字列を返します。
 */
function escape(mixed $value): string
{
    // 文字列・数値・真偽値・null 以外（配列やオブジェクト）は表示できないので、空文字にする
    if (!is_scalar($value) && $value !== null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}


/**
 * ページ番号の入力値を、1 以上の整数にする
 * 数字以外・0・空文字などの不正な値は 1 ページ目として扱う
 * @param string $value クエリパラメータ（page）の値
 * @return int ページ番号（1 以上）
 */
function parse_page(string $value): int
{
    // ctype_digit は「すべて 0〜9 の数字か」を判定する（マイナス記号や小数点、空文字は false）
    // 桁数が多すぎる値で整数が溢れないよう、6桁までに制限する
    if (!ctype_digit($value) || strlen($value) > 6) {
        return 1;
    }
    return max(1, (int)$value);
}


/**
 * 総件数と1ページの件数から、最後のページ番号を求める（0件のときも 1 ページとする）
 * @param int $total 総件数
 * @param int $per_page 1ページの件数
 * @return int 最後のページ番号（1 以上）
 */
function last_page(int $total, int $per_page): int
{
    return max(1, intdiv($total + $per_page - 1, $per_page));
}


/**
 * ページ切り替えのリンクに表示するページ番号の一覧を作る
 * 最初・最後のページと、現在のページの前後 2 ページを表示し、間は省略（null）にする
 * 例: 現在 6 / 最後 20 → [1, null, 4, 5, 6, 7, 8, null, 20]
 * @param int $current 現在のページ番号
 * @param int $last 最後のページ番号
 * @return list<int|null> ページ番号の一覧（null は「…」で表示する省略部分）
 */
function pagination_pages(int $current, int $last): array
{
    $pages = [];
    $previous = 0;
    for ($page = 1; $page <= $last; $page++) {
        $is_edge = $page === 1 || $page === $last;
        $is_near = abs($page - $current) <= 2;
        if (!$is_edge && !$is_near) {
            continue;
        }
        if ($page - $previous > 1) {
            $pages[] = null;    // 前に表示したページと離れているので、間に「…」を入れる
        }
        $pages[] = $page;
        $previous = $page;
    }
    return $pages;
}
