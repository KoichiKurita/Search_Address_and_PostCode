<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ページングの計算（parse_page・last_page・pagination_pages）のテスト
 */
final class PaginationTest extends TestCase
{
    /**
     * @return array<string, array{string, int}> [入力, 期待するページ番号]
     */
    public static function parsePageProvider(): array
    {
        return [
            '数字'                 => ['3', 3],
            '空文字は1ページ目'     => ['', 1],
            '0 は1ページ目'        => ['0', 1],
            'マイナスは1ページ目'   => ['-2', 1],
            '小数は1ページ目'       => ['1.5', 1],
            '数字以外は1ページ目'   => ['abc', 1],
            '大きすぎる値は1ページ目' => ['99999999999999999999', 1],
        ];
    }

    #[DataProvider('parsePageProvider')]
    public function testParsePage(string $input, int $expected): void
    {
        $this->assertSame($expected, parse_page($input));
    }

    /**
     * @return array<string, array{int, int}> [総件数, 期待する最後のページ番号]（1ページ 50 件）
     */
    public static function lastPageProvider(): array
    {
        return [
            '0件でも1ページ'     => [0, 1],
            '1件'               => [1, 1],
            'ちょうど50件'       => [50, 1],
            '51件で2ページ'      => [51, 2],
            '120件で3ページ'     => [120, 3],
        ];
    }

    #[DataProvider('lastPageProvider')]
    public function testLastPage(int $total, int $expected): void
    {
        $this->assertSame($expected, last_page($total, 50));
    }

    /**
     * @return array<string, array{int, int, list<int|null>}> [現在のページ, 最後のページ, 期待する一覧]
     */
    public static function paginationPagesProvider(): array
    {
        return [
            '1ページだけ'                 => [1, 1, [1]],
            'ページが少なければ全部表示'   => [2, 5, [1, 2, 3, 4, 5]],
            '先頭付近'                    => [1, 20, [1, 2, 3, null, 20]],
            '中ほどは前後2ページと両端'    => [6, 20, [1, null, 4, 5, 6, 7, 8, null, 20]],
            '末尾付近'                    => [20, 20, [1, null, 18, 19, 20]],
            '1つだけ離れていても…で省略しない' => [4, 20, [1, 2, 3, 4, 5, 6, null, 20]],
        ];
    }

    /**
     * @param list<int|null> $expected
     */
    #[DataProvider('paginationPagesProvider')]
    public function testPaginationPages(int $current, int $last, array $expected): void
    {
        $this->assertSame($expected, pagination_pages($current, $last));
    }
}
