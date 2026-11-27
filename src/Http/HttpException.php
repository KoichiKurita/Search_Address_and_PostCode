<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;
use Throwable;

/**
 * エラーのレスポンス（404・405 など）にしたいときに投げる例外
 * フロントコントローラ（Application）が受け取り、エラーページ（API なら JSON）にして返す
 */
final class HttpException extends RuntimeException
{
    /**
     * @param int $status HTTP ステータスコード
     * @param string $message 利用者に表示するメッセージ（エラーの詳細は書かない）
     * @param array<string, string> $headers エラーのレスポンスに付けるヘッダー（405 の Allow など）
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
