<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * 郵便番号データの取り込み結果（ログに出して、何が変わったかを確かめるために使う）
 */
final readonly class ImportResult
{
    /**
     * @param int $before 取り込む前の件数
     * @param int $after 取り込んだ後の件数
     * @param list<string> $added 新しく増えた郵便番号（ハイフンなし7桁）
     * @param list<string> $removed なくなった郵便番号（ハイフンなし7桁）
     */
    public function __construct(
        public int $before,
        public int $after,
        public array $added,
        public array $removed,
    ) {
    }
}
