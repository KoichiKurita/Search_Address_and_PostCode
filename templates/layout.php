<?php
/**
 * すべての画面に共通のレイアウト（<head>・ナビゲーション・JavaScript の読み込み）
 * 各画面のテンプレートで作った HTML が $content に入る
 *
 * @var string $basePath アプリを置いている URL のパス（例: "/postcode"）
 * @var string $path 表示している画面のパス（例: "address"。ナビゲーションで今いる画面を示すのに使う）
 * @var string $title ページのタイトル
 * @var string $content 画面ごとの HTML（テンプレートの中でエスケープ済み）
 * @var string|null $script 読み込む JavaScript（無ければ null）
 */
?>
<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- 相対パスのリンク（css/・js/・address など）の基準を、アプリを置いている場所にする -->
        <!-- これで、どの URL の画面からでも同じ書き方でリンクできる（例: 404 になった /postcode/a/b の画面でも CSS が読み込める） -->
        <base href="<?=escape($basePath)?>/">
        <title><?=escape($title)?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>

    <body>
        <!-- すべての画面に共通のナビゲーション（今いる画面は aria-current="page" で示す） -->
        <nav class="site-nav" aria-label="サイト内のページ">
            <?php foreach (['address' => '住所検索', 'postcode' => '郵便番号検索', 'autofill' => '住所の自動入力デモ'] as $href => $label): ?>
                <a href="<?=$href?>"<?=$href === $path ? ' aria-current="page"' : ''?>><?=$label?></a>
            <?php endforeach; ?>
        </nav>
        
        <?=$content?>

        <?php if ($script !== null): ?>
            <script src="<?=escape($script)?>" type="module"></script>
        <?php endif; ?>
    </body>
</html>
