<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 郵便番号の正規化（normalize_post_code）と書式チェック（is_valid_post_code）のテスト
 *
 * データプロバイダ（#[DataProvider]）を使い、「入力」と「期待する結果」の組み合わせを表のように並べて、
 * 同じテストを何通りもの入力で実行する。どの組み合わせで失敗したかは、配列のキー（日本語の説明）で表示される。
 */
final class PostCodeTest extends TestCase
{
    /**
     * @return array<string, array{string, string}> [入力, 期待する結果]
     */
    public static function normalizeProvider(): array
    {
        return [
            'ハイフンありはそのまま'   => ['330-0063', '330-0063'],
            'ハイフンなしはそのまま'   => ['3300063', '3300063'],
            '長音記号（ー）'           => ['330ー0063', '330-0063'],
            '全角ハイフン（－）'       => ['330－0063', '330-0063'],
            'マイナス記号（−）'        => ['330−0063', '330-0063'],
            '漢数字の一'               => ['330一0063', '330-0063'],
            '全角数字'                 => ['３３０ー００６３', '330-0063'],
            '前後の半角スペース'       => [' 330-0063 ', '330-0063'],
            '前後の全角スペース'       => ['　３３０００６３　', '3300063'],
        ];
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalizePostCode(string $input, string $expected): void
    {
        $this->assertSame($expected, normalize_post_code($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validProvider(): array
    {
        return [
            'ハイフンあり' => ['330-0063'],
            'ハイフンなし' => ['3300063'],
        ];
    }

    #[DataProvider('validProvider')]
    public function testValidPostCode(string $postCode): void
    {
        $this->assertTrue(is_valid_post_code($postCode));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidProvider(): array
    {
        return [
            '空文字'                         => [''],
            '数字以外'                       => ['abc'],
            '8桁'                            => ['330-00634'],
            '前半が2桁'                      => ['33-00634'],
            'ハイフンが2つ'                  => ['330--0063'],
            '前後に余計な文字（^ と $ の確認）' => ['abc1234567xyz'],
            'スペース区切り'                 => ['330 0063'],
            '正規化前の全角数字'             => ['３３０００６３'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidPostCode(string $postCode): void
    {
        $this->assertFalse(is_valid_post_code($postCode));
    }

    public function testNormalizedFullWidthInputBecomesValid(): void
    {
        // 画面での使い方と同じ順番（正規化 → 書式チェック）で、全角の入力が検索できる形になることを確認する
        $this->assertTrue(is_valid_post_code(normalize_post_code('３３０ー００６３')));
    }
}
