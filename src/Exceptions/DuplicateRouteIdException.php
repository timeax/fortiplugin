<?php

namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;

final class DuplicateRouteIdException extends RuntimeException
{
    public function __construct(
        public readonly string $routeId,
        public readonly string $firstFile,
        public readonly string $firstPath,
        public readonly string $dupFile,
        public readonly string $dupPath
    )
    {
        parent::__construct(
            "Duplicate route id '$routeId' found.\n" .
            " - First seen in: $firstFile $firstPath\n" .
            " - Duplicate in:  $dupFile $dupPath"
        );
    }
}