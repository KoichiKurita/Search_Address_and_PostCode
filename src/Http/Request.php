<?php

declare(strict_types=1);

namespace App\Http;

/**
 * HTTP リクエストを表すオブジェクト
 *
 * $_GET や $_SERVER を直接読むのは、フロントコントローラ（fromGlobals()）の1か所だけにする。
 * コントローラはこのオブジェクトだけを受け取るので、テストでは new Request(...) で好きなリクエストを作れる。
 */
final readonly class Request
{
    /**
     * @param string $method HTTP メソッド（GET・POST など。大文字）
     * @param string $path アプリの中のパス（先頭の「/」なし。例: "address"、トップページは ""）
     * @param array<mixed> $query クエリパラメータ（$_GET）
     * @param string $body リクエストの本文（POST で送られた JSON など）
     * @param string $basePath アプリを置いている URL のパス（例: "/postcode"。ドメイン直下なら ""）
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $body = '',
        public string $basePath = '',
    ) {
    }

    /**
     * PHP のグローバル変数（$_SERVER・$_GET・本文）から、リクエストを作る
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

        [$basePath, $path] = self::splitPath(
            is_string($requestUri) ? $requestUri : '/',
            is_string($scriptName) ? $scriptName : '/index.php',
        );

        return new self(
            is_string($method) ? strtoupper($method) : 'GET',
            $path,
            $_GET,
            (string)file_get_contents('php://input'),
            $basePath,
        );
    }

    /**
     * URL のパスを「アプリを置いている場所」と「アプリの中のパス」に分ける
     *
     * 例: /postcode/address?post-code=1 を /postcode/index.php が処理する場合 → ["/postcode", "address"]
     * 公開ルートを public/ にできていない（リポジトリのルートを公開している）場合は、
     * ルートの .htaccess が public/ に振り分けるので、index.php が /postcode/public/index.php になる。
     * そのときは URL に public が含まれないので、"/public" を取り除いた場所をアプリの場所とする。
     *
     * @return array{string, string} [アプリを置いている場所, アプリの中のパス]
     */
    public static function splitPath(string $requestUri, string $scriptName): array
    {
        $urlPath = parse_url($requestUri, PHP_URL_PATH);
        $urlPath = is_string($urlPath) ? $urlPath : '/';

        $basePath = rtrim(dirname($scriptName), '/\\');     // 例: "/postcode"（ドメイン直下なら ""）
        if (!self::isUnder($urlPath, $basePath) && str_ends_with($basePath, '/public')) {
            $basePath = substr($basePath, 0, -strlen('/public'));
        }
        if (!self::isUnder($urlPath, $basePath)) {
            $basePath = '';
        }

        return [$basePath, trim(substr($urlPath, strlen($basePath)), '/')];
    }

    /**
     * URL のパスが、指定した場所（またはその下）かどうか
     */
    private static function isUnder(string $urlPath, string $basePath): bool
    {
        return $urlPath === $basePath || str_starts_with($urlPath, $basePath . '/');
    }

    /**
     * クエリパラメータから、文字列の値を取り出す（配列が送られた場合や、無い場合は空文字）
     */
    public function query(string $name): string
    {
        return query_string($this->query, $name);
    }

    /**
     * クエリパラメータが送られているか（値が空でも、名前があれば true）
     * 入力画面（パラメータなし）と、検索結果（パラメータあり）を見分けるのに使う
     */
    public function hasQuery(string $name): bool
    {
        return array_key_exists($name, $this->query);
    }

    /**
     * アプリの中のパスを、ブラウザから見た URL のパスにする（例: "address" → "/postcode/address"）
     * @param array<string, string|int> $query 付けるクエリパラメータ
     */
    public function url(string $path, array $query = []): string
    {
        $url = $this->basePath . '/' . $path;
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}
