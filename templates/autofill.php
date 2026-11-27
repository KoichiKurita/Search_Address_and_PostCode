<?php
/**
 * 住所の自動入力デモ（#34）  GET /autofill
 * 郵便番号を入力すると、JSON API（/api/address）を使って住所の欄を埋める。画面の処理は js/autofill_page.js
 * このテンプレートが受け取る変数はない
 */
?>
<h1>住所の自動入力デモ</h1>
<p>郵便番号を入力すると、<a href="api/address?zip=330-0063">住所の JSON API</a> を使って、住所の欄を自動で埋めます。</p>
<p class="note">注：デモなので、このフォームは送信されません。</p>

<form id="autofill-form" class="autofill-form">
    <!-- autocomplete に住所の種類を指定すると、ブラウザの自動入力（保存した住所）も正しい欄に入る -->
    <label for="zip">郵便番号：
        <input type="text" id="zip" name="zip" inputmode="numeric" autocomplete="postal-code" placeholder="例：330-0063" maxlength="8">
    </label>

    <!-- 検索中・見つからない・候補が複数、などの状態を表示する（role="status" で読み上げソフトにも伝わる） -->
    <p id="autofill-status" role="status"></p>

    <!-- 候補が多いときだけ、町域の名前で絞り込む入力欄を出す（例: 452-0961 は66件） -->
    <label for="candidate-filter" id="candidate-filter-label" hidden>候補を絞り込む：
        <input type="search" id="candidate-filter" placeholder="町域の名前の一部（例：一番）" aria-describedby="candidate-count">
    </label>
    <p id="candidate-count" class="candidate-count" aria-live="polite" hidden></p>

    <!-- 1つの郵便番号に複数の町域がある場合は、ここに候補のボタンを並べる -->
    <ul id="autofill-candidates" class="candidates" hidden></ul>

    <label for="prefecture">都道府県：
        <input type="text" id="prefecture" name="prefecture" autocomplete="address-level1">
    </label>
    <label for="municipality">市区町村：
        <input type="text" id="municipality" name="municipality" autocomplete="address-level2">
    </label>
    <label for="town_area">町域：
        <input type="text" id="town_area" name="town_area" autocomplete="address-line1">
    </label>
    <label for="street">番地・建物名：
        <input type="text" id="street" name="street" autocomplete="address-line2" placeholder="例：1-2-3 〇〇マンション101">
    </label>
</form>

<h2>API の使い方</h2>
<p>ほかのサイトの JavaScript からも、次のように使えます（CORS を許可しています）。</p>
<pre class="code"><code>const response = await fetch('https://graycow428.sakura.ne.jp/postcode/api/address?zip=330-0063');
const data = await response.json();
console.log(data.addresses[0].full_address);   // "埼玉県さいたま市浦和区高砂"</code></pre>
