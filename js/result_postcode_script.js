/*
    Webアプリ実践課題
    郵便番号検索(result_postcode_script.js)
    作成者：リンクス新越谷 栗田幸一
    作成日：2023.3.31
    修正日：2026.11.27
 */

import {setupMunicipalitySelect} from "./municipality.js";   // 都道府県と市区町村のプルダウンの連動

// Webページロード時に、都道府県と市区町村のプルダウンを連動させる
// 検索した市区町村は、PHP が data-selected 属性に埋め込んでいるので、それを初期選択にする
document.addEventListener('DOMContentLoaded', () => {
    const municipalitySelect = document.getElementById('municipality');
    setupMunicipalitySelect(municipalitySelect.dataset.selected ?? '');
});
