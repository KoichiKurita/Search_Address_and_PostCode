<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Address;
use App\Domain\Office;
use App\Domain\PostalCode;
use App\Domain\PostCodeRepository;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use Closure;

/**
 * 住所検索（郵便番号 → 住所）の画面  GET /address
 *
 * クエリパラメータ post-code が無ければ入力画面、あれば検索結果の画面を表示する。
 * 以前は入力画面（search_address.html）と結果画面（result_address.php）に分かれていたが、
 * どちらも同じフォームを表示していたので、1つの画面にまとめた。
 */
final readonly class AddressSearchController
{
    /**
     * @param Closure(): PostCodeRepository $repository Repository を作る関数
     *        （入力画面では DB を使わないので、検索するときにだけ DB に接続する）
     */
    public function __construct(
        private Closure $repository,
        private View $view,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // 入力された郵便番号（画面に入力値を残すため、正規化した文字列も持っておく）
        $input = PostalCode::normalize($request->query('post-code'));

        $postalCode = null;     // 検索した郵便番号（入力画面と、入力エラーのときは null）
        /** @var list<Address> $addresses */
        $addresses = [];
        /** @var list<Office> $offices 事業所の個別郵便番号（#36） */
        $offices = [];
        $errorMessage = '';
        $status = 200;

        if ($request->hasQuery('post-code')) {
            // PostCode に変換する。正しい郵便番号でなければ null になる（検索できるのは PostalCode だけ）
            $postalCode = PostalCode::tryFromString($input);

            if ($postalCode === null) {
                // 書式が正しくない場合は DB を検索せず、400（Bad Request）とエラーメッセージを返す
                // JS のチェックは URL を直接書き換えれば通らずに送れるので、サーバー側でも必ず検証する
                $status = 400;
                $errorMessage = '郵便番号は7桁の数字で入力してください（例：330-0063）';
            } else {
                $repository = ($this->repository)();
                $addresses = $repository->findByPostalCode($postalCode);
                $offices = $repository->findOfficesByPostalCode($postalCode);
            }
        }

        return $this->view->page(
            $request,
            'address',
            $request->hasQuery('post-code') ? '住所検索結果' : '住所検索',
            [
                'input' => $input,
                'postalCode' => $postalCode,
                'addresses' => $addresses,
                'offices' => $offices,
                'errorMessage' => $errorMessage,
            ],
            'js/address_page.js',
            $status,
        );
    }
}
