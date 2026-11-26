/*
    Webアプリ実践課題
    住所検索(search_address_script.js)
    作成者：リンクス新越谷 栗田幸一
    作成日：2023.3.31
    修正日：2026.11.27
 */

import {setupPostCodeForm} from "./postcode.js";   // 郵便番号の正規化・書式チェック

// Webページロード時に、検索フォームの入力チェックを設定する
document.addEventListener('DOMContentLoaded', setupPostCodeForm);
