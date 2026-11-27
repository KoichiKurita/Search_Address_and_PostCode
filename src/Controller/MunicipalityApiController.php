<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\PostCodeRepository;
use App\Http\Request;
use App\Http\Response;
use Closure;

/**
 * 都道府県に対応する市区町村の一覧を JSON で返す API  POST /api/municipalities
 *   リクエスト: 本文は JSON の文字列（例: "埼玉県"）
 *   レスポンス: 200 … 市区町村名の配列（例: ["さいたま市西区", ...]）
 *               400 … 本文が文字列でない
 *   エラー時は {"error_msg": "..."} を返す（405・500 は Application が返す）
 */
final readonly class MunicipalityApiController
{
    /**
     * @param Closure(): PostCodeRepository $repository Repository を作る関数
     */
    public function __construct(private Closure $repository)
    {
    }

    public function __invoke(Request $request): Response
    {
        // 送られた値を読み込む（JSON として解釈できなければ null になる）
        $prefecture = json_decode($request->body);

        // 文字列以外が送られた場合は、不正なリクエストとして 400 を返す
        if (!is_string($prefecture) || $prefecture === '') {
            return Response::json(['error_msg' => '都道府県が正しく送信されていません。'], 400);
        }

        // JSON にはエスケープせずに入れる（HTML エスケープは、表示する側でだけ行う）
        return Response::json(($this->repository)()->municipalities($prefecture));
    }
}
