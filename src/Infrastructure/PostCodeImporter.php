<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\PostalCode;
use PDO;
use SplFileObject;

/**
 * 郵便番号データ（ken_all_to_sql.py --format csv で作った CSV）を、post_codes テーブルに取り込む
 *
 * 安全に入れ替えるための工夫
 *   1. 先に CSV 全体を検証する（郵便番号の形・文字数・件数）。1行でも誤りがあれば、DB に触れずに止める
 *      本番の MySQL は strict モードではなく、長すぎる値を黙って切り詰めてしまうため、文字数はここで確かめる
 *   2. 件数が前回より大きく減っていたら止める（ダウンロードの失敗や、日本郵便側のデータの誤りに備える）
 *   3. 「全件削除 → 全件追加」を1つのトランザクションで行う。コミットするまで、画面と API には古いデータが見えるので、
 *      取り込みの途中でも検索は止まらず、途中で失敗しても元のデータのまま残る
 *   4. 最後に、都道府県・市区町村のマスタを作り直す（db/refresh_masters.sql）
 *
 * 使っている SQL は MySQL と SQLite のどちらでも動くので、テストでは SQLite のメモリ上の DB を使う。
 */
final readonly class PostCodeImporter
{
    /** 1つの INSERT 文で追加する行数 */
    private const ROWS_PER_INSERT = 500;

    /**
     * CSV の列（db/ken_all_to_sql.py の COLUMNS と同じ順）と、それぞれの最大文字数（db/schema.sql と合わせる）
     * 郵便番号（1列目）は、PostalCode で別に検証する
     */
    private const COLUMNS = [
        'post_code_noHyphen' => 7,
        'prefecture' => 10,
        'municipality' => 50,
        'town_area' => 1000,
        'prefecture_kana' => 20,
        'municipality_kana' => 100,
        'town_area_kana' => 2000,
    ];

    /**
     * @param PDO $pdo 書き込みのできる DB 接続
     * @param string $refreshMastersSql マスタを作り直す SQL ファイル（db/refresh_masters.sql）
     * @param int $minimumRows これより少ない件数のデータは取り込まない（全国で約12万件）
     * @param float $minimumRatio 今の件数に対して、この割合より少なくなるデータは取り込まない
     */
    public function __construct(
        private PDO $pdo,
        private string $refreshMastersSql,
        private int $minimumRows = 100_000,
        private float $minimumRatio = 0.95,
    ) {
    }

    /**
     * CSV を検証してから、post_codes の中身を入れ替える
     * @throws ImportException データに誤りがある、または件数が少なすぎる場合（DB は変わらない）
     */
    public function import(string $csvPath): ImportResult
    {
        // 1. 検証（DB にはまだ触れない）
        $newCodes = [];
        $count = 0;
        foreach ($this->readRows($csvPath) as $row) {
            $newCodes[$row[0]] = true;
            $count++;
        }

        // 2. 件数の確認
        $before = $this->countRows();
        if ($count < $this->minimumRows) {
            throw new ImportException("件数が少なすぎます（{$count} 件。{$this->minimumRows} 件以上必要）。");
        }
        if ($count < $before * $this->minimumRatio) {
            throw new ImportException(sprintf(
                '件数が今より %.1f%% 減ります（%d 件 → %d 件）。データに異常がないか確認してください。',
                (1 - $count / $before) * 100,
                $before,
                $count,
            ));
        }

        // 何が変わったかをログに出すため、今の郵便番号の一覧を控えておく
        $oldCodes = array_fill_keys($this->postalCodes(), true);

        // 3. 入れ替え（コミットするまで、ほかの接続からは古いデータが見える）
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM post_codes');
            $batch = [];
            foreach ($this->readRows($csvPath) as $row) {
                $batch[] = $row;
                if (count($batch) === self::ROWS_PER_INSERT) {
                    $this->insert($batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $this->insert($batch);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        // 4. マスタの作り直し（refresh_masters.sql も、1つのトランザクションで入れ替える）
        $this->refreshMasters();

        return new ImportResult(
            $before,
            $this->countRows(),
            array_map('strval', array_keys(array_diff_key($newCodes, $oldCodes))),
            array_map('strval', array_keys(array_diff_key($oldCodes, $newCodes))),
        );
    }

    /**
     * CSV を1行ずつ読み、検証した値を返す
     * @return \Generator<int, list<string>> COLUMNS の順の値
     * @throws ImportException 誤りのある行があった場合（何行目かをメッセージに入れる）
     */
    private function readRows(string $csvPath): \Generator
    {
        if (!is_file($csvPath)) {
            throw new ImportException("ファイルがありません: {$csvPath}");
        }
        $file = new SplFileObject($csvPath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        foreach ($file as $index => $columns) {
            $line = (int)$index + 1;
            if (!is_array($columns) || count($columns) !== count(self::COLUMNS)) {
                throw new ImportException("{$line} 行目: 列の数が " . count(self::COLUMNS) . ' ではありません。');
            }
            // SplFileObject が読んだ CSV の値は文字列（null は、列が空のときだけ）
            $values = array_values(array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $columns));
            [$zip, $prefecture, $municipality] = $values;

            // 郵便番号は7桁の数字だけを受け付ける（PostalCode で検証し、ハイフンなしの形にそろえる）
            $postalCode = PostalCode::tryFromString($zip);
            if ($postalCode === null || $postalCode->digits() !== $zip) {
                throw new ImportException("{$line} 行目: 郵便番号が7桁の数字ではありません（{$zip}）。");
            }
            if ($prefecture === '' || $municipality === '') {
                throw new ImportException("{$line} 行目: 都道府県または市区町村が空です。");
            }
            $position = 0;
            foreach (self::COLUMNS as $name => $maxLength) {
                if (mb_strlen($values[$position++]) > $maxLength) {
                    throw new ImportException("{$line} 行目: {$name} が {$maxLength} 文字を超えています。");
                }
            }
            yield $values;
        }
    }

    /**
     * まとめて追加する（1行ずつ INSERT するより、ずっと速い）
     * 列は「ハイフンありの郵便番号」と、COLUMNS の列
     * @param list<list<string>> $rows
     */
    private function insert(array $rows): void
    {
        $columns = 'post_code, ' . implode(', ', array_keys(self::COLUMNS));
        $row = '(' . implode(', ', array_fill(0, count(self::COLUMNS) + 1, '?')) . ')';
        $statement = $this->pdo->prepare(
            "INSERT INTO post_codes ({$columns}) VALUES " . implode(', ', array_fill(0, count($rows), $row))
        );

        $values = [];
        foreach ($rows as $columnValues) {
            $zip = $columnValues[0];
            array_push($values, substr($zip, 0, 3) . '-' . substr($zip, 3), ...$columnValues);
        }
        $statement->execute($values);
    }

    /**
     * db/refresh_masters.sql を、文ごとに分けて実行する
     * （PDO の exec() に複数の文をまとめて渡すと、2つ目以降の文のエラーに気づけないことがあるため）
     */
    private function refreshMasters(): void
    {
        $sql = file_get_contents($this->refreshMastersSql);
        if ($sql === false) {
            throw new ImportException("ファイルを読み込めません: {$this->refreshMastersSql}");
        }
        // 「--」で始まるコメントの行を除き、「;」で文に分ける
        $sql = (string)preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                $this->pdo->exec($statement);
            }
        }
    }

    private function countRows(): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM post_codes');
        $statement->execute();
        $count = $statement->fetchColumn();
        return is_numeric($count) ? (int)$count : 0;
    }

    /**
     * 今の郵便番号の一覧（ハイフンなし7桁。重複なし）
     * @return list<string>
     */
    private function postalCodes(): array
    {
        $statement = $this->pdo->prepare('SELECT DISTINCT post_code_noHyphen FROM post_codes');
        $statement->execute();
        $codes = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $code) {
            $codes[] = is_string($code) ? $code : '';
        }
        return $codes;
    }
}
