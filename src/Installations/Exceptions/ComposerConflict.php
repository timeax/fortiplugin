<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class ComposerConflict extends RuntimeException
{
    public function __construct(string $message = 'COMPOSER_CORE_CONFLICT', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
