<?php

declare(strict_types=1);

namespace Sendara\Exception;

use Exception;
use Throwable;

class SendaraException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
