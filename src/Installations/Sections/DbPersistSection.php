<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\PackageEntry;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;

/**
 * DbPersistSection
 *
 * Responsibilities
 * - Upsert Plugin using InstallMeta (canonical identity).
 * - Create PluginVersion with a caller-supplied version tag and the same meta snapshot.
 * - Link the PluginZip to the created version.
 * - Save canonical meta and (optionally) the packages map (name => PackageEntry).
 * - Persist a concise "db_persist" block into installation.json.
 *
 * Notes
 * - No activation here; this only writes DB rows and logs the outcome.
 * - Emits terse installer events via InstallationLogStore::appendInstallerEmit().
 */
final readonly class DbPersistSection
{
    public function __construct(
        private InstallationLogStore $log,
        private PluginRepository     $plugins,
    )
    {
    }

    /**
     * Persist Plugin + Version, link Zip, and store meta/packages.
     *
     * @param InstallMeta $meta Canonical install meta (identity, paths, fingerprint, hashes)
     * @param string $versionTag Free-form version tag/fingerprint for PluginVersion
     * @param int|string $zipId PluginZip id to link to the created version
     * @param array<string,PackageEntry>|null $packages Optional packages map: name => PackageEntry
     * @param callable|null $emit Optional installer-level emitter fn(array $payload): void
     * @return array{status:'ok'|'fail', plugin_id?:int, plugin_version_id?:int}
     * @throws JsonException
     * @noinspection PhpUndefinedClassInspection
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public function run(
        InstallMeta $meta,
        string      $versionTag,
        int|string  $zipId,
        ?array      $packages = null,
        ?callable   $emit = null
    ): array
    {
        $emit && $emit([
            'title' => 'DB_PERSIST_START',
            'description' => 'Persisting plugin + version',
            'meta' => [
                'placeholder_name' => $meta->placeholder_name,
                'zip_id' => (string)$zipId,
                'version_tag' => $versionTag,
            ],
        ]);
        $this->log->appendInstallerEmit([
            'title' => 'DB_PERSIST_START',
            'description' => 'Persisting plugin + version',
            'meta' => [
                'placeholder_name' => $meta->placeholder_name,
                'zip_id' => (string)$zipId,
                'version_tag' => $versionTag,
            ],
        ]);

        try {
            // 1) Upsert Plugin (by placeholder id/name per your repo impl)
            $pluginId = $this->plugins->upsertPlugin($meta);
            if ($pluginId === null) {
                throw new RuntimeException('Upsert returned null plugin id');
            }

            // 2) Create Version with same meta snapshot
            $pluginVersionId = $this->plugins->createVersion($pluginId, $versionTag, $meta);
            if ($pluginVersionId === null) {
                throw new RuntimeException('CreateVersion returned null id');
            }

            // 3) Link Zip → Version
            $this->plugins->linkZip($pluginVersionId, $zipId);

            // 4) Save canonical meta
            $this->plugins->saveMeta($pluginId, $meta);

            // 5) Save packages map (if provided)
            if (is_array($packages) && $packages !== []) {
                $this->plugins->savePackages($pluginId, $packages);
            }

            // 6) Persist concise db_persist block
            $this->log->writeSection('db_persist', [
                'plugin_id' => $pluginId,
                'plugin_version_id' => $pluginVersionId,
                'zip_id' => (string)$zipId,
                'version_tag' => $versionTag,
                'meta' => $meta->toArray(),
                'packages_saved' => is_array($packages) && $packages !== [],
            ]);

            $okEmit = [
                'title' => 'DB_PERSIST_OK',
                'description' => 'Plugin + version persisted and zip linked',
                'meta' => [
                    'plugin_id' => $pluginId,
                    'plugin_version_id' => $pluginVersionId,
                ],
            ];
            $emit && $emit($okEmit);
            $this->log->appendInstallerEmit($okEmit);

            return ['status' => 'ok', 'plugin_id' => $pluginId, 'plugin_version_id' => $pluginVersionId];
        } catch (Throwable $e) {
            $failMeta = [
                'error' => $e->getMessage(),
                'placeholder_name' => $meta->placeholder_name,
                'zip_id' => (string)$zipId,
                'version_tag' => $versionTag,
            ];

            // Best-effort persist of failure context
            try {
                $this->log->writeSection('db_persist', ['error' => $e->getMessage(), 'meta' => $failMeta]);
            } catch (Throwable $_) {
            }

            $failEmit = [
                'title' => 'DB_PERSIST_FAIL',
                'description' => 'Failed to persist DB records',
                'meta' => $failMeta,
            ];
            $emit && $emit($failEmit);
            $this->log->appendInstallerEmit($failEmit);

            return ['status' => 'fail'];
        }
    }
}