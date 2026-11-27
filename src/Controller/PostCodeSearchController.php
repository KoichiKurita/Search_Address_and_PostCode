<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Address;
use App\Domain\PostCodeRepository;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use Closure;

/**
 * 郵便番号検索（住所 → 郵便番号）の画面  GET /postcode
 *
 * クエリパラメータ prefecture が無ければ入力画面、あれば検索結果の画面を表示する。
 * 以前の search_postcode.php（入力画面）と result_postcode.php（結果画面）を1つにまとめた。
 */
final readonly class PostCodeSearchController
{
    /**
     * @param Closure(): PostCodeRepository $repository Repository を作る関数
     */
    public function __construct(
        private Closure $repository,
        private View $view,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $repository = ($this->repository)();
        $searched = $request->hasQuery('prefecture');

        // 検索条件（未入力や、配列が送られた場合は空文字にする）
        $prefecture   = $request->query('prefecture');
        $municipality = $request->query('municipality');
        $townArea     = trim($request->query('town_area'));
        $page         = parse_page($request->query('page'));   // 表示するページ番号（不正な値は 1）

        // 都道府県の一覧（プルダウンの表示と、入力値の検証の両方に使う）
        $prefectures = $repository->prefectures();

        /** @var list<Address> $addresses */
        $addresses = [];        // 検索結果（表示するページの分だけ）
        $total = 0;             // 検索結果の総件数
        $lastPage = 1;          // 最後のページ番号
        $offset = 0;            // 表示するページの先頭が、全体の何件目の次か（0 から数える）
        $errorMessage = '';     // 入力エラーのメッセージ（空ならエラーなし）
        $isFallback = false;    // 町名で見つからず、市区町村だけで検索し直したかどうか

        if ($searched) {
            // 入力値の検証：都道府県・市区町村は「DB に存在する値か」で確かめる（ホワイトリスト方式）
            if (!in_array($prefecture, $prefectures, true)) {
                $errorMessage = '都道府県を選択してください。';
            } elseif (!in_array($municipality, $repository->municipalities($prefecture), true)) {
                $errorMessage = '市区町村を選択してください。';
            } elseif (mb_strlen($townArea) > TOWN_AREA_MAX_LENGTH) {
                $errorMessage = '町名は' . TOWN_AREA_MAX_LENGTH . '文字以内で入力してください。';
            }
        }

        if ($searched && $errorMessage === '') {
            // 町名が入力されていれば町名でも絞り込む（未入力なら null で、町名の条件を付けない）
            $townCondition = $townArea !== '' ? $townArea : null;
            $total = $repository->countByArea($prefecture, $municipality, $townCondition);

            // 町名で0件だった場合は、都道府県+市区町村で検索し直す
            if ($total === 0 && $townCondition !== null) {
                $townCondition = null;
                $total = $repository->countByArea($prefecture, $municipality);
                $isFallback = $total > 0;
            }

            // 最後のページより大きい番号が指定された場合は、最後のページを表示する
            $lastPage = last_page($total, POST_CODE_PAGE_SIZE);
            $page = min($page, $lastPage);

            // 表示するページの分だけ取得する（例: 2ページ目なら 51〜100件目）
            $offset = ($page - 1) * POST_CODE_PAGE_SIZE;
            $addresses = $repository->searchByArea($prefecture, $municipality, $townCondition, POST_CODE_PAGE_SIZE, $offset);
        }

        // 同じ検索条件で、指定したページを表示する URL（http_build_query は URL エンコードもまとめて行う）
        $pageUrl = static fn (int $number): string => 'postcode?' . http_build_query([
            'prefecture'   => $prefecture,
            'municipality' => $municipality,
            'town_area'    => $townArea,
            'page'         => $number,
        ]);

        return $this->view->page(
            $request,
            'postcode',
            $searched ? '郵便番号検索結果' : '郵便番号検索',
            [
                'prefectures' => $prefectures,
                'prefecture' => $prefecture,
                'municipality' => $municipality,
                'townArea' => $townArea,
                'showResult' => $searched && $errorMessage === '',
                'addresses' => $addresses,
                'total' => $total,
                'page' => $page,
                'lastPage' => $lastPage,
                'offset' => $offset,
                'isFallback' => $isFallback,
                'errorMessage' => $errorMessage,
                'pageUrl' => $pageUrl,
            ],
            'js/postcode_page.js',
            $errorMessage === '' ? 200 : 400,   // 入力値が正しくない場合は 400（Bad Request）
        );
    }
}
