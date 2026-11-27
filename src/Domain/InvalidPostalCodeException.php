<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * 郵便番号として正しくない値から PostalCode を作ろうとしたときの例外
 */
final class InvalidPostalCodeException extends InvalidArgumentException
{
}
