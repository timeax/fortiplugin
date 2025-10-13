# Infra

This README lists all files in this folder and their source code.

## EloquentPluginRepository.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Infra;

use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginVersion;

class EloquentPluginRepository implements PluginRepository
{
    /** @return array{id:int}|null */
    public function upsertPlugin(array $pluginData): ?array
    {
        $name = (string)($pluginData['slug'] ?? $pluginData['name'] ?? 'plugin');
        // Find by unique name; create if missing
        $model = Plugin::query()->where('name', $name)->first();
        if (!$model) {
            $model = new Plugin();
            $model->name = $name;
        }
        // Optionally persist meta/paths to meta for visibility
        $meta = (array)($model->meta ?? []);
        if (isset($pluginData['paths']) && is_array($pluginData['paths'])) {
            $meta['install_paths'] = $pluginData['paths'];
        }
        $model->meta = $meta;
        $model->save();
        return ['id' => (int)$model->id];
    }

    /** @return array{id:int}|null */
    public function createVersion(int $pluginId, array $versionData): ?array
    {
        $versionId = (string)($versionData['version_id'] ?? date('YmdHis'));
        $paths = (array)($versionData['paths'] ?? []);
        $archiveUrl = (string)($paths['version_dir'] ?? ($paths['install_root'] ?? ''));

        $ver = new PluginVersion();
        $ver->plugin_id = $pluginId;
        $ver->version = $versionId;
        $ver->archive_url = $archiveUrl;
        $ver->manifest = null;
        // Keep default status; optional validation_report can hold installer hints
        $ver->validation_report = ['installer_paths' => $paths];
        $ver->save();
        return ['id' => (int)$ver->id];
    }

    public function linkZip(int $pluginVersionId, int|string $zipId): void
    {
        // No explicit schema link in current models; annotate validation_report as a best-effort.
        $ver = PluginVersion::query()->find($pluginVersionId);
        if ($ver) {
            $report = (array)($ver->validation_report ?? []);
            $report['linked_zip_id'] = (string)$zipId;
            $ver->validation_report = $report;
            $ver->save();
        }
    }

    public function saveMeta(int $pluginId, array $meta): void
    {
        $plugin = Plugin::query()->find($pluginId);
        if ($plugin) {
            $existing = (array)($plugin->meta ?? []);
            $plugin->meta = $existing + $meta; // merge, new keys win
            $plugin->save();
        }
    }
}
```

## EloquentZipRepository.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Infra;

use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;
use Timeax\FortiPlugin\Models\PluginZip;
use Timeax\FortiPlugin\Enums\ValidationStatus as ModelValidationStatus;

class EloquentZipRepository implements ZipRepository
{
    public function __construct()
    {
    }

    public function getZip(int|string $zipId): array|null
    {
        $zip = PluginZip::query()->find($zipId);
        if (!$zip) return null;
        return [
            'id' => (string)$zip->id,
            'validation_status' => $zip->validation_status?->value ?? null,
            'path' => $zip->path,
            'meta' => (array)$zip->meta,
            'placeholder_id' => $zip->placeholder_id,
            'uploaded_by_author_id' => $zip->uploaded_by_author_id,
            'created_at' => (string)$zip->created_at,
        ];
    }

    public function getValidationStatus(int|string $zipId): ZipValidationStatus
    {
        $zip = PluginZip::query()->select(['id', 'validation_status'])->find($zipId);
        if (!$zip) return ZipValidationStatus::UNKNOWN;
        return $this->mapModelToGate($zip->validation_status);
    }

    public function setValidationStatus(int|string $zipId, ZipValidationStatus $status): void
    {
        $zip = PluginZip::query()->findOrFail($zipId);
        $zip->validation_status = $this->mapGateToModel($status);
        $zip->save();
    }

    private function mapModelToGate(?ModelValidationStatus $s): ZipValidationStatus
    {
        return match ($s) {
            ModelValidationStatus::valid => ZipValidationStatus::VERIFIED,
            ModelValidationStatus::pending => ZipValidationStatus::PENDING,
            ModelValidationStatus::failed => ZipValidationStatus::FAILED,
            default => ZipValidationStatus::UNKNOWN,
        };
    }

    private function mapGateToModel(ZipValidationStatus $s): ModelValidationStatus
    {
        return match ($s) {
            ZipValidationStatus::VERIFIED => ModelValidationStatus::valid,
            ZipValidationStatus::PENDING => ModelValidationStatus::pending,
            ZipValidationStatus::FAILED => ModelValidationStatus::failed,
            ZipValidationStatus::UNKNOWN => ModelValidationStatus::unverified,
        };
    }
}
```

## InMemoryPluginRepository.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Infra;

use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;

class InMemoryPluginRepository implements PluginRepository
{
    private int $nextPluginId = 1;
    private int $nextVersionId = 1;

    /** @var array<int,array> */
    private array $plugins = [];
    /** @var array<int,array> */
    private array $versions = [];
    /** @var array<int,int|string> */
    private array $links = [];
    /** @var array<int,array> */
    private array $meta = [];

    public function upsertPlugin(array $pluginData): ?array
    {
        // naive: find by name|slug
        $name = (string)($pluginData['slug'] ?? $pluginData['name'] ?? 'plugin');
        foreach ($this->plugins as $id => $p) {
            if (($p['name'] ?? '') === $name) {
                $this->plugins[$id] = $p + $pluginData;
                return ['id' => $id] + $this->plugins[$id];
            }
        }
        $id = $this->nextPluginId++;
        $this->plugins[$id] = ['id' => $id, 'name' => $name] + $pluginData;
        return ['id' => $id] + $this->plugins[$id];
    }

    public function createVersion(int $pluginId, array $versionData): ?array
    {
        $id = $this->nextVersionId++;
        $this->versions[$id] = ['id' => $id, 'plugin_id' => $pluginId] + $versionData;
        return ['id' => $id] + $this->versions[$id];
    }

    public function linkZip(int $pluginVersionId, int|string $zipId): void
    {
        $this->links[$pluginVersionId] = $zipId;
    }

    public function saveMeta(int $pluginId, array $meta): void
    {
        $this->meta[$pluginId] = $meta;
    }
}
```

## InMemoryZipRepository.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Infra;

use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;

class InMemoryZipRepository implements ZipRepository
{
    /** @var array<string,array{status: ZipValidationStatus, data: array}> */
    private array $store = [];

    public function __construct(array $seed = [])
    {
        foreach ($seed as $id => $status) {
            $this->setValidationStatus($id, is_string($status) ? ZipValidationStatus::from($status) : $status);
        }
    }

    public function getZip(int|string $zipId): array|null
    {
        $key = (string)$zipId;
        if (!isset($this->store[$key])) return null;
        return ['id' => $key, 'validation_status' => $this->store[$key]['status']->value] + ($this->store[$key]['data'] ?? []);
    }

    public function getValidationStatus(int|string $zipId): ZipValidationStatus
    {
        $key = (string)$zipId;
        return $this->store[$key]['status'] ?? ZipValidationStatus::UNKNOWN;
    }

    public function setValidationStatus(int|string $zipId, ZipValidationStatus $status): void
    {
        $key = (string)$zipId;
        $this->store[$key]['status'] = $status;
        $this->store[$key]['data'] = $this->store[$key]['data'] ?? [];
    }
}
```
