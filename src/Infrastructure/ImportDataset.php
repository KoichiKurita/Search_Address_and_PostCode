<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * 取り込むデータの種類ごとの定義（どのテーブルに、どの列を、どう検証して入れるか）
 *
 * 住所の郵便番号（post_codes）と、事業所の個別郵便番号（offices）は、列も件数も違うが、
 * 「CSV を検証して、1つのトランザクションで全件入れ替える」流れは同じ。
 * 違う部分だけをこのクラスにまとめ、取り込みの処理（PostCodeImporter）は1つで両方に使う。
 */
final readonly class ImportDataset
{
    /**
     * @param string $table 入れ替えるテーブル（コードの中で決めた名前だけを使う。入力からは受け取らない）
     * @param array<string, int> $columns CSV の列（DB の列名 => 最大文字数）。1列目は郵便番号7桁（post_code_noHyphen）
     * @param list<string> $requiredColumns 空にできない列
     * @param array<string, string> $patterns 値の形を正規表現で確かめる列（列名 => 正規表現）
     * @param int $minimumRows これより少ない件数のデータは取り込まない
     * @param string|null $afterImportSql 取り込んだあとに実行する SQL ファイル（マスタの作り直しなど）
     */
    public function __construct(
        public string $table,
        public array $columns,
        public array $requiredColumns,
        public array $patterns,
        public int $minimumRows,
        public ?string $afterImportSql = null,
    ) {
    }

    /**
     * 住所の郵便番号（db/ken_all_to_sql.py --format csv で作った CSV。全国で約12万件）
     * @param string $dbDirectory db/ ディレクトリ（取り込んだあとに refresh_masters.sql でマスタを作り直す）
     */
    public static function addresses(string $dbDirectory): self
    {
        return new self(
            'post_codes',
            [
                'post_code_noHyphen' => 7,
                'prefecture' => 10,
                'municipality' => 50,
                'town_area' => 1000,
                'prefecture_kana' => 20,
                'municipality_kana' => 100,
                'town_area_kana' => 2000,
            ],
            ['prefecture', 'municipality'],
            [],
            100_000,
            $dbDirectory . '/refresh_masters.sql',
        );
    }

    /**
     * 事業所の個別郵便番号（db/jigyosyo_to_csv.py で作った CSV。全国で約2万件。#36）
     */
    public static function offices(): self
    {
        return new self(
            'offices',
            [
                'post_code_noHyphen' => 7,
                'name' => 100,
                'name_kana' => 100,
                'prefecture' => 10,
                'municipality' => 50,
                'town_area' => 50,
                'street' => 100,
                'is_po_box' => 1,
            ],
            ['name', 'prefecture', 'municipality'],
            ['is_po_box' => '/^[01]$/'],     // 0: 大口事業所 / 1: 私書箱
            10_000,
        );
    }

    /**
     * 件数の下限だけを変えた定義を返す（テストで、少ない件数のデータを取り込むために使う）
     */
    public function withMinimumRows(int $minimumRows): self
    {
        return new self($this->table, $this->columns, $this->requiredColumns, $this->patterns, $minimumRows, $this->afterImportSql);
    }
}
