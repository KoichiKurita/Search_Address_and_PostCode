<?php

declare(strict_types=1);

/**
 * PHP-CS-Fixer（コードの書き方をそろえるツール）の設定
 *   make fmt       … 自動で整形する
 *   make fmt-check … 整形が必要な箇所を表示するだけ（CI で実行）
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'docker', 'db'])
    // Git 管理外・サーバーごとに異なる設定ファイルは対象外
    ->notPath(['config.php', 'config_dummy.php'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,                                  // PHP の標準的なコーディング規約
        'concat_space' => ['spacing' => 'one'],            // 文字列の連結「.」の前後にスペースを1つ
        'no_unused_imports' => true,                       // 使っていない use を消す
        'ordered_imports' => ['sort_algorithm' => 'alpha'], // use をアルファベット順に並べる
        'single_quote' => true,                            // 変数を展開しない文字列はシングルクォートにする
    ])
    ->setFinder($finder);
