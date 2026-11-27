<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Address;
use App\Domain\PostalCode;
use App\Infrastructure\PdoPostCodeRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * 郵便番号データの Repository（App\Infrastructure\PdoPostCodeRepository）のテスト
 *
 * 本番は MySQL だが、テストでは準備がいらない SQLite のメモリ上の DB を使う。
 * 使っている SQL（JOIN・GROUP BY・MIN・LIKE ... ESCAPE・LIMIT/OFFSET）は、MySQL と SQLite のどちらでも同じ結果になる。
 * 都道府県・市区町村のマスタは、本番と同じ db/refresh_masters.sql を実行して作る。
 * テストごとに DB を作り直すので、テスト同士が影響し合わない。
 */
final class PdoPostCodeRepositoryTest extends TestCase
{
    private PDO $pdo;

    private PdoPostCodeRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::create();
        $this->repository = new PdoPostCodeRepository($this->pdo);
    }

    /**
     * 本番と同じ SQL ファイルで、都道府県・市区町村のマスタを作り直す
     */
    private function refreshMasters(): void
    {
        TestDatabase::refreshMasters($this->pdo);
    }

    /**
     * Address の配列を、比べやすい「郵便番号」の配列にする
     * @param list<Address> $addresses
     * @return list<string>
     */
    private static function postalCodes(array $addresses): array
    {
        return array_map(fn (Address $address): string => $address->postalCode->withHyphen(), $addresses);
    }

    public function testRefreshMastersCanRunRepeatedly(): void
    {
        // 郵便番号データを取り込むたびに実行するので、何度実行しても同じ結果になること
        $this->refreshMasters();
        $this->refreshMasters();
        $this->assertSame(['北海道', '埼玉県', '東京都'], $this->repository->prefectures());
        $this->assertSame(['さいたま市浦和区', 'さいたま市大宮区'], $this->repository->municipalities('埼玉県'));
    }

    public function testPrefecturesAreOrderedById(): void
    {
        $this->assertSame(['北海道', '埼玉県', '東京都'], $this->repository->prefectures());
    }

    public function testMunicipalitiesAreOrderedById(): void
    {
        $this->assertSame(['さいたま市浦和区', 'さいたま市大宮区'], $this->repository->municipalities('埼玉県'));
    }

    public function testMunicipalitiesOfUnknownPrefectureIsEmpty(): void
    {
        $this->assertSame([], $this->repository->municipalities('架空県'));
    }

    public function testFindByPostalCodeReturnsAddress(): void
    {
        // ハイフンの有無に関係なく、同じ PostalCode として検索できる
        foreach (['330-0063', '3300063'] as $input) {
            $addresses = $this->repository->findByPostalCode(PostalCode::fromString($input));

            $this->assertCount(1, $addresses, $input);
            $this->assertSame('330-0063', $addresses[0]->postalCode->withHyphen());
            $this->assertSame('埼玉県さいたま市浦和区高砂', $addresses[0]->fullAddress());
        }
    }

    public function testFindByPostalCodeNotFound(): void
    {
        $this->assertSame([], $this->repository->findByPostalCode(PostalCode::fromString('999-9999')));
    }

    public function testCountByArea(): void
    {
        $this->assertSame(2, $this->repository->countByArea('埼玉県', 'さいたま市浦和区'));
        $this->assertSame(1, $this->repository->countByArea('埼玉県', 'さいたま市浦和区', '高'));
        $this->assertSame(0, $this->repository->countByArea('埼玉県', 'さいたま市浦和区', '%'));
    }

    public function testSearchByAreaWithoutTownArea(): void
    {
        $addresses = $this->repository->searchByArea('埼玉県', 'さいたま市浦和区', null, 50, 0);
        $this->assertSame(['330-0063', '330-0062'], self::postalCodes($addresses));
    }

    public function testSearchByAreaPartialMatch(): void
    {
        $addresses = $this->repository->searchByArea('埼玉県', 'さいたま市浦和区', '高', 50, 0);
        $this->assertSame(['330-0063'], self::postalCodes($addresses));
        $this->assertSame('高砂', $addresses[0]->townArea);
    }

    public function testPercentIsNotWildcard(): void
    {
        // エスケープしないと「%」が「何でも一致」になり、浦和区の全件が返ってしまう
        $this->assertSame([], $this->repository->searchByArea('埼玉県', 'さいたま市浦和区', '%', 50, 0));
    }

    public function testUnderscoreIsNotWildcard(): void
    {
        // エスケープしないと「_」が「任意の1文字」になり、2文字の町名（高砂・仲町）に一致してしまう
        $this->assertSame([], $this->repository->searchByArea('埼玉県', 'さいたま市浦和区', '__', 50, 0));
    }

    public function testPercentAndUnderscoreAsLiteralCharacters(): void
    {
        // 「%」「_」そのものを含む町名は、文字として検索できる
        $addresses = $this->repository->searchByArea('東京都', '千代田区', '100%_', 50, 0);
        $this->assertSame(['100-0099'], self::postalCodes($addresses));
    }

    public function testSearchByAreaReturnsOnePageInIdOrder(): void
    {
        // 1ページ 1 件で、1ページ目と2ページ目を取得する。並び順は id の順で固定されている
        $this->assertSame(['330-0063'], self::postalCodes($this->repository->searchByArea('埼玉県', 'さいたま市浦和区', null, 1, 0)));
        $this->assertSame(['330-0062'], self::postalCodes($this->repository->searchByArea('埼玉県', 'さいたま市浦和区', null, 1, 1)));
        $this->assertSame([], $this->repository->searchByArea('埼玉県', 'さいたま市浦和区', null, 1, 2));
    }
}
