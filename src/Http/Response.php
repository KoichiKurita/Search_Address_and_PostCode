<?php

declare(strict_types=1);

namespace App\Http;

/**
 * HTTP レスポンスを表すオブジェクト
 *
 * コントローラは echo や header() を直接使わず、このオブジェクトを返す。
 * 実際にブラウザへ送るのは、フロントコントローラが最後に呼ぶ send() の1か所だけなので、
 * 「HTML を出力したあとでステータスコードを変えられない」という問題が起きない。
 * テストでは、送る前のステータスコード・ヘッダー・本文をそのまま確かめられる。
 */
final readonly class Response
{
    /**
     * @param int $status HTTP ステータスコード
     * @param string $body 本文
     * @param array<string, string> $headers ヘッダー（名前 => 値）
     */
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
    ) {
    }

    /**
     * HTML のレスポンス
     */
    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * JSON のレスポンス
     * JSON_UNESCAPED_UNICODE: 日本語を \u3042 のような形にせず、そのまま出力する
     * @throws \JsonException JSON に変換できない値の場合
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        return new self($status, $body, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /**
     * 別の URL へ移動させるレスポンス
     * 301: 恒久的な移動（古い URL など） / 302: 一時的な移動 / 308: 恒久的な移動（POST を POST のまま送り直させる）
     */
    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, '', ['Location' => $location]);
    }

    /**
     * ヘッダーを追加した、新しいレスポンスを返す（readonly なので、自分自身は変えない）
     */
    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, $this->body, [$name => $value] + $this->headers);
    }

    /**
     * ブラウザへ送る（フロントコントローラで1回だけ呼ぶ）
     */
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
