<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use RuntimeException;

/**
 * Registry for permission definitions declared by a plugin.
 *
 * These are definitions only (no grants). Persistence strategy is implementation-specific.
 */
interface PermissionRegistry
{
    /**
     * Register a batch of permission definitions for a plugin.
     *
     * @param array $definitions Validated definitions (shape is host-defined).
     * @return void
     *
     * @throws RuntimeException On persistence errors.
     */
    public function registerDefinitions(array $definitions): void;
}