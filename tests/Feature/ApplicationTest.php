<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Application;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Infrastructure\PdoPostCodeRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * アプリ全体（URL → コントローラ → Repository → テンプレート）のテスト
 *
 * Web サーバーを起動せずに、Request を渡して返ってきた Response（ステータスコード・ヘッダー・HTML）を確かめる。
 * DB には SQLite のメモリ上の DB を使う。
 */
final class ApplicationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::create();

        // 千代田区に 55 件追加して、2ページ（50件 + 7件）になるようにする
        $rows = [];
        for ($i = 1; $i <= 55; $i++) {
            $rows[] = [sprintf('102-%04d', $i), '東京都', '千代田区', "番町{$i}"];
        }
        TestDatabase::insert($this->pdo, $rows);
        TestDatabase::refreshMasters($this->pdo);
    }

    /**
     * リクエストを処理して、レスポンスを返す（アプリは /postcode に置いているものとする）
     * @param array<string, string> $query
     */
    private function request(string $method, string $path, array $query = [], string $body = ''): Response
    {
        $pdo = $this->pdo;
        $application = new Application(
            static fn (): PdoPostCodeRepository => new PdoPostCodeRepository($pdo),
            new View(dirname(__DIR__, 2) . '/templates'),
        );
        return $application->handle(new Request($method, $path, $query, $body, '/postcode'));
    }

    // ---- 住所検索（/address）------------------------------------------------

    public function testAddressFormDoesNotConnectToDatabase(): void
    {
        // 入力画面では DB に接続しない（接続しようとしたら失敗する Repository を渡して確かめる）
        $application = new Application(
            static fn (): never => throw new PDOException('接続してはいけない'),
            new View(dirname(__DIR__, 2) . '/templates'),
        );
        $response = $application->handle(new Request('GET', 'address'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<title>住所検索</title>', $response->body);
        $this->assertStringNotContainsString('result_heading', $response->body);
    }

    public function testAddressSearch(): void
    {
        $response = $this->request('GET', 'address', ['post-code' => '３３０ー００６３']);

        $this->assertSame(200, $response->status);
        $this->assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        $this->assertStringContainsString('郵便番号「330-0063」の住所の検索結果：1件', $response->body);
        $this->assertStringContainsString('埼玉県さいたま市浦和区高砂', $response->body);
        // 入力欄には、正規化した郵便番号を残す
        $this->assertStringContainsString('value="330-0063"', $response->body);
        // 相対パスのリンクの基準
        $this->assertStringContainsString('<base href="/postcode/">', $response->body);
    }

    public function testAddressSearchWithInvalidPostCodeReturns400(): void
    {
        $response = $this->request('GET', 'address', ['post-code' => '<script>']);

        $this->assertSame(400, $response->status);
        $this->assertStringContainsString('郵便番号は7桁の数字で入力してください', $response->body);
        // 入力値はエスケープして表示する（XSS 対策）
        $this->assertStringContainsString('value="&lt;script&gt;"', $response->body);
        $this->assertStringNotContainsString('<script>"', $response->body);
    }

    // ---- 郵便番号検索（/postcode）--------------------------------------------

    public function testPostCodeForm(): void
    {
        $response = $this->request('GET', 'postcode');

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<option value="埼玉県" >埼玉県</option>', $response->body);
        $this->assertStringNotContainsString('result_heading', $response->body);
    }

    public function testPostCodeSearch(): void
    {
        $response = $this->request('GET', 'postcode', ['prefecture' => '埼玉県', 'municipality' => 'さいたま市浦和区', 'town_area' => '高']);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('検索結果：1件', $response->body);
        $this->assertStringContainsString('<td>330-0063</td>', $response->body);
        // 検索した都道府県を、プルダウンで選んだ状態にする
        $this->assertStringContainsString('<option value="埼玉県" selected>', $response->body);
    }

    public function testPostCodeSearchFallsBackToMunicipality(): void
    {
        // 町名で見つからなければ、市区町村全体の結果を、そのことを知らせて表示する
        $response = $this->request('GET', 'postcode', ['prefecture' => '埼玉県', 'municipality' => 'さいたま市浦和区', 'town_area' => '存在しない町']);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('さいたま市浦和区全体の結果を表示しています', $response->body);
        $this->assertStringContainsString('検索結果：2件', $response->body);
    }

    public function testPostCodeSearchPagination(): void
    {
        // 千代田区は 2 + 55 = 57 件 → 2ページ目は 51〜57件目
        $response = $this->request('GET', 'postcode', ['prefecture' => '東京都', 'municipality' => '千代田区', 'page' => '2']);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('51〜57件目を表示（2 / 2 ページ）', $response->body);
        $this->assertStringContainsString('<span aria-current="page">2</span>', $response->body);
        $this->assertStringContainsString('href="postcode?prefecture=%E6%9D%B1%E4%BA%AC%E9%83%BD&amp;municipality=%E5%8D%83%E4%BB%A3%E7%94%B0%E5%8C%BA&amp;town_area=&amp;page=1" rel="prev"', $response->body);
    }

    public function testPostCodeSearchWithUnknownPrefectureReturns400(): void
    {
        $response = $this->request('GET', 'postcode', ['prefecture' => '架空県', 'municipality' => '架空市']);

        $this->assertSame(400, $response->status);
        $this->assertStringContainsString('都道府県を選択してください。', $response->body);
        $this->assertStringNotContainsString('result_heading', $response->body);
    }

    // ---- 市区町村の API（/api/municipalities）-------------------------------

    public function testMunicipalityApi(): void
    {
        $response = $this->request('POST', 'api/municipalities', [], '"埼玉県"');

        $this->assertSame(200, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        $this->assertSame('["さいたま市浦和区","さいたま市大宮区"]', $response->body);
    }

    public function testMunicipalityApiWithInvalidBodyReturns400(): void
    {
        $response = $this->request('POST', 'api/municipalities', [], '{"a":1}');

        $this->assertSame(400, $response->status);
        $this->assertSame('{"error_msg":"都道府県が正しく送信されていません。"}', $response->body);
    }

    public function testMunicipalityApiWithGetReturns405Json(): void
    {
        // API のエラーは、HTML ではなく JSON で返す
        $response = $this->request('GET', 'api/municipalities');

        $this->assertSame(405, $response->status);
        $this->assertSame('POST', $response->headers['Allow']);
        $this->assertSame('{"error_msg":"許可されていないメソッドです。"}', $response->body);
    }

    // ---- 住所の JSON API（/api/address）#33 ----------------------------------

    public function testAddressApi(): void
    {
        // 全角数字・長音記号のハイフンも、画面と同じく正規化して検索する
        $response = $this->request('GET', 'api/address', ['zip' => '３３０ー００６３']);

        $this->assertSame(200, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        $this->assertSame([
            'zip' => '330-0063',
            'count' => 1,
            'addresses' => [[
                'zip' => '330-0063',
                'prefecture' => '埼玉県',
                'municipality' => 'さいたま市浦和区',
                'town_area' => '高砂',
                'full_address' => '埼玉県さいたま市浦和区高砂',
            ]],
        ], json_decode($response->body, true));
    }

    public function testAddressApiAllowsCorsAndCache(): void
    {
        // ほかのサイトの JavaScript からも使え、ブラウザに1日キャッシュさせる
        $response = $this->request('GET', 'api/address', ['zip' => '3300063']);

        $this->assertSame('*', $response->headers['Access-Control-Allow-Origin']);
        $this->assertSame('public, max-age=86400', $response->headers['Cache-Control']);
    }

    public function testAddressApiNotFoundReturnsEmptyList(): void
    {
        // 正しい形の郵便番号で、見つからない場合は 200 で 0 件（エラーではない）
        $response = $this->request('GET', 'api/address', ['zip' => '999-9999']);

        $this->assertSame(200, $response->status);
        $this->assertSame('{"zip":"999-9999","count":0,"addresses":[]}', $response->body);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function invalidZipProvider(): array
    {
        return [
            'zip が無い'     => [[]],
            '空'             => [['zip' => '']],
            '6桁'            => [['zip' => '330006']],
            '数字以外'       => [['zip' => 'abcdefg']],
        ];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('invalidZipProvider')]
    public function testAddressApiWithInvalidZipReturns400(array $query): void
    {
        $response = $this->request('GET', 'api/address', $query);

        $this->assertSame(400, $response->status);
        $this->assertStringContainsString('"error_msg"', $response->body);
        // エラーの内容も、ほかのサイトの JavaScript から読めるようにする。間違った応答はキャッシュさせない
        $this->assertSame('*', $response->headers['Access-Control-Allow-Origin']);
        $this->assertArrayNotHasKey('Cache-Control', $response->headers);
    }

    public function testAddressApiWithPostReturns405Json(): void
    {
        $response = $this->request('POST', 'api/address', ['zip' => '3300063']);

        $this->assertSame(405, $response->status);
        $this->assertSame('GET', $response->headers['Allow']);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
    }

    public function testAddressApiAcceptsHead(): void
    {
        // HEAD は GET と同じ処理をする（本文は Web サーバーが送らない）
        $response = $this->request('HEAD', 'api/address', ['zip' => '3300063']);

        $this->assertSame(200, $response->status);
    }

    // ---- 住所の自動入力デモ（/autofill）#34 ----------------------------------

    public function testAutofillDemo(): void
    {
        // 画面を表示するだけなので、DB には接続しない
        $application = new Application(
            static fn (): never => throw new PDOException('接続してはいけない'),
            new View(dirname(__DIR__, 2) . '/templates'),
        );
        $response = $application->handle(new Request('GET', 'autofill', [], '', '/postcode'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<title>住所の自動入力デモ</title>', $response->body);
        $this->assertStringContainsString('autocomplete="postal-code"', $response->body);
        $this->assertStringContainsString('<script src="js/autofill_page.js" type="module"></script>', $response->body);
    }

    public function testNavigationMarksCurrentPage(): void
    {
        // ナビゲーションでは、今いる画面だけに aria-current="page" を付ける
        $response = $this->request('GET', 'postcode');

        $this->assertStringContainsString('<a href="postcode" aria-current="page">郵便番号検索</a>', $response->body);
        $this->assertStringContainsString('<a href="address">住所検索</a>', $response->body);
        $this->assertSame(1, substr_count($response->body, 'aria-current="page">'));
    }

    // ---- エラーとリダイレクト ------------------------------------------------

    public function testUnknownPathReturns404(): void
    {
        $response = $this->request('GET', 'config.php');

        $this->assertSame(404, $response->status);
        $this->assertStringContainsString('ページが見つかりません。', $response->body);
    }

    public function testDatabaseErrorReturns500WithoutDetails(): void
    {
        $application = new Application(
            static fn (): never => throw new PDOException('SQLSTATE[HY000] [2002] 接続先の詳細'),
            new View(dirname(__DIR__, 2) . '/templates'),
        );

        // エラーの詳細はログにだけ出す（テストの出力に混ざらないよう、ログの出力先を一時的に捨てる）
        $log = ini_set('error_log', '/dev/null');
        $response = $application->handle(new Request('GET', 'postcode'));
        ini_set('error_log', (string)$log);

        $this->assertSame(500, $response->status);
        $this->assertStringContainsString('エラーが発生しました。時間をおいて再度お試しください。', $response->body);
        $this->assertStringNotContainsString('SQLSTATE', $response->body);
    }

    public function testTopPageRedirectsToAddress(): void
    {
        $response = $this->request('GET', '');

        $this->assertSame(302, $response->status);
        $this->assertSame('/postcode/address', $response->headers['Location']);
    }

    public function testOldUrlRedirectsWithQuery(): void
    {
        // 以前の URL は、クエリパラメータを引き継いで新しい URL へ移動させる
        $response = $this->request('GET', 'result_address.php', ['post-code' => '330-0063']);

        $this->assertSame(301, $response->status);
        $this->assertSame('/postcode/address?post-code=330-0063', $response->headers['Location']);
    }

    public function testOldApiRedirectsWith308(): void
    {
        $response = $this->request('POST', 'get_municipality.php', [], '"埼玉県"');

        $this->assertSame(308, $response->status);
        $this->assertSame('/postcode/api/municipalities', $response->headers['Location']);
    }
}
