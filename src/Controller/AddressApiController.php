<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Address;
use App\Domain\PostalCode;
use App\Domain\PostCodeRepository;
use App\Http\Request;
use App\Http\Response;
use Closure;

/**
 * 郵便番号から住所を返す JSON API  GET /api/address?zip=3300063
 *   zip: 郵便番号（ハイフンの有無・全角数字・各種ハイフンは、画面と同じく正規化する）
 *   レスポンス: 200 … {"zip": "330-0063", "count": 1, "addresses": [{...}]}（見つからなければ count が 0）
 *               400 … zip が無い、または郵便番号の形でない
 *   エラー時は {"error_msg": "..."} を返す（405・500 は Application が返す）
 *
 * ほかのサイトの JavaScript からも使えるように、CORS を許可する（Access-Control-Allow-Origin: *）。
 * 公開されている郵便番号データを返すだけで、Cookie などの利用者の情報は扱わないので、すべてのサイトに許可してよい。
 */
final readonly class AddressApiController
{
    /**
     * 結果をブラウザなどにキャッシュしてよい秒数（1日）
     * 郵便番号データは月に1回しか変わらないので、同じ郵便番号を何度も問い合わせさせない
     */
    private const CACHE_SECONDS = 86400;

    /**
     * @param Closure(): PostCodeRepository $repository Repository を作る関数
     */
    public function __construct(private Closure $repository)
    {
    }

    public function __invoke(Request $request): Response
    {
        $postalCode = PostalCode::tryFromString(PostalCode::normalize($request->query('zip')));
        if ($postalCode === null) {
            // エラーの内容もほかのサイトの JavaScript から読めるように、CORS のヘッダーを付ける（キャッシュはさせない）
            return Response::json(['error_msg' => '郵便番号を7桁の数字で指定してください（例：?zip=330-0063）。'], 400)
                ->withHeader('Access-Control-Allow-Origin', '*');
        }

        $addresses = ($this->repository)()->findByPostalCode($postalCode);

        // 返す JSON の形は、ここで決める（Address のプロパティ名を変えても、API の形は変わらない）
        return Response::json([
            'zip' => $postalCode->withHyphen(),
            'count' => count($addresses),
            'addresses' => array_map(static fn (Address $address): array => [
                'zip' => $address->postalCode->withHyphen(),
                'prefecture' => $address->prefecture,
                'municipality' => $address->municipality,
                'town_area' => $address->townArea,
                'full_address' => $address->fullAddress(),
                // フリガナ（全角カタカナ。#37）
                'prefecture_kana' => $address->prefectureKana,
                'municipality_kana' => $address->municipalityKana,
                'town_area_kana' => $address->townAreaKana,
                'full_address_kana' => $address->fullAddressKana(),
            ], $addresses),
        ])
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Cache-Control', 'public, max-age=' . self::CACHE_SECONDS);
    }
}
