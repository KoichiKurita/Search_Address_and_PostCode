/*
    Webアプリ実践課題
    住所検索(result_address_script.js)
    作成者：リンクス新越谷 栗田幸一
    作成日：2023.3.31
    修正日：2026.11.27
 */

import {setupPostCodeForm} from "./postcode.js";   // 郵便番号の正規化・書式チェック

// 入力値の復元は PHP 側（result_address.php の value 属性）で行うので、ここでは入力チェックだけを設定する
document.addEventListener('DOMContentLoaded', setupPostCodeForm);
