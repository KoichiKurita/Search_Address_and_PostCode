<?php

declare(strict_types=1);

/**
 * 郵便番号・住所検索(public/index.php)
 * 作成者：栗田幸一
 * 作成日：2026.11.27
 *
 * フロントコントローラ（すべてのリクエストの入口）
 *   public/ だけを公開し、ファイルとして存在しない URL（/address など）は、.htaccess がすべてこのファイルに送る。
 *   設定ファイル・src/・vendor/ などは public/ の外にあるので、URL から開かれることはない。
 */

use App\Http\Application;
use App\Http\Request;
use App\Http\View;
use App\Infrastructure\PdoPostCodeRepository;

require_once dirname(__DIR__) . '/bootstrap.php';

$application = new Application(
    static fn (): PdoPostCodeRepository => new PdoPostCodeRepository(connect()),
    new View(dirname(__DIR__) . '/templates'),
);

$application->handle(Request::fromGlobals())->send();
