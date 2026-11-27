<?php
    declare(strict_types=1);

    /**
     * 郵便番号・住所検索(src/helpers.php)
     * 作成者：栗田幸一
     * 作成日：2026.11.27
     *
     * DB や画面に依存しない関数と定数（入力の正規化・検証・エスケープ）
     * 入力と出力だけで結果が決まる「純粋な関数」なので、PHPUnit で単体テストしやすい
     * functions.php とテスト（tests/bootstrap.php）の両方から読み込む
     */

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
     * HTMLエスケープする関数（画面に出力する直前に使う）
     * @param mixed $value 出力する値（数値なども文字列にしてからエスケープする）
     * @return string エスケープ処理した文字列を返します。
     */
    function escape(mixed $value): string
    {
        return htmlspecialchars(strval($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
