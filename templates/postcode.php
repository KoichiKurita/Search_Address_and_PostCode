<?php
/**
 * 郵便番号検索（住所 → 郵便番号）の画面  入力画面と検索結果の画面で共通
 *
 * @var list<string> $prefectures 都道府県の一覧（プルダウンの選択肢）
 * @var string $prefecture 検索した都道府県
 * @var string $municipality 検索した市区町村
 * @var string $townArea 検索した町名
 * @var bool $showResult 検索結果を表示するか（入力画面と、入力エラーのときは false）
 * @var list<\App\Domain\Address> $addresses 検索結果（表示するページの分だけ）
 * @var int $total 検索結果の総件数
 * @var int $page 表示しているページ番号
 * @var int $lastPage 最後のページ番号
 * @var int $offset 表示しているページの先頭が、全体の何件目の次か
 * @var bool $isFallback 町名で見つからず、市区町村全体の結果を表示しているか
 * @var string $errorMessage 入力エラーのメッセージ（空ならエラーなし）
 * @var \Closure(int): string $pageUrl 同じ検索条件で、指定したページを表示する URL
 */
?>
<h1>郵便番号検索</h1>
<p id="direction">都道府県, 市区町村, 町名を入力してください。住所検索は<a href="address">こちら</a></p>
<form name="search-post-code" action="postcode" method="GET">
    <div class="address-input-wrapper">
        <div class="address-input">
            <label for="prefecture">都道府県<span class="required">（必須）</span>：
                <select name="prefecture" id="prefecture" required>
                    <option value="">選択してください</option>
                    <?php foreach ($prefectures as $name): ?>
                        <!-- 検索した都道府県を初期表示する -->
                        <option value="<?=escape($name)?>" <?=$name === $prefecture ? 'selected' : ''?>><?=escape($name)?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="address-input">
            <label for="municipality">市区町村<span class="required">（必須）</span>：
                <!-- 検索した市区町村を data-selected に埋め込み、JS で選択肢を作るときに初期選択にする -->
                <select name="municipality" id="municipality" data-selected="<?=escape($municipality)?>" disabled required>
                    <option value="">選択してください</option>
                </select>
            </label>
        </div>

        <div class="address-input">
            <label for="town_area">町名（漢字またはフリガナ）：
                <!-- maxlength はブラウザ側の制限。サーバー側でも同じ文字数で検証している -->
                <input type="text" name="town_area" id="town_area" value="<?=escape($townArea)?>" maxlength="<?=TOWN_AREA_MAX_LENGTH?>">
            </label>
        </div>
    </div>

    <button type="submit">検索開始</button>
</form>

<!-- 入力エラーや、市区町村の取得に失敗したときのエラーメッセージ（JS とサーバーの両方が使う） -->
<p id="error-message" role="alert"><?=escape($errorMessage)?></p>

<?php if ($showResult): ?>
    <hr class="divider">

    <h1 id="result_heading">住所「<?=escape($prefecture . $municipality . $townArea)?>」の郵便番号の検索結果：<?=$total?>件</h1>

    <?php if ($total > 0): ?>
        <!-- 何件目から何件目までを表示しているか -->
        <p class="result-range"><?=$offset + 1?>〜<?=$offset + count($addresses)?>件目を表示（<?=$page?> / <?=$lastPage?> ページ）</p>
    <?php endif; ?>
    <?php if ($isFallback): ?>
        <!-- 町名では見つからず、市区町村全体の結果を表示していることを利用者に知らせる -->
        <p class="notice">町名「<?=escape($townArea)?>」に一致する住所が見つからなかったため、<?=escape($municipality)?>全体の結果を表示しています。</p>
    <?php endif; ?>

    <table class="result-table">
        <tr>
            <th>郵便番号</th>
            <th>都道府県</th>
            <th>市区町村</th>
            <th>町名</th>
            <th>町名のフリガナ</th>
        </tr>

        <?php foreach ($addresses as $address): ?>
        <tr>
            <td><?=escape($address->postalCode->withHyphen())?></td>
            <td><?=escape($address->prefecture)?></td>
            <td><?=escape($address->municipality)?></td>
            <td><?=escape($address->townArea)?></td>
            <td><?=escape($address->townAreaKana)?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if ($lastPage > 1): ?>
        <!-- ページ切り替え（現在のページはリンクにせず、aria-current で読み上げソフトにも伝える） -->
        <nav class="pagination" aria-label="検索結果のページ">
            <?php if ($page > 1): ?>
                <a href="<?=escape($pageUrl($page - 1))?>" rel="prev">前へ</a>
            <?php endif; ?>

            <?php foreach (pagination_pages($page, $lastPage) as $number): ?>
                <?php if ($number === null): ?>
                    <span class="ellipsis">…</span>
                <?php elseif ($number === $page): ?>
                    <span aria-current="page"><?=$number?></span>
                <?php else: ?>
                    <a href="<?=escape($pageUrl($number))?>"><?=$number?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($page < $lastPage): ?>
                <a href="<?=escape($pageUrl($page + 1))?>" rel="next">次へ</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <a href="postcode">郵便番号検索トップへ戻る</a>
<?php endif; ?>
