<?php
/**
 * 住所検索（郵便番号 → 住所）の画面  入力画面と検索結果の画面で共通
 *
 * @var string $input 入力された郵便番号（正規化済み。入力欄に残す）
 * @var \App\Domain\PostalCode|null $postalCode 検索した郵便番号（入力画面と、入力エラーのときは null）
 * @var list<\App\Domain\Address> $addresses 検索結果
 * @var list<\App\Domain\Office> $offices 検索結果（事業所の個別郵便番号。#36）
 * @var string $errorMessage 入力エラーのメッセージ（空ならエラーなし）
 */
?>
<h1>住所検索</h1>
<p id="direction">郵便番号を入力してください。郵便番号検索は<a href="postcode">こちら</a></p>
<p class="note">注：7桁の数字で入力してください。ハイフン（-）は有り・無しどちらでも検索可能です。</p>
<form name="search-address" action="address" method="GET">
    <div class="post-code-input-wrapper">
        <div class="post-code-input">
            <label for="post-code">郵便番号<span class="required">（必須）</span>：
                <!-- 検索した郵便番号を入力欄に残す（JS ではなく PHP で値を埋め込む） -->
                <input type="text" name="post-code" id="post-code" value="<?=escape($input)?>" required>
            </label>
        </div>
    </div>

    <button type="submit" id="button">検索開始</button>
</form>

<!-- 郵便番号の書式が誤っている場合のエラーメッセージ（JS とサーバーの両方が使う） -->
<p id="error-message" role="alert"><?=escape($errorMessage)?></p>

<?php if ($postalCode !== null): ?>
    <hr class="divider">

    <!-- 見出しは、入力の形（ハイフンの有無）に関係なく「330-0063」の形にそろえる -->
    <h1 id="result_heading">郵便番号「<?=escape($postalCode->withHyphen())?>」の住所の検索結果：<?=count($addresses) + count($offices)?>件</h1>
    <?php if ($offices === [] || $addresses !== []): ?>
        <table class="result-table">
            <tr>
                <th>郵便番号</th>
                <th>住所</th>
                <th>フリガナ</th>
            </tr>

            <?php foreach ($addresses as $address): ?>
            <tr>
                <td><?=escape($address->postalCode->withHyphen())?></td>
                <td><?=escape($address->fullAddress())?></td>
                <td><?=escape($address->fullAddressKana())?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if ($offices !== []): ?>
        <!-- 事業所の個別郵便番号（大口事業所・私書箱だけの郵便番号）の場合 -->
        <p class="notice">この郵便番号は、事業所の個別郵便番号です。郵便番号だけで、この事業所に届きます。</p>
        <table class="result-table">
            <tr>
                <th>郵便番号</th>
                <th>事業所名</th>
                <th>住所</th>
                <th>種別</th>
            </tr>

            <?php foreach ($offices as $office): ?>
            <tr>
                <td><?=escape($office->postalCode->withHyphen())?></td>
                <td><?=escape($office->name)?><br><small><?=escape($office->nameKana)?></small></td>
                <td><?=escape($office->fullAddress())?></td>
                <td><?=$office->isPoBox ? '私書箱' : '大口事業所'?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <a href="address">住所検索トップへ戻る</a>
<?php endif; ?>
