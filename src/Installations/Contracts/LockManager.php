<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use RuntimeException;

/**
 * Per-slug lease to prevent concurrent installs of the same plugin.
 *
 * Implementations should store leases durably (DB, cache) and enforce:
 *  - exclusivity (only one holder),
 *  - TTL (auto-expire to avoid deadlocks),
 *  - renewal (refresh),
 *  - safe release by the current lease holder only.
 */
interface LockManager
{
    /**
     * Acquire a lease for the given slug.
     *
     * @param string $slug       Canonical plugin slug (often derived from Placeholder.name).
     * @param int    $ttlSeconds Time-to-live in seconds.
     * @return string            Opaque lease identifier (unique per acquisition).
     *
     * @throws RuntimeException If already locked or on store failure.
     */
    public function acquire(string $slug, int $ttlSeconds = 30): string;

    /**
     * Refresh (extend) an existing lease.
     *
     * @param string $slug
     * @param string $leaseId    Lease identifier returned by acquire().
     * @param int    $ttlSeconds New TTL in seconds.
     * @return void
     *
     * @throws RuntimeException If the lease is not held by $leaseId or cannot be extended.
     */
    public function refresh(string $slug, string $leaseId, int $ttlSeconds = 30): void;

    /**
     * Release a held lease.
     *
     * @param string $slug
     * @param string $leaseId
     * @return void
     *
     * @throws RuntimeException If the lease is not held by $leaseId or on store failure.
     */
    public function release(string $slug, string $leaseId): void;

    /**
     * Determine whether a lease is currently held.
     *
     * @param string      $slug
     * @param string|null $byLeaseId Optional lease id to check ownership.
     * @return bool
     */
    public function isHeld(string $slug, ?string $byLeaseId = null): bool;
}