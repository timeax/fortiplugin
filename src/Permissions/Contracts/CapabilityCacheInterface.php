<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

/**
 * Caches compiled capabilities per plugin for fast checks.
 * The capability map is an implementation-defined array structure keyed by type.
 */
interface CapabilityCacheInterface
{
    /**
     * Fetch a compiled capability map for a plugin.
     * @param int $pluginId
     * @return array|null
     */
    public function get(int $pluginId): ?array;

    /**
     * Store a compiled capability map for a plugin.
     *
     * @param int        $pluginId
     * @param array      $capabilities Implementation-defined map (per-type compiled rules).
     * @param int|null   $ttlSeconds   Optional TTL.
     * @param string|null $etag        Optional content hash for change detection.
     * @return void
     */
    public function put(int $pluginId, array $capabilities, ?int $ttlSeconds = null, ?string $etag = null): void;

    /**
     * Current ETag (hash) for a plugin’s cached capabilities, if any.
     * @param int $pluginId
     * @return string|null
     */
    public function etag(int $pluginId): ?string;

    /**
     * Invalidate cached capabilities for a plugin.
     * @param int $pluginId
     * @return void
     */
    public function invalidate(int $pluginId): void;
}