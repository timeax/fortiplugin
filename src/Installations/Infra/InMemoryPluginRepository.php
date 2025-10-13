<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Infra;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\PackageEntry;

/**
 * In-memory PluginRepository for tests/dev.
 */
final class InMemoryPluginRepository implements PluginRepository
{
    private int $nextPluginId = 1;
    private int $nextVersionId = 1;

    /** @var array<int,array{ id:int, name:string, plugin_placeholder_id:int|string, meta:array, status?:string }> */
    private array $plugins = [];

    /** @var array<int,array{ id:int, plugin_id:int, version:string, validation_report?:array, archive_url?:string|null }> */
    private array $versions = [];

    /** @var array<int,int|string> pluginVersionId => zipId */
    private array $links = [];

    public function upsertPlugin(InstallMeta $meta): ?int
    {
        foreach ($this->plugins as $id => $p) {
            if ($p['plugin_placeholder_id'] === $meta->plugin_placeholder_id || $p['name'] === $meta->placeholder_name) {
                $p['name'] = $meta->placeholder_name;
                $p['plugin_placeholder_id'] = $meta->plugin_placeholder_id;
                $p['meta']['install_meta'] = $meta->toArray();
                $this->plugins[$id] = $p;
                return $id;
            }
        }
        $id = $this->nextPluginId++;
        $this->plugins[$id] = [
            'id' => $id,
            'name' => $meta->placeholder_name,
            'plugin_placeholder_id' => $meta->plugin_placeholder_id,
            'meta' => ['install_meta' => $meta->toArray()],
        ];
        return $id;
    }

    public function createVersion(int $pluginId, string $versionTag, InstallMeta $meta): ?int
    {
        if (!isset($this->plugins[$pluginId])) {
            throw new RuntimeException("Plugin #$pluginId not found");
        }
        $id = $this->nextVersionId++;
        $this->versions[$id] = [
            'id' => $id,
            'plugin_id' => $pluginId,
            'version' => $versionTag,
            'validation_report' => ['install_meta' => $meta->toArray()],
            'archive_url' => $meta->paths['install'] ?? ($meta->paths['staging'] ?? null),
        ];
        return $id;
    }

    public function linkZip(int $pluginVersionId, int|string $zipId): void
    {
        if (!isset($this->versions[$pluginVersionId])) {
            throw new RuntimeException("PluginVersion #$pluginVersionId not found");
        }
        $this->links[$pluginVersionId] = $zipId;
        $report = (array)($this->versions[$pluginVersionId]['validation_report'] ?? []);
        $report['linked_zip_id'] = (string)$zipId;
        $this->versions[$pluginVersionId]['validation_report'] = $report;
    }

    public function saveMeta(int $pluginId, InstallMeta $meta): void
    {
        if (!isset($this->plugins[$pluginId])) {
            throw new RuntimeException("Plugin #$pluginId not found");
        }
        $this->plugins[$pluginId]['meta']['install_meta'] = $meta->toArray();
    }

    /** @param array<string,PackageEntry> $packages */
    public function savePackages(int $pluginId, array $packages): void
    {
        if (!isset($this->plugins[$pluginId])) {
            throw new RuntimeException("Plugin #$pluginId not found");
        }
        $map = [];
        foreach ($packages as $name => $entry) {
            if (!$entry instanceof PackageEntry) {
                throw new RuntimeException("packages['$name'] must be a PackageEntry DTO");
            }
            $map[$name] = $entry->toArray();
        }
        $this->plugins[$pluginId]['meta']['packages'] = $map;
    }

    public function setStatus(int $pluginId, string $status): void
    {
        if (!isset($this->plugins[$pluginId])) {
            throw new RuntimeException("Plugin #$pluginId not found");
        }
        $this->plugins[$pluginId]['status'] = $status;
    }
}