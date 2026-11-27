/*
    郵便番号・住所検索(postcode.js)
    郵便番号の正規化・書式チェック
    住所検索の画面（/address）で使う
 */

/**
 * 郵便番号の書式：数字3桁 + ハイフン（省略可）+ 数字4桁
 * ^ と $ で「文字列全体」がこの形かを検査する（付けないと abc1234567xyz も一致してしまう）
 */
const PATTERN_POST_CODE = /^[0-9]{3}-?[0-9]{4}$/u;

/**
 * 「ハイフン」として扱う文字（PHP 側の App\Domain\PostalCode::HYPHEN_PATTERN と同じもの）
 * g フラグを付けているので、replace() で一致した箇所をすべて置換する
 * U+4E00（一 漢数字のいち）は意図的に含めている（日本語入力で「-」のつもりで「一」に変換されることがあるため）
 */
const PATTERN_HYPHEN_ALL = /[\u{30FC}\u{2010}-\u{2015}\u{2212}\u{FF70}\u{2053}\u{2E3A}\u{301C}\u{3030}\u{2500}\u{4E00}\u{FE63}\u{FF0D}\u{2043}\u{FE58}\u{23AF}\u{208B}\u{007E}]/gu;

/**
 * 全角数字（０〜９）を半角数字にする
 * 全角数字の文字コードは、半角数字より 0xFEE0 だけ大きいことを利用する
 * @param {string} value 変換する文字列
 * @return {string} 変換した文字列
 */
const toHalfWidthDigits = (value) =>
    value.replace(/[０-９]/g, (digit) => String.fromCharCode(digit.charCodeAt(0) - 0xFEE0));

/**
 * 郵便番号を正規化する
 * 全角数字を半角にし、前後の空白（全角スペースを含む）を取り除き、各種ハイフンを半角「-」にそろえる
 * 例: 「３３０ー００６３」→「330-0063」
 * @param {string} value 入力された郵便番号
 * @return {string} 正規化した郵便番号
 */
export const normalizePostCode = (value) => toHalfWidthDigits(value).trim().replace(PATTERN_HYPHEN_ALL, '-');

/**
 * 郵便番号の書式が正しいかを判定する
 * @param {string} value 正規化済みの郵便番号
 * @return {boolean} 正しい書式なら true
 */
export const isValidPostCode = (value) => PATTERN_POST_CODE.test(value);

/**
 * 住所検索フォームに「送信前の正規化と書式チェック」を設定する
 * ボタンの click ではなく form の submit で処理するので、Enter キーでの送信にも効く
 */
export const setupPostCodeForm = () => {
    const form = document.forms['search-address'];
    const input = document.getElementById('post-code');
    const errorMessage = document.getElementById('error-message');

    form.addEventListener('submit', (event) => {
        // 送信する値そのものを正規化した値に書き換える
        input.value = normalizePostCode(input.value);

        if (!isValidPostCode(input.value)) {
            errorMessage.textContent = '郵便番号は7桁の数字で入力してください（例：330-0063）';
            event.preventDefault();     // 結果表示ページ(result_address.php)に遷移しない
            input.focus();
            return;
        }

        errorMessage.textContent = '';
    });
};
