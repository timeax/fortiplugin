<?php
// src/Routes/Exceptions/MissingRoutePermissionsException.php
namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;

final class MissingRoutePermissionsException extends RuntimeException
{
    /** @param string[] $missingIds */
    public function __construct(public readonly array $missingIds, string $pluginSlug)
    {
        parent::__construct(
            "Cannot write routes for plugin '{$pluginSlug}': missing approvals for route ids: " .
            implode(', ', $missingIds)
        );
    }

    public function getMissingIds(): array
    {
        return $this->missingIds;
    }
}