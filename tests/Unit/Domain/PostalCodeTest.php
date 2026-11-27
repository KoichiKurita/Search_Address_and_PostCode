<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\InvalidPostalCodeException;
use App\Domain\PostalCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 郵便番号の値オブジェクト（App\Domain\PostalCode）のテスト
 */
final class PostalCodeTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validInputProvider(): array
    {
        return [
            'ハイフンあり'         => ['330-0063'],
            'ハイフンなし'         => ['3300063'],
            '長音記号（ー）'       => ['330ー0063'],
            '全角数字と全角ハイフン' => ['３３０－００６３'],
            '漢数字の一'           => ['330一0063'],
            '前後の空白'           => ['　330-0063 '],
        ];
    }

    #[DataProvider('validInputProvider')]
    public function testFromStringAcceptsVariousInputs(string $input): void
    {
        // どんな書き方で入力しても、同じ値の PostalCode になる
        $postalCode = PostalCode::fromString($input);

        $this->assertSame('3300063', $postalCode->digits());
        $this->assertSame('330-0063', $postalCode->withHyphen());
        $this->assertSame('330-0063', (string)$postalCode);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            '空文字'       => [''],
            '6桁'          => ['330-006'],
            '8桁'          => ['330-00634'],
            '数字以外'      => ['abc-defg'],
            '前後に余計な文字' => ['〒330-0063'],
        ];
    }

    #[DataProvider('invalidInputProvider')]
    public function testFromStringRejectsInvalidInput(string $input): void
    {
        // 正しくない値からは PostalCode を作れない（例外になる）
        $this->expectException(InvalidPostalCodeException::class);
        PostalCode::fromString($input);
    }

    #[DataProvider('invalidInputProvider')]
    public function testTryFromStringReturnsNullForInvalidInput(string $input): void
    {
        $this->assertNull(PostalCode::tryFromString($input));
    }

    public function testEqualsComparesValues(): void
    {
        // 値オブジェクトは、別々に作ったオブジェクトでも「中身の値」が同じなら等しい
        $this->assertTrue(PostalCode::fromString('330-0063')->equals(PostalCode::fromString('３３０００６３')));
        $this->assertFalse(PostalCode::fromString('330-0063')->equals(PostalCode::fromString('330-0062')));
    }
}
