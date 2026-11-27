<?php

declare(strict_types=1);

namespace App\Http;

use Throwable;

/**
 * テンプレート（templates/*.php）から HTML を作る
 *
 * 各画面のテンプレートは「その画面に固有の部分」だけを書き、
 * <head> やリンクなどの共通部分は templates/layout.php にまとめる。
 * テンプレートの中では、渡した配列のキーが、そのまま変数として使える（例: ['title' => 'x'] → $title）。
 */
final readonly class View
{
    /**
     * @param string $templateDirectory テンプレートを置いているディレクトリ
     */
    public function __construct(private string $templateDirectory)
    {
    }

    /**
     * 画面のテンプレートを、共通のレイアウトにはめ込んだ HTML のレスポンスを返す
     * @param string $template テンプレート名（例: "address" → templates/address.php）
     * @param string $title ページのタイトル
     * @param array<string, mixed> $data テンプレートに渡す変数
     * @param string|null $script 読み込む JavaScript（例: "js/address_page.js"。無ければ null）
     * @param int $status HTTP ステータスコード
     */
    public function page(Request $request, string $template, string $title, array $data = [], ?string $script = null, int $status = 200): Response
    {
        $html = $this->render('layout', [
            'basePath' => $request->basePath,
            'path' => $request->path,
            'title' => $title,
            'content' => $this->render($template, $data),
            'script' => $script,
        ]);
        return Response::html($html, $status);
    }

    /**
     * エラーページのレスポンスを返す
     * @param string $message 利用者に表示するメッセージ（エラーの詳細は書かない）
     */
    public function error(Request $request, int $status, string $message): Response
    {
        return $this->page($request, 'error', "エラー（{$status}）", ['message' => $message], null, $status);
    }

    /**
     * テンプレートを実行して、出力された HTML を文字列で受け取る
     * @param array<string, mixed> $data テンプレートに渡す変数
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->templateDirectory . '/' . $template . '.php';
        return self::capture($file, $data);
    }

    /**
     * テンプレートを、ほかの変数が見えない関数の中で実行する（$this や $file をテンプレートから触らせない）
     * @param string $__file テンプレートのファイル
     * @param array<string, mixed> $__data テンプレートに渡す変数
     */
    private static function capture(string $__file, array $__data): string
    {
        // EXTR_SKIP: すでにある変数（$__file・$__data）を上書きしない
        extract($__data, EXTR_SKIP);

        // echo した内容を画面に出さずにため込み、最後に文字列として取り出す
        ob_start();
        try {
            require $__file;
            return (string)ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();     // 途中までの HTML を捨てる（エラーページと混ざらないように）
            throw $e;
        }
    }
}
