<?php

declare(strict_types=1);

/**
 * テストの前に1回だけ読み込まれるファイル（phpunit.xml の bootstrap で指定）
 *
 * テストするのは、DB の接続情報（config.php）に依存しない src/ の関数だけ。
 * そのため bootstrap.php ではなく、src/helpers.php を直接読み込む（src/ のクラスはオートローダーが読み込む）。
 * （Composer のオートローダーは vendor/bin/phpunit が自動で読み込む）
 */

require_once dirname(__DIR__) . '/src/helpers.php';
