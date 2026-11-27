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
            town_area TEXT NOT NULL DEFAULT ""
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
            ['060-0000', '北海道', '札幌市中央区', ''],
            ['330-0063', '埼玉県', 'さいたま市浦和区', '高砂'],
            ['330-0062', '埼玉県', 'さいたま市浦和区', '仲町'],
            ['330-0802', '埼玉県', 'さいたま市大宮区', '宮町'],
            ['100-0001', '東京都', '千代田区', '千代田'],
            ['100-0099', '東京都', '千代田区', '100%_テスト'],
        ]);

        self::refreshMasters($pdo);
        return $pdo;
    }

    /**
     * 郵便番号データを追加する（追加したら refreshMasters() でマスタを作り直す）
     * @param list<array{string, string, string, string}> $rows [郵便番号, 都道府県, 市区町村, 町域] の配列
     */
    public static function insert(PDO $pdo, array $rows): void
    {
        $statement = $pdo->prepare('INSERT INTO post_codes (post_code, post_code_noHyphen, prefecture, municipality, town_area) VALUES (?, ?, ?, ?, ?)');
        foreach ($rows as [$postCode, $prefecture, $municipality, $townArea]) {
            $statement->execute([$postCode, str_replace('-', '', $postCode), $prefecture, $municipality, $townArea]);
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
