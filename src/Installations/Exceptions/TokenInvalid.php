<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class TokenInvalid extends RuntimeException
{
    public function __construct(string $message = 'TOKEN_INVALID', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
