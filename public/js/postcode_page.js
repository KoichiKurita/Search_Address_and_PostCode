/*
    郵便番号・住所検索(postcode_page.js)
    郵便番号検索の画面（/postcode）で読み込む
 */

import {setupMunicipalitySelect} from "./municipality.js";   // 都道府県と市区町村のプルダウンの連動

// 都道府県と市区町村のプルダウンを連動させる
// 検索した市区町村は、PHP が data-selected 属性に埋め込んでいるので、それを初期選択にする（入力画面では空）
document.addEventListener('DOMContentLoaded', () => {
    const municipalitySelect = document.getElementById('municipality');
    setupMunicipalitySelect(municipalitySelect.dataset.selected ?? '');
});
