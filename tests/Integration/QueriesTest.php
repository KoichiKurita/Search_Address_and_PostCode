<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * DB を検索する関数（src/queries.php）のテスト
 *
 * 本番は MySQL だが、テストでは準備がいらない SQLite のメモリ上の DB を使う。
 * 使っている SQL（JOIN・GROUP BY・MIN・LIKE ... ESCAPE）は、MySQL と SQLite のどちらでも同じ結果になる。
 * 都道府県・市区町村のマスタは、本番と同じ db/refresh_masters.sql を実行して作る。
 */
final class QueriesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE post_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_code TEXT NOT NULL,
            post_code_noHyphen TEXT NOT NULL,
            prefecture TEXT NOT NULL,
            municipality TEXT NOT NULL,
            town_area TEXT NOT NULL DEFAULT ""
        )');
        $this->pdo->exec('CREATE TABLE prefectures (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            sort_order INTEGER NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE municipalities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prefecture_id INTEGER NOT NULL REFERENCES prefectures (id),
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL,
            UNIQUE (prefecture_id, name)
        )');

        // id の順（＝全国地方公共団体コード順）に入れる。
        // 「さいたま市浦和区」「さいたま市大宮区」は、文字コード順に並べると逆（大宮区が先）になるので、
        // id の順で並んでいるかを確かめられる。
        $rows = [
            ['060-0000', '北海道', '札幌市中央区', ''],
            ['330-0063', '埼玉県', 'さいたま市浦和区', '高砂'],
            ['330-0062', '埼玉県', 'さいたま市浦和区', '仲町'],
            ['330-0802', '埼玉県', 'さいたま市大宮区', '宮町'],
            ['100-0001', '東京都', '千代田区', '千代田'],
            ['100-0099', '東京都', '千代田区', '100%_テスト'],
        ];
        $statement = $this->pdo->prepare('INSERT INTO post_codes (post_code, post_code_noHyphen, prefecture, municipality, town_area) VALUES (?, ?, ?, ?, ?)');
        foreach ($rows as [$postCode, $prefecture, $municipality, $townArea]) {
            $statement->execute([$postCode, str_replace('-', '', $postCode), $prefecture, $municipality, $townArea]);
        }

        $this->refreshMasters();
    }

    /**
     * 本番と同じ SQL ファイルで、都道府県・市区町村のマスタを作り直す
     */
    private function refreshMasters(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/db/refresh_masters.sql');
        $this->assertIsString($sql);
        $this->pdo->exec($sql);
    }

    public function testRefreshMastersCanRunRepeatedly(): void
    {
        // 郵便番号データを取り込むたびに実行するので、何度実行しても同じ結果になること
        $this->refreshMasters();
        $this->refreshMasters();
        $this->assertSame(['北海道', '埼玉県', '東京都'], get_prefecture($this->pdo)->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame(
            ['さいたま市浦和区', 'さいたま市大宮区'],
            get_municipality($this->pdo, '埼玉県')->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function testGetPrefectureIsOrderedById(): void
    {
        $this->assertSame(['北海道', '埼玉県', '東京都'], get_prefecture($this->pdo)->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testGetMunicipalityIsOrderedById(): void
    {
        $this->assertSame(
            ['さいたま市浦和区', 'さいたま市大宮区'],
            get_municipality($this->pdo, '埼玉県')->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function testGetMunicipalityOfUnknownPrefectureIsEmpty(): void
    {
        $this->assertSame([], get_municipality($this->pdo, '架空県')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testSearchAddressWithAndWithoutHyphen(): void
    {
        foreach (['330-0063', '3300063'] as $postCode) {
            $rows = search_address($this->pdo, $postCode)->fetchAll(PDO::FETCH_ASSOC);
            $this->assertCount(1, $rows, $postCode);
            $this->assertSame('高砂', $rows[0]['town_area']);
        }
    }

    public function testSearchAddressNotFound(): void
    {
        $this->assertSame([], search_address($this->pdo, '999-9999')->fetchAll());
    }

    public function testSearchPostCodeWithoutTownArea(): void
    {
        $rows = search_post_code($this->pdo, '埼玉県', 'さいたま市浦和区')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['330-0063', '330-0062'], $rows);
    }

    public function testSearchPostCodePartialMatch(): void
    {
        $rows = search_post_code($this->pdo, '埼玉県', 'さいたま市浦和区', '高')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['330-0063'], $rows);
    }

    public function testPercentIsNotWildcard(): void
    {
        // エスケープしないと「%」が「何でも一致」になり、浦和区の全件が返ってしまう
        $this->assertSame([], search_post_code($this->pdo, '埼玉県', 'さいたま市浦和区', '%')->fetchAll());
    }

    public function testUnderscoreIsNotWildcard(): void
    {
        // エスケープしないと「_」が「任意の1文字」になり、2文字の町名（高砂・仲町）に一致してしまう
        $this->assertSame([], search_post_code($this->pdo, '埼玉県', 'さいたま市浦和区', '__')->fetchAll());
    }

    public function testPercentAndUnderscoreAsLiteralCharacters(): void
    {
        // 「%」「_」そのものを含む町名は、文字として検索できる
        $rows = search_post_code($this->pdo, '東京都', '千代田区', '100%_')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['100-0099'], $rows);
    }

    public function testCountPostCode(): void
    {
        $this->assertSame(2, count_post_code($this->pdo, '埼玉県', 'さいたま市浦和区'));
        $this->assertSame(1, count_post_code($this->pdo, '埼玉県', 'さいたま市浦和区', '高'));
        $this->assertSame(0, count_post_code($this->pdo, '埼玉県', 'さいたま市浦和区', '%'));
    }

    public function testSearchPostCodeReturnsOnePageInIdOrder(): void
    {
        // 1ページ 1 件で、1ページ目と2ページ目を取得する。並び順は id の順で固定されている
        $page1 = search_post_code($this->pdo, '埼玉県', 'さいたま市浦和区', null, 1, 0)->fetchAll(PDO::FETCH_COLUMN);
        $page2 = search_post_code($this->pdo, '埼玉県', 'さいたま市浦和区', null, 1, 1)->fetchAll(PDO::FETCH_COLUMN);
        $page3 = search_post_code($this->pdo, '埼玉県', 'さいたま市浦和区', null, 1, 2)->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['330-0063'], $page1);
        $this->assertSame(['330-0062'], $page2);
        $this->assertSame([], $page3);
    }
}
