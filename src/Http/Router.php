<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

/**
 * URL（メソッドとパス）から、処理する関数を選ぶ
 *
 * 例: $router->get('address', $handler) … GET /address を $handler で処理する
 * 一致するパスが無ければ 404、パスはあるがメソッドが違えば 405 の HttpException を投げる。
 */
final class Router
{
    /**
     * パス => [メソッド => 処理する関数]（メソッドが「*」のものは、すべてのメソッドで使う）
     * @var array<string, array<string, Closure(Request): Response>>
     */
    private array $routes = [];

    /**
     * @param Closure(Request): Response $handler
     */
    public function get(string $path, Closure $handler): void
    {
        $this->routes[$path]['GET'] = $handler;
    }

    /**
     * @param Closure(Request): Response $handler
     */
    public function post(string $path, Closure $handler): void
    {
        $this->routes[$path]['POST'] = $handler;
    }

    /**
     * 古い URL を、新しい URL へ移動させる（クエリパラメータはそのまま引き継ぐ）
     * ブックマークや検索エンジンに残っている古い URL を、404 にしないために使う
     */
    public function redirect(string $from, string $to, int $status = 301): void
    {
        $this->routes[$from]['*'] = static fn (Request $request): Response => Response::redirect(
            $request->url($to) . ($request->query === [] ? '' : '?' . http_build_query($request->query)),
            $status,
        );
    }

    /**
     * リクエストを処理する関数を選んで実行する
     * @throws HttpException 一致するパスが無い（404）、またはメソッドが違う（405）場合
     */
    public function dispatch(Request $request): Response
    {
        $handlers = $this->routes[$request->path] ?? null;
        if ($handlers === null) {
            throw new HttpException(404, 'ページが見つかりません。');
        }

        // HEAD は「本文なしの GET」なので、GET と同じ関数で処理する（本文は PHP が送らない）
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $handler = $handlers[$method] ?? $handlers['*'] ?? null;
        if ($handler === null) {
            // 405 を返すときは、使えるメソッドを Allow ヘッダーで知らせる
            throw new HttpException(405, '許可されていないメソッドです。', ['Allow' => implode(', ', array_keys($handlers))]);
        }

        return $handler($request);
    }
}
