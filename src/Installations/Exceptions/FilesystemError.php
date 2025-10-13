<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class FilesystemError extends RuntimeException
{
    public function __construct(string $message = 'INSTALL_COPY_OR_PROMOTION_FAILED', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
