<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * 郵便番号データを検索する Repository のインターフェース
 *
 * 画面（PHP のページ）は、このインターフェースだけを使い、SQL や DB の種類を知らない。
 * そのため、DB の構造を変えたり、テストで別の実装に差し替えたりしても、画面を直す必要がない。
 * 実装は App\Infrastructure\PdoPostCodeRepository（PDO で MySQL / SQLite を検索する）。
 */
interface PostCodeRepository
{
    /**
     * 都道府県の一覧（全国地方公共団体コード順）
     * @return list<string>
     */
    public function prefectures(): array;

    /**
     * 指定した都道府県の市区町村の一覧（全国地方公共団体コード順）
     * @return list<string>
     */
    public function municipalities(string $prefecture): array;

    /**
     * 郵便番号から住所を検索する
     * @return list<Address>
     */
    public function findByPostalCode(PostalCode $postalCode): array;

    /**
     * 郵便番号から、事業所の個別郵便番号を検索する（#36）
     * @return list<Office>
     */
    public function findOfficesByPostalCode(PostalCode $postalCode): array;

    /**
     * 住所（都道府県・市区町村・町名の部分一致）に一致する件数
     * @param string|null $townArea 町名（null のときは町名で絞り込まない）
     */
    public function countByArea(string $prefecture, string $municipality, ?string $townArea = null): int;

    /**
     * 住所から郵便番号を検索する（1ページ分）
     * @param string|null $townArea 町名（null のときは町名で絞り込まない）
     * @param int $limit 取得する最大件数（1ページの件数）
     * @param int $offset 先頭から何件飛ばすか
     * @return list<Address>
     */
    public function searchByArea(string $prefecture, string $municipality, ?string $townArea, int $limit, int $offset): array;
}
