<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * 事業所の個別郵便番号（#36）
 *
 * 郵便物の多い大口事業所や私書箱には、その事業所だけの郵便番号が付いている（例: 100-8792 日本郵便株式会社）。
 * 住所の郵便番号（Address）とは、日本郵便のデータも、持っている項目（事業所名・番地）も違うので、別のクラスにしている。
 */
final readonly class Office
{
    /**
     * @param string $nameKana 事業所名のフリガナ（全角カタカナ。日本郵便のデータでは「ャ」などの小さい字が「ヤ」になっている）
     * @param string $street 小字・丁目・番地など（例: "1丁目3-2"）
     * @param bool $isPoBox true: 私書箱 / false: 大口事業所
     */
    public function __construct(
        public PostalCode $postalCode,
        public string $name,
        public string $nameKana,
        public string $prefecture,
        public string $municipality,
        public string $townArea,
        public string $street,
        public bool $isPoBox,
    ) {
    }

    /**
     * 番地までの住所（例: "東京都千代田区大手町２丁目３－１"）
     */
    public function fullAddress(): string
    {
        return $this->prefecture . $this->municipality . $this->townArea . $this->street;
    }
}
