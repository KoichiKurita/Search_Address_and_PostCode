<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RuntimeException;

/**
 * 郵便番号データを取り込めなかった（データの誤り・件数の異常など）ときの例外
 * この例外が出たときは、DB は取り込む前のまま変わっていない
 */
final class ImportException extends RuntimeException
{
}
