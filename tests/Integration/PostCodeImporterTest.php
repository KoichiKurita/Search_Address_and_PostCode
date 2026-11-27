<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\ImportException;
use App\Infrastructure\PdoPostCodeRepository;
use App\Infrastructure\PostCodeImporter;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * 郵便番号データの取り込み（App\Infrastructure\PostCodeImporter）のテスト
 * 取り込みに失敗したときに、DB が元のまま残ることを特に確かめる
 */
final class PostCodeImporterTest extends TestCase
{
    private PDO $pdo;

    private string $csvPath;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::create();     // 6 件（北海道・埼玉県・東京都）
        $this->csvPath = (string)tempnam(sys_get_temp_dir(), 'post_codes');
    }

    protected function tearDown(): void
    {
        @unlink($this->csvPath);
    }

    /**
     * テスト用の CSV を書き出す
     * @param list<list<string>> $rows
     */
    private function writeCsv(array $rows): void
    {
        $file = fopen($this->csvPath, 'w');
        $this->assertNotFalse($file);
        foreach ($rows as $row) {
            fputcsv($file, $row, ',', '"', '');
        }
        fclose($file);
    }

    /**
     * テストでは件数の下限を小さくする（本番は 100,000 件）
     */
    private function importer(int $minimumRows = 1): PostCodeImporter
    {
        return new PostCodeImporter($this->pdo, dirname(__DIR__, 2) . '/db/refresh_masters.sql', $minimumRows);
    }

    private function rowCount(): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM post_codes');
        $statement->execute();
        $count = $statement->fetchColumn();
        return is_numeric($count) ? (int)$count : -1;
    }

    /**
     * 今の 6 件のうち 5 件はそのまま、1 件（100-0099）が廃止、2 件（沖縄県）が追加された CSV
     */
    private function writeNextMonth(): void
    {
        $this->writeCsv([
            ['0600000', '北海道', '札幌市中央区', ''],
            ['3300063', '埼玉県', 'さいたま市浦和区', '高砂'],
            ['3300062', '埼玉県', 'さいたま市浦和区', '仲町'],
            ['3300802', '埼玉県', 'さいたま市大宮区', '宮町'],
            ['1000001', '東京都', '千代田区', '千代田'],
            ['9000006', '沖縄県', '那覇市', 'おもろまち'],
            ['9000001', '沖縄県', '那覇市', '港町'],
        ]);
    }

    public function testImportReplacesAllRows(): void
    {
        $this->writeNextMonth();

        $result = $this->importer()->import($this->csvPath);

        $this->assertSame(6, $result->before);
        $this->assertSame(7, $result->after);
        $this->assertSame(['9000006', '9000001'], $result->added);
        $this->assertSame(['1000099'], $result->removed);

        // 検索できる形（ハイフンあり・なしの両方の列）で入っている
        $repository = new PdoPostCodeRepository($this->pdo);
        $this->assertSame(['北海道', '埼玉県', '東京都', '沖縄県'], $repository->prefectures());   // マスタも作り直されている
        $this->assertSame(['那覇市'], $repository->municipalities('沖縄県'));
        $this->assertSame('900-0006', $repository->searchByArea('沖縄県', '那覇市', 'おもろ', 50, 0)[0]->postalCode->withHyphen());
    }

    public function testImportTwiceGivesSameResult(): void
    {
        // 同じデータを2回取り込んでも、件数は増えない（全件を入れ替えるため）
        $this->writeNextMonth();
        $this->importer()->import($this->csvPath);
        $result = $this->importer()->import($this->csvPath);

        $this->assertSame(7, $result->after);
        $this->assertSame([], $result->added);
        $this->assertSame([], $result->removed);
    }

    public function testTooFewRowsIsRejected(): void
    {
        // 件数の下限を下回るデータは取り込まない
        $this->writeNextMonth();

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('件数が少なすぎます');
        try {
            $this->importer(minimumRows: 100)->import($this->csvPath);
        } finally {
            $this->assertSame(6, $this->rowCount());     // DB は元のまま
        }
    }

    public function testLargeDecreaseIsRejected(): void
    {
        // 今の 6 件から 3 件に減る（50% 減）のは、データの異常とみなして取り込まない
        $this->writeCsv([
            ['0600000', '北海道', '札幌市中央区', ''],
            ['3300063', '埼玉県', 'さいたま市浦和区', '高砂'],
            ['1000001', '東京都', '千代田区', '千代田'],
        ]);

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('50.0% 減ります');
        try {
            $this->importer()->import($this->csvPath);
        } finally {
            $this->assertSame(6, $this->rowCount());
        }
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function invalidRowProvider(): array
    {
        return [
            '郵便番号が6桁'          => [['330006', '埼玉県', 'さいたま市浦和区', ''], '郵便番号が7桁の数字ではありません'],
            '郵便番号にハイフン'     => [['330-0063', '埼玉県', 'さいたま市浦和区', ''], '郵便番号が7桁の数字ではありません'],
            '列が足りない'           => [['3300063', '埼玉県', 'さいたま市浦和区'], '列の数が 4 ではありません'],
            '市区町村が空'           => [['3300063', '埼玉県', '', ''], '都道府県または市区町村が空です'],
            '町域が長すぎる'         => [['3300063', '埼玉県', 'さいたま市浦和区', str_repeat('あ', 1001)], 'town_area が 1000 文字を超えています'],
        ];
    }

    /**
     * @param list<string> $badRow
     */
    #[DataProvider('invalidRowProvider')]
    public function testInvalidRowIsRejectedWithLineNumber(array $badRow, string $message): void
    {
        // 誤りのある行が1行でもあれば、何行目かを示して止める（途中まで取り込んだりしない）
        $this->writeCsv([
            ['0600000', '北海道', '札幌市中央区', ''],
            $badRow,
        ]);

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage("2 行目: {$message}");
        try {
            $this->importer()->import($this->csvPath);
        } finally {
            $this->assertSame(6, $this->rowCount());
        }
    }

    public function testMissingFileIsRejected(): void
    {
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('ファイルがありません');
        $this->importer()->import('/nonexistent/post_codes.csv');
    }
}
