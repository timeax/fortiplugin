<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class ZipValidationFailed extends RuntimeException
{
    public function __construct(string $message = 'ZIP_VALIDATION_FAILED', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}


