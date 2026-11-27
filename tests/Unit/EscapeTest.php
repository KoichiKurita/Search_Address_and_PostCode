<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * LIKE 検索用のエスケープ（escape_like）と、HTML エスケープ（escape）のテスト
 */
final class EscapeTest extends TestCase
{
    /**
     * @return array<string, array{string, string}> [入力, 期待する結果]
     */
    public static function escapeLikeProvider(): array
    {
        return [
            '記号なしはそのまま'                     => ['高砂', '高砂'],
            '% をエスケープ'                         => ['10%', '10!%'],
            '_ をエスケープ'                         => ['a_b', 'a!_b'],
            'エスケープ文字 ! 自体もエスケープ'      => ['!', '!!'],
            '! と % が並んでいても二重にならない'     => ['!%', '!!!%'],
        ];
    }

    #[DataProvider('escapeLikeProvider')]
    public function testEscapeLike(string $input, string $expected): void
    {
        $this->assertSame($expected, escape_like($input));
    }

    /**
     * @return array<string, array{mixed, string}> [入力, 期待する結果]
     */
    public static function escapeProvider(): array
    {
        return [
            'タグ'             => ['<script>', '&lt;script&gt;'],
            'アンパサンド'     => ['A&B', 'A&amp;B'],
            'ダブルクォート'   => ['"', '&quot;'],
            'シングルクォート' => ["'", '&apos;'],
            '日本語はそのまま' => ['埼玉県', '埼玉県'],
            '数値は文字列にする' => [400, '400'],
            'null は空文字'     => [null, ''],
            '配列は空文字'       => [['<script>'], ''],
        ];
    }

    #[DataProvider('escapeProvider')]
    public function testEscape(mixed $input, string $expected): void
    {
        $this->assertSame($expected, escape($input));
    }
}
