<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Models\PluginSetting;
use Timeax\FortiPlugin\Permissions\Evaluation\PermissionService;
use Timeax\FortiPlugin\Support\HostConfigToPluginSettings;

readonly class IngestSection
{
    public function __construct(private PermissionService $permissionService, private InstallationLogStore $logStore)
    {
    }

    /**
     * @throws Throwable
     */
    public function ingestPermissions(mixed $permission_manifest, int $pluginId, string $runId, int $zipId, $emitInstaller): void
    {
        $hasManifest =
            is_array($permission_manifest)
            && (array_key_exists('required_permissions', $permission_manifest)
                || array_key_exists('optional_permissions', $permission_manifest));

        if ($hasManifest) {
            // Normalize buckets so ingestManifest always receives a stable shape.
            $normalizedManifest = $permission_manifest;

            if (!array_key_exists('required_permissions', $normalizedManifest) || !is_array($normalizedManifest['required_permissions'] ?? null)) {
                $normalizedManifest['required_permissions'] = [];
            }
            if (!array_key_exists('optional_permissions', $normalizedManifest) || !is_array($normalizedManifest['optional_permissions'] ?? null)) {
                $normalizedManifest['optional_permissions'] = [];
            }

            $emitInstaller([
                'event' => 'PERMISSION_MANIFEST_INGEST_START',
                'title' => 'PERMISSION_MANIFEST_INGEST_START',
                'description' => 'Ingesting permission manifest into concrete permission tables',
                'meta' => [
                    'plugin_id' => $pluginId,
                    'run_id' => $runId,
                    'zip_id' => (string)$zipId,
                    'required_count' => count($normalizedManifest['required_permissions']),
                    'optional_count' => count($normalizedManifest['optional_permissions']),
                ],
            ]);

            try {
                $ingest = $this->permissionService->ingestManifest($pluginId, $normalizedManifest);

                $emitInstaller([
                    'event' => 'PERMISSION_MANIFEST_INGEST_OK',
                    'title' => 'PERMISSION_MANIFEST_INGEST_OK',
                    'description' => 'Permission manifest ingested successfully',
                    'meta' => [
                        'plugin_id' => $pluginId,
                        'created' => $ingest->created,
                        'linked' => $ingest->linked,
                        'warnings_count' => count($ingest->warnings ?? []),
                        'warnings_preview' => array_slice($ingest->warnings ?? [], 0, 10),
                        'items_preview' => array_slice(
                            array_map(
                                static fn($i) => method_exists($i, 'toArray') ? $i->toArray() : $i,
                                $ingest->items ?? []
                            ),
                            0,
                            10
                        ),
                    ],
                ]);

                if (!empty($ingest->warnings)) {
                    $emitInstaller([
                        'event' => 'PERMISSION_MANIFEST_INGEST_WARNINGS',
                        'title' => 'PERMISSION_MANIFEST_INGEST_WARNINGS',
                        'description' => 'Ingest completed with warnings',
                        'meta' => [
                            'plugin_id' => $pluginId,
                            'warnings' => array_slice($ingest->warnings, 0, 25),
                        ],
                    ]);
                }

                try {
                    $this->logStore->writeSection('permission_manifest_ingest', [
                        'plugin_id' => $pluginId,
                        'zip_id' => (string)$zipId,
                        'run_id' => $runId,
                        'summary' => $ingest->toArray(),
                    ]);
                } catch (Throwable) {
                    // best effort only
                }
            } catch (Throwable $e) {
                $emitInstaller([
                    'event' => 'PERMISSION_MANIFEST_INGEST_FAIL',
                    'title' => 'PERMISSION_MANIFEST_INGEST_FAIL',
                    'description' => 'Permission manifest ingestion failed',
                    'meta' => [
                        'plugin_id' => $pluginId,
                        'exception' => $e->getMessage(),
                    ],
                ]);

                // Let your outer transaction handler roll back + terminate cleanly.
                throw new RuntimeException('Permission manifest ingestion failed: ' . $e->getMessage(), 0, $e);
            }
        } else {
            $emitInstaller([
                'event' => 'PERMISSION_MANIFEST_INGEST_SKIP',
                'title' => 'PERMISSION_MANIFEST_INGEST_SKIP',
                'description' => 'No permission manifest loaded; skipping ingestion',
                'meta' => ['plugin_id' => $pluginId],
            ]);
        }
    }

    /**
     * @throws JsonException
     */
    public function ingestSettings(int $pluginId, array $pluginConfig, string $runId, int $zipId, $emitInstaller): void
    {
        $emit = is_callable($emitInstaller) ? $emitInstaller : null;

        $hostConfig = $pluginConfig['hostConfig'] ?? $pluginConfig['host_config'] ?? null;

        if ($hostConfig === null) {
            $emit && $emit([
                'event' => 'SETTINGS_INGEST_SKIP',
                'title' => 'SETTINGS_INGEST_SKIP',
                'description' => 'No hostConfig found in plugin config; skipping settings ingestion',
                'meta' => ['plugin_id' => $pluginId, 'run_id' => $runId, 'zip_id' => $zipId],
            ]);
            return;
        }

        if (!is_array($hostConfig) && !is_object($hostConfig)) {
            $emit && $emit([
                'event' => 'SETTINGS_HOSTCONFIG_INVALID_TYPE',
                'title' => 'SETTINGS_HOSTCONFIG_INVALID_TYPE',
                'description' => 'hostConfig must be an object/map',
                'meta' => [
                    'plugin_id' => $pluginId,
                    'run_id' => $runId,
                    'zip_id' => $zipId,
                    'type' => gettype($hostConfig),
                ],
            ]);

            throw new InvalidArgumentException('hostConfig must be an object/map.');
        }

        $emit && $emit([
            'event' => 'SETTINGS_ROWS_BUILD_START',
            'title' => 'SETTINGS_ROWS_BUILD_START',
            'description' => 'Converting hostConfig.settings into plugin_settings rows',
            'meta' => ['plugin_id' => $pluginId, 'run_id' => $runId, 'zip_id' => $zipId],
        ]);

        try {
            $rows = HostConfigToPluginSettings::makeRows($pluginId, $hostConfig);
        } catch (Throwable $e) {
            $emit && $emit([
                'event' => 'SETTINGS_ROWS_BUILD_FAIL',
                'title' => 'SETTINGS_ROWS_BUILD_FAIL',
                'description' => 'hostConfig.settings is invalid; cannot build settings rows',
                'meta' => [
                    'plugin_id' => $pluginId,
                    'run_id' => $runId,
                    'zip_id' => $zipId,
                    'exception' => $e->getMessage(),
                ],
            ]);

            throw new RuntimeException('Settings ingestion failed: invalid hostConfig.', 0, $e);
        }

        if ($rows === []) {
            $emit && $emit([
                'event' => 'SETTINGS_ROWS_EMPTY',
                'title' => 'SETTINGS_ROWS_EMPTY',
                'description' => 'No settings rows produced; skipping',
                'meta' => ['plugin_id' => $pluginId, 'run_id' => $runId, 'zip_id' => $zipId],
            ]);
            return;
        }

        $keys = array_values(array_unique(array_map(static fn(array $r) => (string)$r['key'], $rows)));

        // Pull existing rows once so we can protect secrets from being wiped by empty defaults.
        $existing = PluginSetting::query()
            ->where('plugin_id', $pluginId)
            ->whereIn('key', $keys)
            ->get(['id', 'key', 'value', 'is_sensitive'])
            ->keyBy('key');

        $created = 0;
        $updated = 0;
        $protected_sensitive = 0;

        $emit && $emit([
            'event' => 'SETTINGS_DB_APPLY_START',
            'title' => 'SETTINGS_DB_APPLY_START',
            'description' => 'Applying settings rows to plugin_settings',
            'meta' => [
                'plugin_id' => $pluginId,
                'run_id' => $runId,
                'zip_id' => $zipId,
                'rows' => count($rows),
            ],
        ]);

        try {
            foreach ($rows as $row) {
                $key = (string)$row['key'];

                /**
                 * @var PluginSetting $existingRow
                 */
                $existingRow = $existing->get($key);
                $exists = $existingRow !== null;

                $incomingSensitive = (bool)($row['is_sensitive'] ?? false);
                $existingSensitive = $exists && $existingRow->is_sensitive;
                $effectiveSensitive = $incomingSensitive || $existingSensitive;

                $incomingValue = (string)($row['value'] ?? '');

                // Protect existing sensitive value if manifest provides "empty" placeholder.
                // (This avoids wiping secrets during re-install/upgrade.)
                $shouldProtectValue =
                    $exists
                    && $effectiveSensitive
                    && ($incomingValue === '' || $incomingValue === 'null');

                $updates = [
                    'label' => (string)$row['label'],
                    'type' => (string)$row['type'],
                    'group' => $row['group'] ?? null,
                    'is_required' => (bool)$row['is_required'],
                    'is_sensitive' => (bool)$row['is_sensitive'],
                ];

                if ($shouldProtectValue) {
                    $protected_sensitive++;
                } else {
                    // For new rows OR non-protected updates, write value.
                    $updates['value'] = $incomingValue;
                }

                $model = PluginSetting::query()->updateOrCreate(
                    ['plugin_id' => $pluginId, 'key' => $key],
                    $updates
                );

                if ($model->wasRecentlyCreated) {
                    $created++;
                } elseif ($model->wasChanged()) {
                    $updated++;
                }
            }

            $emit && $emit([
                'event' => 'SETTINGS_INGEST_OK',
                'title' => 'SETTINGS_INGEST_OK',
                'description' => 'Settings ingestion completed',
                'meta' => [
                    'plugin_id' => $pluginId,
                    'run_id' => $runId,
                    'zip_id' => $zipId,
                    'rows_total' => count($rows),
                    'created' => $created,
                    'updated' => $updated,
                    'protected_sensitive' => $protected_sensitive,
                ],
            ]);
        } catch (Throwable $e) {
            $emit && $emit([
                'event' => 'SETTINGS_INGEST_FAIL',
                'title' => 'SETTINGS_INGEST_FAIL',
                'description' => 'Settings ingestion failed while applying DB changes',
                'meta' => [
                    'plugin_id' => $pluginId,
                    'run_id' => $runId,
                    'zip_id' => $zipId,
                    "rows" => $rows,
                    'exception' => $e->getMessage(),
                ],
            ]);

            throw new RuntimeException('Settings ingestion failed: ' . $e->getMessage(), 0, $e);
        }
    }
}