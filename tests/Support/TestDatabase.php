<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use RuntimeException;

/**
 * テスト用の DB（SQLite のメモリ上の DB）を作る
 *
 * 本番は MySQL だが、テストでは準備がいらない SQLite を使う。
 * 使っている SQL（JOIN・GROUP BY・MIN・LIKE ... ESCAPE・LIMIT/OFFSET）は、MySQL と SQLite のどちらでも同じ結果になる。
 * Repository のテストと、アプリ全体（画面・API）のテストの両方で使う。
 */
final class TestDatabase
{
    /**
     * テーブルを作り、サンプルの郵便番号データとマスタを入れた DB を返す
     */
    public static function create(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE post_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_code TEXT NOT NULL,
            post_code_noHyphen TEXT NOT NULL,
            prefecture TEXT NOT NULL,
            municipality TEXT NOT NULL,
            town_area TEXT NOT NULL DEFAULT "",
            prefecture_kana TEXT NOT NULL DEFAULT "",
            municipality_kana TEXT NOT NULL DEFAULT "",
            town_area_kana TEXT NOT NULL DEFAULT ""
        )');
        $pdo->exec('CREATE TABLE offices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_code TEXT NOT NULL,
            post_code_noHyphen TEXT NOT NULL,
            name TEXT NOT NULL,
            name_kana TEXT NOT NULL DEFAULT "",
            prefecture TEXT NOT NULL,
            municipality TEXT NOT NULL,
            town_area TEXT NOT NULL DEFAULT "",
            street TEXT NOT NULL DEFAULT "",
            is_po_box INTEGER NOT NULL DEFAULT 0
        )');
        $pdo->exec('CREATE TABLE prefectures (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            sort_order INTEGER NOT NULL
        )');
        $pdo->exec('CREATE TABLE municipalities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prefecture_id INTEGER NOT NULL REFERENCES prefectures (id),
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL,
            UNIQUE (prefecture_id, name)
        )');

        // id の順（＝全国地方公共団体コード順）に入れる。
        // 「さいたま市浦和区」「さいたま市大宮区」は、文字コード順に並べると逆（大宮区が先）になるので、
        // id の順で並んでいるかを確かめられる。
        self::insert($pdo, [
            ['060-0000', '北海道', '札幌市中央区', '', 'ホッカイドウ', 'サッポロシチュウオウク', ''],
            ['330-0063', '埼玉県', 'さいたま市浦和区', '高砂', 'サイタマケン', 'サイタマシウラワク', 'タカサゴ'],
            ['330-0062', '埼玉県', 'さいたま市浦和区', '仲町', 'サイタマケン', 'サイタマシウラワク', 'ナカチョウ'],
            ['330-0802', '埼玉県', 'さいたま市大宮区', '宮町', 'サイタマケン', 'サイタマシオオミヤク', 'ミヤチョウ'],
            ['100-0001', '東京都', '千代田区', '千代田', 'トウキョウト', 'チヨダク', 'チヨダ'],
            ['100-0099', '東京都', '千代田区', '100%_テスト'],
        ]);

        // 事業所の個別郵便番号（#36）
        $pdo->exec("INSERT INTO offices (post_code, post_code_noHyphen, name, name_kana, prefecture, municipality, town_area, street, is_po_box) VALUES
            ('100-8798', '1008798', '日本郵便株式会社', 'ニツポンユウビンカブシキガイシヤ', '東京都', '千代田区', '霞が関', '１丁目３－２', 0),
            ('330-8799', '3308799', 'さいたま中央郵便局私書箱', 'サイタマチユウオウユウビンキヨクシシヨバコ', '埼玉県', 'さいたま市大宮区', '吉敷町', '１丁目６８', 1)");

        self::refreshMasters($pdo);
        return $pdo;
    }

    /**
     * 郵便番号データを追加する（追加したら refreshMasters() でマスタを作り直す）
     * フリガナ（5〜7番目）は省略できる（省略すると空）
     * @param list<array{0: string, 1: string, 2: string, 3: string, 4?: string, 5?: string, 6?: string}> $rows
     *        [郵便番号, 都道府県, 市区町村, 町域, 都道府県カナ, 市区町村カナ, 町域カナ] の配列
     */
    public static function insert(PDO $pdo, array $rows): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO post_codes (post_code, post_code_noHyphen, prefecture, municipality, town_area,'
            . ' prefecture_kana, municipality_kana, town_area_kana) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $row) {
            $statement->execute([
                $row[0], str_replace('-', '', $row[0]), $row[1], $row[2], $row[3], $row[4] ?? '', $row[5] ?? '', $row[6] ?? '',
            ]);
        }
    }

    /**
     * 本番と同じ SQL ファイル（db/refresh_masters.sql）で、都道府県・市区町村のマスタを作り直す
     */
    public static function refreshMasters(PDO $pdo): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/db/refresh_masters.sql');
        if ($sql === false) {
            throw new RuntimeException('db/refresh_masters.sql を読み込めません。');
        }
        $pdo->exec($sql);
    }
}
