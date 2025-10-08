<?php

namespace Timeax\FortiPlugin\Core\Exceptions;

use RuntimeException;

final class DuplicateSettingIdException extends RuntimeException
{
    public function __construct(string|int|float $id, string $where)
    {
        parent::__construct("Duplicate setting id '{$id}' detected {$where}.");
    }
}