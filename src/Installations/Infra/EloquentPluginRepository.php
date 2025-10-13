<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Infra;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\PackageEntry;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginVersion;

/**
 * Eloquent-backed PluginRepository.
 *
 * Schema alignment:
 * - Plugin:      name (unique), plugin_placeholder_id (unique), meta (JSON), status (enum)
 * - PluginVersion: version, archive_url, validation_report (JSON), status (enum)
 *
 * Conventions:
 * - Plugin.name = InstallMeta::placeholder_name (Studly)
 * - Plugin.plugin_placeholder_id = InstallMeta::plugin_placeholder_id
 * - Plugin.meta['install_meta'] mirrors InstallMeta::toArray()
 * - Plugin.meta['packages'] holds { packageName => PackageEntry::toArray() }
 * - PluginVersion.validation_report['install_meta'] keeps a snapshot for traceability
 */
final class EloquentPluginRepository implements PluginRepository
{
    /**
     * Upsert Plugin by (plugin_placeholder_id) and/or (placeholder_name).
     * Writes InstallMeta to Plugin.meta['install_meta'].
     *
     * @param InstallMeta $meta
     * @return int|null   Plugin primary key (never null in practice)
     */
    public function upsertPlugin(InstallMeta $meta): ?int
    {
        /** @var Plugin|null $model */
        $model = Plugin::query()
            ->where('plugin_placeholder_id', $meta->plugin_placeholder_id)
            ->orWhere('name', $meta->placeholder_name)
            ->first();

        if (!$model) {
            $model = new Plugin();
        }
        $model->plugin_placeholder_id = (int)$meta->plugin_placeholder_id;
        $model->name = $meta->placeholder_name;

        $existing = (array)($model->meta ?? []);
        $existing['install_meta'] = $meta->toArray();
        $model->meta = $existing;

        $model->save();

        return $model->id;
    }

    /**
     * Create a PluginVersion row and persist an InstallMeta snapshot.
     *
     * - Stores InstallMeta under validation_report['install_meta'].
     * - Uses InstallMeta.paths['install'] (if present) as archive_url.
     *
     * @param int        $pluginId
     * @param string     $versionTag
     * @param InstallMeta $meta
     * @return int|null  PluginVersion primary key
     */
    public function createVersion(int $pluginId, string $versionTag, InstallMeta $meta): ?int
    {
        /** @var Plugin|null $plugin */
        $plugin = Plugin::query()->find($pluginId);
        if (!$plugin) {
            throw new RuntimeException("Plugin #$pluginId not found");
        }

        $ver = new PluginVersion();
        $ver->plugin_id = $pluginId;
        $ver->version = $versionTag;
        $ver->archive_url = (string)($meta->paths['install'] ?? $meta->paths['staging'] ?? '');

        // Keep the install meta snapshot with the version
        $report = (array)($ver->validation_report ?? []);
        $report['install_meta'] = $meta->toArray();
        $ver->validation_report = $report;

        $ver->save();
        return $ver->id;
    }

    /**
     * Link a PluginZip to a PluginVersion.
     * Since there is no FK on PluginVersion schema, annotate validation_report.
     *
     * @param int        $pluginVersionId
     * @param int|string $zipId
     * @return void
     */
    public function linkZip(int $pluginVersionId, int|string $zipId): void
    {
        /** @var PluginVersion|null $ver */
        $ver = PluginVersion::query()->find($pluginVersionId);
        if (!$ver) {
            throw new RuntimeException("PluginVersion #$pluginVersionId not found");
        }

        $report = (array)($ver->validation_report ?? []);
        $report['linked_zip_id'] = (string)$zipId;
        $ver->validation_report = $report;

        $ver->save();
    }

    /**
     * Persist canonical plugin meta snapshot to Plugin.meta['install_meta'].
     *
     * @param int        $pluginId
     * @param InstallMeta $meta
     * @return void
     */
    public function saveMeta(int $pluginId, InstallMeta $meta): void
    {
        /** @var Plugin|null $plugin */
        $plugin = Plugin::query()->find($pluginId);
        if (!$plugin) {
            throw new RuntimeException("Plugin #$pluginId not found");
        }

        $existing = (array)($plugin->meta ?? []);
        $existing['install_meta'] = $meta->toArray();
        $plugin->meta = $existing;
        $plugin->save();
    }

    /**
     * Persist the packages map (foreign/verified) under Plugin.meta['packages'].
     *
     * @param int                           $pluginId
     * @param array<string,PackageEntry>    $packages
     * @return void
     */
    public function savePackages(int $pluginId, array $packages): void
    {
        /** @var Plugin|null $plugin */
        $plugin = Plugin::query()->find($pluginId);
        if (!$plugin) {
            throw new RuntimeException("Plugin #$pluginId not found");
        }

        $existing = (array)($plugin->meta ?? []);
        $existing['packages'] = [];

        foreach ($packages as $name => $entry) {
            if (!$entry instanceof PackageEntry) {
                throw new RuntimeException("packages['$name'] must be a PackageEntry DTO");
            }
            $existing['packages'][$name] = $entry->toArray();
        }

        $plugin->meta = $existing;
        $plugin->save();
    }

    /**
     * Update Plugin.status (enum as string).
     *
     * @param int    $pluginId
     * @param string $status
     * @return void
     */
    public function setStatus(int $pluginId, string $status): void
    {
        /** @var Plugin|null $plugin */
        $plugin = Plugin::query()->find($pluginId);
        if (!$plugin) {
            throw new RuntimeException("Plugin #$pluginId not found");
        }
        $plugin->status = $status;
        $plugin->save();
    }
}