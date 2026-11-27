/*
    郵便番号・住所検索(address_page.js)
    住所検索の画面（/address）で読み込む
    入力値の復元は PHP 側（テンプレートの value 属性）で行うので、ここでは送信前の入力チェックだけを設定する
 */

import {setupPostCodeForm} from "./postcode.js";   // 郵便番号の正規化・書式チェック

document.addEventListener('DOMContentLoaded', setupPostCodeForm);
