<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\PostalCode;
use App\Infrastructure\ImportDataset;
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
     * 住所の郵便番号の取り込み（テストでは件数の下限を小さくする。本番は 100,000 件）
     */
    private function importer(int $minimumRows = 1): PostCodeImporter
    {
        $dataset = ImportDataset::addresses(dirname(__DIR__, 2) . '/db')->withMinimumRows($minimumRows);
        return new PostCodeImporter($this->pdo, $dataset);
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
            ['0600000', '北海道', '札幌市中央区', '', 'ホッカイドウ', 'サッポロシチュウオウク', ''],
            ['3300063', '埼玉県', 'さいたま市浦和区', '高砂', 'サイタマケン', 'サイタマシウラワク', 'タカサゴ'],
            ['3300062', '埼玉県', 'さいたま市浦和区', '仲町', 'サイタマケン', 'サイタマシウラワク', 'ナカチョウ'],
            ['3300802', '埼玉県', 'さいたま市大宮区', '宮町', 'サイタマケン', 'サイタマシオオミヤク', 'ミヤチョウ'],
            ['1000001', '東京都', '千代田区', '千代田', 'トウキョウト', 'チヨダク', 'チヨダ'],
            ['9000006', '沖縄県', '那覇市', 'おもろまち', 'オキナワケン', 'ナハシ', 'オモロマチ'],
            ['9000001', '沖縄県', '那覇市', '港町', 'オキナワケン', 'ナハシ', 'ミナトマチ'],
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
        // フリガナも取り込まれている（#37）
        $this->assertSame('ミナトマチ', $repository->searchByArea('沖縄県', '那覇市', 'みなと', 50, 0)[0]->townAreaKana);
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
            ['0600000', '北海道', '札幌市中央区', '', '', '', ''],
            ['3300063', '埼玉県', 'さいたま市浦和区', '高砂', '', '', ''],
            ['1000001', '東京都', '千代田区', '千代田', '', '', ''],
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
            '郵便番号が6桁'          => [['330006', '埼玉県', 'さいたま市浦和区', '', '', '', ''], '郵便番号が7桁の数字ではありません'],
            '郵便番号にハイフン'     => [['330-0063', '埼玉県', 'さいたま市浦和区', '', '', '', ''], '郵便番号が7桁の数字ではありません'],
            '列が足りない（フリガナなしの古い形式）' => [['3300063', '埼玉県', 'さいたま市浦和区', ''], '列の数が 7 ではありません'],
            '市区町村が空'           => [['3300063', '埼玉県', '', '', '', '', ''], 'municipality が空です'],
            '町域が長すぎる'         => [['3300063', '埼玉県', 'さいたま市浦和区', str_repeat('あ', 1001), '', '', ''], 'town_area が 1000 文字を超えています'],
            '町域のフリガナが長すぎる' => [['3300063', '埼玉県', 'さいたま市浦和区', '', '', '', str_repeat('ア', 2001)], 'town_area_kana が 2000 文字を超えています'],
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
            ['0600000', '北海道', '札幌市中央区', '', '', '', ''],
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

    // ---- 事業所の個別郵便番号（#36） -------------------------------------------

    private function officeImporter(): PostCodeImporter
    {
        return new PostCodeImporter($this->pdo, ImportDataset::offices()->withMinimumRows(1));
    }

    public function testImportOffices(): void
    {
        // 同じ取り込みの処理で、別のテーブル（offices）を入れ替えられる
        $this->writeCsv([
            ['1008798', '日本郵便株式会社', 'ニツポンユウビンカブシキガイシヤ', '東京都', '千代田区', '霞が関', '１丁目３－２', '0'],
            ['1028688', '千代田区役所', 'チヨダクヤクシヨ', '東京都', '千代田区', '九段南', '１丁目２－１', '0'],
        ]);

        $result = $this->officeImporter()->import($this->csvPath);

        $this->assertSame(2, $result->before);       // TestDatabase の2件
        $this->assertSame(2, $result->after);
        $this->assertSame(['1028688'], $result->added);
        $this->assertSame(['3308799'], $result->removed);
        $offices = (new PdoPostCodeRepository($this->pdo))->findOfficesByPostalCode(PostalCode::fromString('1028688'));
        $this->assertSame('千代田区役所', $offices[0]->name);
        $this->assertSame(6, $this->rowCount());     // 住所の郵便番号（post_codes）は変わらない
    }

    public function testOfficeWithInvalidTypeIsRejected(): void
    {
        // 種別は 0（大口事業所）か 1（私書箱）だけ
        $this->writeCsv([
            ['1008798', '日本郵便株式会社', '', '東京都', '千代田区', '霞が関', '', '2'],
        ]);

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('1 行目: is_po_box の値が正しくありません（2）');
        $this->officeImporter()->import($this->csvPath);
    }

    public function testOfficeWithoutNameIsRejected(): void
    {
        $this->writeCsv([
            ['1008798', '', '', '東京都', '千代田区', '霞が関', '', '0'],
        ]);

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('1 行目: name が空です');
        $this->officeImporter()->import($this->csvPath);
    }
}
