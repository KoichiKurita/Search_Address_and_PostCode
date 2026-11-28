<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * 住所（郵便番号データの1行）を表すオブジェクト
 *
 * DB の行（列名がキーの配列）のままだと、どのキーがあるか・値が文字列かを、使う側が毎回気にする必要がある。
 * 型の決まったオブジェクトにしておくと、画面側は $address->prefecture のように安全に使える。
 */
final readonly class Address
{
    public function __construct(
        public PostalCode $postalCode,
        public string $prefecture,
        public string $municipality,
        public string $townArea,
        public string $prefectureKana = '',
        public string $municipalityKana = '',
        public string $townAreaKana = '',
    ) {
    }

    /**
     * 都道府県・市区町村・町域をつなげた住所（例: "埼玉県さいたま市浦和区高砂"）
     */
    public function fullAddress(): string
    {
        return $this->prefecture . $this->municipality . $this->townArea;
    }


    /**
     * 住所のフリガナ（例: "サイタマケンサイタマシウラワクタカサゴ"）
     */
    public function fullAddressKana(): string
    {
        return $this->prefectureKana . $this->municipalityKana . $this->townAreaKana;
    }
}
