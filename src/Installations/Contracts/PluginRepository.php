<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\PackageEntry;

/**
 * DB façade for Plugin + related records (Eloquent-backed in production).
 *
 * Methods are DTO-first; avoid raw array payloads.
 */
interface PluginRepository
{
    /**
     * Upsert the Plugin row (keyed by plugin_placeholder_id and/or placeholder_name).
     *
     * @param InstallMeta $meta Identity & canonical meta (psr4_root, placeholder_name, ids, fingerprint, etc.)
     * @return int|null         Plugin ID (primary key) or null on no-op
     */
    public function upsertPlugin(InstallMeta $meta): ?int;

    /**
     * Create a PluginVersion row linked to the Plugin.
     *
     * @param int        $pluginId
     * @param string     $versionTag   Free-form version tag or fingerprint
     * @param InstallMeta $meta        Meta snapshot (paths, fingerprint/config hash)
     * @return int|null                PluginVersion ID or null on no-op
     */
    public function createVersion(int $pluginId, string $versionTag, InstallMeta $meta): ?int;

    /**
     * Link a PluginZip to a PluginVersion.
     *
     * @param int        $pluginVersionId
     * @param int|string $zipId
     */
    public function linkZip(int $pluginVersionId, int|string $zipId): void;

    /**
     * Persist canonical plugin meta (usually derived from installation.json.meta).
     *
     * @param int        $pluginId
     * @param InstallMeta $meta
     */
    public function saveMeta(int $pluginId, InstallMeta $meta): void;

    /**
     * Persist the packages map (foreign/verified statuses).
     *
     * @param int                           $pluginId
     * @param array<string,PackageEntry>    $packages Map: package name => PackageEntry DTO
     */
    public function savePackages(int $pluginId, array $packages): void;

    /**
     * Update Plugin status (e.g., installed_inactive, active, failed_install).
     */
    public function setStatus(int $pluginId, string $status): void;
}