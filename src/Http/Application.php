<?php

declare(strict_types=1);

namespace App\Http;

use App\Controller\AddressApiController;
use App\Controller\AddressSearchController;
use App\Controller\MunicipalityApiController;
use App\Controller\PostCodeSearchController;
use App\Domain\PostCodeRepository;
use Closure;
use Throwable;

/**
 * アプリ全体（URL と画面の対応表と、エラーの処理）
 *
 * フロントコントローラ（public/index.php）は、すべてのリクエストをここに渡す。
 * どの URL で何を表示するかが、このクラスの routes() を見れば分かる。
 */
final class Application
{
    private Router $router;

    /**
     * @param Closure(): PostCodeRepository $repository Repository を作る関数（DB に接続する）
     *        テストでは、SQLite のメモリ上の DB を使う Repository を渡す
     */
    public function __construct(
        private Closure $repository,
        private View $view,
    ) {
        $this->router = $this->routes();
    }

    /**
     * URL と、それを処理するコントローラの対応表
     */
    private function routes(): Router
    {
        // DB への接続は、最初に必要になったときに1回だけ行う（入力画面だけなら接続しない）
        $connection = null;
        $repository = function () use (&$connection): PostCodeRepository {
            return $connection ??= ($this->repository)();
        };

        $router = new Router();
        $router->get('address', (new AddressSearchController($repository, $this->view))(...));
        $router->get('postcode', (new PostCodeSearchController($repository, $this->view))(...));
        $router->post('api/municipalities', (new MunicipalityApiController($repository))(...));
        $router->get('api/address', (new AddressApiController($repository))(...));     // 郵便番号 → 住所の JSON API（#33）

        // トップページは住所検索へ（302: 一時的な移動。トップページを作ったら変えられるように）
        $router->redirect('', 'address', 302);

        // 以前の URL（ブックマークや検索エンジンに残っているもの）は、新しい URL へ移動させる
        $router->redirect('search_address.html', 'address');
        $router->redirect('result_address.php', 'address');
        $router->redirect('search_postcode.php', 'postcode');
        $router->redirect('result_postcode.php', 'postcode');
        $router->redirect('get_municipality.php', 'api/municipalities', 308);   // 308: POST のまま送り直させる
        $router->redirect('index.php', 'address');

        return $router;
    }

    /**
     * リクエストを処理して、レスポンスを返す（例外が起きても、必ずレスポンスを返す）
     */
    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            $response = $this->error($request, $e->status, $e->getMessage());
            foreach ($e->headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
            return $response;
        } catch (Throwable $e) {
            // DB に接続できないなど、想定外のエラー
            // 詳細（SQL のエラー内容など）は画面には出さず、サーバーのログにだけ記録する
            error_log("{$request->method} /{$request->path}: {$e}");
            return $this->error($request, 500, 'エラーが発生しました。時間をおいて再度お試しください。');
        }
    }

    /**
     * エラーのレスポンス（API は JSON、画面は HTML のエラーページ）
     */
    private function error(Request $request, int $status, string $message): Response
    {
        if (str_starts_with($request->path, 'api/')) {
            return Response::json(['error_msg' => $message], $status);
        }
        return $this->view->error($request, $status, $message);
    }
}
