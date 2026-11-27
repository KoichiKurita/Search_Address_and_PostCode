<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Address;
use App\Domain\PostalCode;
use App\Domain\PostCodeRepository;
use PDO;
use PDOStatement;
use UnexpectedValueException;

/**
 * PostCodeRepository の PDO による実装（SQL はすべてこのクラスにまとめる）
 *
 * 本番・開発は MySQL、テストは SQLite のメモリ上の DB で動く（使っている SQL はどちらでも同じ結果になる）。
 * DB から取り出した値は型が決まっていない（mixed）ので、このクラスの中で文字列かどうかを確かめてから
 * Address や string の配列にして返す。外には「型の決まった値」しか出さない。
 */
final readonly class PdoPostCodeRepository implements PostCodeRepository
{
    private const SELECT_COLUMNS = 'SELECT post_code, prefecture, municipality, town_area FROM post_codes';

    public function __construct(private PDO $pdo)
    {
    }

    public function prefectures(): array
    {
        // 都道府県マスタ（47件）から取得する。sort_order の順に並べると全国地方公共団体コード順になる
        $statement = $this->pdo->prepare('SELECT name FROM prefectures ORDER BY sort_order');
        $statement->execute();

        return self::toStrings($statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function municipalities(string $prefecture): array
    {
        $statement = $this->pdo->prepare(
            'SELECT m.name FROM municipalities m'
            . ' INNER JOIN prefectures p ON p.id = m.prefecture_id'
            . ' WHERE p.name = :prefecture ORDER BY m.sort_order'
        );
        $statement->bindValue(':prefecture', $prefecture, PDO::PARAM_STR);
        $statement->execute();

        return self::toStrings($statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findByPostalCode(PostalCode $postalCode): array
    {
        // PostalCode は常に「ハイフンなしの7桁」を取り出せるので、ハイフンの有無で SQL を分ける必要がない
        $statement = $this->pdo->prepare(self::SELECT_COLUMNS . ' WHERE post_code_noHyphen = :post_code ORDER BY id');
        $statement->bindValue(':post_code', $postalCode->digits(), PDO::PARAM_STR);
        $statement->execute();

        return self::toAddresses($statement);
    }

    public function countByArea(string $prefecture, string $municipality, ?string $townArea = null): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM post_codes WHERE ' . self::areaCondition($townArea));
        self::bindArea($statement, $prefecture, $municipality, $townArea);
        $statement->execute();

        $count = $statement->fetchColumn();
        return is_numeric($count) ? (int)$count : 0;
    }

    public function searchByArea(string $prefecture, string $municipality, ?string $townArea, int $limit, int $offset): array
    {
        // ORDER BY id で並び順を固定する（全国地方公共団体コード順）
        // 並び順が決まっていないと、ページを切り替えたときに同じ行が2回出たり、出ない行ができたりする
        $statement = $this->pdo->prepare(
            self::SELECT_COLUMNS . ' WHERE ' . self::areaCondition($townArea) . ' ORDER BY id LIMIT :limit OFFSET :offset'
        );
        self::bindArea($statement, $prefecture, $municipality, $townArea);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return self::toAddresses($statement);
    }

    /**
     * 住所で検索するときの WHERE 句（件数の取得と、1ページ分の取得で共通に使う）
     */
    private static function areaCondition(?string $townArea): string
    {
        $condition = 'prefecture = :prefecture AND municipality = :municipality';
        if ($townArea !== null) {
            // 入力に含まれる % や _ をワイルドカードとして扱わないよう、escape_like() でエスケープしてから部分一致にする
            $condition .= " AND town_area LIKE :town_area ESCAPE '!'";
        }
        return $condition;
    }

    /**
     * areaCondition() で作った WHERE 句のパラメータに値を割り当てる
     */
    private static function bindArea(PDOStatement $statement, string $prefecture, string $municipality, ?string $townArea): void
    {
        $statement->bindValue(':prefecture', $prefecture, PDO::PARAM_STR);
        $statement->bindValue(':municipality', $municipality, PDO::PARAM_STR);
        if ($townArea !== null) {
            $statement->bindValue(':town_area', '%' . escape_like($townArea) . '%', PDO::PARAM_STR);
        }
    }

    /**
     * 1列だけの検索結果を、文字列の配列にする
     * @param array<mixed> $values
     * @return list<string>
     */
    private static function toStrings(array $values): array
    {
        $strings = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new UnexpectedValueException('DB から文字列以外の値が返されました。');
            }
            $strings[] = $value;
        }
        return $strings;
    }

    /**
     * 検索結果の各行を Address にする
     * @return list<Address>
     */
    private static function toAddresses(PDOStatement $statement): array
    {
        $addresses = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $addresses[] = new Address(
                PostalCode::fromString(self::column($row, 'post_code')),
                self::column($row, 'prefecture'),
                self::column($row, 'municipality'),
                self::column($row, 'town_area'),
            );
        }
        return $addresses;
    }

    /**
     * 行（配列）から、文字列の列の値を取り出す。無い・文字列でない場合は、DB の構造の誤りとして例外にする
     */
    private static function column(mixed $row, string $name): string
    {
        $value = is_array($row) ? ($row[$name] ?? null) : null;
        if (!is_string($value)) {
            throw new UnexpectedValueException("DB の行に文字列の列 {$name} がありません。");
        }
        return $value;
    }
}
