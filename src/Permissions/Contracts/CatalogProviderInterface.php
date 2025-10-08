<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

/**
 * Provides host-defined catalogs used for normalization and enforcement.
 * These are read-only views over config/state; implementations can pull from config files or DB.
 */
interface CatalogProviderInterface
{
    /**
     * Model catalog.
     * @return array alias => [
     *   'map'       => string FQCN,
     *   'relations' => array<string,string>,    // optional alias→alias
     *   'columns'   => ['all'=>?string[], 'writable'=>?string[]], // optional policy
     * ]
     */
    public function models(): array;

    /**
     * Notification channels.
     * @return string[] List of channel aliases.
     */
    public function notificationChannels(): array;

    /**
     * Host modules.
     * @return array alias => ['map'=>string FQCN, 'docs'=>?string]
     */
    public function modules(): array;

    /**
     * Codec/Obfuscator groups.
     * @return array group => string[] method names (from Obfuscator::availableGroups()).
     */
    public function codecGroups(): array;

    /**
     * Current environment (e.g., 'production', 'staging', 'dev').
     * @return string
     */
    public function env(): string;

    /**
     * Host/plugin settings snapshot for conditions evaluation, if needed.
     * @param int $pluginId
     * @return array e.g., ['enable_codec'=>true, ...]
     */
    public function settingsForPlugin(int $pluginId): array;
}