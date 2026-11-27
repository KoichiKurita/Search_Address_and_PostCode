<?php

declare(strict_types=1);

/**
 * 郵便番号・住所検索(bin/import-postcodes.php)
 * 作成者：栗田幸一
 * 作成日：2026.11.27
 *
 * 郵便番号データを DB に取り込むコマンド（#35）
 *   使い方: php bin/import-postcodes.php <post_codes.csv>
 *   CSV は、db/ken_all_to_sql.py --format csv で作ったもの
 *
 *   本番: GitHub Actions（.github/workflows/update-postcodes.yml）が、毎月 SSH で実行する
 *   開発: make seed-full
 *
 *   終了コード: 0 … 取り込んだ / 1 … 取り込まなかった（DB は変わっていない）
 */

use App\Infrastructure\ImportException;
use App\Infrastructure\PostCodeImporter;

// Web から実行されないようにする（public/ の外にあるので URL では開けないが、念のため）
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

$csvPath = $argv[1] ?? '';
if ($csvPath === '') {
    fwrite(STDERR, "使い方: php bin/import-postcodes.php <post_codes.csv>\n");
    exit(1);
}

$started = microtime(true);
try {
    // 書き込むので、読み取り専用ではない接続を使う
    $importer = new PostCodeImporter(connect(readOnly: false), dirname(__DIR__) . '/db/refresh_masters.sql');
    $result = $importer->import($csvPath);
} catch (ImportException $e) {
    fwrite(STDERR, '取り込みを中止しました: ' . $e->getMessage() . "\n");
    exit(1);
}

// 結果（GitHub Actions のログに残る）
printf("取り込みました（%.1f 秒）: %d 件 → %d 件\n", microtime(true) - $started, $result->before, $result->after);
foreach (['追加' => $result->added, '廃止' => $result->removed] as $label => $codes) {
    $sample = implode(', ', array_slice($codes, 0, 20));
    printf("%sされた郵便番号: %d 件%s\n", $label, count($codes), $codes === [] ? '' : "（{$sample}" . (count($codes) > 20 ? ' …' : '') . '）');
}
