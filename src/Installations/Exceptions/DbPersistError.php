<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class DbPersistError extends RuntimeException
{
    public function __construct(string $message = 'DB_PERSIST_FAILED', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
