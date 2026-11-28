<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * フリガナの入力をそろえる関数 to_katakana() のテスト（#37）
 */
final class KatakanaTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function katakanaProvider(): array
    {
        return [
            'ひらがな'             => ['たかさご', 'タカサゴ'],
            '半角カナ（濁点つき）' => ['ﾀｶｻｺﾞ', 'タカサゴ'],
            '全角カタカナ'         => ['タカサゴ', 'タカサゴ'],
            '長音記号'             => ['おーさか', 'オーサカ'],
            '漢字はそのまま'       => ['高砂', '高砂'],
            '混在'                 => ['高さご', '高サゴ'],
            '空'                   => ['', ''],
        ];
    }

    #[DataProvider('katakanaProvider')]
    public function testToKatakana(string $input, string $expected): void
    {
        $this->assertSame($expected, to_katakana($input));
    }
}
