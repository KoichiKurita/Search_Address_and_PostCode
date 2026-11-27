<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * リクエスト（App\Http\Request）のテスト
 * URL を「アプリを置いている場所」と「アプリの中のパス」に正しく分けられるか
 */
final class RequestTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function splitPathProvider(): array
    {
        return [
            // [REQUEST_URI, SCRIPT_NAME, アプリを置いている場所, アプリの中のパス]
            '本番（公開ルートが public/）'            => ['/postcode/address?post-code=1', '/postcode/index.php', '/postcode', 'address'],
            '本番のトップページ'                      => ['/postcode/', '/postcode/index.php', '/postcode', ''],
            '本番の API'                              => ['/postcode/api/municipalities', '/postcode/index.php', '/postcode', 'api/municipalities'],
            '保険の .htaccess で public/ に振り分け'  => ['/postcode/address', '/postcode/public/index.php', '/postcode', 'address'],
            'Docker（ドメイン直下）'                  => ['/address?post-code=1', '/index.php', '', 'address'],
            'Docker のトップページ'                   => ['/', '/index.php', '', ''],
            '末尾の「/」は無視する'                   => ['/postcode/address/', '/postcode/index.php', '/postcode', 'address'],
        ];
    }

    #[DataProvider('splitPathProvider')]
    public function testSplitPath(string $requestUri, string $scriptName, string $basePath, string $path): void
    {
        $this->assertSame([$basePath, $path], Request::splitPath($requestUri, $scriptName));
    }

    public function testQueryReturnsEmptyStringForArray(): void
    {
        // ?post-code[]=1 のように配列が送られても、警告を出さずに空文字として扱う
        $request = new Request('GET', 'address', ['post-code' => ['1']]);
        $this->assertSame('', $request->query('post-code'));
        $this->assertTrue($request->hasQuery('post-code'));
        $this->assertFalse($request->hasQuery('page'));
    }

    public function testUrl(): void
    {
        $request = new Request('GET', 'address', [], '', '/postcode');
        $this->assertSame('/postcode/address', $request->url('address'));
        $this->assertSame('/postcode/postcode?page=2', $request->url('postcode', ['page' => 2]));
    }
}
