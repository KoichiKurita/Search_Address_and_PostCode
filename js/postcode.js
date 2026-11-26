/*
    郵便番号・住所検索(postcode.js)
    郵便番号の正規化・書式チェック
    住所検索フォーム（search_address.html / result_address.php）で共通して使う
 */

import {PATTERN_HYPHEN} from "./config.js";

/**
 * 郵便番号の書式：数字3桁 + ハイフン（省略可）+ 数字4桁
 * ^ と $ で「文字列全体」がこの形かを検査する（付けないと abc1234567xyz も一致してしまう）
 */
const PATTERN_POST_CODE = /^[0-9]{3}-?[0-9]{4}$/u;

/**
 * config.js のハイフンのパターンに g フラグを付けたもの
 * g が無いと replace() は最初に見つかった1文字しか置換しない
 */
const PATTERN_HYPHEN_ALL = new RegExp(PATTERN_HYPHEN.source, 'gu');

/**
 * 郵便番号を正規化する
 * 前後の空白を取り除き、全角ハイフン・長音記号などの各種ハイフンを半角ハイフン（U+002D）にそろえる
 * @param {string} value 入力された郵便番号
 * @return {string} 正規化した郵便番号
 */
export const normalizePostCode = (value) => value.trim().replace(PATTERN_HYPHEN_ALL, '-');

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
