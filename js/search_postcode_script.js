/*
    Webアプリ実践課題
    郵便番号検索(search_postcode_script.js)
    作成者：リンクス新越谷 栗田幸一
    作成日：2023.3.3
    修正日：2026.11.27
 */

import {setupMunicipalitySelect} from "./municipality.js";   // 都道府県と市区町村のプルダウンの連動

// Webページロード時に、都道府県と市区町村のプルダウンを連動させる
document.addEventListener('DOMContentLoaded', () => setupMunicipalitySelect());
