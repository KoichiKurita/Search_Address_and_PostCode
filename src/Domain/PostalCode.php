<?php

declare(strict_types=1);

namespace App\Domain;

use Stringable;

/**
 * 郵便番号を表す値オブジェクト
 *
 * - 正しい郵便番号（数字7桁）からしか作れない。作られた時点で、正規化と検証は済んでいる
 * - 一度作ったら値は変わらない（readonly）
 * - 「ハイフンあり」「ハイフンなし」のどちらの形でも取り出せる
 *
 * 例:
 *   $postalCode = PostalCode::fromString('３３０ー００６３');
 *   $postalCode->digits();      // "3300063"
 *   $postalCode->withHyphen();  // "330-0063"
 */
final readonly class PostalCode implements Stringable
{
    /**
     * 「ハイフン」として扱う文字の正規表現
     *
     * U+30FC（ー 長音記号）、U+2010〜U+2015（各種ダッシュ）、U+2212（− マイナス）、U+FF0D（－ 全角ハイフン）など
     * U+4E00（一 漢数字のいち）は意図的に含めている。
     *   日本語入力で「-」のつもりで「一」に変換されてしまうことがあり、
     *   郵便番号は数字だけなので「一」を「-」に置き換えても困ることがないため。
     *
     * 参考:
     * https://qiita.com/ryounagaoka/items/4cf5191d1a2763667add
     * https://hydrocul.github.io/wiki/blog/2014/1101-hyphen-minus-wave-tilde.html
     */
    private const HYPHEN_PATTERN = '/[\x{30FC}\x{2010}-\x{2015}\x{2212}\x{FF70}\x{2053}\x{2E3A}\x{301C}\x{3030}\x{2500}\x{4E00}\x{FE63}\x{FF0D}\x{2043}\x{FE58}\x{23AF}\x{208B}\x{007E}]/u';

    /**
     * 郵便番号の書式：数字3桁 + ハイフン（省略可）+ 数字4桁
     * 前半3桁と後半4桁を、それぞれ取り出せるようにグループ（括弧）にしている
     */
    private const FORMAT_PATTERN = '/^([0-9]{3})-?([0-9]{4})$/';

    /**
     * コンストラクタは private にして、必ず fromString() を通して作らせる（検証を飛ばして作れないようにする）
     * @param string $digits ハイフンなしの数字7桁
     */
    private function __construct(private string $digits)
    {
    }

    /**
     * 入力された文字列から PostalCode を作る
     * @param string $input 入力された郵便番号（全角・各種ハイフン・前後の空白を含んでいてもよい）
     * @return self
     * @throws InvalidPostalCodeException 郵便番号として正しくない場合
     */
    public static function fromString(string $input): self
    {
        if (preg_match(self::FORMAT_PATTERN, self::normalize($input), $matches) !== 1) {
            throw new InvalidPostalCodeException('郵便番号は7桁の数字で入力してください（例：330-0063）');
        }
        return new self($matches[1] . $matches[2]);
    }

    /**
     * 入力された文字列から PostalCode を作る。正しくない場合は、例外ではなく null を返す
     * （入力エラーを画面に表示するときなど、「正しくないこと」が想定内の場面で使う）
     * @param string $input 入力された郵便番号
     * @return self|null 正しくない場合は null
     */
    public static function tryFromString(string $input): ?self
    {
        try {
            return self::fromString($input);
        } catch (InvalidPostalCodeException) {
            return null;
        }
    }

    /**
     * 入力された郵便番号を正規化する（検証はしない）
     * 全角数字・全角スペースを半角にし、前後の空白を取り除き、各種ハイフンを半角「-」にそろえる
     * 例: 「３３０ー００６３」→「330-0063」
     * @param string $input 入力された郵便番号
     * @return string 正規化した文字列
     */
    public static function normalize(string $input): string
    {
        // mb_convert_kana の n は「全角数字→半角」、s は「全角スペース→半角」
        $value = trim(mb_convert_kana($input, 'ns', 'UTF-8'));

        // preg_replace は一致した箇所をすべて置換する。正規表現の実行に失敗したときは null が返るので空文字にする
        return preg_replace(self::HYPHEN_PATTERN, '-', $value) ?? '';
    }

    /**
     * 正規化済みの文字列が、郵便番号の書式かどうかを判定する
     * @param string $normalized 正規化済みの文字列
     * @return bool 正しい書式なら true
     */
    public static function isValidFormat(string $normalized): bool
    {
        return preg_match(self::FORMAT_PATTERN, $normalized) === 1;
    }

    /**
     * ハイフンなしの数字7桁（例: "3300063"）
     */
    public function digits(): string
    {
        return $this->digits;
    }

    /**
     * ハイフンありの形（例: "330-0063"）
     */
    public function withHyphen(): string
    {
        return substr($this->digits, 0, 3) . '-' . substr($this->digits, 3);
    }

    /**
     * 同じ郵便番号かどうかを判定する（値オブジェクトは「中身の値」で比べる）
     */
    public function equals(self $other): bool
    {
        return $this->digits === $other->digits;
    }

    /**
     * 文字列として使ったときは、ハイフンありの形にする
     */
    public function __toString(): string
    {
        return $this->withHyphen();
    }
}
