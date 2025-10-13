<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

/**
 * Resolves the current actor identifier (e.g., authenticated user id or 'system').
 *
 * Implementations should be environment-aware (HTTP request, CLI job, queue worker).
 * Keep it side-effect free and fast; it is called during token binding and audit logging.
 */
interface ActorResolver
{
    /**
     * Resolve an identifier for the current actor.
     *
     * Typical values:
     *  - a stringified numeric user ID (e.g., "42"),
     *  - a service name in non-interactive contexts (e.g., "system", "queue-worker").
     *
     * @return string Non-empty, URL-safe identifier. Implementations should normalize consistently.
     */
    public function resolve(): string;
}