# Index 

Included Source Files (47)
- [src/Enums/AuthorStatus.php](#1)
- [src/Enums/IssueStatus.php](#2)
- [src/Enums/KeyPurpose.php](#3)
- [src/Enums/PermissionType.php](#4)
- [src/Enums/PluginSettingValueType.php](#5)
- [src/Enums/PluginStatus.php](#6)
- [src/Enums/RoutePermissionStatus.php](#7)
- [src/Enums/ValidationStatus.php](#8)
- [src/Installations/Activation/ActivationResult.php](#9)
- [src/Installations/Activation/Activator.php](#10)
- [src/Installations/Activation/Writers/ProvidersRegistryWriter.php](#11)
- [src/Installations/Activation/Writers/RoutesRegistryWriter.php](#12)
- [src/Installations/Activation/Writers/UiRegistryWriter.php](#13)
- [src/Installations/Contracts/Filesystem.php](#14)
- [src/Installations/Contracts/HostKeyService.php](#15)
- [src/Installations/Contracts/RegistryWriter.php](#16)
- [src/Installations/Contracts/ZipRepository.php](#17)
- [src/Installations/DTO/TokenContext.php](#18)
- [src/Installations/Enums/Install.php](#19)
- [src/Installations/Enums/VendorMode.php](#20)
- [src/Installations/Enums/ZipValidationStatus.php](#21)
- [src/Installations/InstallerPolicy.php](#22)
- [src/Installations/Sections/Decision.php](#23)
- [src/Installations/Sections/ZipValidationGate.php](#24)
- [src/Installations/Support/AtomicFilesystem.php](#25)
- [src/Installations/Support/InstallerTokenManager.php](#26)
- [src/Models/AuditLog.php](#27)
- [src/Models/Author.php](#28)
- [src/Models/AuthorToken.php](#29)
- [src/Models/HostKey.php](#30)
- [src/Models/PermissionTag.php](#31)
- [src/Models/PermissionTagItem.php](#32)
- [src/Models/Plugin.php](#33)
- [src/Models/PluginAuditLog.php](#34)
- [src/Models/PluginIssue.php](#35)
- [src/Models/PluginIssueMessage.php](#36)
- [src/Models/PluginPermission.php](#37)
- [src/Models/PluginPermissionTag.php](#38)
- [src/Models/PluginPlaceholder.php](#39)
- [src/Models/PluginRoutePermission.php](#40)
- [src/Models/PluginSetting.php](#41)
- [src/Models/PluginSignature.php](#42)
- [src/Models/PluginToken.php](#43)
- [src/Models/PluginVersion.php](#44)
- [src/Models/PluginZip.php](#45)
- [src/Services/HostKeyService.php](#46)
- [src/Support/Encryption.php](#47)

---
---
#### 1


` File: src/Enums/AuthorStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum AuthorStatus: string
{
	case pending = "pending";
	case active = "active";
	case inactive = "inactive";
	case blocked = "blocked";
}
```

---
#### 2


` File: src/Enums/IssueStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum IssueStatus: string
{
	case open = "open";
	case triage = "triage";
	case in_progress = "in_progress";
	case resolved = "resolved";
	case rejected = "rejected";
	case closed = "closed";
}
```

---
#### 3


` File: src/Enums/KeyPurpose.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum KeyPurpose: string
{
	case packager_sign = "packager_sign";
	case installer_verify = "installer_verify";
}
```

---
#### 4


` File: src/Enums/PermissionType.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum PermissionType: string
{
	case db = "db";
	case file = "file";
	case notification = "notification";
	case module = "module";
	case network = "network";
	case codec = "codec";
}
```

---
#### 5


` File: src/Enums/PluginSettingValueType.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum PluginSettingValueType: string
{
	case string = "string";
	case number = "number";
	case boolean = "boolean";
	case json = "json";
	case file = "file";
	case blob = "blob";
}
```

---
#### 6


` File: src/Enums/PluginStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum PluginStatus: string
{
	case active = "active";
	case inactive = "inactive";
	case archived = "archived";
}
```

---
#### 7


` File: src/Enums/RoutePermissionStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum RoutePermissionStatus: string
{
	case pending = "pending";
	case approved = "approved";
	case denied = "denied";
	case revoked = "revoked";
}
```

---
#### 8


` File: src/Enums/ValidationStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum ValidationStatus: string
{
    case valid = "valid";
    case unchecked = "unchecked";
    case unverified = "unverified";
    case failed = "failed";
    case pending = "pending";
}
```

---
#### 9


` File: src/Installations/Activation/ActivationResult.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation;

final class ActivationResult
{
    /** @var 'ok'|'fail' */
    public string $status;
    /** @var array<string,mixed> */
    public array $data;

    private function __construct(string $status, array $data = [])
    {
        $this->status = $status;
        $this->data = $data;
    }

    /** @param array<string,mixed> $data */
    public static function ok(array $data = []): self
    {
        return new self('ok', $data);
    }

    /** @param array<string,mixed> $data */
    public static function fail(array $data = []): self
    {
        return new self('fail', $data);
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    public function isFail(): bool
    {
        return $this->status === 'fail';
    }

    /** @return array<string,mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
```

---
#### 10


` File: src/Installations/Activation/Activator.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation;

use Artisan;
use Illuminate\Support\Facades\DB;
use JsonException;
use Random\RandomException;
use Throwable;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Installations\Activation\Writers\ProvidersRegistryWriter;
use Timeax\FortiPlugin\Installations\Activation\Writers\RoutesRegistryWriter;
use Timeax\FortiPlugin\Installations\Activation\Writers\UiRegistryWriter;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Sections\ZipValidationGate;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginVersion;

final readonly class Activator
{
    public function __construct(
        private InstallerPolicy         $policy,
        private AtomicFilesystem        $afs,
        private ZipValidationGate       $zipGate,
        private RoutesRegistryWriter    $routesWriter,
        private ProvidersRegistryWriter $providersWriter,
        private UiRegistryWriter        $uiWriter,
    )
    {
    }

    /**
     * Activate an already-installed plugin version (stand-alone, not wired to Installer).
     *
     * @param Plugin $plugin
     * @param int|string $versionId
     * @param string $installedPluginRoot Absolute path to the plugin's installed root
     * @param string $actor
     * @param string $runId Correlates with the original installation run
     * @param callable|null $emit Optional domain emits: fn(array $payload): void
     * @return ActivationResult
     * @throws Throwable
     * @throws JsonException
     * @throws RandomException
     */
    public function run(
        Plugin     $plugin,
        int|string $versionId,
        string     $installedPluginRoot,
        string     $actor,
        string     $runId,
        ?callable  $emit = null
    ): ActivationResult
    {
        $fs = $this->afs->fs();

        // ── Preflight & lock (naive mutex via file)
        $lockPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . '.internal' . DIRECTORY_SEPARATOR . 'activate.lock';
        $this->afs->ensureParentDirectory($lockPath);
        $lock = @fopen($lockPath, 'cb+');
        if (!$lock || !@flock($lock, LOCK_EX)) {
            return ActivationResult::fail(['reason' => 'activation_lock_failed']);
        }

        try {
            // Resolve version
            /** @var PluginVersion|null $version */
            $version = PluginVersion::query()->where('id', $versionId)->where('plugin_id', $plugin->id)->first();
            if (!$version) {
                return ActivationResult::fail(['reason' => 'version_not_found', 'version_id' => $versionId]);
            }

            // Already active? no-op
            if ((int)($plugin->active_version_id ?? 0) === $version->id) {
                $emit && $emit(['title' => 'ACTIVATION_NOOP', 'description' => 'Version already active']);
                return ActivationResult::ok([
                    'plugin_id' => $plugin->id,
                    'version_id' => $version->id,
                    'changed' => false,
                    'reason' => 'already_active',
                ]);
            }

            // 1) Read install log and verify prior validators for this run
            $logPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR
                . trim($this->policy->getLogsDirName(), "\\/") . DIRECTORY_SEPARATOR
                . $this->policy->getInstallationLogFilename();

            if (!$fs->exists($logPath)) {
                return ActivationResult::fail(['reason' => 'installation_log_missing']);
            }
            $doc = $fs->readJson($logPath);

            // Verify that verification & provider checks existed
            if (!isset($doc['verification'])) {
                return ActivationResult::fail(['reason' => 'verification_missing']);
            }
            if (!empty($doc['verification']['summary']['should_fail'] ?? false)
                && $this->policy->shouldBreakOnVerificationErrors()) {
                return ActivationResult::fail(['reason' => 'verification_failed']);
            }

            // Verify file_scan decision acceptable for activation
            $decisions = (array)($doc['decisions'] ?? []);
            $okDecision = $this->extractOkDecisionForRun($decisions, $runId);
            if ($okDecision === null) {
                return ActivationResult::fail(['reason' => 'scan_decision_missing_or_not_accepted', 'run_id' => $runId]);
            }

            // UI config validation (optional but recommended)
            $ui = $doc['ui_validation'] ?? $doc['ui_config'] ?? null;
            if (is_array($ui)) {
                $accepted = (int)($ui['accepted'] ?? 0);
                if ($accepted <= 0) {
                    return ActivationResult::fail(['reason' => 'ui_not_accepted']);
                }
            }

            // 3) Stage registry writes
            $routes = $this->routesWriter->stage($plugin, $version->id, $installedPluginRoot);
            $providers = $this->providersWriter->stage($plugin, $version->id, $installedPluginRoot);
            $uiReg = $this->uiWriter->stage($plugin, $version->id, $installedPluginRoot);

            // 4) Transaction: flip active version + publish registries
            DB::beginTransaction();
            try {
                // flip active
                $plugin->active_version_id = $version->id;
                $plugin->status = PluginStatus::active;
                $plugin->activated_at = now();
                $plugin->activated_by = $actor;
                $plugin->save();

                // commit staged registries
                ($routes['commit'])();
                ($providers['commit'])();
                ($uiReg['commit'])();

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                // best-effort rollback staged files
                try {
                    ($routes['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    ($providers['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    ($uiReg['rollback'])();
                } catch (Throwable $_) {
                }

                return ActivationResult::fail([
                    'reason' => 'activation_tx_failed',
                    'exception' => $e->getMessage(),
                ]);
            }

            // 5) Optionally clear caches per policy (minimal nudges)
            if (config('fortiplugin.activation.clear_route_cache', false)) {
                try {
                    Artisan::call('route:clear');
                } catch (Throwable $_) {
                }
            }
            if (config('fortiplugin.activation.clear_config_cache', false)) {
                try {
                    Artisan::call('config:clear');
                } catch (Throwable $_) {
                }
            }

            $emit && $emit([
                'title' => 'ACTIVATION_OK',
                'description' => 'Plugin version activated',
                'meta' => [
                    'plugin_id' => $plugin->id,
                    'version_id' => $version->id,
                    'routes' => $routes['meta'] ?? [],
                    'providers' => $providers['meta'] ?? [],
                    'ui' => $uiReg['meta'] ?? [],
                ],
            ]);

            return ActivationResult::ok([
                'plugin_id' => $plugin->id,
                'version_id' => $version->id,
                'changed' => true,
                'routes' => $routes['meta'] ?? [],
                'providers' => $providers['meta'] ?? [],
                'ui' => $uiReg['meta'] ?? [],
            ]);
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /**
     * Acceptable decision for activation:
     *  - status 'installed' (clean scan), or
     *  - status 'ask' resolved by host override for the SAME run_id.
     * @param array<int,array<string,mixed>> $decisions
     */
    private function extractOkDecisionForRun(array $decisions, string $runId): ?array
    {
        // Find the latest decision matching runId
        $filtered = array_values(array_filter($decisions, static function ($d) use ($runId) {
            return is_array($d) && ($d['run_id'] ?? null) === $runId;
        }));
        if ($filtered === []) return null;

        $last = end($filtered);
        $status = (string)($last['status'] ?? '');
        // 'installed' is always ok; 'ask' only ok if reason shows host decision override
        if ($status === 'installed') return $last;
        if ($status === 'ask' && ($last['reason'] ?? '') === 'host_decision_on_scan_errors') {
            return $last;
        }
        return null;
    }
}
```

---
#### 11


` File: src/Installations/Activation/Writers/ProvidersRegistryWriter.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation\Writers;

use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class ProvidersRegistryWriter implements RegistryWriter
{
    public function __construct(
        private AtomicFilesystem $afs,
        private InstallerPolicy  $policy,
    ) {}

    /**
     * Strategy:
     *  - Read fortiplugin.json in installed root for "providers" array.
     *  - Merge into a host providers registry JSON (configurable path).
     *  - Host bootstrapping can include this registry to auto-register providers.
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array
    {
        $fs = $this->afs->fs();

        $cfgPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . 'fortiplugin.json';
        if (!$fs->exists($cfgPath)) {
            // No config — nothing to write
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'fortiplugin.json_missing'],
            ];
        }

        $cfg = $fs->readJson($cfgPath);
        $providers = array_values(array_filter((array)($cfg['providers'] ?? []), 'is_string'));
        if ($providers === []) {
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'no_providers_declared'],
            ];
        }

        $registryPath = (string)(config('fortiplugin.providers.registry_path') ?? base_path('bootstrap/fortiplugin.providers.json'));
        $json = $fs->exists($registryPath) ? $fs->readJson($registryPath) : [];
        if (!is_array($json)) $json = [];

        $slug = (string)($plugin->placeholder->slug ?? $plugin->slug ?? $plugin->id);
        $json[$slug] = $providers;

        $newJson = $json;

        return [
            'commit' => function () use ($registryPath, $newJson): void {
                $this->afs->writeJsonAtomic($registryPath, $newJson, true);
            },
            'rollback' => static function (): void {},
            'meta' => [
                'changed'       => true,
                'registry_path' => $registryPath,
                'providers'     => $providers,
            ],
        ];
    }
}
```

---
#### 12


` File: src/Installations/Activation/Writers/RoutesRegistryWriter.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation\Writers;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class RoutesRegistryWriter implements RegistryWriter
{
    public function __construct(
        private AtomicFilesystem $afs,
        private InstallerPolicy  $policy,
    ) {}

    /**
     * Strategy:
     *  - Read plugin’s installed log to find the routes' aggregator path written by RouteWriteSection.
     *  - Update host registry JSON (configurable path) with [plugin_slug => aggregator].
     *  - Regenerate a single host PHP aggregator that requires all registered aggregators.
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array
    {
        $fs = $this->afs->fs();

        // 1) Locate installation log in installed root
        $logsDir   = trim($this->policy->getLogsDirName(), "\\/");
        $logFile   = $this->policy->getInstallationLogFilename();
        $logPath   = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . $logsDir . DIRECTORY_SEPARATOR . $logFile;

        if (!$fs->exists($logPath)) {
            throw new RuntimeException("activation: installation log not found at $logPath");
        }
        $doc = $fs->readJson($logPath);
        $routesWrite = $doc['routes_write'] ?? null;
        if (!is_array($routesWrite) || empty($routesWrite['aggregator'])) {
            // No routes for this plugin — nothing to publish
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'no_routes_aggregator'],
            ];
        }

        $aggregator = (string)$routesWrite['aggregator'];
        if ($aggregator === '' || !$fs->exists($aggregator)) {
            throw new RuntimeException("activation: aggregator file not found: $aggregator");
        }

        // 2) Host registry paths (configurable)
        $registryPath   = (string) (config('fortiplugin.routes.registry_path')    ?? base_path('routes/fortiplugin.registry.json'));
        $aggregatorPath = (string) (config('fortiplugin.routes.aggregator_path')  ?? base_path('routes/fortiplugin.plugins.php'));

        // 3) Read and update registry JSON (plugin_slug => aggregator)
        $slug  = (string)($plugin->placeholder->slug ?? $plugin->slug ?? $plugin->id);
        $json  = $fs->exists($registryPath) ? $fs->readJson($registryPath) : [];
        if (!is_array($json)) $json = [];
        $json[$slug] = $aggregator;

        // Staged contents
        $newRegistryJson = $json;
        $newAggregatorPhp = $this->renderAggregatorPhp($newRegistryJson);

        // 4) Return commit/rollback closures (atomic writes)
        return [
            'commit' => function () use ($registryPath, $aggregatorPath, $newRegistryJson, $newAggregatorPhp): void {
                $this->afs->writeJsonAtomic($registryPath, $newRegistryJson, true);
                $this->afs->fs()->writeAtomic($aggregatorPath, $newAggregatorPhp);
            },
            'rollback' => static function (): void { /* best effort noop */ },
            'meta' => [
                'changed'         => true,
                'registry_path'   => $registryPath,
                'aggregator_path' => $aggregatorPath,
            ],
        ];
    }

    /** @param array<string,string> $registry */
    private function renderAggregatorPhp(array $registry): string
    {
        $lines = [];
        $lines[] = "<?php";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "/** Host aggregator for FortiPlugin routes (auto-generated) */";
        $lines[] = "";
        foreach ($registry as $slug => $file) {
            $fileEsc = var_export($file, true);
            $slugEsc = var_export($slug, true);
            $lines[] = "// plugin: $slugEsc";
            $lines[] = "if (file_exists($fileEsc)) { require $fileEsc; }";
        }
        $lines[] = "";
        return implode("\n", $lines);
    }
}
```

---
#### 13


` File: src/Installations/Activation/Writers/UiRegistryWriter.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation\Writers;

use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class UiRegistryWriter implements RegistryWriter
{
    public function __construct(
        private AtomicFilesystem $afs,
        private InstallerPolicy  $policy,
    ) {}

    /**
     * Strategy:
     *  - Read installation log for a persisted UI validation block (written by UiConfigValidationSection).
     *  - If accepted>0, register this plugin’s UI into a host UI registry JSON.
     *  - This only records the “presence”; the host app reads and mounts UI at runtime.
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array
    {
        $fs = $this->afs->fs();

        $logsDir = trim($this->policy->getLogsDirName(), "\\/");
        $logFile = $this->policy->getInstallationLogFilename();
        $logPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . $logsDir . DIRECTORY_SEPARATOR . $logFile;

        if (!$fs->exists($logPath)) {
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'installation_log_missing'],
            ];
        }

        $doc = $fs->readJson($logPath);
        $ui = $doc['ui_validation'] ?? $doc['ui_config'] ?? null; // tolerate either key
        $accepted = is_array($ui) ? (int)($ui['accepted'] ?? 0) : 0;
        if ($accepted <= 0) {
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'ui_not_accepted'],
            ];
        }

        $registryPath = (string)(config('fortiplugin.ui.registry_path') ?? base_path('bootstrap/fortiplugin.ui.json'));
        $json = $fs->exists($registryPath) ? $fs->readJson($registryPath) : [];
        if (!is_array($json)) $json = [];

        $slug = (string)($plugin->placeholder->slug ?? $plugin->slug ?? $plugin->id);
        $json[$slug] = ['accepted' => $accepted, 'version_id' => $versionId];

        $newJson = $json;

        return [
            'commit' => function () use ($registryPath, $newJson): void {
                $this->afs->writeJsonAtomic($registryPath, $newJson, true);
            },
            'rollback' => static function (): void {},
            'meta' => [
                'changed'       => true,
                'registry_path' => $registryPath,
                'accepted'      => $accepted,
            ],
        ];
    }
}
```

---
#### 14


` File: src/Installations/Contracts/Filesystem.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use RuntimeException;

/**
 * Minimal filesystem facade with atomic guarantees and basic introspection.
 *
 * Implementations MUST:
 *  - perform safe, race-aware writes (writeAtomic),
 *  - respect directory creation semantics (ensureDirectory),
 *  - avoid following symlinks during tree copies where possible (copyTree),
 *  - throw \RuntimeException (or a subtype) on failures.
 */
interface Filesystem
{
    /**
     * Whether a path exists (file or directory).
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Whether the path is a regular file.
     *
     * @param string $path
     * @return bool
     */
    public function isFile(string $path): bool;

    /**
     * Whether the path is a directory.
     *
     * @param string $path
     * @return bool
     */
    public function isDirectory(string $path): bool;

    /**
     * Ensure a directory exists (create recursively if needed).
     *
     * @param string $path Absolute or project-root-relative path.
     * @param int    $mode Permissions (POSIX environments).
     * @return void
     *
     * @throws RuntimeException On failure to create or if a non-directory exists at $path.
     */
    public function ensureDirectory(string $path, int $mode = 0755): void;

    /**
     * Read a file as raw bytes (no decoding).
     *
     * @param string $path
     * @return string
     *
     * @throws RuntimeException If not readable or not a file.
     */
    public function readFile(string $path): string;

    /**
     * Read and decode a JSON file into an associative array.
     *
     * @param string $path
     * @return array
     *
     * @throws RuntimeException If missing, unreadable, or invalid JSON.
     */
    public function readJson(string $path): array;

    /**
     * Atomically write file contents.
     *
     * MUST write to a temporary file in the same directory and rename over the destination.
     *
     * @param string $path
     * @param string $contents
     * @return void
     *
     * @throws RuntimeException On write or rename failure.
     */
    public function writeAtomic(string $path, string $contents): void;

    /**
     * Recursively copy a directory tree.
     *
     * Implementations should avoid copying dangerous entries (e.g., symlinks) and honor an optional filter.
     *
     * @param string        $from   Source directory
     * @param string        $to     Destination directory (will be created if missing)
     * @param callable|null $filter Optional filter with signature fn(string $relativePath): bool
     * @return void
     *
     * @throws RuntimeException On IO errors or invalid arguments.
     */
    public function copyTree(string $from, string $to, ?callable $filter = null): void;

    /**
     * List files under a path (non-recursive or recursive per implementation).
     *
     * @param string        $path
     * @param callable|null $filter Optional filter with signature fn(string $absolutePath): bool
     * @return array<int,string> List of paths
     */
    public function listFiles(string $path, ?callable $filter = null): array;

    /**
     * Rename/move a file or directory.
     *
     * @param string $from
     * @param string $to
     * @return void
     *
     * @throws RuntimeException On failure.
     */
    public function rename(string $from, string $to): void;

    /**
     * Delete a file or directory (recursive for directories).
     *
     * @param string $path
     * @return void
     *
     * @throws RuntimeException On failure.
     */
    public function delete(string $path): void;

    /**
     * File size in bytes, if applicable.
     *
     * @param string $path
     * @return int|null Null if not a file or not determinable.
     */
    public function fileSize(string $path): ?int;
}
```

---
#### 15


` File: src/Installations/Contracts/HostKeyService.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use RuntimeException;
use Timeax\FortiPlugin\Installations\DTO\TokenContext;

/**
 * Cryptographic envelope for issuing and validating installer tokens.
 *
 * Requirements:
 *  - Encrypt/sign payloads (support key rotation via 'kid')
 *  - Validate integrity & expiry
 *  - NEVER persist raw/encrypted tokens to DB/logs; only safe metadata elsewhere
 */
interface HostKeyService
{
    /**
     * Issue an encrypted/signed token for the given claims.
     *
     * @param TokenContext $claims Mandatory fields (purpose, zip_id, fingerprint, config hash, actor, exp, nonce, run_id)
     * @return non-empty-string     Opaque token
     *
     * @throws RuntimeException On crypto/key issues.
     */
    public function issue(TokenContext $claims): string;

    /**
     * Validate/decrypt a token and return its claims if valid.
     *
     * @param non-empty-string $token Opaque token previously issued by issue()
     * @return TokenContext           Decoded claims
     *
     * @throws RuntimeException If invalid, expired, or unrecognized.
     */
    public function validate(string $token): TokenContext;
}
```

---
#### 16


` File: src/Installations/Contracts/RegistryWriter.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use Timeax\FortiPlugin\Models\Plugin;

interface RegistryWriter
{
    /**
     * Prepare any filesystem/registry changes for activation.
     * Must be idempotent and safe to call more than once.
     *
     * Return two closures for a 2-phase commit:
     *  - commit(): void   → publish staged changes
     *  - rollback(): void → revert staged work (best effort)
     *
     * @return array{
     *   commit: callable():void,
     *   rollback: callable():void,
     *   meta?: array<string,mixed>
     * }
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array;
}
```

---
#### 17


` File: src/Installations/Contracts/ZipRepository.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus as ValidationStatus;


/**
 * Accessor for plugin zip metadata & lifecycle.
 *
 * Central truth for:
 *  - zip file path (staging/extract),
 *  - plugin identity (Placeholder.name, placeholder id),
 *  - validation status (verified/pending/failed),
 *  - immutable fingerprints & config hashes for token binding,
 *  - operational pointers (installation.json path, timestamps).
 */
interface ZipRepository
{
    /**
     * Retrieve an arbitrary zip record (implementation-defined shape) or null.
     *
     * @param int|string $zipId
     * @return array|null
     */
    public function getZip(int|string $zipId): ?array;

    /**
     * Current validation status for the zip (verified|pending|failed|unknown).
     *
     * @param int|string $zipId
     * @return ValidationStatus
     */
    public function getValidationStatus(int|string $zipId): ValidationStatus;

    /**
     * Set validation status for the zip.
     *
     * @param int|string $zipId
     * @param ValidationStatus $status
     * @return void
     */
    public function setValidationStatus(int|string $zipId, ValidationStatus $status): void;

    /**
     * Absolute filesystem path to the zip (for extraction).
     *
     * @param int|string $zipId
     * @return string
     *
     * @throws RuntimeException If not available.
     */
    public function getZipPath(int|string $zipId): string;

    /**
     * Canonical plugin unique name (Studly): Placeholder.name.
     *
     * @param int|string $zipId
     * @return string
     */
    public function getPlaceholderName(int|string $zipId): string;

    /**
     * Plugin placeholder id for DB linking.
     *
     * @param int|string $zipId
     * @return int|string
     */
    public function getPluginPlaceholderId(int|string $zipId): int|string;

    /**
     * Optional human/kebab slug if maintained separately.
     *
     * @param int|string $zipId
     * @return string|null
     */
    public function getSlug(int|string $zipId): ?string;

    /**
     * Strong content fingerprint (e.g., sha256 of the zip).
     *
     * @param int|string $zipId
     * @return string
     */
    public function getFingerprint(int|string $zipId): string;

    /**
     * Hash of the validator configuration used for scans (binds tokens to config).
     *
     * @param int|string $zipId
     * @return string|null Null if not computed.
     */
    public function getValidatorConfigHash(int|string $zipId): ?string;

    /**
     * Persist the absolute path to the canonical installation.json for this zip.
     *
     * @param int|string $zipId
     * @param string $installationJsonPath
     * @return void
     */
    public function recordLogPath(int|string $zipId, string $installationJsonPath): void;

    /**
     * Audit hook: mark the time a validation run completed.
     *
     * @param int|string $zipId
     * @return void
     */
    public function touchValidatedAt(int|string $zipId): void;
}
```

---
#### 18


` File: src/Installations/DTO/TokenContext.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TTokenPurpose 'background_scan'|'install_override'
 * @phpstan-type TTokenClaims array{
 *   purpose: TTokenPurpose,
 *   zip_id: int|string,
 *   fingerprint: string,
 *   validator_config_hash: string,
 *   actor: string,
 *   exp: int,
 *   nonce: string,
 *   run_id: string
 * }
 */
final readonly class TokenContext implements ArraySerializable
{
    public function __construct(
        public string     $purpose,               /** @var TTokenPurpose */
        public int|string $zip_id,
        public string     $fingerprint,
        public string     $validator_config_hash,
        public string     $actor,
        public int        $exp,
        public string     $nonce,
        public string     $run_id,
    ) {}

    /** @param TTokenClaims $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['purpose'],
            $data['zip_id'],
            $data['fingerprint'],
            $data['validator_config_hash'],
            $data['actor'],
            (int)$data['exp'],
            $data['nonce'],
            $data['run_id'],
        );
    }

    /** @return TTokenClaims */
    public function toArray(): array
    {
        return [
            'purpose' => $this->purpose,
            'zip_id' => $this->zip_id,
            'fingerprint' => $this->fingerprint,
            'validator_config_hash' => $this->validator_config_hash,
            'actor' => $this->actor,
            'exp' => $this->exp,
            'nonce' => $this->nonce,
            'run_id' => $this->run_id,
        ];
    }
}
```

---
#### 19


` File: src/Installations/Enums/Install.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum Install: string
{
    case BREAK = 'break';
    case INSTALL = 'install';
    case ASK = 'ask';
}
```

---
#### 20


` File: src/Installations/Enums/VendorMode.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum VendorMode: string
{
    case STRIP_BUNDLED_VENDOR = 'strip_bundled_vendor';
    case ALLOW_BUNDLED_VENDOR = 'allow_bundled_vendor';
}
```

---
#### 21


` File: src/Installations/Enums/ZipValidationStatus.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Enums;

/**
 * High-level validation status for an uploaded plugin zip (PluginZip.validation_status).
 *
 * - VERIFIED → Headline checks passed (and any host-required scans completed).
 * - PENDING  → Background validation/scans in progress.
 * - FAILED   → One or more blocking issues detected.
 * - UNKNOWN  → Not checked or source didn’t provide a recognized status.
 *
 * NOTE: When mapping from Eloquent models, keep the translation consistent
 * with your model enum (e.g., valid/pending/failed/unverified → VERIFIED/PENDING/FAILED/UNKNOWN).
 */
enum ZipValidationStatus: string
{
    case VERIFIED = 'verified';
    case PENDING  = 'pending';
    case FAILED   = 'failed';
    case UNKNOWN  = 'unknown';
}
```

---
#### 22


` File: src/Installations/InstallerPolicy.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations;

use InvalidArgumentException;
use Timeax\FortiPlugin\Installations\Enums\VendorMode;

/**
 * Centralized, chainable policy that drives installer behavior.
 *
 * Defaults match your requirements:
 *  - File scan OFF by default (host may enable)
 *  - Vendor mode = STRIP_BUNDLED_VENDOR
 *  - Verification errors → BREAK
 *  - File-scan errors (when enabled) → ASK
 *
 * You can hydrate from config via ::fromArray() and inspect/serialize via toArray().
 */
final class InstallerPolicy
{
    /** Whether security file scanning (content/token/AST) is enabled. */
    private bool $fileScanEnabled = false;

    /** How to treat a bundled vendor directory in the plugin. */
    private VendorMode $vendorMode = VendorMode::STRIP_BUNDLED_VENDOR;

    /** Token TTLs (seconds) for flows coordinated by InstallerTokenManager. */
    private int $backgroundScanTtl = 600; // default 10 minutes
    private int $installOverrideTtl = 600;

    /** Host PSR-4 root (used for per-plugin mapping checks). */
    private string $psr4Root = 'Plugins';

    /** Absolute path to the routes JSON schema (optional, if host wants strict schema validation). */
    private ?string $routeSchemaPath = null;

    /** Names of middleware allowed in route files (empty = host checks elsewhere). */
    private array $middlewareAllowlist = [];

    /** Packages that must never be introduced/overridden by a plugin (e.g., php, laravel/framework). */
    private array $corePackageBlocklist = ['php', 'laravel/framework'];

    /** Decision behaviors */
    private bool $askOnFileScanErrors = true;          // when file scan is enabled and emits errors → ASK
    private bool $breakOnVerificationErrors = true;    // headline verification (composer/config/host/manifest/routes) → BREAK on any error
    private bool $presentForeignPackagesForScan = true; // show foreign packages and offer scan pre-activation

    /** Log locations inside the plugin dir. */
    private string $logsDirName = '.internal/logs';
    private string $installationLogFilename = 'installation.json';

    // ───────────────────────────── Mutators (chainable) ─────────────────────────────
    private bool $breakOnFileScanErrors;

    /** Enable/disable security file scanning (token/AST/etc.). */
    public function enableFileScan(bool $enable = true): self
    {
        $this->fileScanEnabled = $enable;
        return $this;
    }

    public function isFileScanEnabled(): bool
    {
        return $this->fileScanEnabled;
    }

    public function setVendorMode(VendorMode $mode): self
    {
        $this->vendorMode = $mode;
        return $this;
    }

    public function getVendorMode(): VendorMode
    {
        return $this->vendorMode;
    }

    /** Background-scan token TTL (seconds). Clamped to >= 60s. */
    public function setBackgroundScanTtl(int $seconds): self
    {
        $this->backgroundScanTtl = max(60, $seconds);
        return $this;
    }

    public function getBackgroundScanTtl(): int
    {
        return $this->backgroundScanTtl;
    }

    /** Install-override token TTL (seconds). Clamped to >= 60s. */
    public function setInstallOverrideTtl(int $seconds): self
    {
        $this->installOverrideTtl = max(60, $seconds);
        return $this;
    }

    public function getInstallOverrideTtl(): int
    {
        return $this->installOverrideTtl;
    }

    /** Host PSR-4 root (e.g., 'Plugins'). */
    public function setPsr4Root(string $root): self
    {
        $root = trim($root);
        if ($root === '') {
            throw new InvalidArgumentException('psr4Root cannot be empty');
        }
        $this->psr4Root = $root;
        return $this;
    }

    public function getPsr4Root(): string
    {
        return $this->psr4Root;
    }

    /** Absolute path to the route schema json (optional). */
    public function setRouteSchemaPath(?string $path): self
    {
        $this->routeSchemaPath = $path ? rtrim($path) : null;
        return $this;
    }

    public function getRouteSchemaPath(): ?string
    {
        return $this->routeSchemaPath;
    }

    /** Replace the middleware allow-list for route validation. */
    public function setMiddlewareAllowlist(array $names): self
    {
        $this->middlewareAllowlist = array_values(array_unique(array_map('strval', $names)));
        return $this;
    }

    /** @return list<string> */
    public function getMiddlewareAllowlist(): array
    {
        return $this->middlewareAllowlist;
    }

    /** Replace the core package blocklist (packages a plugin must not introduce/override). */
    public function setCorePackageBlocklist(array $packages): self
    {
        $this->corePackageBlocklist = array_values(array_unique(array_map('strval', $packages)));
        return $this;
    }

    /** @return list<string> */
    public function getCorePackageBlocklist(): array
    {
        return $this->corePackageBlocklist;
    }

    /** If true and file scan is enabled, installer returns ASK on scan errors (with token). */
    public function setAskOnFileScanErrors(bool $ask = true): self
    {
        $this->askOnFileScanErrors = $ask;
        return $this;
    }

    public function shouldAskOnFileScanErrors(): bool
    {
        return $this->askOnFileScanErrors;
    }

    /** If true, any verification error (composer/config/host/manifest/routes) forces BREAK. */
    public function setBreakOnVerificationErrors(bool $break = true): self
    {
        $this->breakOnVerificationErrors = $break;
        return $this;
    }

    public function shouldBreakOnVerificationErrors(): bool
    {
        return $this->breakOnVerificationErrors;
    }

    /** Whether to present foreign packages for optional scanning before activation. */
    public function setPresentForeignPackagesForScan(bool $present = true): self
    {
        $this->presentForeignPackagesForScan = $present;
        return $this;
    }

    public function shouldBreakOnFileScanErrors(): bool
    {
        return $this->breakOnFileScanErrors;
    }

    public function setBreakOnFileScanErrors(bool $v): void
    {
        $this->breakOnFileScanErrors = $v;
    }

    public function shouldPresentForeignPackagesForScan(): bool
    {
        return $this->presentForeignPackagesForScan;
    }

    /** Customize logs directory name inside the plugin root (default ".internal/logs"). */
    public function setLogsDirName(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('logsDirName cannot be empty');
        }
        $this->logsDirName = $name;
        return $this;
    }

    public function getLogsDirName(): string
    {
        return $this->logsDirName;
    }

    /** Customize installation log filename (default "installation.json"). */
    public function setInstallationLogFilename(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('installationLogFilename cannot be empty');
        }
        $this->installationLogFilename = $name;
        return $this;
    }

    public function getInstallationLogFilename(): string
    {
        return $this->installationLogFilename;
    }

    // ───────────────────────────── Serialization ─────────────────────────────

    /**
     * Build a policy from a config array. Unknown keys are ignored.
     *
     * Supported keys:
     *  - file_scan (bool)
     *  - vendor_mode ('STRIP_BUNDLED_VENDOR'|'ALLOW_BUNDLED_VENDOR')
     *  - token_ttl: { background_scan:int, install_override:int }
     *  - psr4_root (string)
     *  - route_schema (string|null)
     *  - middleware_allowlist (string[])
     *  - core_package_blocklist (string[])
     *  - ask_on_file_scan_errors (bool)
     *  - break_on_verification_errors (bool)
     *  - present_foreign_packages_for_scan (bool)
     *  - logs_dir_name (string)
     *  - installation_log_filename (string)
     */
    public static function fromArray(array $cfg): self
    {
        $p = new self();

        if (array_key_exists('file_scan', $cfg)) {
            $p->enableFileScan((bool)$cfg['file_scan']);
        }
        if (isset($cfg['vendor_mode'])) {
            $p->setVendorMode(
                $cfg['vendor_mode'] instanceof VendorMode
                    ? $cfg['vendor_mode']
                    : VendorMode::from((string)$cfg['vendor_mode'])
            );
        }
        if (isset($cfg['token_ttl']['background_scan'])) {
            $p->setBackgroundScanTtl((int)$cfg['token_ttl']['background_scan']);
        }
        if (isset($cfg['token_ttl']['install_override'])) {
            $p->setInstallOverrideTtl((int)$cfg['token_ttl']['install_override']);
        }
        if (isset($cfg['psr4_root'])) {
            $p->setPsr4Root((string)$cfg['psr4_root']);
        }
        if (array_key_exists('route_schema', $cfg)) {
            $p->setRouteSchemaPath($cfg['route_schema'] !== null ? (string)$cfg['route_schema'] : null);
        }
        if (isset($cfg['middleware_allowlist']) && is_array($cfg['middleware_allowlist'])) {
            $p->setMiddlewareAllowlist($cfg['middleware_allowlist']);
        }
        if (isset($cfg['core_package_blocklist']) && is_array($cfg['core_package_blocklist'])) {
            $p->setCorePackageBlocklist($cfg['core_package_blocklist']);
        }
        if (array_key_exists('ask_on_file_scan_errors', $cfg)) {
            $p->setAskOnFileScanErrors((bool)$cfg['ask_on_file_scan_errors']);
        }
        if (array_key_exists('break_on_verification_errors', $cfg)) {
            $p->setBreakOnVerificationErrors((bool)$cfg['break_on_verification_errors']);
        }
        if (array_key_exists('present_foreign_packages_for_scan', $cfg)) {
            $p->setPresentForeignPackagesForScan((bool)$cfg['present_foreign_packages_for_scan']);
        }
        if (isset($cfg['logs_dir_name'])) {
            $p->setLogsDirName((string)$cfg['logs_dir_name']);
        }
        if (isset($cfg['installation_log_filename'])) {
            $p->setInstallationLogFilename((string)$cfg['installation_log_filename']);
        }

        if (isset($cfg['break_on_file_scan_errors'])) {
            $p->setBreakOnFileScanErrors((bool)$cfg['break_on_file_scan_errors']);
        }

        return $p;
    }

    /** Export a normalized array snapshot for logs/DB. */
    public function toArray(): array
    {
        return [
            'file_scan' => $this->fileScanEnabled,
            'vendor_mode' => $this->vendorMode->name,
            'token_ttl' => [
                'background_scan' => $this->backgroundScanTtl,
                'install_override' => $this->installOverrideTtl,
            ],
            'psr4_root' => $this->psr4Root,
            'route_schema' => $this->routeSchemaPath,
            'middleware_allowlist' => $this->middlewareAllowlist,
            'core_package_blocklist' => $this->corePackageBlocklist,
            'ask_on_file_scan_errors' => $this->askOnFileScanErrors,
            'break_on_verification_errors' => $this->breakOnVerificationErrors,
            'present_foreign_packages_for_scan' => $this->presentForeignPackagesForScan,
            'logs_dir_name' => $this->logsDirName,
            'installation_log_filename' => $this->installationLogFilename,
        ];
    }
}
```

---
#### 23


` File: src/Installations/Sections/Decision.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use Timeax\FortiPlugin\Installations\Enums\Install;

trait Decision
{
    /**
     * @throws JsonException
     */
    private function persistDecision(string $pluginDir, Install $decision, string $reason, ?array $tokenSummary = null): void
    {
        $path = $this->installationLogPath($pluginDir);
        $this->afs->ensureParentDirectory($path);

        $doc = $this->afs->fs()->exists($path) ? $this->afs->fs()->readJson($path) : [];
        $doc['decision'] = array_filter([
            'status' => $decision->value,
            'reason' => $reason,
            'token'  => $tokenSummary,
        ]);
        $this->afs->writeJsonAtomic($path, $doc, true);
    }
}
```

---
#### 24


` File: src/Installations/Sections/ZipValidationGate.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use Closure;
use JsonException;
use Random\RandomException;
use Throwable;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;

/**
 * ZipValidationGate
 *
 * Gate install based on PluginZip.validation_status, coordinating background_scan tokens.
 * - VERIFIED  → INSTALL
 * - PENDING   → ASK (issue/extend background_scan token)
 * - FAILED    → BREAK
 * - UNKNOWN/UNVERIFIED → BREAK
 */
final readonly class ZipValidationGate
{
    use Decision;
    public function __construct(
        private InstallerPolicy       $policy,
        private InstallerTokenManager $tokens,
        private ZipRepository         $zips,
        private AtomicFilesystem      $afs,
        /** optional installer-level emitter: fn(array $payload): void */
        private ?Closure              $emit = null
    ) {}

    /**
     * @param string $pluginDir
     * @param int|string $zipId
     * @param string $actor
     * @param string $runId
     * @param string $validatorConfigHash
     * @param string|null $installerToken
     * @return array{decision:Install, meta:array}
     * @throws JsonException
     * @throws RandomException
     */
    public function run(
        string $pluginDir,
        int|string $zipId,
        string $actor,
        string $runId,
        string $validatorConfigHash,
        ?string $installerToken = null
    ): array {
        $status = $this->zips->getValidationStatus($zipId);

        // Try to validate supplied token (best-effort)
        $tokenPurpose = null;
        if (is_string($installerToken) && $installerToken !== '') {
            try {
                $claims = $this->tokens->validate($installerToken);
                $tokenPurpose = $claims->purpose;
            } catch (Throwable $e) {
                $this->emit && ($this->emit)([
                    'title' => 'TOKEN_INVALID',
                    'description' => 'Installer token invalid or expired',
                    'meta' => ['zip_id' => (string)$zipId, 'reason' => $e->getMessage()],
                ]);
            }
        }

        $this->emit && ($this->emit)(['title' => 'ZIP_STATUS_CHECK', 'description' => 'Evaluating zip validation status', 'meta' => ['zip_id' => (string)$zipId, 'status' => $status->value]]);

        return match ($status) {
            ZipValidationStatus::VERIFIED => $this->allow($pluginDir, $zipId),
            ZipValidationStatus::PENDING  => $this->pending($pluginDir, $zipId, $actor, $runId, $validatorConfigHash, $tokenPurpose),
            ZipValidationStatus::FAILED   => $this->deny($pluginDir, $zipId, 'zip_validation_failed'),
            default                       => $this->deny($pluginDir, $zipId, 'zip_validation_unknown'),
        };
    }

    // ── decisions ──────────────────────────────────────────────────────────

    /**
     * @throws JsonException
     */
    private function allow(string $pluginDir, int|string $zipId): array
    {
        $this->persistGate($pluginDir, 'verified');
        $this->persistDecision($pluginDir, Install::INSTALL, 'zip_verified');
        $this->emit && ($this->emit)(['title' => 'INSTALL_DECISION', 'description' => 'INSTALL: zip verified', 'meta' => ['zip_id' => (string)$zipId]]);
        return ['decision' => Install::INSTALL, 'meta' => []];
    }

    /**
     * @throws RandomException
     * @throws JsonException
     */
    private function pending(
        string $pluginDir,
        int|string $zipId,
        string $actor,
        string $runId,
        string $validatorConfigHash,
        ?string $tokenPurpose
    ): array {
        // idempotent set
        $this->zips->setValidationStatus($zipId, ZipValidationStatus::PENDING);

        $ttl   = $this->policy->getBackgroundScanTtl();
        $token = $this->tokens->issueBackgroundScanToken($zipId, $validatorConfigHash, $actor, $runId, $ttl);
        $summary = $this->tokens->summarize('background_scan', time() + $ttl);

        $this->persistGate($pluginDir, 'pending', $summary);
        $this->persistDecision($pluginDir, Install::ASK, 'background_scans_pending', $summary);
        $this->emit && ($this->emit)(['title' => 'INSTALL_DECISION', 'description' => 'ASK: waiting on background scans', 'meta' => ['zip_id' => (string)$zipId]]);

        return ['decision' => Install::ASK, 'meta' => ['token' => $token, 'token_summary' => $summary]];
    }

    /**
     * @throws JsonException
     */
    private function deny(string $pluginDir, int|string $zipId, string $reason): array
    {
        $this->persistGate($pluginDir, $reason === 'zip_validation_failed' ? 'failed' : 'unknown');
        $this->persistDecision($pluginDir, Install::BREAK, $reason);
        $this->emit && ($this->emit)(['title' => 'INSTALL_DECISION', 'description' => 'BREAK: zip not eligible', 'meta' => ['zip_id' => (string)$zipId, 'reason' => $reason]]);
        return ['decision' => Install::BREAK, 'meta' => []];
    }

    // ── persistence helpers ────────────────────────────────────────────────

    /**
     * @throws JsonException
     */
    private function persistGate(string $pluginDir, string $status, ?array $tokenSummary = null): void
    {
        $path = $this->installationLogPath($pluginDir);
        $this->afs->ensureParentDirectory($path);

        $doc = $this->afs->fs()->exists($path) ? $this->afs->fs()->readJson($path) : [];
        $doc['zip_gate'] = array_filter([
            'status' => $status,
            'token'  => $tokenSummary, // { purpose, expires_at }
        ]);
        $this->afs->writeJsonAtomic($path, $doc, true);
    }

    private function installationLogPath(string $pluginDir): string
    {
        return rtrim($pluginDir, "\\/") . DIRECTORY_SEPARATOR
            . trim($this->policy->getLogsDirName(), "\\/") . DIRECTORY_SEPARATOR
            . $this->policy->getInstallationLogFilename();
    }
}
```

---
#### 25


` File: src/Installations/Support/AtomicFilesystem.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\Filesystem;

/**
 * AtomicFilesystem
 *
 * Lightweight helper that layers **atomic JSON operations** on top of a concrete
 * {@see Filesystem} implementation. It does NOT implement the Filesystem contract,
 * so there is no binding/circularity concern. Use this for installer logs and
 * other structured files that must be written atomically.
 *
 * Typical usage:
 *   $afs = new AtomicFilesystem($fs); // $fs is your Contracts\Filesystem
 *   $afs->ensureParentDirectory($pathToJson);
 *   $afs->writeJsonAtomic($pathToJson, $data, true);
 *   $afs->appendJsonArrayAtomic($pathToArrayJson, $item);
 */
final readonly class AtomicFilesystem
{
    public function __construct(private Filesystem $fs) {}

    /**
     * Access to the underlying low-level filesystem.
     * Useful when you need plain readJson(), exists(), etc.
     */
    public function fs(): Filesystem
    {
        return $this->fs;
    }

    /**
     * Ensure the parent directory of a path exists (mkdir -p semantics).
     * Uses native PHP so we don't require extra methods on the Filesystem contract.
     *
     * @throws RuntimeException if the directory cannot be created
     */
    public function ensureParentDirectory(string $path, int $mode = 0755): void
    {
        $dir = dirname($path);
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, $mode, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create directory: $dir");
        }
    }

    /**
     * Atomically write JSON to a file (UTF-8, no BOM).
     *
     * @param string $path   Absolute or project-relative path
     * @param array  $data   Data to encode
     * @param bool   $pretty Pretty-print JSON (for human-readable logs)
     *
     * @throws JsonException   If encoding fails
     * @throws RuntimeException If the write operation fails
     */
    public function writeJsonAtomic(string $path, array $data, bool $pretty = false): void
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        // Deterministic encode with exceptions so callers can catch details
        $json = json_encode($data, JSON_THROW_ON_ERROR | $flags);
        if ($json === false) {
            // Unreachable with JSON_THROW_ON_ERROR but kept for completeness
            throw new RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
        }

        $this->fs->writeAtomic($path, $json);
    }

    /**
     * Atomically append an item to a JSON array file.
     * If the target file doesn't exist, it is initialized as [] before append.
     *
     * @param string $path Target JSON file that holds a top-level array
     * @param array  $item Item to append
     *
     * @throws JsonException   If encoding/decoding fails
     * @throws RuntimeException If the write operation fails
     */
    public function appendJsonArrayAtomic(string $path, array $item): void
    {
        $arr = [];
        if ($this->fs->exists($path)) {
            $current = $this->fs->readJson($path);
            $arr = is_array($current) ? $current : [];
        }
        $arr[] = $item;

        $this->writeJsonAtomic($path, $arr, true);
    }
}
```

---
#### 26


` File: src/Installations/Support/InstallerTokenManager.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use Random\RandomException;
use RuntimeException;
use SodiumException;
use Timeax\FortiPlugin\Installations\DTO\TokenContext;
use Timeax\FortiPlugin\Installations\Contracts\HostKeyService as HostKeyServiceContract;
use Timeax\FortiPlugin\Services\HostKeyService as CryptoHostKeys;

/**
 * Token envelope built on top of the crypto HostKey service (sign/verify).
 *
 * Opaque token (string) = base64url(
 *   json_encode({
 *     v: 1,
 *     claims: {
 *       purpose, zip_id, fingerprint, validator_config_hash,
 *       actor, exp, nonce, run_id
 *     },
 *     sig: { alg, fingerprint, signature_b64 } // 'fingerprint' acts like a KID
 *   })
 * )
 *
 * Security notes:
 *  - Signs a deterministic JSON representation of the claims (stable key order).
 *  - NEVER log or persist the opaque token; expose only summarize() output if needed.
 */
final readonly class InstallerTokenManager implements HostKeyServiceContract
{
    public function __construct(private CryptoHostKeys $keys)
    {
    }

    /**
     * Issue an encrypted/signed token for given claims.
     * The TokenContext should already contain a sensible exp.
     * @throws JsonException
     * @throws JsonException
     * @throws JsonException
     */
    public function issue(TokenContext $claims): string
    {
        $arr = $claims->toArray();        // DTO → array
        $this->assertClaims($arr);        // sanity checks
        $data = $this->stableJson($arr);  // deterministic representation

        // Sign with current host key; returns ['alg','fingerprint','signature_b64'] (b64 is already encrypted by your service)
        $sig = $this->keys->sign($data);

        $env = ['v' => 1, 'claims' => $arr, 'sig' => $sig];
        return $this->encode($env);
    }

    /**
     * Issue a background_scan token (fingerprint is resolved internally).
     *
     * @param int|string $zipId
     * @param string $validatorConfigHash
     * @param string $actor
     * @param string $runId
     * @param int $ttlSeconds Desired TTL; bounded to 60–3600 seconds.
     * @return non-empty-string
     *
     * @throws JsonException
     * @throws RandomException
     * @throws RuntimeException
     */
    public function issueBackgroundScanToken(
        int|string $zipId,
        string     $validatorConfigHash,
        string     $actor,
        string     $runId,
        int        $ttlSeconds
    ): string
    {
        // Bound TTL to a sane window (adjust if you prefer different bounds)
        $ttl = min(3600, max(60, $ttlSeconds));

        // Resolve current verify-key fingerprint (acts like KID)
        $fp = $this->keys->currentVerifyKey()['fingerprint'];

        // Build claims DTO and issue the signed/enveloped token
        $claims = $this->makeBackgroundScanClaims(
            zipId: $zipId,
            fingerprint: $fp,
            validatorConfigHash: $validatorConfigHash,
            actor: $actor,
            runId: $runId,
            ttlSeconds: $ttl
        );

        return $this->issue($claims);
    }

    /**
     * Validate/decode a token and return its claims DTO.
     * @throws JsonException
     * @throws JsonException|SodiumException
     */
    public function validate(string $token): TokenContext
    {
        $env = $this->decode($token);
        if (!is_array($env) || ($env['v'] ?? null) !== 1 || !isset($env['claims'], $env['sig'])) {
            throw new RuntimeException('Invalid token envelope');
        }

        $claims = $env['claims'];
        $sig = $env['sig'];

        $this->assertClaims($claims);

        // Recreate deterministic string and verify with the host keys
        $data = $this->stableJson($claims);
        $ok = $this->keys->verify(
            data: $data,
            signatureB64: (string)($sig['signature_b64'] ?? ''),
            fingerprint: (string)($sig['fingerprint'] ?? '')
        );
        if (!$ok) {
            throw new RuntimeException('Invalid token signature');
        }

        $exp = (int)$claims['exp'];
        if ($exp < time()) {
            throw new RuntimeException('Token expired');
        }

        // Normalize into DTO
        return new TokenContext(
            purpose: (string)$claims['purpose'],
            zip_id: $claims['zip_id'],
            fingerprint: (string)$claims['fingerprint'],
            validator_config_hash: (string)$claims['validator_config_hash'],
            actor: (string)$claims['actor'],
            exp: $exp,
            nonce: (string)$claims['nonce'],
            run_id: (string)$claims['run_id'],
        );
    }

    /** Safe metadata for logs/UI (never include the token). */
    public function summarize(string $purpose, int $exp): array
    {
        return ['purpose' => $purpose, 'expires_at' => gmdate('c', $exp)];
    }

    // ── Optional helpers to build common claim sets (host can ignore if not needed) ──

    /**
     * @throws RandomException
     */
    public function makeBackgroundScanClaims(
        int|string $zipId,
        string     $fingerprint,
        string     $validatorConfigHash,
        string     $actor,
        string     $runId,
        int        $ttlSeconds
    ): TokenContext
    {
        return new TokenContext(
            purpose: 'background_scan',
            zip_id: $zipId,
            fingerprint: $fingerprint,
            validator_config_hash: $validatorConfigHash,
            actor: $actor,
            exp: time() + max(60, $ttlSeconds),
            nonce: bin2hex(random_bytes(12)),
            run_id: $runId,
        );
    }

    /**
     * Issue an install_override token (fingerprint resolved internally).
     *
     * @param int|string $zipId
     * @param string $validatorConfigHash
     * @param string $actor
     * @param string $runId
     * @param int $ttlSeconds Desired TTL; bounded to 60–3600 seconds.
     * @return non-empty-string
     *
     * @throws JsonException
     * @throws RandomException
     * @throws RuntimeException
     */
    public function issueInstallOverrideToken(
        int|string $zipId,
        string     $validatorConfigHash,
        string     $actor,
        string     $runId,
        int        $ttlSeconds
    ): string
    {
        $ttl = min(3600, max(60, $ttlSeconds));

        // Resolve current verify-key fingerprint (KID)
        $fp = $this->keys->currentVerifyKey()['fingerprint'];

        // Build claims DTO and issue the signed/enveloped token
        $claims = $this->makeInstallOverrideClaims(
            zipId: $zipId,
            fingerprint: $fp,
            validatorConfigHash: $validatorConfigHash,
            actor: $actor,
            runId: $runId,
            ttlSeconds: $ttl
        );

        return $this->issue($claims);
    }

    /**
     * @throws RandomException
     */
    public function makeInstallOverrideClaims(
        int|string $zipId,
        string     $fingerprint,
        string     $validatorConfigHash,
        string     $actor,
        string     $runId,
        int        $ttlSeconds
    ): TokenContext
    {
        return new TokenContext(
            purpose: 'install_override',
            zip_id: $zipId,
            fingerprint: $fingerprint,
            validator_config_hash: $validatorConfigHash,
            actor: $actor,
            exp: time() + max(60, $ttlSeconds),
            nonce: bin2hex(random_bytes(12)),
            run_id: $runId,
        );
    }

    // ── internals ───────────────────────────────────────────────────────────

    /** @param array<string,mixed> $claims */
    private function assertClaims(array $claims): void
    {
        foreach (['purpose', 'zip_id', 'fingerprint', 'validator_config_hash', 'actor', 'exp', 'nonce', 'run_id'] as $k) {
            if (!array_key_exists($k, $claims)) {
                throw new RuntimeException("Missing claim: $k");
            }
        }
        if (!is_int($claims['exp'])) {
            throw new RuntimeException('Claim exp must be an integer epoch');
        }
        if (!is_string($claims['purpose']) || $claims['purpose'] === '') {
            throw new RuntimeException('Claim purpose must be a non-empty string');
        }
    }

    /**
     * @throws JsonException
     */
    private function encode(array $env): string
    {
        $json = json_encode($env, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Token encoding failed');
        }
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        // (No encryption of the envelope itself; the signature is already protected via your HostKeyService)
    }

    /**
     * @throws JsonException
     */
    private function decode(string $token): array
    {
        $json = base64_decode(strtr($token, '-_', '+/'), true);
        if ($json === false) {
            throw new RuntimeException('Token decoding failed');
        }
        $env = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($env)) {
            throw new RuntimeException('Token JSON invalid');
        }
        return $env;
    }

    /** Deterministic JSON for signing/verification (assoc keys sorted, recursive).
     * @throws JsonException
     * @throws JsonException
     */
    private function stableJson(mixed $value): string
    {
        if (is_array($value)) {
            // associative?
            if ($value !== [] && !array_is_list($value)) {
                ksort($value);
                $pairs = [];
                foreach ($value as $k => $v) {
                    $pairs[] = json_encode((string)$k, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ':' . $this->stableJson($v);
                }
                return '{' . implode(',', $pairs) . '}';
            }
            // sequential
            return '[' . implode(',', array_map(fn($v) => $this->stableJson($v), $value)) . ']';
        }
        $enc = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($enc === false) {
            throw new RuntimeException('Stable JSON encode failed');
        }
        return $enc;
    }
}
```

---
#### 27


` File: src/Models/AuditLog.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $actor
 * @property int|null $actor_author_id
 * @property string $action
 * @property array|null $context
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Author::class $actorAuthor
 */
class AuditLog extends Model
{
	protected $table = "scpl_audit_logs";

	protected $fillable = ["actor", "actor_author_id", "action", "context"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"context" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function actorAuthor()
	{
		return $this->belongsTo(Author::class, "actor_author_id", "id");
	}
}
```

---
#### 28


` File: src/Models/Author.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\AuthorStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $handle
 * @property string|null $email
 * @property string $password
 * @property string|null $avatar_url
 * @property string|null $org
 * @property string|null $website
 * @property array|null $meta
 * @property AuthorStatus::class $status
 * @property bool $verified
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, Plugin::class> $pluginLinks
 * @property \Illuminate\Support\Collection<int, PluginIssue::class> $reportedIssues
 * @property \Illuminate\Support\Collection<int, PluginIssueMessage::class> $issueMessages
 * @property \Illuminate\Support\Collection<int, PluginZip::class> $uploadedZips
 * @property \Illuminate\Support\Collection<int, PluginToken::class> $pluginTokens
 * @property \Illuminate\Support\Collection<int, AuthorToken::class> $tokens
 * @property \Illuminate\Support\Collection<int, PluginAuditLog::class> $pluginAuditActors
 * @property \Illuminate\Support\Collection<int, AuditLog::class> $auditActors
 */
class Author extends Model
{
	protected $table = "scpl_authors";

	protected $fillable = [
		"slug",
		"name",
		"handle",
		"email",
		"password",
		"avatar_url",
		"org",
		"website",
		"meta",
		"status",
		"verified",
	];

	protected $hidden = ["password"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"status" => AuthorStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function pluginLinks()
	{
		return $this->belongsToMany(
			Plugin::class,
			"plugin_author",
			"author_id",
			"plugin_id",
			"id",
			"id",
		); // pivot: plugin_author
	}

	public function reportedIssues()
	{
		return $this->hasMany(PluginIssue::class, "reporter_id", "id");
	}

	public function issueMessages()
	{
		return $this->hasMany(PluginIssueMessage::class, "author_id", "id");
	}

	public function uploadedZips()
	{
		return $this->hasMany(PluginZip::class, "uploaded_by_author_id", "id");
	}

	public function pluginTokens()
	{
		return $this->hasMany(PluginToken::class, "author_id", "id");
	}

	public function tokens()
	{
		return $this->hasMany(AuthorToken::class, "author_id", "id");
	}

	public function pluginAuditActors()
	{
		return $this->hasMany(PluginAuditLog::class, "actor_author_id", "id");
	}

	public function auditActors()
	{
		return $this->hasMany(AuditLog::class, "actor_author_id", "id");
	}
}
```

---
#### 29


` File: src/Models/AuthorToken.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $author_id
 * @property string $token_hash
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $last_used
 * @property bool $revoked
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Author::class $author
 */
class AuthorToken extends Model
{
	protected $table = "scpl_author_tokens";

	protected $fillable = [
		"author_id",
		"token_hash",
		"expires_at",
		"last_used",
		"revoked",
		"meta",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"expires_at" => "datetime",
		"last_used" => "datetime",
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
```

---
#### 30


` File: src/Models/HostKey.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\KeyPurpose;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property KeyPurpose::class $purpose
 * @property string $public_pem
 * @property string|null $private_pem
 * @property string $fingerprint
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $rotated_at
 */
class HostKey extends Model
{
	protected $table = "scpl_host_keys";

	protected $fillable = [
		"purpose",
		"public_pem",
		"private_pem",
		"fingerprint",
		"created_at",
		"rotated_at",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"purpose" => KeyPurpose::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
		"rotated_at" => "datetime",
	];
}
```

---
#### 31


` File: src/Models/PermissionTag.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\PluginStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property PluginStatus::class $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, PluginPermissionTag::class> $plugins
 * @property \Illuminate\Support\Collection<int, PermissionTagItem::class> $items
 */
class PermissionTag extends Model
{
	protected $table = "scpl_permission_tags";

	protected $fillable = ["name", "description", "is_system", "status"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"status" => PluginStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugins()
	{
		return $this->hasMany(PluginPermissionTag::class, "tag_id", "id");
	}

	public function items()
	{
		return $this->hasMany(PermissionTagItem::class, "tag_id", "id");
	}
}
```

---
#### 32


` File: src/Models/PermissionTagItem.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PermissionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tag_id
 * @property PermissionType::class $permission_type
 * @property int $permission_id
 * @property array|null $constraints
 * @property array|null $audit
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PermissionTag::class $tag
 */
class PermissionTagItem extends Model
{
	protected $table = "scpl_permission_tag_items";

	protected $fillable = [
		"tag_id",
		"permission_type",
		"permission_id",
		"constraints",
		"audit",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"permission_type" => PermissionType::class,
		"constraints" => AsArrayObject::class,
		"audit" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function tag()
	{
		return $this->belongsTo(PermissionTag::class, "tag_id", "id");
	}
}
```

---
#### 33


` File: src/Models/Plugin.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $image
 * @property PluginStatus::class $status
 * @property array|null $config
 * @property array|null $meta
 * @property int $plugin_placeholder_id
 * @property int $active_version_id
 * @property string|null $owner_ref
 * @property \Carbon\Carbon|null $activated_at
 * @property int|null $activated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property \Illuminate\Support\Collection<int, PluginSetting::class> $plugin_settings
 * @property \Illuminate\Support\Collection<int, PluginVersion::class> $plugin_versions
 * @property \Illuminate\Support\Collection<int, PluginAuditLog::class> $logs
 * @property \Illuminate\Support\Collection<int, Author::class> $authors
 * @property \Illuminate\Support\Collection<int, PluginIssue::class> $issues
 * @property \Illuminate\Support\Collection<int, PluginPermission::class> $plugin_permissions
 * @property \Illuminate\Support\Collection<int, PluginPermissionTag::class> $permission_tags
 * @property \Illuminate\Support\Collection<int, PluginRoutePermission::class> $routes
 */
class Plugin extends Model
{
	protected $table = "scpl_plugins";

	protected $guarded = [];

	protected $casts = [
		"status" => PluginStatus::class,
		"config" => AsArrayObject::class,
		"meta" => AsArrayObject::class,
		"activated_at" => "datetime",
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"plugin_placeholder_id",
			"id",
		);
	}

	public function plugin_settings()
	{
		return $this->hasMany(PluginSetting::class, "plugin_id", "id");
	}

	public function plugin_versions()
	{
		return $this->hasMany(PluginVersion::class, "plugin_id", "id");
	}

	public function logs()
	{
		return $this->hasMany(PluginAuditLog::class, "plugin_id", "id");
	}

	public function authors()
	{
		return $this->belongsToMany(
			Author::class,
			"plugin_author",
			"plugin_id",
			"author_id",
			"id",
			"id",
		); // pivot: plugin_author
	}

	public function issues()
	{
		return $this->hasMany(PluginIssue::class, "plugin_id", "id");
	}

	public function plugin_permissions()
	{
		return $this->hasMany(PluginPermission::class, "plugin_id", "id");
	}

	public function permission_tags()
	{
		return $this->hasMany(PluginPermissionTag::class, "plugin_id", "id");
	}

	public function routes()
	{
		return $this->hasMany(PluginRoutePermission::class, "plugin_id", "id");
	}
}
```

---
#### 34


` File: src/Models/PluginAuditLog.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string|null $actor
 * @property int|null $actor_author_id
 * @property string $type
 * @property string $action
 * @property string $resource
 * @property array|null $context
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 * @property Author::class $actorAuthor
 */
class PluginAuditLog extends Model
{
	protected $table = "scpl_plugin_audit_logs";

	protected $fillable = [
		"plugin_id",
		"actor",
		"actor_author_id",
		"type",
		"action",
		"resource",
		"context",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"context" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function actorAuthor()
	{
		return $this->belongsTo(Author::class, "actor_author_id", "id");
	}
}
```

---
#### 35


` File: src/Models/PluginIssue.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property int $reporter_id
 * @property string $type
 * @property string $description
 * @property IssueStatus::class $status
 * @property string|null $severity
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 * @property Author::class $reporter
 * @property \Illuminate\Support\Collection<int, PluginIssueMessage::class> $messages
 */
class PluginIssue extends Model
{
	protected $table = "scpl_plugin_issues";

	protected $fillable = [
		"plugin_id",
		"reporter_id",
		"type",
		"description",
		"status",
		"severity",
		"meta",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"status" => IssueStatus::class,
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function reporter()
	{
		return $this->belongsTo(Author::class, "reporter_id", "id");
	}

	public function messages()
	{
		return $this->hasMany(PluginIssueMessage::class, "issue_id", "id");
	}
}
```

---
#### 36


` File: src/Models/PluginIssueMessage.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $issue_id
 * @property int $author_id
 * @property string $message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginIssue::class $issue
 * @property Author::class $author
 */
class PluginIssueMessage extends Model
{
	protected $table = "scpl_plugin_issue_messages";

	protected $fillable = ["issue_id", "author_id", "message"];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function issue()
	{
		return $this->belongsTo(PluginIssue::class, "issue_id", "id");
	}

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
```

---
#### 37


` File: src/Models/PluginPermission.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PermissionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property PermissionType::class $permission_type
 * @property int $permission_id
 * @property bool $active
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property array|null $constraints
 * @property array|null $audit
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginPermission extends Model
{
	protected $table = "scpl_plugin_permissions";

	protected $fillable = [
		"plugin_id",
		"permission_type",
		"permission_id",
		"active",
		"limited",
		"limit_type",
		"limit_value",
		"constraints",
		"audit",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"permission_type" => PermissionType::class,
		"constraints" => AsArrayObject::class,
		"audit" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
```

---
#### 38


` File: src/Models/PluginPermissionTag.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property int $tag_id
 * @property bool $active
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 * @property PermissionTag::class $tag
 */
class PluginPermissionTag extends Model
{
	protected $table = "scpl_plugin_permission_tags";

	protected $fillable = [
		"plugin_id",
		"tag_id",
		"active",
		"limited",
		"limit_type",
		"limit_value",
	];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function tag()
	{
		return $this->belongsTo(PermissionTag::class, "tag_id", "id");
	}
}
```

---
#### 39


` File: src/Models/PluginPlaceholder.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $unique_key
 * @property string|null $owner_ref
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, PluginToken::class> $tokens
 * @property \Illuminate\Support\Collection<int, PluginSignature::class> $signatures
 * @property \Illuminate\Support\Collection<int, PluginZip::class> $zips
 * @property Plugin::class $plugin
 */
class PluginPlaceholder extends Model
{
	protected $table = "scpl_placeholders";

	protected $fillable = ["slug", "name", "unique_key", "owner_ref", "meta"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function tokens()
	{
		return $this->hasMany(
			PluginToken::class,
			"plugin_placeholder_id",
			"id",
		);
	}

	public function signatures()
	{
		return $this->hasMany(PluginSignature::class, "placeholder_id", "id");
	}

	public function zips()
	{
		return $this->hasMany(PluginZip::class, "placeholder_id", "id");
	}

	public function plugin()
	{
		return $this->hasOne(Plugin::class, "plugin_placeholder_id", "id");
	}
}
```

---
#### 40


` File: src/Models/PluginRoutePermission.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\RoutePermissionStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $route_id
 * @property RoutePermissionStatus::class $status
 * @property string|null $guard
 * @property array|null $meta
 * @property \Carbon\Carbon|null $approved_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginRoutePermission extends Model
{
	protected $table = "scpl_plugin_route_permissions";

	protected $fillable = [
		"plugin_id",
		"route_id",
		"status",
		"guard",
		"meta",
		"approved_at",
	];

	protected $casts = [
		"status" => RoutePermissionStatus::class,
		"meta" => AsArrayObject::class,
		"approved_at" => "datetime",
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
```

---
#### 41


` File: src/Models/PluginSetting.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\PluginSettingValueType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $key
 * @property string $value
 * @property PluginSettingValueType::class $type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginSetting extends Model
{
	protected $table = "scpl_plugin_settings";

	protected $fillable = ["plugin_id", "key", "value", "type"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"type" => PluginSettingValueType::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
```

---
#### 42


` File: src/Models/PluginSignature.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $placeholder_id
 * @property string $host_domain
 * @property string $owner_host
 * @property string $plugin_key
 * @property string $signature
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 */
class PluginSignature extends Model
{
	protected $table = "scpl_plugin_signatures";

	protected $fillable = [
		"placeholder_id",
		"host_domain",
		"owner_host",
		"plugin_key",
		"signature",
	];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"placeholder_id",
			"id",
		);
	}
}
```

---
#### 43


` File: src/Models/PluginToken.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_placeholder_id
 * @property string $token_hash
 * @property array $meta
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $last_used
 * @property bool $revoked
 * @property int|null $author_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property Author::class $author
 */
class PluginToken extends Model
{
	protected $table = "scpl_plugin_tokens";

	protected $fillable = [
		"plugin_placeholder_id",
		"token_hash",
		"meta",
		"expires_at",
		"last_used",
		"revoked",
		"author_id",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"expires_at" => "datetime",
		"last_used" => "datetime",
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"plugin_placeholder_id",
			"id",
		);
	}

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
```

---
#### 44


` File: src/Models/PluginVersion.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $version
 * @property string $archive_url
 * @property array|null $manifest
 * @property array|null $validation_report
 * @property ValidationStatus::class $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginVersion extends Model
{
	protected $table = "scpl_plugin_versions";

	protected $fillable = [
		"plugin_id",
		"version",
		"archive_url",
		"manifest",
		"validation_report",
		"status",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"manifest" => AsArrayObject::class,
		"validation_report" => AsArrayObject::class,
		"status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
```

---
#### 45


` File: src/Models/PluginZip.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $placeholder_id
 * @property string $path
 * @property array $meta
 * @property PluginStatus::class $status
 * @property ValidationStatus::class $validation_status
 * @property int|null $uploaded_by_author_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property Author::class $uploadedBy
 */
class PluginZip extends Model
{
	protected $table = "scpl_plugin_zips";

	protected $fillable = [
		"placeholder_id",
		"path",
		"meta",
		"status",
		"validation_status",
		"uploaded_by_author_id",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"status" => PluginStatus::class,
		"validation_status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"placeholder_id",
			"id",
		);
	}

	public function uploadedBy()
	{
		return $this->belongsTo(Author::class, "uploaded_by_author_id", "id");
	}
}
```

---
#### 46


` File: src/Services/HostKeyService.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Services;

use Illuminate\Support\Facades\File;
use JsonException;
use OpenSSLAsymmetricKey;
use RuntimeException;
use SodiumException;
use Timeax\FortiPlugin\Enums\KeyPurpose;
use Timeax\FortiPlugin\Models\HostKey;
use Timeax\FortiPlugin\Support\Encryption;

final class HostKeyService
{
    /**
     * Return the current verifying key (public) for installers.
     * @return array{fingerprint:string, public_pem:string}
     */
    public function currentVerifyKey(string|KeyPurpose $purpose = null): array
    {
        $purpose ?: config('fortiplugin.keys.verify_purpose', 'installer_verify');

        $key = HostKey::query()
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (!$key) {
            throw new RuntimeException('No host verify key found (purpose=' . $purpose . ').');
        }

        return [
            'fingerprint' => $key->fingerprint,
            'public_pem' => $key->public_pem,
        ];
    }

    /**
     * Sign arbitrary data with the current signing key.
     * @return array{alg:string,fingerprint:string,signature_b64:string}
     * @throws JsonException
     */
    public function sign(string $data): array
    {
        $purpose = config('fortiplugin.keys.sign_purpose', 'packager_sign');
        $digest = (int)config('fortiplugin.keys.digest', OPENSSL_ALGO_SHA256);

        $key = HostKey::query()
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (!$key || empty($key->private_pem)) {
            throw new RuntimeException('No host signing key available (purpose=' . $purpose . ').');
        }

        $privateKey = openssl_pkey_get_private($key->private_pem);
        if (!$privateKey) {
            throw new RuntimeException('Invalid private key in HostKey#' . $key->id);
        }

        $ok = openssl_sign($data, $sigBin, $privateKey, $digest);
        // NOTE: openssl_free_key() is deprecated; let GC handle the resource/object.

        if (!$ok) {
            throw new RuntimeException('Signing failed.');
        }

        return [
            'alg' => (string)config('fortiplugin.keys.algo', 'RS256'),
            'fingerprint' => $key->fingerprint,
            'signature_b64' => Encryption::encrypt(base64_encode($sigBin)),
        ];
    }

    /**
     * Verify a signature using a public key (by fingerprint or provided PEM).
     * @throws JsonException|SodiumException
     */
    public function verify(string $data, string $signatureB64, ?string $fingerprint = null, ?string $publicPem = null): bool
    {
        $sig = base64_decode(Encryption::decrypt($signatureB64), true);
        if ($sig === false) {
            return false;
        }

        if (!$publicPem) {
            if (!$fingerprint) {
                throw new RuntimeException('Either publicPem or fingerprint must be provided for verification.');
            }
            $publicPem = $this->publicByFingerprint($fingerprint);
        }

        $publicKey = openssl_pkey_get_public($publicPem);
        if (!$publicKey) {
            return false;
        }

        $digest = (int)config('fortiplugin.keys.digest', OPENSSL_ALGO_SHA256);
        $res = openssl_verify($data, $sig, $publicKey, $digest);

        return $res === 1; // 1 = valid, 0 = invalid, -1 = error
    }

    public function generate(string $purpose): HostKey
    {
        $bits = (int)config('fortiplugin.keys.bits', 2048);
        $cnf = config('fortiplugin.keys.openssl_cnf') ?: $this->resolveOpensslConfigPath();

        // try with configured bits
        $res = $this->tryMakeKey([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $cnf ?: null,
        ]);
        if ($res === false) {
            $errs1 = $this->collectOpenSslErrors();

            // retry with 2048 (Windows builds can reject larger sizes)
            $res = $this->tryMakeKey([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'config' => $cnf ?: null,
            ]);
            if ($res === false) {
                $errs2 = $this->collectOpenSslErrors();
                $msg = "Unable to generate RSA keypair. "
                    . ($cnf ? "Tried config: $cnf. " : "")
                    . ($errs1 ? '[pass1] ' . implode(' | ', $errs1) . '. ' : '')
                    . ($errs2 ? '[pass2] ' . implode(' | ', $errs2) . '. ' : '');
                throw new RuntimeException(rtrim($msg));
            }
        }

        // export with a temporary OPENSSL_CONF / RANDFILE
        $privatePem = '';
        $exportOk = $this->withOpenSslEnv($cnf, function () use ($res, &$privatePem) {
            // avoid encryption to reduce RNG/config needs
            $args = ['config' => getenv('OPENSSL_CONF') ?: null, 'encrypt_key' => false];
            return @openssl_pkey_export($res, $privatePem, null, array_filter($args));
        });
        if (!$exportOk) {
            $errs = $this->collectOpenSslErrors();
            throw new RuntimeException('Unable to export private key. ' . implode(' | ', $errs));
        }

        $details = @openssl_pkey_get_details($res);
        if (!$details || empty($details['key'])) {
            throw new RuntimeException('Unable to extract public key.');
        }
        $publicPem = $details['key'];
        $fingerprint = $this->fingerprint($publicPem);

        return HostKey::create([
            'purpose' => $purpose,
            'public_pem' => $publicPem,
            'private_pem' => $privatePem,
            'fingerprint' => $fingerprint,
        ]);
    }

    private function tryMakeKey(array $args): OpenSSLAsymmetricKey|false
    {
        return @openssl_pkey_new(array_filter($args, static fn($v) => $v !== null));
    }

    private function collectOpenSslErrors(): array
    {
        $out = [];
        while ($e = openssl_error_string()) {
            $out[] = $e;
        }
        return $out;
    }

    /**
     * Temporarily sets OPENSSL_CONF (if provided) and a writable RANDFILE on Windows,
     * runs $fn, then restores the environment.
     */
    private function withOpenSslEnv(?string $cnf, callable $fn)
    {
        $restore = [];

        if ($cnf && is_file($cnf)) {
            $restore['OPENSSL_CONF'] = getenv('OPENSSL_CONF') !== false ? getenv('OPENSSL_CONF') : null;
            putenv('OPENSSL_CONF=' . $cnf);
        }

        // Windows-only: ensure a writable RANDFILE to satisfy configs referencing it
        if (DIRECTORY_SEPARATOR === '\\') {
            $hadRand = getenv('RANDFILE') !== false;
            if (!$hadRand || !is_file((string)getenv('RANDFILE'))) {
                $randPath = storage_path('app/forti/openssl/randseed.rnd');
                // Idempotent: no warnings if directory already exists
                File::ensureDirectoryExists(dirname($randPath), 0777);
                // Create the file atomically if missing (no error if it already exists)
                $this->ensureFileExistsAtomic($randPath);
                $restore['RANDFILE'] = $hadRand ? getenv('RANDFILE') : null;
                putenv('RANDFILE=' . $randPath);
            }
        }

        try {
            return $fn();
        } finally {
            foreach ($restore as $k => $v) {
                if ($v === null) {
                    // unset
                    putenv($k);
                } else {
                    putenv($k . '=' . $v);
                }
            }
        }
    }

    /** Ensure a file exists without race-condition warnings (atomic create). */
    private function ensureFileExistsAtomic(string $path): void
    {
        clearstatcache(true, $path);
        if (is_file($path)) {
            return;
        }

        $dir = dirname($path);
        File::ensureDirectoryExists($dir, 0777);

        // Try atomic create; if another process wins, this returns false but that's fine.
        $h = @fopen($path, 'xb');
        if ($h !== false) {
            fclose($h);
        } elseif (!is_file($path)) {
            // If it still doesn't exist (other error), best-effort create.
            @touch($path);
        }

        // Ensure writable (best-effort; ignores failures)
        if (is_file($path) && !is_writable($path)) {
            @chmod($path, 0666 & ~umask());
        }
    }

    /** Best-effort openssl.cnf discovery for Windows PHP bundles. */
    private function resolveOpensslConfigPath(): ?string
    {
        $env = getenv('OPENSSL_CONF');
        if ($env && is_file($env)) return $env;

        $candidates = [
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'openssl.cnf',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }
        return null;
    }

    /** Mark current key rotated and generate a new one. */
    public function rotate(string $purpose): HostKey
    {
        $current = HostKey::query()
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if ($current && !$current->rotated_at) {
            $current->rotated_at = now();
            $current->save();
        }

        return $this->generate($purpose);
    }

    // ---- internals ----

    private function publicByFingerprint(string $fp): string
    {
        $key = HostKey::query()->where('fingerprint', $fp)->first();
        if (!$key) {
            throw new RuntimeException('HostKey not found for fingerprint ' . $fp);
        }
        return $key->public_pem;
    }

    /** SHA-256 over DER SubjectPublicKeyInfo bytes (stable fingerprint). */
    public function fingerprint(string $publicPem): string
    {
        $der = $this->pemToDer($publicPem);
        return hash('sha256', $der);
    }

    private function pemToDer(string $pem): string
    {
        $clean = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $pem);
        $bin = base64_decode($clean, true);
        if ($bin === false) {
            throw new RuntimeException('Invalid PEM format.');
        }
        return $bin;
    }
}
```

---
#### 47


` File: src/Support/Encryption.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Support;


use JsonException;
use SodiumException;

class Encryption
{
    /**
     * @throws
     */
    public static function encrypt(string $plaintext, int $numShards = 7): string
    {
        $encryptionKey = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
        $shards = self::splitKeyIntoShards($encryptionKey, $numShards);

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, sodium_base642bin($encryptionKey, SODIUM_BASE64_VARIANT_URLSAFE));
        $payload = [
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
        ];
        $payloadEncoded = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        // Embed key shards as labeled markers in pseudo-random positions in payload
        $block = $payloadEncoded;
        $positions = self::calculateShardPositions($block, $numShards);
        foreach ($positions as $i => $pos) {
            $block = substr($block, 0, $pos) . "[KEY$i=$shards[$i]]" . substr($block, $pos);
        }
        // Encode the map as a hidden suffix
        $block .= "\n--KEYMAP:" . base64_encode(json_encode(['count' => $numShards], JSON_THROW_ON_ERROR)) . "--";
        return $block;
    }

    /**
     * @throws SodiumException|JsonException
     */
    public static function decrypt(string $encrypted): ?string
    {
        // Find keymap (required for number of shards)
        if (!preg_match('/--KEYMAP:([A-Za-z0-9+\/=_-]+)--/', $encrypted, $m)) {
            return null;
        }
        $keymap = json_decode(base64_decode($m[1]), true, 512, JSON_THROW_ON_ERROR);
        $numShards = $keymap['count'] ?? 7;

        // Remove keymap for base64 decode
        $main = preg_replace('/--KEYMAP:([A-Za-z0-9+\/=_-]+)--/', '', $encrypted);

        // Extract shards in order
        $shards = [];
        for ($i = 0; $i < $numShards; $i++) {
            if (preg_match("/\[KEY$i=([A-Za-z0-9+\/=_-]+)]/", $main, $matches)) {
                $shards[$i] = $matches[1];
                // Remove marker from main so it doesn't mess up offsets for next
                $main = str_replace($matches[0], '', $main);
            }
        }
        ksort($shards);
        $key = implode('', $shards);

        // Now base64-decode, then decrypt
        $payload = json_decode(base64_decode($main), true, 512, JSON_THROW_ON_ERROR);
        if (!$payload || !isset($payload['nonce'], $payload['ciphertext'])) {
            return null;
        }

        $nonce = base64_decode($payload['nonce']);
        $ciphertext = base64_decode($payload['ciphertext']);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, sodium_base642bin($key, SODIUM_BASE64_VARIANT_URLSAFE));
        return $plaintext === false ? null : $plaintext;
    }

    // Helper: split key into N shards
    private static function splitKeyIntoShards(string $key, int $numShards): array
    {
        $len = strlen($key);
        $shardSize = (int)ceil($len / $numShards);
        $shards = [];
        for ($i = 0; $i < $numShards; $i++) {
            $shards[] = substr($key, $i * $shardSize, $shardSize);
        }
        return $shards;
    }

    // Helper: find pseudo-random insert positions for markers
    private static function calculateShardPositions(string $block, int $numShards): array
    {
        $positions = [];
        $len = strlen($block);
        for ($i = 0; $i < $numShards; $i++) {
            // Example: offset is (block len / (numShards+1)) * (i+1), +i to scramble a bit
            $positions[] = min(
                (int)(($len / ($numShards + 1)) * ($i + 1)) + $i,
                $len - 1
            );
        }
        arsort($positions); // Insert from the end for stable offsets
        return array_values($positions);
    }

    /**
     * @throws JsonException
     */
    public static function encryptFile($inputFile, $outputFile): void
    {
        $data = file_get_contents($inputFile);
        $encrypted = self::encrypt($data); // Pass the key explicitly
        file_put_contents($outputFile, $encrypted);
    }

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    public static function decryptFile($inputFile, $outputFile): void
    {
        $data = file_get_contents($inputFile);
        $encrypted = self::decrypt($data); // Pass the key explicitly
        file_put_contents($outputFile, $encrypted);
    }
}
```


---
*Generated with [Prodex](https://github.com/emxhive/prodex) — Codebase decoded.*
<!-- PRODEx v1.4.7 | 2025-12-02T17:33:42.392Z -->