<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class ValidationFailed extends RuntimeException
{
    public function __construct(string $message = 'VALIDATION_FAILED', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
