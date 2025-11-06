# Index 

Included Source Files (83)
- [src/Contracts/ConfigInterface.php](#1)
- [src/Core/ChecksModulePermission.php](#2)
- [src/Core/Exceptions/DuplicateSettingIdException.php](#3)
- [src/Core/Exceptions/HostConfigException.php](#4)
- [src/Core/Exceptions/RouteCompileException.php](#5)
- [src/Core/Install/JsonRouteCompiler.php](#6)
- [src/Core/PluginPolicy.php](#7)
- [src/Core/Security/ComposerScan.php](#8)
- [src/Core/Security/Concerns/ResolvesNames.php](#9)
- [src/Core/Security/ConfigValidator.php](#10)
- [src/Core/Security/ContentValidator.php](#11)
- [src/Core/Security/FileScanner.php](#12)
- [src/Core/Security/HostConfigValidator.php](#13)
- [src/Core/Security/PermissionManifestValidator.php](#14)
- [src/Core/Security/PluginSecurityScanner.php](#15)
- [src/Core/Security/RouteFileValidator.php](#16)
- [src/Core/Security/RouteIdRegistry.php](#17)
- [src/Core/Security/TokenUsageAnalyzer.php](#18)
- [src/Enums/KeyPurpose.php](#19)
- [src/Enums/PermissionType.php](#20)
- [src/Enums/PluginStatus.php](#21)
- [src/Enums/ValidationStatus.php](#22)
- [src/Exceptions/DuplicateRouteIdException.php](#23)
- [src/Exceptions/PermissionDeniedException.php](#24)
- [src/Exceptions/PluginContextException.php](#25)
- [src/Installations/Activation/Activator.php](#26)
- [src/Installations/Activation/Writers/ProvidersRegistryWriter.php](#27)
- [src/Installations/Activation/Writers/RoutesRegistryWriter.php](#28)
- [src/Installations/Activation/Writers/UiRegistryWriter.php](#29)
- [src/Installations/Contracts/Filesystem.php](#30)
- [src/Installations/Contracts/HostKeyService.php](#31)
- [src/Installations/Contracts/PluginRepository.php](#32)
- [src/Installations/Contracts/RegistryWriter.php](#33)
- [src/Installations/Contracts/ZipRepository.php](#34)
- [src/Installations/DTO/ComposerPlan.php](#35)
- [src/Installations/DTO/DecisionResult.php](#36)
- [src/Installations/DTO/InstallerResult.php](#37)
- [src/Installations/DTO/InstallMeta.php](#38)
- [src/Installations/DTO/InstallSummary.php](#39)
- [src/Installations/DTO/PackageEntry.php](#40)
- [src/Installations/DTO/TokenContext.php](#41)
- [src/Installations/Enums/Install.php](#42)
- [src/Installations/Enums/PackageStatus.php](#43)
- [src/Installations/Enums/VendorMode.php](#44)
- [src/Installations/Enums/ZipValidationStatus.php](#45)
- [src/Installations/Installer.php](#46)
- [src/Installations/InstallerPolicy.php](#47)
- [src/Installations/Readme.md](#48)
- [src/Installations/Sections/ComposerPlanSection.php](#49)
- [src/Installations/Sections/DbPersistSection.php](#50)
- [src/Installations/Sections/FileScanSection.php](#51)
- [src/Installations/Sections/InstallFilesSection.php](#52)
- [src/Installations/Sections/ProviderValidationSection.php](#53)
- [src/Installations/Sections/RouteWriteSection.php](#54)
- [src/Installations/Sections/UiConfigValidationSection.php](#55)
- [src/Installations/Sections/VendorPolicySection.php](#56)
- [src/Installations/Sections/VerificationSection.php](#57)
- [src/Installations/Sections/ZipValidationGate.php](#58)
- [src/Installations/Support/AtomicFilesystem.php](#59)
- [src/Installations/Support/ComposerInspector.php](#60)
- [src/Installations/Support/EmitsEvents.php](#61)
- [src/Installations/Support/ErrorCodes.php](#62)
- [src/Installations/Support/Events.php](#63)
- [src/Installations/Support/InstallationLogStore.php](#64)
- [src/Installations/Support/InstallerTokenManager.php](#65)
- [src/Installations/Support/Psr4Checker.php](#66)
- [src/Installations/Support/RouteMaterializer.php](#67)
- [src/Installations/Support/RouteRegistryStore.php](#68)
- [src/Installations/Support/RouteUiBridge.php](#69)
- [src/Installations/Support/ValidatorBridge.php](#70)
- [src/Lib/Obfuscator.php](#71)
- [src/Lib/Utils/ObfuscatorUtil.php](#72)
- [src/Models/HostKey.php](#73)
- [src/Models/Plugin.php](#74)
- [src/Models/PluginAuditLog.php](#75)
- [src/Models/PluginVersion.php](#76)
- [src/Permissions/Evaluation/Dto/PermissionListResult.php](#77)
- [src/Permissions/Support/HostConfigNormalizer.php](#78)
- [src/Services/HostKeyService.php](#79)
- [src/Services/ValidatorService.php](#80)
- [src/Support/Encryption.php](#81)
- [src/Support/MiddlewareNormalizer.php](#82)
- [src/Support/PluginContext.php](#83)

---
---
#### 1


` File: src/Contracts/ConfigInterface.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection ALL */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Contracts;

use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\PermissionListResult;

/**
 * Contract each plugin’s internal Config class must implement.
 *
 * This interface exposes:
 * - Static accessors for the plugin’s declared config/metadata.
 * - Read-only views of the plugin’s granted permissions (both required & extra).
 * - Two complementary permission helpers:
 *    • hasPermission(...) → boolean “can I do X on Y?”
 *    • getPermission(...) → returns the matched permission definition (or null)
 *
 * Implementations are typically thin facades over host services (e.g. the
 * PermissionService facade) and the plugin’s own config/manifest payload.
 */
interface ConfigInterface
{
    /**
     * Return the entire parsed plugin config.
     *
     * The array SHOULD include top-level keys you expose in your plugin’s
     * config/manifest (e.g. "name", "alias", "version", "ui", "host", ...).
     *
     * @return array<string, mixed>
     */
    public static function all(): array;

    /**
     * Get a config key or a default value when missing.
     *
     * Nested keys MAY be host-defined (e.g., "ui.theme"), but plain keys are recommended.
     *
     * @param string $key Config key to read.
     * @param mixed|null $default Fallback if the key is not present.
     * @return mixed               The stored value or $default.
     */
    public static function get(string $key, mixed $default = null): mixed;

    /**
     * Declared plugin name (from config/manifest).
     *
     * @return string Non-empty human-friendly name.
     */
    public static function getName(): string;

    /**
     * Declared plugin alias, used by the host to resolve runtime behaviors.
     *
     * @return string Non-empty machine-friendly slug/alias.
     */
    public static function getAlias(): string;

    /**
     * Declared plugin version (semantic version string recommended).
     *
     * @return string e.g. "1.2.3"
     */
    public static function getVersion(): string;

    /**
     * Optional plugin description.
     *
     * @return string|null A short human-readable summary, or null when not set.
     */
    public static function getDescription(): ?string;

    /**
     * Optional author display name (as declared in config/manifest).
     *
     * @return string|null
     */
    public static function getAuthor(): ?string;

    /**
     * UI config block (if defined by the plugin).
     *
     * Example shape (host-defined):
     * [
     *   'theme' => 'dark',
     *   'features' => ['dashboard' => true, 'betaFlag' => false],
     * ]
     *
     * @return array<string,mixed>|null
     */
    public static function getUiConfig(): ?array;

    /**
     * Host-facing (API) config block (if defined by the plugin).
     *
     * Example shape (host-defined):
     * [
     *   'webhooks' => ['onInstall' => '...'],
     *   'endpoints' => ['health' => '/internal/health'],
     * ]
     *
     * @return array<string,mixed>|null
     */
    public static function getHostConfig(): ?array;

    /**
     * Runtime install info for the plugin, set by the host at install time.
     *
     * Keys are host-defined but MUST include:
     *  - id:    The installed plugin's primary key (or null before install).
     *  - alias: The installed alias (may be present before id exists).
     *  - name:  The installed name (for convenience).
     *
     * @return array{id:int|null, alias:string|null, name:string}
     */
    public static function getInfo(): array;

    /**
     * Convenience accessor for the installed plugin’s database id.
     *
     * @return int|null The plugin id or null if not installed yet.
     */
    public static function getPluginId(): ?int;

    /**
     * Convenience accessor for the installed plugin’s alias.
     *
     * @return string|null The alias or null if not known yet.
     */
    public static function getInstalledAlias(): ?string;

    /**
     * Combined permission view for this plugin (required + extra).
     *
     * This returns the same DTO produced by PermissionService::listPermissions(),
     * which includes:
     *  - the flattened list of concrete permissions,
     *  - per-permission “required” flag and source (direct/tag),
     *  - summary counters (totals, required satisfied/pending, etc.).
     *
     * @return PermissionListResult
     * @see PermissionListResult::class for exact shape & accessors.
     *
     */
    public static function getPermissions(): PermissionListResult;

    /**
     * Check whether specific permissions are granted for a plugin asset.
     *
     * @param PermissionType|string $type Permission family: db|file|notification|module|network|codec
     * @param string $actionOrIntent
     *        Action/intent to verify:
     *        - db: select|insert|update|delete|truncate|grouped_queries
     *        - file: read|write|append|delete|mkdir|rmdir|list
     *        - notification: send|receive
     *        - module: call
     *        - network: request
     *        - codec: invoke
     * @param string|array|null $meta Type-specific selector describing the target:
     *        - db:      ['model'=>'User'] or ['table'=>'users','columns'=>['id','name']]
     *        - file:    ['baseDir'=>'/var/data','path'=>'reports/2024.csv']
     *        - notification: ['channel'=>'email','template'=>'welcome','recipient'=>'x@y']
     *        - module:  ['module'=>'analytics','api'=>'track']
     *        - network: ['method'=>'GET','url'=>'https://api.example.com/v1/...']
     *        - codec:   ['method'=>'json_encode','options'=>[...]]
     * @param array $context Optional runtime hints (e.g., ['guard'=>'api','env'=>'staging']).
     * @return bool True if the action/intent is allowed for the given selector.
     */
    public static function hasPermission(
        PermissionType|string $type,
        string                $actionOrIntent,
        string|array|null     $meta = null,
        array                 $context = []
    ): bool;

    /**
     * Fetch the granted permission definition that matches the given selector.
     *
     * Returns a host-defined associative array describing the concrete,
     * currently effective permission for the provided selector, or null if
     * no matching permission is found.
     *
     * Suggested keys (host may include more):
     *  [
     *    'type'     => 'network',              // one of db|file|notification|module|network|codec
     *    'meta'     => array<string,mixed>,    // normalized target selector (see hasPermission meta)
     *    'grants'   => string[],               // actions currently allowed (e.g., ['request'])
     *    'required' => bool,                   // came from manifest.required_permissions
     *    'source'   => 'direct'|'tag',         // optional provenance
     *  ]
     *
     * Examples:
     *  getPermission('module', ['module'=>'analytics','api'=>'track'])
     *  getPermission(PermissionType::db, ['table'=>'users','columns'=>['id']])
     *
     * @param PermissionType|string $type Permission family: db|file|notification|module|network|codec
     * @param string|array|null $meta Type-specific selector (same shapes accepted as in hasPermission)
     * @return array<string,mixed>|null    Matched definition or null when no match.
     */
    public static function getPermission(
        PermissionType|string $type,
        string|array|null     $meta = null
    ): ?array;

    /**
     * Read the raw content of .internal/Signed if present.
     *
     * This can be used for host verification or debugging. Implementations
     * SHOULD avoid expensive I/O by caching or delegating to the host.
     *
     * @return string|null The signature payload or null if the file is not present.
     */
    public static function getSignature(): ?string;

    /**
     * Convenience helper: whether ALL manifest “required_permissions” are satisfied.
     *
     * Implementations typically delegate to PermissionService::listPermissions()
     * and return summary.requiredPending === 0.
     *
     * @return bool True if there are no outstanding required permissions.
     */
    public static function hasRequiredPermissions(): bool;

    /**
     * Get a persisted host setting for this plugin (runtime, database-backed).
     *
     * Values are read from the `plugin_settings` table (unique per plugin_id + key) and
     * decoded according to `PluginSettingValueType`:
     *
     *  - string  → returned as string (exact)
     *  - number  → cast to int if it fits, otherwise float
     *  - boolean → cast to bool
     *  - json    → `json_decode`(value, true) as associative array (on decode error, returns $default)
     *  - file    → string path/identifier (host-defined semantics)
     *  - blob    → raw string/binary payload
     *
     * Behavior:
     *  - If no row exists for ($pluginId, $key), return $default.
     *  - If type is `json` and decoding fails, return $default.
     *  - Implementations SHOULD use the installed plugin id from `getPluginId()` and must never
     *    throw for “missing id”; instead, return $default when id is not available yet.
     *
     * Examples:
     *  - getHost('webhook.secret')                 // "s3cr3t"
     *  - getHost('flags.enable_payments', false)   // true|false
     *  - getHost('limits', ['maxRequests'=>100])   // ['maxRequests'=>500] (decoded from JSON)
     *
     * NOTE: This is distinct from {@see getHostConfig()} which returns the plugin-declared
     * static config block. `getHost()` reads the host-managed, mutable settings persisted
     * in the database.
     *
     * @param string $key Setting key (exact match against `plugin_settings.key`).
     * @param mixed|null $default Fallback value when not set/decoding fails.
     * @return mixed                The decoded value or $default.
     */
    public static function getHost(string $key, mixed $default = null): mixed;

    /**
     * Return all persisted host settings for this plugin as a key => decodedValue map.
     *
     * Suggested behavior:
     *  - Decodes each row by `type` (same rules as getHost()).
     *  - On invalid JSON rows, silently skips the row (or returns `null`) depending on your preference.
     *
     * @return array<string,mixed>
     */
    public static function getAllHost(): array;

    /**
     * Resolve and (optionally) autoload the class for the plugin’s main entry or a named export.
     *
     * Behavior:
     *  - When $export is null, resolve the plugin’s “main” entry and return its class-string.
     *  - When $export is a slug/key, resolve it from the config “exports” map and return its class-string.
     *  - If the target cannot be resolved or autoloaded, return null (implementations MUST NOT throw).
     *
     * Expectations for implementations:
     *  - Read from the plugin config’s `main` and `exports` definitions (PHP files) and map them to an FQCN
     *    using PSR-4/project autoloading conventions.
     *  - Attempt to make the class autoloadable (e.g., rely on Composer autoload or require the file) before returning.
     *  - If multiple classes are present in a file, use host conventions to pick the intended one.
     *
     * Examples:
     *  - Config::load()                         // → "Vendor\\Plugin\\MainEntry" | null
     *  - Config::load('dashboard-widget')       // → "Vendor\\Plugin\\Exports\\DashboardWidget" | null
     *
     * @param string|null $export Export slug/key from `exports` (null selects `main`).
     * @return class-string|null   Fully-qualified class name if resolved, or null when not found.
     */
    public static function load(?string $export = null): ?string;
}
```

---
#### 2


` File: src/Core/ChecksModulePermission.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core;

 use Timeax\FortiPlugin\Contracts\ConfigInterface;
use Timeax\FortiPlugin\Models\PluginAuditLog;
use Timeax\FortiPlugin\Support\PluginContext;
use Timeax\FortiPlugin\Exceptions\PermissionDeniedException;
use Timeax\FortiPlugin\Exceptions\PluginContextException;
use Illuminate\Http\Request;

/**
 * Trait ChecksModulePermission
 *
 * Provides unified permission checking for plugin modules.
 * Requires $type and $target to be defined in using class.
 */
trait ChecksModulePermission
{
    /**
     * Cached config class FQCN for this module instance.
     * @var class-string|null
     */
    protected ?string $cachedConfigClass = null;

    /**
     * Checks if the plugin has permission for the current operation.
     *
     * @param string|string[]|null $permissions
     * @param string|null $type Override the module type (optional)
     * @param string|null $target Override the target (optional)
     * @param Request|null $request The original request (for exception context, optional)
     * @return void
     * @throws PermissionDeniedException|PluginContextException
     * @noinspection LaravelEloquentGuardedAttributeAssignmentInspection
     */
    protected function checkModulePermission(
        string|array|null $permissions = null,
        ?string           $type = null,
        ?string           $target = null,
        ?Request          $request = null
    ): void
    {
        $type = $type ?? ($this->type ?? null);
        $target = $target ?? ($this->target ?? null);

        if (!$type || !$target) {
            throw new PluginContextException("Module permission properties \$type and \$target must be set in the module class.");
        }

        // --- CACHE THE CONFIG CLASS PER INSTANCE ---
        $configClass = $this->getPluginConfigClass();

        $info = method_exists($configClass, 'getInfo') ? $configClass::getInfo() : [];
        $pluginName = $info['name'] ?? (method_exists($configClass, 'getName') ? $configClass::getName() : 'unknown_plugin');
        $pluginId = method_exists($configClass, 'getPluginId') ? $configClass::getPluginId() : null;
        $userId = auth()->id();

        // --- CHECK PERMISSION ---
        $allowed = $configClass::getPermission($type, $target, $permissions);

        // --- AUDIT LOGGING ---
        $context = [
            'permissions' => $permissions,
            'class' => static::class,
            'method' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? null,
            'request' => $request ? [
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'params' => $request->all(),
            ] : null,
        ];

        PluginAuditLog::create([
            'plugin_id' => $pluginId,
            'user_id' => $userId,
            'type' => $type,
            'action' => is_array($permissions) ? implode(',', $permissions) : ($permissions ?? 'access'),
            'resource' => $target,
            'context' => array_merge($context, [
                'granted' => $allowed,
                'plugin' => $pluginName,
            ]),
        ]);

        if (!$allowed) {
            throw new PermissionDeniedException(
                $type,
                $target,
                $permissions,
                $request
            );
        }
    }

    /**
     * Immediately deny permission for the given parameters.
     *
     * @param string $message
     * @param string|null $target
     * @param string|array|null $permissions
     * @param string|null $type
     * @return void
     * @throws PermissionDeniedException
     */
    protected function denyPermission(
        string            $message,
        string|null       $target,
        string|array|null $permissions,
        ?string           $type = null
    ): void
    {
        $type = $type ?? ($this->type ?? 'module');
        throw new PermissionDeniedException(
            $type,
            $target ?? $this->target,
            $permissions,
            request(),
            $message
        );
    }

    /**
     * @return class-string<ConfigInterface>
     */
    public function getPluginConfigClass(): string
    {
        if ($this->cachedConfigClass === null) {
            $configClass = PluginContext::getCurrentConfigClass();
            if (!$configClass || !method_exists($configClass, 'getPermission')) {
                throw new PluginContextException("Unable to resolve plugin config for permission checks.");
            }
            $this->cachedConfigClass = $configClass;
        }

        return $this->cachedConfigClass;
    }
}
```

---
#### 3


` File: src/Core/Exceptions/DuplicateSettingIdException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Exceptions;

use RuntimeException;

final class DuplicateSettingIdException extends RuntimeException
{
    public function __construct(string|int|float $id, string $where)
    {
        parent::__construct("Duplicate setting id '{$id}' detected {$where}.");
    }
}
```

---
#### 4


` File: src/Core/Exceptions/HostConfigException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Exceptions;

use RuntimeException;

class HostConfigException extends RuntimeException
{
}
```

---
#### 5


` File: src/Core/Exceptions/RouteCompileException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Exceptions;

use RuntimeException;

class RouteCompileException extends RuntimeException
{

}
```

---
#### 6


` File: src/Core/Install/JsonRouteCompiler.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Install;

use Illuminate\Support\Str;
use JsonException;
use Timeax\FortiPlugin\Core\Exceptions\RouteCompileException;
use Timeax\FortiPlugin\Support\MiddlewareNormalizer;

/**
 * Compile FortiPlugin route JSON.
 *
 * Legacy (compat):
 *   compileFiles() → array<int, { source, php, routeIds, slug }>
 *
 * Registry-first (new):
 *   compileFileToRegistry() → { entries: list<{ route, id, content, file }>, routeIds: list<string> }
 *   compileDataToRegistry() → same
 */
final class JsonRouteCompiler
{
    /**
     * @param string[] $files
     * @return array<int, array{source:string, php:string, routeIds:string[], slug:string}>
     * @throws JsonException
     */
    public function compileFiles(array $files): array
    {
        $out = [];
        foreach ($files as $file) {
            $out[] = $this->compileFile($file);
        }
        return $out;
    }

    /**
     * @return array{source:string, php:string, routeIds:string[], slug:string}
     * @throws JsonException
     */
    public function compileFile(string $file): array
    {
        $json = @file_get_contents($file);
        if ($json === false) {
            throw new RouteCompileException("Cannot read route json: $file");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RouteCompileException("Invalid JSON in $file");
        }

        return $this->compileData($data, $file);
    }

    /**
     * @param array $data
     * @param string|null $source
     * @return array{source:string, php:string, routeIds:string[], slug:string}
     */
    public function compileData(array $data, ?string $source = null): array
    {
        $em = new PhpEmitter();
        $routeIds = [];

        $group  = (array)($data['group'] ?? []);
        $routes = $data['routes'] ?? null;
        if (!is_array($routes) || $routes === []) {
            throw new RouteCompileException("Missing or empty 'routes' array" . ($source ? " in $source" : ''));
        }

        // Optional comment header (no <?php tag; RouteWriter/Materializer will wrap if needed)
        $em->line("/** FortiPlugin compiled routes " . ($source ? basename($source) : '') . " **/");

        // File-level group wrapper
        $this->emitGroupOpen($em, $group);

        foreach (array_values($routes) as $i => $node) {
            $this->emitNode($em, (array)$node, $group, $routeIds, "/routes[$i]");
        }

        // Close file-level group
        $this->emitGroupClose($em, $group);

        return [
            'source'   => $source ?? '(inline)',
            'php'      => $em->code(),
            'routeIds' => array_values(array_unique($routeIds)),
            'slug'     => $source ? $this->slugFromPath($source) : 'inline',
        ];
    }

    /* ───────────────────── Registry-first API ───────────────────── */

    /**
     * Build one registry entry per terminal route id (resources become a single entry with route:string[]).
     * @return array{entries: list<array{route:string|array, id:string, content:string, file:string}>, routeIds:list<string>}
     * @throws JsonException
     */
    public function compileFileToRegistry(string $file): array
    {
        $json = @file_get_contents($file);
        if ($json === false) {
            throw new RouteCompileException("Cannot read route json: $file");
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RouteCompileException("Invalid JSON in $file");
        }
        return $this->compileDataToRegistry($data, $file);
    }

    /**
     * @return array{entries: list<array{route:string|array, id:string, content:string, file:string}>, routeIds:list<string>}
     */
    public function compileDataToRegistry(array $data, ?string $source = null): array
    {
        $entries  = [];
        $routeIds = [];

        $group  = (array)($data['group'] ?? []);
        $routes = $data['routes'] ?? null;
        if (!is_array($routes) || $routes === []) {
            throw new RouteCompileException("Missing or empty 'routes' array" . ($source ? " in $source" : ''));
        }

        $collect = function (array $node, array $inheritedGroup, string $jsonPath) use (&$entries, &$routeIds, &$collect, $source): void {
            $type = $node['type'] ?? null;
            if (!is_string($type)) {
                throw new RouteCompileException("Route node missing 'type' at $jsonPath");
            }

            if ($type === 'group') {
                $merged = $this->mergeGroups($inheritedGroup, (array)($node['group'] ?? []));
                foreach ((array)($node['routes'] ?? []) as $i => $child) {
                    $collect((array)$child, $merged, "$jsonPath/routes[$i]");
                }
                return;
            }

            if (!isset($node['id'], $node['desc']) || !is_string($node['id']) || !is_string($node['desc'])) {
                throw new RouteCompileException("Route node must include string 'id' and 'desc' at $jsonPath");
            }

            $id        = $node['id'];
            $routeIds[] = $id;
            $guard     = $node['guard'] ?? null;

            $contentLines = [];
            $routesForId  = [];

            $emitOne = static function (string $codeLine) use (&$contentLines): void {
                $contentLines[] = $codeLine;
            };

            switch ($type) {
                case 'http': {
                    $method = $node['method'] ?? null;
                    $path   = $node['path'] ?? null;
                    $action = $node['action'] ?? null;
                    if ($path === null || $action === null || $method === null) {
                        throw new RouteCompileException("HTTP route requires 'method','path','action' at $jsonPath");
                    }
                    [$chain,$mw,$name,$where,$domain,$prefix] = $this->commonProps($node, $inheritedGroup, $guard);
                    $emitOne($chain . '->' . $this->methodCallFor($method, $path, $action) . $this->tail($name,$mw,$where,$domain,$prefix) . ';');
                    $routesForId = $path;
                    break;
                }
                case 'redirect': {
                    $path = $node['path'] ?? null;
                    $to   = $node['to'] ?? null;
                    $status = (int)($node['status'] ?? 302);
                    if (!$path || !$to) throw new RouteCompileException("Redirect requires 'path' and 'to' at $jsonPath");
                    [$chain,$mw,$name,, $domain,$prefix] = $this->commonProps($node,$inheritedGroup,$guard);
                    $emitOne($chain . '->redirect(' . $this->s($path) . ', ' . $this->s($to) . ', ' . $status . ')' . $this->tail($name,$mw,null,$domain,$prefix) . ';');
                    $routesForId = $path;
                    break;
                }
                case 'view': {
                    $path = $node['path'] ?? null;
                    $view = $node['view'] ?? null;
                    $data = (array)($node['data'] ?? []);
                    if (!$path || !$view) throw new RouteCompileException("View requires 'path' and 'view' at $jsonPath");
                    [$chain,$mw,$name,, $domain,$prefix] = $this->commonProps($node,$inheritedGroup,$guard);
                    $emitOne($chain . '->view(' . $this->s($path) . ', ' . $this->s($view) . ', ' . var_export($data, true) . ')' . $this->tail($name,$mw,null,$domain,$prefix) . ';');
                    $routesForId = $path;
                    break;
                }
                case 'fallback': {
                    [$chain,$mw,$name] = $this->commonProps($node,$inheritedGroup,$guard);
                    $action = $node['action'] ?? null;
                    if (!$action) throw new RouteCompileException("Fallback requires 'action' at $jsonPath");
                    $emitOne($chain . '->fallback(' . $this->actionExpr($action) . ')' . $this->tail($name,$mw,null,null,null) . ';');
                    $routesForId = '__fallback__';
                    break;
                }
                case 'resource':
                case 'apiResource': {
                    $resource   = $node['name'] ?? null;
                    $controller = $node['controller'] ?? null;
                    if (!$resource || !$controller) {
                        throw new RouteCompileException("Resource requires 'name' and 'controller' at $jsonPath");
                    }
                    [$chain,$mw,$baseName,$where,$domain,$prefix] = $this->commonProps($node,$inheritedGroup,$guard);

                    $paths = [];
                    if (!empty($where)) {
                        $isApi = ($type === 'apiResource');
                        $all = $isApi
                            ? ['index','store','show','update','destroy']
                            : ['index','create','store','show','edit','update','destroy'];

                        $only   = isset($node['only'])   ? array_values((array)$node['only'])   : null;
                        $except = isset($node['except']) ? array_values((array)$node['except']) : null;
                        $actions = $all;
                        if ($only)   $actions = array_values(array_intersect($actions, $only));
                        if ($except) $actions = array_values(array_diff($actions, $except));

                        $paramMap = (array)($node['parameters'] ?? []);
                        $param    = $paramMap[$resource] ?? Str::singular($resource);
                        $names    = (array)($node['names'] ?? []);
                        $base     = $baseName ?: $resource;

                        foreach ($actions as $action) {
                            $path  = $this->resourcePath($resource, $param, $action);
                            $verb  = $this->resourceVerb($action);
                            $act   = $controller . '@' . $this->resourceControllerMethod($action);
                            $rname = $names[$action] ?? ($base ? "$base.$action" : null);
                            $emitOne($chain . '->' . $this->methodCallFor($verb, $path, $act) . $this->tail($rname,$mw,(array)$where,$domain,$prefix) . ';');
                            $paths[] = $path;
                        }
                    } else {
                        $call = $type === 'apiResource'
                            ? "apiResource(" . $this->s($resource) . ', ' . $this->s($controller) . ')'
                            : "resource(" . $this->s($resource) . ', ' . $this->s($controller) . ')';

                        $line = $chain . '->' . $call;
                        if (!empty($node['only']))        $line .= "->only(" . $this->exportArraySimple($node['only']) . ")";
                        if (!empty($node['except']))      $line .= "->except(" . $this->exportArraySimple($node['except']) . ")";
                        if (!empty($node['parameters']))  $line .= "->parameters(" . var_export((array)$node['parameters'], true) . ")";
                        if (!empty($node['names']))       $line .= "->names(" . var_export((array)$node['names'], true) . ")";
                        if (!empty($node['shallow']))     $line .= "->shallow()";
                        foreach ($this->tailParts($baseName,$mw,null,$domain,$prefix) as $part) {
                            $line .= $part;
                        }
                        $emitOne($line . ';');

                        $isApi = ($type === 'apiResource');
                        $all   = $isApi
                            ? ['index','store','show','update','destroy']
                            : ['index','create','store','show','edit','update','destroy'];
                        $param = Str::singular($resource);
                        foreach ($all as $action) {
                            $paths[] = $this->resourcePath($resource, $param, $action);
                        }
                    }

                    $routesForId = $paths;
                    break;
                }

                default:
                    throw new RouteCompileException("Unknown route type '$type' at $jsonPath");
            }

            $php = [];
            $php[] = "<?php";
            $php[] = "declare(strict_types=1);";
            $php[] = "/** compiled unit for route id: $id" . ($source ? " (source: ".basename($source).")" : "") . " */";
            $php[] = "use Illuminate\\Support\\Facades\\Route;";
            $php[] = "";
            foreach ($contentLines as $ln) $php[] = $ln;
            $php[] = "";

            $entries[] = [
                'route'   => $routesForId,
                'id'      => $id,
                'content' => implode("\n", $php),
                'file'    => $this->fileNameForId($id),
            ];
        };

        foreach ($routes as $i => $node) {
            $collect((array)$node, $group, "/routes[$i]");
        }

        return [
            'entries'  => $entries,
            'routeIds' => array_values(array_unique($routeIds)),
        ];
    }

    private function fileNameForId(string $id): string
    {
        $name = (string) Str::of($id)->replaceMatches('/[^A-Za-z0-9_.-]+/', '_')->trim('_')->lower();
        if ($name === '') $name = 'route';
        if (!str_ends_with($name, '.php')) $name .= '.php';
        return $name;
    }

    /* ========================= EMIT HELPERS ========================= */

    private function emitNode(PhpEmitter $em, array $node, array $inheritedGroup, array &$routeIds, string $jsonPath): void
    {
        $type = $node['type'] ?? null;
        if (!is_string($type)) {
            throw new RouteCompileException("Route node missing 'type' at $jsonPath");
        }

        if (!isset($node['id'], $node['desc']) || !is_string($node['id']) || !is_string($node['desc'])) {
            throw new RouteCompileException("Route node must include string 'id' and 'desc' at $jsonPath");
        }
        $routeIds[] = $node['id'];

        $routeGuard = $node['guard'] ?? null;

        switch ($type) {
            case 'group':
                $this->emitNestedGroup($em, $node, $inheritedGroup, $routeIds, $jsonPath);
                break;

            case 'http':
                $this->emitHttp($em, $node, $inheritedGroup, $routeGuard);
                break;

            case 'resource':
            case 'apiResource':
                $this->emitResource($em, $node, $inheritedGroup, $routeGuard);
                break;

            case 'redirect':
                $this->emitRedirect($em, $node, $inheritedGroup, $routeGuard);
                break;

            case 'view':
                $this->emitView($em, $node, $inheritedGroup, $routeGuard);
                break;

            case 'fallback':
                $this->emitFallback($em, $node, $inheritedGroup, $routeGuard);
                break;

            default:
                throw new RouteCompileException("Unknown route type '$type' at $jsonPath");
        }
    }

    private function emitGroupOpen(PhpEmitter $em, array $group): void
    {
        if ($group === []) return;
        $em->open($this->startChain($group) . '->group(function () {');
    }

    private function emitGroupClose(PhpEmitter $em, array $group): void
    {
        if ($group === []) return;
        $em->close('});');
    }

    private function emitNestedGroup(PhpEmitter $em, array $node, array $inheritedGroup, array &$routeIds, string $jsonPath): void
    {
        $group  = (array)($node['group'] ?? []);
        $merged = $this->mergeGroups($inheritedGroup, $group);

        $em->open($this->startChain($merged) . '->group(function () {');

        foreach (array_values((array)($node['routes'] ?? [])) as $i => $child) {
            $this->emitNode($em, (array)$child, $merged, $routeIds, "$jsonPath/routes[$i]");
        }

        $em->close('});');
    }

    private function emitHttp(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $method = $node['method'] ?? null;
        $path   = $node['path'] ?? null;
        $action = $node['action'] ?? null;

        if ($path === null || $action === null || $method === null) {
            throw new RouteCompileException("HTTP route requires 'method','path','action'");
        }

        [$chain, $mw, $name, $where, $domain, $prefix] = $this->commonProps($node, $group, $routeGuard);
        $methodCall = $this->methodCallFor($method, $path, $action);
        $suffix     = $this->tail($name, $mw, $where, $domain, $prefix);

        $em->line($chain . '->' . $methodCall . $suffix . ';');
    }

    private function emitResource(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $type       = $node['type'];
        $resource   = $node['name'] ?? null;
        $controller = $node['controller'] ?? null;
        if (!$resource || !$controller) {
            throw new RouteCompileException("Resource route requires 'name' and 'controller'");
        }

        [$chain, $mw, $baseName, $where, $domain, $prefix] = $this->commonProps($node, $group, $routeGuard);

        if (!empty($where)) {
            $this->emitResourceExpanded($em, $type, $resource, $controller, $chain, $mw, $baseName, (array)$where, $domain, $prefix, $node);
            return;
        }

        $call = $type === 'apiResource'
            ? "apiResource(" . $this->s($resource) . ', ' . $this->s($controller) . ')'
            : "resource(" . $this->s($resource) . ', ' . $this->s($controller) . ')';

        $em->open($chain . '->' . $call);
        if (!empty($node['only']))       $em->line("->only(" . $this->exportArraySimple($node['only']) . ")");
        if (!empty($node['except']))     $em->line("->except(" . $this->exportArraySimple($node['except']) . ")");
        if (!empty($node['parameters'])) $em->line("->parameters(" . var_export((array)$node['parameters'], true) . ")");
        if (!empty($node['names']))      $em->line("->names(" . var_export((array)$node['names'], true) . ")");
        if (!empty($node['shallow']))    $em->line("->shallow()");
        foreach ($this->tailParts($baseName, $mw, null, $domain, $prefix) as $part) {
            $em->line($part);
        }
        $em->close(';');
    }

    private function emitResourceExpanded(
        PhpEmitter $em,
        string     $type,
        string     $resource,
        string     $controller,
        string     $chain,
        array      $mw,
        ?string    $baseName,
        array      $where,
        ?string    $domain,
        ?string    $prefix,
        array      $node
    ): void {
        $isApi = ($type === 'apiResource');
        $all   = $isApi
            ? ['index','store','show','update','destroy']
            : ['index','create','store','show','edit','update','destroy'];

        $only   = isset($node['only'])   ? array_values((array)$node['only'])   : null;
        $except = isset($node['except']) ? array_values((array)$node['except']) : null;
        $actions = $all;
        if ($only)   $actions = array_values(array_intersect($actions, $only));
        if ($except) $actions = array_values(array_diff($actions, $except));

        $paramMap = (array)($node['parameters'] ?? []);
        $param    = $paramMap[$resource] ?? Str::singular($resource);
        $names    = (array)($node['names'] ?? []);
        $base     = $baseName ?: $resource;

        foreach ($actions as $action) {
            $path  = $this->resourcePath($resource, $param, $action);
            $verb  = $this->resourceVerb($action);
            $act   = $controller . '@' . $this->resourceControllerMethod($action);
            $rname = $names[$action] ?? ($base ? "$base.$action" : null);
            $em->line($chain . '->' . $this->methodCallFor($verb, $path, $act) . $this->tail($rname,$mw,$where,$domain,$prefix) . ';');
        }
    }

    /** Resolve URI for a given resource action */
    private function resourcePath(string $resource, string $param, string $action): string
    {
        return match ($action) {
            'create' => "/$resource/create",
            'show', 'destroy', 'update' => "/$resource/{{$param}}",
            'edit' => "/$resource/{{$param}}/edit",
            default => "/$resource",
        };
    }

    /** Resolve HTTP verb(s) for a given resource action */
    private function resourceVerb(string $action): string|array
    {
        return match ($action) {
            'store'   => 'POST',
            'update'  => ['PUT', 'PATCH'],
            'destroy' => 'DELETE',
            default   => 'GET',
        };
    }

    /** Resolve controller method name for a given resource action */
    private function resourceControllerMethod(string $action): string
    {
        return $action;
    }

    private function emitRedirect(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $path   = $node['path'] ?? null;
        $to     = $node['to'] ?? null;
        $status = $node['status'] ?? 302;
        if (!$path || !$to) {
            throw new RouteCompileException("Redirect route requires 'path' and 'to'");
        }
        [$chain, $mw, $name, , $domain, $prefix] = $this->commonProps($node, $group, $routeGuard);
        $suffix = $this->tail($name, $mw, null, $domain, $prefix);
        $em->line($chain . '->redirect(' . $this->s($path) . ', ' . $this->s($to) . ', ' . (int)$status . ')' . $suffix . ';');
    }

    private function emitView(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $path = $node['path'] ?? null;
        $view = $node['view'] ?? null;
        $data = $node['data'] ?? [];
        if (!$path || !$view) {
            throw new RouteCompileException("View route requires 'path' and 'view'");
        }
        [$chain, $mw, $name, , $domain, $prefix] = $this->commonProps($node, $group, $routeGuard);
        $suffix = $this->tail($name, $mw, null, $domain, $prefix);
        $em->line($chain . '->view(' . $this->s($path) . ', ' . $this->s($view) . ', ' . var_export((array)$data, true) . ')' . $suffix . ';');
    }

    private function emitFallback(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        [$chain, $mw, $name] = $this->commonProps($node, $group, $routeGuard);
        $action = $node['action'] ?? null;
        if (!$action) {
            throw new RouteCompileException("Fallback route requires 'action'");
        }
        $suffix = $this->tail($name, $mw, null, null, null);
        $em->line($chain . '->fallback(' . $this->actionExpr($action) . ')' . $suffix . ';');
    }

    /* ========================= UTILITIES ========================= */

    private function mergeGroups(array $a, array $b): array
    {
        $out = $a;
        foreach (['prefix', 'domain', 'namePrefix', 'guard'] as $k) {
            if (array_key_exists($k, $b)) $out[$k] = $b[$k];
        }
        $mwA = (array)($a['middleware'] ?? []);
        $mwB = (array)($b['middleware'] ?? []);
        if ($mwA || $mwB) $out['middleware'] = array_values(array_merge($mwA, $mwB));
        return $out;
    }

    private function getChain(array $group): string
    {
        $chain = 'Route';
        if (!empty($group['domain'])) $chain .= '->domain(' . $this->s($group['domain']) . ')';
        if (!empty($group['prefix'])) $chain .= '->prefix(' . $this->s($group['prefix']) . ')';
        return $chain;
    }

    private function startChain(array $group): string
    {
        $chain = $this->getChain($group);

        $mw = MiddlewareNormalizer::normalize($group['guard'] ?? null, null, (array)($group['middleware'] ?? []));
        if ($mw) $chain .= '->middleware(' . $this->exportArraySimple($mw) . ')';

        if (!empty($group['namePrefix'])) {
            $np = (string)$group['namePrefix'];
            if ($np !== '' && !str_ends_with($np, '.')) $np .= '.';
            $chain .= '->name(' . $this->s($np) . ')';
        }

        return $chain;
    }

    /**
     * @return array{0:string,1:array,2:?string,3:?array,4:?string,5:?string}
     */
    private function commonProps(array $node, array $group, ?string $routeGuard): array
    {
        $chain = $this->getChain($group);

        $mw = MiddlewareNormalizer::normalize($group['guard'] ?? null, $routeGuard, (array)($node['middleware'] ?? []));

        $name   = $node['name'] ?? null;
        $where  = $node['where'] ?? null;
        $domain = $node['domain'] ?? null;
        $prefix = $node['prefix'] ?? null;

        return [$chain, $mw, $name, $where, $domain, $prefix];
    }

    private function tail(?string $name, array $mw, ?array $where, ?string $domain, ?string $prefix): string
    {
        $parts = $this->tailParts($name, $mw, $where, $domain, $prefix);
        return $parts ? implode('', $parts) : '';
    }

    /** @return string[] */
    private function tailParts(?string $name, array $mw, ?array $where, ?string $domain, ?string $prefix): array
    {
        $parts = [];
        if ($mw)    $parts[] = '->middleware(' . $this->exportArraySimple($mw) . ')';
        if ($name)  $parts[] = '->name(' . $this->s($name) . ')';
        if ($where) $parts[] = '->where(' . var_export($where, true) . ')';
        if ($domain)$parts[] = '->domain(' . $this->s($domain) . ')';
        if ($prefix)$parts[] = '->prefix(' . $this->s($prefix) . ')';
        return $parts;
    }

    private function methodCallFor(string|array $method, string $path, string|array $action): string
    {
        if (is_array($method)) {
            $verbs = array_values(array_map('strtoupper', array_map('strval', $method)));
            return 'match(' . $this->exportArraySimple($verbs) . ', ' . $this->s($path) . ', ' . $this->actionExpr($action) . ')';
        }
        $verb = strtoupper($method);
        if ($verb === 'ANY') {
            return 'any(' . $this->s($path) . ', ' . $this->actionExpr($action) . ')';
        }
        $lower = strtolower($verb);
        return "$lower(" . $this->s($path) . ', ' . $this->actionExpr($action) . ')';
    }

    private function actionExpr(string|array $action): string
    {
        if (is_string($action)) {
            if (str_contains($action, '@')) {
                [$class, $method] = explode('@', $action, 2);
                return '[' . $this->s($class) . ', ' . $this->s($method) . ']';
            }
            return $this->s($action) . '::class';
        }

        $class = $action['class'] ?? null;
        if (!$class) {
            throw new RouteCompileException("ControllerRef requires 'class'");
        }
        $method = $action['method'] ?? null;

        return $method
            ? '[' . $this->s($class) . '::class, ' . $this->s($method) . ']'
            : $this->s($class) . '::class';
    }

    private function exportArraySimple(array $arr): string
    {
        return '[' . implode(', ', array_map([$this, 's'], array_values($arr))) . ']';
    }

    private function s(string $value): string
    {
        return var_export($value, true);
    }

    private function slugFromPath(string $path): string
    {
        $base = pathinfo($path, PATHINFO_FILENAME);
        return (string)Str::of($base)->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_')->lower();
    }
}
```

---
#### 7


` File: src/Core/PluginPolicy.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection SpellCheckingInspection */
/** @noinspection PhpUnused */
/** @noinspection ClassConstantCanBeUsedInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Core;

/**
 * PluginPolicy
 *
 * Effective scanning policy for FortiPlugin:
 *   1) Start from Forti defaults (this class).
 *   2) Add host overlays from config (validator.*).
 *   3) Apply host "overrides" to ALLOW specific items otherwise blocked.
 *
 * Notes
 * - "Forbidden" => hard block (must not be used).
 * - "Unsupported" => flagged/risky (can be treated as warnings by scanner).
 * - Overrides are *surgical* ALLOWs. Prefer granting permissions via review.
 */
class PluginPolicy
{
    /* ---------------------------------------------------------------------
     |  Forti defaults (base deny lists)
     |---------------------------------------------------------------------*/

    /** @var array<int,string> */
    protected array $fileIoMethods = [
        // File & Directory Read
        'fopen', 'fread', 'file_get_contents', 'file', 'fgets', 'fgetc', 'fgetcsv',
        'readfile', 'stream_get_contents', 'stream_get_line', 'file_exists',
        'is_readable', 'stat', 'lstat', 'scandir', 'opendir', 'readdir',
        'parse_ini_file', 'parse_ini_string', 'glob', 'realpath',

        // File & Directory Write
        'fwrite', 'file_put_contents', 'fputcsv', 'fflush', 'ftruncate', 'flock',
        'rename', 'touch', 'chmod', 'chown', 'chgrp', 'move_uploaded_file',
        'stream_set_write_buffer', 'tempnam', 'tmpfile', 'mkdir', 'rmdir',

        // Copy/Move/Delete
        'copy', 'unlink', 'symlink', 'link',
    ];

    /** @var array<int,string> */
    protected array $streamFunctions = [
        'stream_context_create', 'stream_context_set_option', 'stream_context_get_options',
        'stream_context_set_params', 'stream_copy_to_stream', 'stream_filter_append',
        'stream_filter_prepend', 'stream_filter_remove', 'stream_get_contents',
        'stream_get_line', 'stream_get_meta_data', 'stream_get_transports',
        'stream_get_wrappers', 'stream_is_local', 'stream_register_wrapper',
        'stream_resolve_include_path', 'stream_select', 'stream_set_blocking',
        'stream_set_chunk_size', 'stream_set_read_buffer', 'stream_set_timeout',
        'stream_socket_accept', 'stream_socket_client', 'stream_socket_enable_crypto',
        'stream_socket_get_name', 'stream_socket_pair', 'stream_socket_recvfrom',
        'stream_socket_sendto', 'stream_socket_server', 'stream_wrapper_register',
        'stream_wrapper_restore', 'stream_wrapper_unregister',
    ];

    /** @var array<int,string> */
    protected array $curlMethods = [
        'curl_close', 'curl_copy_handle', 'curl_errno', 'curl_error', 'curl_escape', 'curl_exec', 'curl_getinfo', 'curl_init',
        'curl_multi_add_handle', 'curl_multi_close', 'curl_multi_errno', 'curl_multi_exec', 'curl_multi_getcontent',
        'curl_multi_info_read', 'curl_multi_init', 'curl_multi_remove_handle', 'curl_multi_select', 'curl_multi_setopt',
        'curl_multi_strerror', 'curl_pause', 'curl_reset', 'curl_setopt', 'curl_setopt_array', 'curl_share_close',
        'curl_share_errno', 'curl_share_init', 'curl_share_init_persistent', 'curl_share_setopt', 'curl_share_strerror',
        'curl_unescape', 'curl_upkeep', 'curl_version',
    ];

    /** @var array<int,string> */
    protected array $forbiddenNamespaceList = [
        'Illuminate\\Routing\\',           // Route
        'Illuminate\\Filesystem\\',        // File
        'Illuminate\\Support\\Facades\\File',
        'Illuminate\\Support\\Facades\\Storage',
        'Illuminate\\Contracts\\Filesystem\\',
        'Illuminate\\Http\\UploadedFile',
        'Symfony\\Component\\HttpFoundation\\File\\', // incl. FileBag etc.
        'Illuminate\\Support\\Facades\\Route',
        'Illuminate\\Support\\Facades\\Artisan',      // Command execution
        'Illuminate\\Support\\Facades\\Schema',       // Schema mutations
        'Illuminate\\Support\\Facades\\DB',           // DB facade directly
        'Illuminate\\Database\\',                     // Direct DB access
    ];

    /** @var array{
     *    functions:array<int,string>,
     *    reflectionPrefix:string,
     *    magicMethods:array<int,string>,
     *    wrappers:array<int,string>
     * }
     */
    protected array $alwaysForbidden = [
        'functions' => [
            'eval', 'assert', 'exec', 'shell_exec', 'passthru', 'system',
            'proc_open', 'popen', 'dl', 'create_function', 'unserialize',
            'register_shutdown_function', 'set_error_handler', 'set_exception_handler', 'register_tick_function',
            'putenv', 'ini_set', 'ini_restore',
        ],
        'reflectionPrefix' => 'Reflection',
        'magicMethods' => ['__call', '__callStatic', '__invoke', '__autoload'],
        'wrappers' => ['php://', 'data://', 'glob://', 'zip://', 'phar://'],
    ];

    /** @var array<int,string> */
    protected array $callbackFunctions = [
        'array_map', 'array_filter', 'array_walk', 'array_walk_recursive', 'usort', 'uasort', 'uksort', 'array_reduce',
        'register_shutdown_function', 'set_error_handler', 'set_exception_handler', 'register_tick_function',
    ];

    /** @var array<int,string> */
    protected array $envManipulationFunctions = [
        // Environment
        'putenv', 'getenv', 'apache_setenv', 'apache_getenv',
        // INI
        'ini_set', 'ini_alter', 'ini_restore', 'ini_get', 'ini_get_all', 'ini_parse_quantity',
        // Process / system
        'proc_open', 'proc_close', 'proc_terminate', 'proc_get_status', 'proc_nice',
        // CLI/Server process manipulation
        'pcntl_exec', 'pcntl_fork', 'pcntl_wait', 'pcntl_waitpid', 'pcntl_signal', 'pcntl_alarm',
        'pcntl_wexitstatus', 'pcntl_wifexited', 'pcntl_wifsignaled', 'pcntl_wifstopped',
        'pcntl_signal_dispatch', 'pcntl_get_last_error', 'pcntl_errno',
        // Limits / shutdown
        'set_time_limit', 'ignore_user_abort', 'fastcgi_finish_request',
    ];

    /** @var array<int,string> */
    protected array $diContainerMethods = [
        // Laravel/Illuminate
        'bind', 'singleton', 'instance', 'scoped', 'share', 'extend', 'when', 'tag', 'alias',
        'resolving', 'afterResolving', 'make',
        // Symfony/PSR
        'register', 'set', 'addArgument', 'addMethodCall', 'setShared', 'addTag',
        // Pimple / Interop
        'offsetSet', 'offsetGet', 'addService', 'addProvider', 'delegate', 'factory',
        // Zend / others
        'configure', 'define', 'protect',
        // CakePHP
        'load', 'unload',
        // Custom markers
        'service', 'handler', 'controller',
    ];

    /** @var array<int,string> */
    protected array $obfuscators = [
        // Encoders/decoders
        'base64_decode', 'base64_encode', 'gzinflate', 'gzdeflate', 'gzencode', 'gzdecode', 'gzcompress', 'gzuncompress',
        'str_rot13', 'rot13', 'bin2hex', 'hex2bin', 'chr', 'ord', 'pack', 'unpack',
        'urlencode', 'urldecode', 'rawurlencode', 'rawurldecode', 'convert_uuencode', 'convert_uudecode',
        'json_encode', 'json_decode', 'serialize', 'unserialize',
        // Misc
        'strrev', 'md5', 'sha1', 'sha256', 'hash', 'hash_hmac', 'openssl_encrypt', 'openssl_decrypt',
        'mcrypt_encrypt', 'mcrypt_decrypt', // legacy
        // Compression/encoding helpers
        'bzcompress', 'bzdecompress', 'zlib_encode', 'zlib_decode', 'deflate_add', 'inflate_add', 'inflate_init', 'deflate_init',
        // Transformations often chained
        'addslashes', 'stripslashes', 'quotemeta', 'strip_tags',
    ];

    /* ---------------------------------------------------------------------
     |  Host overlay & overrides (config-driven)
     |---------------------------------------------------------------------*/

    /** Raw host config (as passed in) */
    protected array $config = [];

    /** Additive risk sets from host (stricter) */
    protected array $unsupportedFunctions = []; // tokens + dangerous + env + obfuscators
    protected array $forbiddenNamespaces = []; // base + host
    protected array $forbiddenPackages = []; // host

    /**
     * Class method allowlist:
     * If a class is present, ONLY the listed methods are allowed; all others are blocked.
     * Merged with host 'blocklist' and then expanded via overrides['classes'].
     */
    protected mixed $blocklist;

    /** Overrides that ALLOW specific items otherwise blocked */
    protected array $overrides = [
        'functions' => [],
        'tokens' => [],
        'dangerous' => [],
        'namespaces' => [],
        'packages' => [],
        'wrappers' => [],
        'magic_methods' => [],
        'classes' => [], // ['ClassName' => ['method1','method2']]
    ];

    // Fast lookup sets for overrides
    protected array $allowFunctionSet = [];
    protected array $allowTokenSet = [];
    protected array $allowDangerSet = [];

    /* ---------------------------------------------------------------------
     |  Construction / normalization
     |---------------------------------------------------------------------*/

    public function __construct(array $config = [])
    {
        // Include stream functions as part of file I/O for stricter default posture
        $this->fileIoMethods = array_values(array_unique(array_merge($this->fileIoMethods, $this->streamFunctions)));

        // Store config reference
        $this->config = $config;

        // Compute "unsupported" = tokens (host) + dangerous (host) + env + obfuscators
        $this->unsupportedFunctions = array_values(array_unique(array_merge(
            $config['dangerous_functions'] ?? [],
            $config['tokens'] ?? [],
            $this->envManipulationFunctions,
            $this->obfuscators
        )));

        // Forbidden namespaces/packages (stricter by host)
        $this->forbiddenNamespaces = array_values(array_unique(array_merge(
            $config['forbidden_namespaces'] ?? [],
            $this->forbiddenNamespaceList
        )));
        $this->forbiddenPackages = array_values(array_unique($config['forbidden_packages'] ?? []));

        // Method allowlist per class (host can define)
        $this->blocklist = $config['allowed_class_methods'] ?? [];

        // Overrides (ALLOWS)
        $this->overrides = array_replace_recursive($this->overrides, $config['overrides'] ?? []);

        // Create lowercase lookup sets for function-name comparisons
        $fn = array_map('strtolower', $this->overrides['functions'] ?? []);
        $tokens = array_map('strtolower', $this->overrides['tokens'] ?? []);
        $danger = array_map('strtolower', $this->overrides['dangerous'] ?? []);

        $this->allowFunctionSet = array_fill_keys($fn, true);
        $this->allowTokenSet = array_fill_keys($tokens, true);
        $this->allowDangerSet = array_fill_keys($danger, true);

        // Subtract overrides from forbidden namespaces/packages
        if (!empty($this->overrides['namespaces'])) {
            $this->forbiddenNamespaces = array_values(array_diff(
                $this->forbiddenNamespaces,
                $this->overrides['namespaces']
            ));
        }
        if (!empty($this->overrides['packages'])) {
            $lowerForbidden = array_map('strtolower', $this->forbiddenPackages);
            $lowerAllowed = array_map('strtolower', $this->overrides['packages']);
            $this->forbiddenPackages = array_values(array_diff($lowerForbidden, $lowerAllowed));
        }

        // Expand class method allowlist using overrides['classes'] (adds allowed methods)
        foreach (($this->overrides['classes'] ?? []) as $class => $methods) {
            $methods = array_values(array_unique($methods));
            if (!isset($this->blocklist[$class])) {
                $this->blocklist[$class] = [];
            }
            $this->blocklist[$class] = array_values(array_unique(array_merge($this->blocklist[$class], $methods)));
        }
    }

    /* ---------------------------------------------------------------------
     |  Checks — Forbidden
     |---------------------------------------------------------------------*/

    public function isForbiddenNamespace(string $namespace): bool
    {
        foreach ($this->forbiddenNamespaces as $forbidden) {
            if (stripos($namespace, $forbidden) === 0) {
                return true;
            }
        }
        return false;
    }

    public function isForbiddenPackage(string $package): bool
    {
        $needle = strtolower($package);
        return in_array($needle, $this->forbiddenPackages, true);
    }

    /**
     * Forbidden functions: Forti defaults + curl + fileIO + alwaysForbidden,
     * then subtract *allowed* overrides.
     */
    public function isForbiddenFunction($name): bool
    {
        $n = strtolower((string)$name);

        // If specifically allowed, it's NOT forbidden
        if (isset($this->allowFunctionSet[$n]) || isset($this->allowTokenSet[$n]) || isset($this->allowDangerSet[$n])) {
            return false;
        }

        return in_array($n, $this->getForbiddenFunctions(), true);
    }

    /**
     * Methods blocked by class method-allowlist semantics.
     * If a class is present in blocklist, any method NOT explicitly listed is blocked.
     */
    public function isBlockedMethod($class, $method): bool
    {
        $class = $this->resolveClass((string)$class);
        if (!isset($this->blocklist[$class])) {
            // No allowlist for this class → not blocked by allowlist semantics
            return false;
        }
        return !in_array((string)$method, $this->blocklist[$class], true);
    }

    public function isForbiddenReflection($class): bool
    {
        // Null / non-string? we can't determine — treat as not-forbidden here.
        if ($class === null) {
            return false;
        }

        // Allow Stringable objects
        if (is_object($class) && method_exists($class, '__toString')) {
            $class = (string)$class;
        }

        if (!is_string($class) || $class === '') {
            return false;
        }

        // Normalize leading backslash
        $class = ltrim($class, '\\');

        // If namespace overrides explicitly ALLOW something, unblock it
        $namespaces = is_array($this->overrides['namespaces'] ?? null) ? $this->overrides['namespaces'] : [];
        foreach ($namespaces as $ns) {
            if (is_string($ns) && $ns !== '' && stripos($class, ltrim($ns, '\\')) === 0) {
                return false;
            }
        }

        // Default rule: anything starting with "Reflection"
        $prefix = $this->alwaysForbidden['reflectionPrefix'] ?? 'Reflection';
        return stripos($class, $prefix) === 0;
    }

    public function getForbiddenWrappers(): array
    {
        // Subtract overrides
        return array_values(array_diff($this->alwaysForbidden['wrappers'], $this->overrides['wrappers'] ?? []));
    }

    public function getForbiddenMagicMethods(): array
    {
        // Subtract overrides
        return array_values(array_diff($this->alwaysForbidden['magicMethods'], $this->overrides['magic_methods'] ?? []));
    }

    /**
     * Effective forbidden functions list (after subtracting allowed overrides).
     */
    public function getForbiddenFunctions(): array
    {
        $forbidden = array_map('strtolower', array_values(array_unique(array_merge(
            $this->alwaysForbidden['functions'],
            $this->fileIoMethods,
            $this->curlMethods
        ))));

        // Subtract overrides (functions/tokens/dangerous)
        $allow = array_keys($this->allowFunctionSet + $this->allowTokenSet + $this->allowDangerSet);
        if (!empty($allow)) {
            $forbidden = array_values(array_diff($forbidden, $allow));
        }

        return $forbidden;
    }

    public function getReflectionPrefix()
    {
        return $this->alwaysForbidden['reflectionPrefix'];
    }

    /* ---------------------------------------------------------------------
     |  Checks — Unsupported (warnings)
     |---------------------------------------------------------------------*/

    /**
     * Return effective unsupported set (after subtracting allowed overrides).
     */
    public function getUnsupportedFunctions(): array
    {
        $list = array_map('strtolower', $this->unsupportedFunctions);
        $allow = array_keys($this->allowFunctionSet + $this->allowTokenSet + $this->allowDangerSet);
        if (!empty($allow)) {
            $list = array_values(array_diff($list, $allow));
        }
        return $list;
    }

    public function isUnsupportedFunction($name): bool
    {
        $n = strtolower((string)$name);
        if (isset($this->allowFunctionSet[$n]) || isset($this->allowTokenSet[$n]) || isset($this->allowDangerSet[$n])) {
            return false;
        }
        return in_array($n, $this->getUnsupportedFunctions(), true);
    }

    /* ---------------------------------------------------------------------
     |  Accessors / Utilities
     |---------------------------------------------------------------------*/

    public function getFileFunctions(): array
    {
        return $this->fileIoMethods;
    }

    public function getObfuscators(): array
    {
        return $this->obfuscators;
    }

    public function getEnvMethods(): array
    {
        return $this->envManipulationFunctions;
    }

    /** Return the current (merged) class method allowlist map. */
    public function getBlocklist()
    {
        return $this->blocklist;
    }

    /** Namespaces currently considered forbidden (after overrides). */
    public function getForbiddenNamespaces(): array
    {
        return $this->forbiddenNamespaces;
    }

    /** Composer packages currently considered forbidden (after overrides). */
    public function getForbiddenPackages(): array
    {
        return $this->forbiddenPackages;
    }

    public function getCallbackFunctions(): array
    {
        return $this->callbackFunctions;
    }

    public function getStreamFunctions(): array
    {
        return $this->streamFunctions;
    }

    public function getDiContainerMethods(): array
    {
        return $this->diContainerMethods;
    }

    /** Raw config as provided to the policy */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function resolveClass($class): string
    {
        // Hook for alias resolution if you track aliases; identity for now.
        return (string)$class;
    }
}
```

---
#### 8


` File: src/Core/Security/ComposerScan.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use Timeax\FortiPlugin\Core\PluginPolicy;

class ComposerScan
{
    protected PluginPolicy $policy;

    public function __construct(PluginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Scan a composer.json file for forbidden packages.
     * @param string $composerJsonPath
     * @return array List of violations.
     * @throws JsonException
     */
    public function scan(string $composerJsonPath): array
    {
        $violations = [];
        if (!is_file($composerJsonPath)) {
            return [
                [
                    'type' => 'composer_file_missing',
                    'file' => $composerJsonPath,
                    'issue' => 'composer.json not found'
                ]
            ];
        }

        $json = json_decode(file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);
        if (!$json) {
            return [
                [
                    'type' => 'composer_file_invalid',
                    'file' => $composerJsonPath,
                    'issue' => 'Invalid JSON in composer.json'
                ]
            ];
        }

        $deps = array_merge(
            $json['require'] ?? [],
            $json['require-dev'] ?? []
        );

        foreach ($this->policy->getForbiddenPackages() as $forbidden) {
            foreach ($deps as $package => $version) {
                if (strtolower($package) === strtolower($forbidden)) {
                    $violations[] = [
                        'type' => 'forbidden_package_dependency',
                        'package' => $package,
                        'version' => $version,
                        'file' => $composerJsonPath,
                        'issue' => "Composer requires forbidden package: $package"
                    ];
                }
            }
        }

        return $violations;
    }
}
```

---
#### 9


` File: src/Core/Security/Concerns/ResolvesNames.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security\Concerns;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Identifier;

trait ResolvesNames
{
    /** Normalize: drop leading "\"; keep the case as-is (callers can strtolower). */
    protected function normClass(string $name): string
    {
        return ltrim($name, '\\');
    }

    /** Prefer NameResolver’s resolvedName/namespacedName when present. */
    protected function fqNameOf(mixed $node): ?string
    {
        if ($node instanceof Name) {
            $resolved = $node->getAttribute('resolvedName');
            if ($resolved instanceof Name) {
                return $this->normClass($resolved->toString());
            }
            // if replaceNodes=true, this is already FullyQualified
            return $this->normClass($node->toString());
        }
        if ($node instanceof Identifier) {
            return $this->normClass($node->toString());
        }
        if (is_string($node)) {
            return $this->normClass($node);
        }
        return null;
    }

    /** For class declarations (NameResolver sets ->namespacedName). */
    protected function declFqcn(Node\Stmt\Class_ $class): ?string
    {
        if (isset($class->namespacedName)) {
            return $this->normClass($class->namespacedName->toString());
        }
        return $class->name?->toString();
    }

    /** For function declarations (NameResolver sets ->namespacedName). */
    protected function declFuncName(Node\Stmt\Function_ $fn): ?string
    {
        if (isset($fn->namespacedName)) {
            return $this->normClass($fn->namespacedName->toString());
        }
        return $fn->name->toString();
    }

    /**
     * Resolve self/static/parent/FQCN to a class key.
     * Return lower-case key when you plan to use it as a map index.
     */
    protected function resolveStaticClassRef(Name|Identifier|string $classNode, string $currentClassKey): ?string
    {
        $raw = $this->fqNameOf($classNode);
        if ($raw === null) return null;

        $lc = strtolower($raw);
        if ($lc === 'self' || $lc === 'static' || $lc === 'parent') {
            return $currentClassKey;
        }
        return strtolower($raw);
    }
}
```

---
#### 10


` File: src/Core/Security/ConfigValidator.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use Opis\JsonSchema\Validator;

class ConfigValidator
{
    /**
     * @throws JsonException
     */
    public function validate(string $pluginRoot, string $schemaPath): array
    {
        $configFile = rtrim($pluginRoot, '/\\') . '/fortiplugin.json';
        if (!file_exists($configFile)) {
            return ['error' => 'fortiplugin.json not found'];
        }

        $json = file_get_contents($configFile);
        $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON in fortiplugin.json: ' . json_last_error_msg()];
        }

        $schema = json_decode(file_get_contents($schemaPath), false, 512, JSON_THROW_ON_ERROR); // <-- just the decoded schema object!
        $validator = new Validator();
        $error = $validator->schemaValidation($data, $schema);

        if ($error !== null) {
            $details = $this->extractErrors($error);
            return [
                'error' => 'Schema validation failed',
                'details' => $details,
            ];
        }

        return []; // Valid!
    }

    protected function extractErrors($error, $parentPointer = ''): array
    {
        if (!$error) {
            return [];
        }

        $pointer = $parentPointer . $error->data()->pointer();
        $message = $error->message();
        $keyword = $error->keyword();
        $args = $error->args();

        $result = [[
            'path' => $pointer,
            'message' => $message,
            'keyword' => $keyword,
            'args' => $args,
        ]];

        return array_reduce(
            $error->subErrors(),
            function ($carry, $sub) use ($pointer) {
                return [...$carry, ...$this->extractErrors($sub, $pointer)];
            },
            $result
        );
    }
}
```

---
#### 11


` File: src/Core/Security/ContentValidator.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection RegExpUnexpectedAnchor */

namespace Timeax\FortiPlugin\Core\Security;

use Timeax\FortiPlugin\Core\PluginPolicy;

class ContentValidator
{
    protected PluginPolicy $policy;
    protected ?string $root = null;

    public function __construct(PluginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Scan one PHP file and return violations.
     */
    public function scanFile(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return [[
                'type' => 'read_error',
                'file' => $filePath,
                'line' => 0,
                'snippet' => '',
                'issue' => 'Unable to read file',
            ]];
        }

        return $this->scanSource($content, $filePath);
    }

    /**
     * Scan a raw PHP source string and return violations.
     */
    public function scanSource(string $content, string $filePath = '[source]'): array
    {
        $violations = [];
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        foreach ($lines as $i => $line) {
            $ln = $i + 1;

            $this->append($violations, $this->containsBlocklistTokens($line, $ln, $filePath));
            $this->append($violations, $this->containsForbiddenNamespaces($line, $ln, $filePath));
            $this->append($violations, $this->containsForbiddenFunctions($line, $ln, $filePath));
            $this->append($violations, $this->containsUnsupportedFunctions($line, $ln, $filePath));
        }

        return $violations;
    }

    /**
     * Append items to the target array without creating extra copies.
     */
    protected function append(array &$target, array $items): void
    {
        if (!$items) return;
        foreach ($items as $v) {
            $target[] = $v;
        }
    }


    /**
     * Detect use of blocklisted classes/facades and their methods.
     */
    protected function containsBlocklistTokens(string $line, int $lineNumber, string $filePath): array
    {
        $violations = [];
        $map = $this->policy->getBlocklist(); // effective allowlist after overrides

        foreach ($map as $class => $allowed) {
            $q = preg_quote($class, '/');

            if (preg_match("/new\s+$q\s*\(/", $line)) {
                $violations[] = [
                    'type' => 'blocklist_instantiation',
                    'token' => $class,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => "Instantiation: new $class",
                ];
            }

            if (preg_match("/$q\s*::\s*__construct\s*\(/", $line)) {
                $violations[] = [
                    'type' => 'blocklist_constructor',
                    'token' => $class,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => "Constructor: $class::__construct",
                ];
            }

            if (preg_match("/\b$q\s*::\s*class\b/", $line)) {
                $violations[] = [
                    'type' => 'blocklist_class_reference',
                    'token' => $class,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => "Class reference: $class::class",
                ];
            }

            if (str_contains($line, "$class::") && !in_array('*', $allowed, true)) {
                preg_match_all("/\\b$q::([A-Za-z_][A-Za-z0-9_]*)/", $line, $m);
                foreach ($m[1] as $method) {
                    if (!in_array($method, $allowed, true)) {
                        $violations[] = [
                            'type' => 'blocklist_method',
                            'token' => $class,
                            'method' => $method,
                            'file' => $filePath,
                            'line' => $lineNumber,
                            'snippet' => trim($line),
                            'issue' => "Method: $class::$method",
                        ];
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * Forbidden namespaces.
     */
    protected function containsForbiddenNamespaces(string $line, int $lineNumber, string $filePath): array
    {
        $violations = [];
        $namespaces = $this->policy->getForbiddenNamespaces();

        // use statements
        if (preg_match('/^use\s+([^;]+);/i', $line, $m)) {
            $ns = trim($m[1]);
            foreach ($namespaces as $forbidden) {
                if (stripos($ns, $forbidden) === 0) {
                    $violations[] = [
                        'type' => 'forbidden_namespace_import',
                        'namespace' => $forbidden,
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'snippet' => trim($line),
                        'issue' => 'Import of forbidden namespace or child',
                    ];
                }
            }
        }

        foreach ($namespaces as $forbidden) {
            $q = preg_quote($forbidden, '/');

            // new/extends/implements/static/instanceof
            if (preg_match('/\b' . $q . '\\\\/', $line)) {
                $violations[] = [
                    'type' => 'forbidden_namespace_reference',
                    'namespace' => $forbidden,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => 'Reference to forbidden namespace',
                ];
            }

            // string references
            if (preg_match('/[\'"]' . $q . '\\\\[^\'"]+[\'"]/', $line)) {
                $violations[] = [
                    'type' => 'forbidden_namespace_string',
                    'namespace' => $forbidden,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => 'Forbidden namespace/class referenced as a string',
                ];
            }
        }

        return $violations;
    }

    /**
     * Hard-blocked functions (Forti defaults + curl + file I/O, minus overrides).
     */
    protected function containsForbiddenFunctions(string $line, int $lineNumber, string $filePath): array
    {
        $funcs = $this->policy->getForbiddenFunctions();
        if (!$funcs) return [];

        $alts = array_map(static fn($f) => preg_quote((string)$f, '/'), $funcs);
        $part = '(?<![A-Za-z0-9_])(' . implode('|', $alts) . ')(?![A-Za-z0-9_])';

        $out = [];

        if (preg_match("/$part\s*\(/i", $line, $m)) {
            $out[] = [
                'type' => 'forbidden_function',
                'function' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Call to forbidden function',
            ];
        }

        if (preg_match("/(?:\$\w+|\$\w+\[.*?]|\w+::\$\w+|\$\w+->\w+)\s*=\s*$part\s*;/i", $line, $m)) {
            $out[] = [
                'type' => 'forbidden_function_assignment',
                'function' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Assigned to variable/array/object/class property',
            ];
        }

        return $out;
    }

    /**
     * Unsupported/risky functions (warnings) after subtracting overrides.
     */
    protected function containsUnsupportedFunctions(string $line, int $lineNumber, string $filePath): array
    {
        $funcs = $this->policy->getUnsupportedFunctions();
        if (!$funcs) return [];

        $alts = array_map(static fn($f) => preg_quote((string)$f, '/'), $funcs);
        $part = '(?<![A-Za-z0-9_])(' . implode('|', $alts) . ')(?![A-Za-z0-9_])';

        $out = [];

        if (preg_match("/$part\s*\(/i", $line, $m)) {
            $out[] = [
                'type' => 'unsupported_function',
                'function' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Call to unsupported/risky function',
            ];
        }

        return $out;
    }
}
```

---
#### 12


` File: src/Core/Security/FileScanner.php`  [↑ Back to top](#index)

```php
<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Security;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionFunction;
use SplFileInfo;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;

/**
 * Class FileScanner
 *
 * Recursively scans a directory tree and invokes a callback for files that are
 * likely to contain PHP code — either by trusted extensions (php/phtml/…)
 * OR because the file CONTENT indicates a PHP payload (e.g. "<?php" in a .jpg).
 *
 * Security goals:
 *  - Detect PHP hidden in "unrelated" files (images, text, vendor assets).
 *  - Detect double-extension tricks and Unicode filename spoofing.
 *  - Prevent symlink escapes.
 *  - Respect host ignore rules, but (by default) DO NOT ignore files that
 *    actually contain PHP payloads.
 *
 * Runtime behavior:
 *  - Web requests: enforce size limits (policy-configurable) to guard memory.
 *  - CLI and background jobs/queue workers: no size limits (full scan).
 *
 * Policy config keys (all optional):
 *  - ignore: string[]
 *      Glob-style patterns; matched against both absolute and root-relative paths.
 *      Supports negation with a leading '!'. (See shouldIgnore()).
 *
 *  - php_extensions: string[]
 *      List of extensions considered PHP-like (default: ['php','phtml','phpt']).
 *
 *  - scan_size: array{string:int}
 *      Per-extension maximum file bytes when web context (e.g., ['php' => 50000]).
 *
 *  - max_web_file_bytes: int
 *      Hard cap (bytes) for any single file read/sniff in web context. If exceeded,
 *      file is skipped without reading content (default: 256 * 1024).
 *
 *  - strict_ignore_blocks_payload: bool
 *      If true, an ignore rule will still exclude a file even when a PHP payload
 *      is detected via content sniffing. Default: false (payloads bypass ignore).
 *
 *  - php_short_open_tag_enabled: bool
 *      If set, overrides auto-detection for short tags ('<?'). Default: autodetect via ini.
 *
 *  - scanner_emit_pre_flags: bool
 *      If true (default), the scanner will emit pre-flag "issue rows" for filename/content
 *      suspicions in addition to calling your callback.
 *
 * Usage:
 *  $results = (new FileScanner($policy))->scan($dir, function (string $path, array $meta = []) {
 *      // $meta['flags'] holds filename/content suspicion flags (if any)
 *      return MyAnalyzer::analyze($path);
 *  });
 *
 * @template T
 */
class FileScanner
{
    protected PluginPolicy $policy;

    /**
     * Absolute realpath of the scan root (set during scan()).
     * @var string|null
     */
    protected ?string $root = null;

    public function __construct(PluginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Recursively scans $directory and invokes $callback for each eligible file.
     *
     * A file is eligible if:
     *  - It is a regular file (not a dir), AND
     *  - Not a symlink, AND
     *  - (has a PHP-like extension) OR (its CONTENT sniff indicates PHP payload),
     *  - Not ignored by policy 'ignore' rules (unless payload detected and
     *    strict_ignore_blocks_payload=false), AND
     *  - (Web context only) does not exceed configured size limits.
     *
     * The callback may accept either (string $path) or (string $path, array $meta).
     * $meta will include ['flags' => array<array{type:string,hint:string}>].
     *
     * @template TResult
     * @param string $directory Directory to scan (absolute or relative).
     * @param Closure(string):TResult $callback
     * @return array<int,TResult|array<int,array<string,mixed>>>      Collected non-falsy callback results (and optional pre-flag issues).
     */
    public function scan(string $directory, Closure $callback, ?Closure $emit): array
    {
        $realRoot = realpath($directory);
        $this->root = $realRoot !== false ? $realRoot : $directory;

        $config = $this->policy->getConfig();
        $allowedExts = $this->resolvePhpExtensions($config);
        $scanLimits = (array)($config['scan_size'] ?? []);
        $webHardCap = (int)($config['max_web_file_bytes'] ?? (256 * 1024));
        $strictIgnore = (bool)($config['strict_ignore_blocks_payload'] ?? false);
        $shortOpenTags = $this->shortOpenTagEnabled($config);
        $emitPreFlags = (bool)($config['scanner_emit_pre_flags'] ?? true);
        $ignore_non_php = (bool)($config['ignore_non_php'] ?? false);

        $collected = [];

        $rdiFlags = FilesystemIterator::SKIP_DOTS; // intentionally do not follow symlinks
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, $rdiFlags)
        );

        if ($emit) {
            $emit([
                'count' => iterator_count($iter),
                'title' => 'Scanning files',
                'message' => 'Scanning files in ' . $directory
            ]);
        }

        /** @var SplFileInfo $info */
        foreach ($iter as $info) {
            // Must be a regular file; reject symlinks to avoid escapes.
            if (!$info->isFile() || $info->isLink()) {
                continue;
            }

            $absPath = $this->normalizeSeparators($info->getPathname());
            $basename = $info->getBasename();

            // Collect suspicion flags for this file
            $preFlags = [];

            // Filename-level Unicode spoofing (bidi controls / isolates)
            if ($this->hasSuspiciousUnicodeName($basename)) {
                $preFlags[] = [
                    'type' => 'suspicious_filename_unicode',
                    'hint' => 'Filename contains bidi control characters (possible extension spoofing)',
                ];
            }

            // Enforce size caps only in web runtime
            if ($this->isWebContext()) {
                if ($this->exceedsMaxSizeByExt($info, $scanLimits)) {
                    $emit && $emit([
                        'title' => 'File ignored',
                        'message' => 'File ignored due to policy rules',
                        'path' => $absPath,
                        'flags' => $preFlags,
                        'issue' => 'max_web_file_bytes'
                    ]);
                    continue;
                }
                // Apply global sniff cap to avoid reading giant binaries in web
                if ($webHardCap > 0 && ($info->getSize() ?: 0) > $webHardCap) {
                    $emit && $emit([
                        'title' => 'File ignored',
                        'message' => 'File ignored due to policy rules',
                        'path' => $absPath,
                        'flags' => $preFlags,
                        'issue' => 'max_web_file_bytes'
                    ]);
                    continue;
                }
            }

            // Decide eligibility:
            // 1) Extension says PHP-like OR filename double-extension trick suggests PHP
            $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
            $extLooksPhp = in_array($ext, $allowedExts, true);
            $doubleExtSusp = $this->isDoubleExtensionSuspicious($basename, $allowedExts);
            if ($doubleExtSusp) {
                $preFlags[] = [
                    'type' => 'suspicious_double_extension',
                    'hint' => 'Double-extension pattern detected (e.g., *.jpg.php or *.php.txt)',
                ];
            }

            // 2) Content sniff says there's PHP payload (<?php, <?=, <? if enabled, or shebang)
            $payload = $this->containsPhpPayload($absPath, $shortOpenTags);
            if ($payload && !$extLooksPhp) {
                $preFlags[] = [
                    'type' => 'php_payload_in_non_php',
                    'hint' => 'PHP payload found in a non-PHP file',
                ];
            }

            if (!($extLooksPhp || $doubleExtSusp || $payload) && !$ignore_non_php) {
                // Not interesting
                $emit && $emit(['title' => 'File ignored', 'message' => 'File ignored due to policy rules', 'path' => $absPath, 'flags' => $preFlags]);
                continue;
            }

            // Ignore rules:
            $ignored = $this->shouldIgnore($absPath);
            if ($ignored) {
                // If payload is detected, we default to BYPASS ignore (safer)
                if (!$payload || $strictIgnore) {
                    $emit && $emit(['title' => 'File ignored', 'message' => 'File ignored due to policy rules', 'path' => $absPath, 'flags' => $preFlags]);
                    continue;
                }
            }

            // Invoke the callback; pass meta if it accepts a second parameter
            $meta = ['flags' => $preFlags];
            $result = $this->invokeCallback($callback, $absPath, $meta);
            if ($result) {
                $collected[] = $result;
            }

            // Optionally emit pre-flag issues directly from the scanner
            if ($emitPreFlags && $preFlags) {
                $issues = $this->makeFlagIssues($absPath, $basename, $preFlags);
                if ($issues) {
                    $collected[] = $issues; // keep chunked; caller may flatten
                }
            }
        }

        return $collected;
    }

    // ───────────────────────────── Helpers ─────────────────────────────

    /**
     * Policy-driven extension list for PHP-like files.
     * Ensures 'php' is present; defaults to ['php','phtml','phpt'].
     *
     * @param array<string,mixed> $config
     * @return array<int,string>
     */
    protected function resolvePhpExtensions(array $config): array
    {
        $exts = $config['php_extensions'] ?? ['php', 'phtml', 'phpt'];
        $exts = array_values(array_unique(array_map(
            static fn($e) => strtolower((string)$e),
            (array)$exts
        )));
        if (!in_array('php', $exts, true)) {
            array_unshift($exts, 'php');
        }
        return $exts;
    }

    /**
     * Check if a file should be ignored by policy ('ignore' patterns).
     * Supports '!' negation to re-include paths.
     *
     * @param string $absolutePath Normalized absolute path.
     * @return bool   True if ignored.
     */
    protected function shouldIgnore(string $absolutePath): bool
    {
        $patterns = $this->policy->getConfig()['ignore'] ?? [];
        if (!$patterns) {
            return false;
        }

        $normalized = $this->normalizeSeparators($absolutePath);
        $rel = $this->root
            ? ltrim($this->normalizeSeparators(str_replace($this->root, '', $normalized)), DIRECTORY_SEPARATOR)
            : $normalized;

        $ignored = false;

        foreach ($patterns as $pattern) {
            $negated = false;
            $p = $pattern;

            if (is_string($p) && $p !== '' && $p[0] === '!') {
                $negated = true;
                $p = substr($p, 1);
            }

            if (!is_string($p) || $p === '') {
                continue;
            }

            $pNorm = $this->normalizeSeparators($p);
            $match = fnmatch($pNorm, $rel) || fnmatch($pNorm, $normalized);

            if ($match) {
                $ignored = !$negated;
            }
        }

        return $ignored;
    }

    /**
     * Returns true when short open tags are enabled.
     * Can be forced via policy 'php_short_open_tag_enabled'.
     *
     * @param array<string,mixed> $config
     * @return bool
     */
    protected function shortOpenTagEnabled(array $config): bool
    {
        if (array_key_exists('php_short_open_tag_enabled', $config)) {
            return (bool)$config['php_short_open_tag_enabled'];
        }
        // Safe default: respect runtime setting
        return (bool)ini_get('short_open_tag');
    }

    /**
     * Lightweight content sniff to detect PHP payload in ANY file.
     * Reads the first ~64KB (CLI/background) or up to policy 'max_web_file_bytes' (web).
     * Looks for:
     *  - "<?php"
     *  - "<?=" (short echo)
     *  - "<?" (if short_open_tag enabled)
     *  - Shebang "#!/usr/bin/php" at start
     *
     * @param string $absPath
     * @param bool $shortTags
     * @return bool
     */
    protected function containsPhpPayload(string $absPath, bool $shortTags): bool
    {
        // Read cap: larger in CLI/background, smaller in web
        $config = $this->policy->getConfig();
        $webSniffCap = (int)($config['max_web_file_bytes'] ?? (256 * 1024));
        $cap = $this->isWebContext() ? max(4096, $webSniffCap) : (64 * 1024);

        $h = @fopen($absPath, 'rb');
        if ($h === false) {
            return false;
        }
        $data = @fread($h, $cap);
        @fclose($h);

        if ($data === false || $data === '') {
            return false;
        }

        if (str_contains($data, '<?php') || str_contains($data, '<?=')) {
            return true;
        }
        // Avoid counting XML headers as PHP payload
        if ($shortTags && str_contains($data, '<?') && !str_starts_with(ltrim($data), '<?xml')) {
            return true;
        }
        // Shebang
        return str_starts_with($data, '#!/usr/bin/php') || str_starts_with($data, "#!/usr/bin/env php");
    }

    /**
     * Detect basic double-extension tricks like "image.jpg.php" or "file.php.txt".
     *
     * @param string $basename
     * @param array<int,string> $phpExts
     * @return bool
     */
    protected function isDoubleExtensionSuspicious(string $basename, array $phpExts): bool
    {
        $lower = strtolower($basename);
        $parts = explode('.', $lower);

        if (count($parts) < 2) {
            return false;
        }

        // Example suspicious forms:
        //  - *.php.*
        //  - *.*.php
        $last = array_pop($parts);
        if (in_array($last, $phpExts, true)) {
            // e.g., name.jpg.php
            return true;
        }
        if (in_array($parts[count($parts) - 1] ?? '', $phpExts, true)) {
            // e.g., name.php.txt
            return true;
        }

        return false;
    }

    /**
     * Detect presence of Unicode bidi override or other RTL control chars in filename
     * that could visually spoof extensions in some UIs.
     *
     * @param string $basename
     * @return bool
     */
    protected function hasSuspiciousUnicodeName(string $basename): bool
    {
        // Common suspects: U+202E (RTL override), U+202A..U+202C (embedding/POP),
        // U+2066..U+2069 (isolates)
        /** @noinspection RegExpSingleCharAlternation */
        return (bool)preg_match('/\x{202E}|\x{202A}|\x{202B}|\x{202C}|\x{2066}|\x{2067}|\x{2068}|\x{2069}/u', $basename);
    }

    /**
     * Web-only: check per-extension max size via policy scan_size.
     *
     * @param SplFileInfo $info
     * @param array<string,int> $limits
     * @return bool
     */
    protected function exceedsMaxSizeByExt(SplFileInfo $info, array $limits): bool
    {
        if (!$this->isWebContext()) {
            return false;
        }
        $ext = strtolower(pathinfo($info->getPathname(), PATHINFO_EXTENSION));
        $limit = ($limits[$ext] ?? 0);
        if ($limit <= 0) {
            return false;
        }
        $size = $info->getSize();
        return $size !== false && $size > $limit;
    }

    /**
     * Normalize path separators to the current OS.
     *
     * @param string $path
     * @return string
     */
    protected function normalizeSeparators(string $path): string
    {
        return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * True if running in a web (non-console) context.
     * Laravel's queue workers and CLI commands runInConsole() === true.
     *
     * @return bool
     */
    protected function isWebContext(): bool
    {
        // Prefer Laravel helper if available; fallback to PHP_SAPI check.
        if (function_exists('app')) {
            try {
                return !app()->runningInConsole();
            } catch (Throwable) {
                // ignore
            }
        }
        return PHP_SAPI !== 'cli';
    }

    /**
     * Invoke analyzer callback. If it accepts (path, meta), pass both.
     *
     * @template TResult
     * @param Closure $callback Closure(string $path [, array $meta]): TResult
     * @param string $path
     * @param array<string,mixed> $meta
     * @return mixed                  TResult|false|null
     */
    protected function invokeCallback(Closure $callback, string $path, array $meta): mixed
    {
        $arity = $this->callbackArity($callback);
        if ($arity >= 2) {
            return $callback($path, $meta);
        }
        return $callback($path);
    }

    /**
     * Determine number of parameters accepted by the callback.
     *
     * @param Closure $callback
     * @return int
     */
    protected function callbackArity(Closure $callback): int
    {
        try {
            return (new ReflectionFunction($callback))->getNumberOfParameters();
        } catch (Throwable) {
            return 1; // safe fallback: assume single-arg
        }
    }

    /**
     * Convert collected pre-flags into canonical issue rows.
     *
     * Each flag item should be ['type'=>string,'hint'=>string].
     * The filename (basename) is reported as the "token" for quick context.
     *
     * @param string $file
     * @param string $basename
     * @param array<int,array<string,mixed>> $flags
     * @return array<int,array<string,mixed>>
     */
    protected function makeFlagIssues(string $file, string $basename, array $flags): array
    {
        $rows = [];
        foreach ($flags as $f) {
            $rows[] = [
                'type' => (string)($f['type'] ?? 'suspicious'),
                'token' => $basename,
                'file' => $file,
                'line' => 0,      // filename-level issue (not line-based)
                'snippet' => '',
                'issue' => (string)($f['hint'] ?? 'Suspicious file indicator'),
            ];
        }
        return $rows;
    }
}
```

---
#### 13


` File: src/Core/Security/HostConfigValidator.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security;

use Timeax\FortiPlugin\Core\Exceptions\HostConfigException;
use Timeax\FortiPlugin\Core\Exceptions\DuplicateSettingIdException;

final class HostConfigValidator
{
    /**
     * Validate a HostConfig object:
     * - 'global' has no 'id' and only SettingValue values
     * - 'settings' is an array of Setting objects with unique 'id'
     * - Each Setting's non-id props are valid SettingValue types
     *
     * @throws HostConfigException
     */
    public static function validate(array $hostConfig): void
    {
        // ----- global (optional) -----
        if (array_key_exists('global', $hostConfig)) {
            if (!is_array($hostConfig['global'])) {
                throw new HostConfigException("'global' must be an object.");
            }
            if (array_key_exists('id', $hostConfig['global'])) {
                throw new HostConfigException("'global' must not contain an 'id'.");
            }
            foreach ($hostConfig['global'] as $k => $v) {
                if (!self::isValidSettingValue($v)) {
                    throw new HostConfigException("Invalid SettingValue at global['{$k}'].");
                }
            }
        }

        // ----- settings (optional) -----
        $ids = [];
        if (array_key_exists('settings', $hostConfig)) {
            if (!is_array($hostConfig['settings'])) {
                throw new HostConfigException("'settings' must be an array of Setting objects.");
            }

            foreach ($hostConfig['settings'] as $i => $setting) {
                $path = "settings[{$i}]";

                if (!is_array($setting)) {
                    throw new HostConfigException("'{$path}' must be an object.");
                }
                if (!array_key_exists('id', $setting)) {
                    throw new HostConfigException("'{$path}.id' is required.");
                }

                $id = $setting['id'];
                if (!is_string($id) && !is_int($id) && !is_float($id)) {
                    throw new HostConfigException("'{$path}.id' must be a string or number.");
                }

                // Enforce uniqueness (stringify so '1' and 1 collide)
                $idKey = (string)$id;
                if (isset($ids[$idKey])) {
                    throw new DuplicateSettingIdException($id, "at {$path}");
                }
                $ids[$idKey] = true;

                // Validate each non-id property value
                foreach ($setting as $k => $v) {
                    if ($k === 'id') {
                        continue;
                    }
                    if (!self::isValidSettingValue($v)) {
                        throw new HostConfigException("Invalid SettingValue at {$path}['{$k}'].");
                    }
                }
            }
        }
    }

    /** SettingValue = boolean | null | string | number | string[] | map<string, TriState> */
    private static function isValidSettingValue(mixed $v): bool
    {
        if (is_bool($v) || is_null($v) || is_string($v) || is_int($v) || is_float($v)) {
            return true;
        }

        if (is_array($v)) {
            // list of strings?
            if (self::isStringList($v)) {
                return true;
            }
            // map<string, TriState> ?
            if (!self::isList($v)) {
                foreach ($v as $kk => $vv) {
                    if (!is_string($kk)) return false;
                    if (!is_bool($vv) && !is_null($vv)) return false; // TriState
                }
                return true;
            }
        }

        return false;
    }

    private static function isStringList(array $arr): bool
    {
        if (!self::isList($arr)) return false;
        foreach ($arr as $item) {
            if (!is_string($item)) return false;
        }
        return true;
    }

    /** Polyfill for PHP < 8.1 array_is_list */
    private static function isList(array $arr): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i++) return false;
        }
        return true;
    }
}
```

---
#### 14


` File: src/Core/Security/PermissionManifestValidator.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection PhpSameParameterValueInspection */

/** @noinspection PhpUndefinedClassInspection */

namespace Timeax\FortiPlugin\Core\Security;

use InvalidArgumentException;
use JsonException;
use Timeax\FortiPlugin\Lib\Obfuscator;
use Timeax\FortiPlugin\Permissions\Support\HostConfigNormalizer;

final class PermissionManifestValidator
{
    // inside PermissionManifestValidator class (properties section)
    private array $moduleAliasMap;      // alias => ['map' => FQCN, 'docs' => string|null]
    private array $moduleFqcnToAlias;   // FQCN => alias
    /** Canonical codec groups → method names */
    private array $codecGroups;

    /** Allowed rule types */
    private const TYPES = ['db', 'file', 'network', 'notify', 'module', 'codec'];

    /** Per-type allowed actions */
    private const ACTIONS = [
        'db' => ['select', 'insert', 'update', 'delete', 'truncate', 'transaction'],
        'file' => ['read', 'write', 'append', 'delete', 'mkdir', 'rmdir', 'list'],
        'network' => ['request'],
        'notify' => ['send'],
        'module' => ['call', 'publish', 'subscribe'],
        'codec' => ['invoke'],
    ];

    /** HTTP method allowlist */
    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /* ========================= Host-provided catalogs ========================= */

    /** @var string[] Allowed notification channels (from host config). */
    private array $allowedChannels;

    /**
     * Host DB model map (alias => ['map' => FQCN, 'relations' => array<string,string>])
     * Example entry: 'user' => ['map' => 'App\\Models\\User', 'relations' => ['posts' => 'post']]
     */
    private array $modelAliasMap;

    /** @var array<string,string> FQCN => alias (reverse lookup for convenience) */
    private array $fqcnToAlias;

    /**
     * @param string[]|null $allowedChannels If null, tries config('fortiplugin.notifications-channels', [])
     * @param array<string,array{map:string,relations?:array<string,string>}>|null $modelConfig
     *        If null, tries config('fortiplugin.models', [])
     */
    public function __construct(
        ?array $allowedChannels = null,
        ?array $modelConfig = null,
        ?array $moduleConfig = null,
        ?array $codecConfig = null,
    )
    {
        $this->allowedChannels = $this->normalizeChannels(
            $allowedChannels ?? $this->readConfig('fortiplugin-maps.notifications-channels', [])
        );

        $this->modelAliasMap = $this->normalizeModels(
            $modelConfig ?? $this->readConfig('fortiplugin-maps.models', [])
        );
        $this->fqcnToAlias = [];
        foreach ($this->modelAliasMap as $alias => $def) {
            $this->fqcnToAlias[$def['map']] = $alias;
        }

        // NEW: modules catalog
        $this->moduleAliasMap = $this->normalizeModules(
            $moduleConfig ?? $this->readConfig('fortiplugin-maps.modules', [])
        );
        $this->moduleFqcnToAlias = [];
        foreach ($this->moduleAliasMap as $alias => $def) {
            $this->moduleFqcnToAlias[$def['map']] = $alias;
        }

        $this->codecGroups = $codecConfig ?? $this->loadCodecGroups();
    }

    /**
     * Pull groups from Timeax\FortiPlugin\Lib\Obfuscator::availableGroups().
     * Obfuscator returns [group => [methodName => wrapperName]].
     * We normalize to [group => [methodName, ...]].
     */
    private function loadCodecGroups(): array
    {
        if (!class_exists(Obfuscator::class) || !method_exists(Obfuscator::class, 'availableGroups')) {
            return []; // no catalog available (e.g., during tests)
        }

        return HostConfigNormalizer::codecGroupsFromObfuscatorMap(Obfuscator::availableGroups());
    }
    /* ========================= Public API ========================= */

    /** Validate a manifest (array or JSON string). Returns normalized manifest or throws. */
    public function validate(array|string $manifest): array
    {
        $data = is_string($manifest) ? $this->decodeJson($manifest) : $manifest;

        $errors = [];
        $norm = ['required_permissions' => [], 'optional_permissions' => []];

        // Top-level shape
        if (!is_array($data)) {
            $this->boom('$.', 'manifest must be an object', $errors);
        }
        $this->rejectUnknownKeys($data, ['required_permissions', 'optional_permissions', '$schema', '$id', 'title', 'description'], '$');

        // required_permissions (required)
        if (!array_key_exists('required_permissions', $data) || !is_array($data['required_permissions'])) {
            $this->boom('$.required_permissions', 'required_permissions must be an array', $errors);
        }

        // optional_permissions (optional)
        if (isset($data['optional_permissions']) && !is_array($data['optional_permissions'])) {
            $this->boom('$.optional_permissions', 'optional_permissions must be an array if provided', $errors);
        }

        // Validate both lists
        foreach (['required_permissions', 'optional_permissions'] as $listKey) {
            if (!isset($data[$listKey]) || !is_array($data[$listKey])) {
                continue;
            }
            foreach (array_values($data[$listKey]) as $i => $rule) {
                $path = '$.' . $listKey . '[' . $i . ']';
                $norm[$listKey][] = $this->validateRule($rule, $path, $errors);
            }
        }

        if ($errors) {
            $msg = "Permission manifest validation failed:\n- " . implode("\n- ", $errors);
            throw new InvalidArgumentException($msg);
        }

        return $norm;
    }

    /* ========================= Rule validators ========================= */

    private function validateRule(mixed $rule, string $path, array &$errors): array
    {
        if (!is_array($rule)) {
            $this->boom($path, 'rule must be an object', $errors);
            return [];
        }

        $this->rejectUnknownKeys($rule, ['type', 'target', 'actions', 'conditions', 'audit', 'justification', 'methods', 'groups', 'options'], $path);

        // common fields
        $type = $rule['type'] ?? null;
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            $this->boom("{$path}.type", 'type must be one of: ' . implode(',', self::TYPES), $errors);
        }

        $actions = $rule['actions'] ?? null;
        if (!is_array($actions) || $actions === []) {
            $this->boom("{$path}.actions", 'actions must be a non-empty array', $errors);
        } else {
            $actions = array_values(array_unique(array_map('strval', $actions)));
            $allowed = self::ACTIONS[$type] ?? [];
            foreach ($actions as $a) {
                if (!in_array($a, $allowed, true)) {
                    $this->boom("{$path}.actions", "action '{$a}' not allowed for type '{$type}'", $errors);
                }
            }
        }

        // audit (optional)
        if (isset($rule['audit'])) {
            $this->validateAudit($rule['audit'], "{$path}.audit", $errors);
        }

        // conditions (optional; only setting_link, guard, env)
        $condNorm = null;
        if (isset($rule['conditions'])) {
            $condNorm = $this->validateConditions($rule['conditions'], "{$path}.conditions", $errors);
        }

        // per-type target + extras
        $target = $rule['target'] ?? null;
        $normalized = [
            'type' => $type,
            'target' => null,
            'actions' => $actions ?? [],
            'conditions' => $condNorm,
            'audit' => $rule['audit'] ?? null,
            'justification' => isset($rule['justification']) ? (string)$rule['justification'] : null,
        ];

        switch ($type) {
            case 'db':
                $normalized['target'] = $this->validateDbTarget($target, "{$path}.target", $errors, $actions);
                break;

            case 'file':
                $normalized['target'] = $this->validateFileTarget($target, "{$path}.target", $errors);
                break;

            case 'network':
                $normalized['target'] = $this->validateNetworkTarget($target, "{$path}.target", $errors);
                break;

            case 'notify':
                $normalized['target'] = $this->validateNotifyTarget($target, "{$path}.target", $errors);
                break;

            case 'module':
                $normalized['target'] = $this->validateModuleTarget($target, "{$path}.target", $errors);
                break;

            case 'codec':
                $normalized['target'] = $this->validateCodecTarget($target, "{$path}.target", $errors);
                [$resolved, $requiresGuard] = $this->validateCodecMethodsAndGroups(
                    $rule['methods'] ?? null,
                    $rule['groups'] ?? null,
                    $rule['options'] ?? null,
                    ($path)
                    , $errors);

                $normalized['methods'] = $rule['methods'] ?? null;
                $normalized['groups'] = $rule['groups'] ?? null;
                $normalized['options'] = $rule['options'] ?? null;
                $normalized['resolved_methods'] = $resolved;
                $normalized['requires_unserialize_guard'] = $requiresGuard;
                break;
        }

        return $normalized;
    }

    /* ========================= Type: DB ========================= */

    private function validateDbTarget(mixed $target, string $path, array &$errors, array $actions): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        $hasModel = array_key_exists('model', $target);
        $hasTable = array_key_exists('table', $target);
        if ($hasModel === $hasTable) {
            $this->boom($path, "exactly one of 'model' or 'table' is required", $errors);
        }

        $modelFqcn = null;
        $modelAlias = null;
        $hostColsAll = null;
        $hostColsWritable = null;

        if ($hasModel) {
            $decl = $target['model'];
            if (!is_string($decl) || $decl === '') {
                $this->boom("{$path}.model", 'model must be a non-empty string (alias or FQCN)', $errors);
            } else if (array_key_exists($decl, $this->modelAliasMap)) {
                $modelAlias = $decl;
                $modelFqcn = $this->modelAliasMap[$decl]['map'];
                $hostColsAll = $this->modelAliasMap[$decl]['columns']['all'] ?? null;
                $hostColsWritable = $this->modelAliasMap[$decl]['columns']['writable'] ?? null;
            } else if (array_key_exists($decl, $this->fqcnToAlias)) {
                $modelFqcn = $decl;
                $modelAlias = $this->fqcnToAlias[$decl];
                $hostColsAll = $this->modelAliasMap[$modelAlias]['columns']['all'] ?? null;
                $hostColsWritable = $this->modelAliasMap[$modelAlias]['columns']['writable'] ?? null;
            } else if ($this->modelAliasMap !== []) {
                $this->boom("{$path}.model", "unknown model alias/FQCN '{$decl}' (not in host 'models' map)", $errors);
            } else {
                $modelFqcn = $decl;
            }
        }

        if ($hasTable && (!is_string($target['table']) || $target['table'] === '')) {
            $this->boom("{$path}.table", 'table must be a non-empty string', $errors);
        }

        // columns in manifest (optional)
        $cols = null;
        if (isset($target['columns'])) {
            if (!$this->isStringList($target['columns'])) {
                $this->boom("{$path}.columns", 'columns must be an array of unique strings', $errors);
            } else {
                $cols = array_values(array_unique(array_map('strval', $target['columns'])));
            }
        }

        $this->rejectUnknownKeys($target, ['model', 'table', 'columns'], $path);

        // Enforce host column policy if present and a model is known
        if ($modelAlias !== null) {
            $hasWrite = (bool)array_intersect($actions, ['insert', 'update']);

            if ($cols !== null) {
                // If write actions requested, require ⊆ writable (when known); else require ⊆ all (when known)
                if ($hasWrite && $hostColsWritable !== null) {
                    $diff = array_diff($cols, $hostColsWritable);
                    if ($diff) {
                        $this->boom("{$path}.columns", "columns not writable by host policy: " . implode(', ', $diff), $errors);
                    }
                }
                if ($hostColsAll !== null) {
                    $diffAll = array_diff($cols, $hostColsAll);
                    if ($diffAll) {
                        $this->boom("{$path}.columns", "columns not allowed by host policy: " . implode(', ', $diffAll), $errors);
                    }
                }
            }
        }

        return [
            'model' => $modelFqcn,
            'model_alias' => $modelAlias,
            'table' => $hasTable ? $target['table'] : null,
            'columns' => $cols,
        ];
    }

    /* ========================= Type: FILE ========================= */

    private function validateFileTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        foreach (['base_dir', 'paths'] as $req) {
            if (!array_key_exists($req, $target)) {
                $this->boom("{$path}.{$req}", "{$req} is required", $errors);
            }
        }
        if (!is_string($target['base_dir'] ?? null) || $target['base_dir'] === '') {
            $this->boom("{$path}.base_dir", 'base_dir must be a non-empty string', $errors);
        }
        if (!$this->isStringList($target['paths'] ?? null, true)) {
            $this->boom("{$path}.paths", 'paths must be a non-empty array of unique strings', $errors);
        }
        foreach ($target['paths'] ?? [] as $idx => $p) {
            if (str_contains($p, '..')) {
                $this->boom("{$path}.paths[{$idx}]", "path must not contain '..'", $errors);
            }
        }
        if (isset($target['follow_symlinks']) && !is_bool($target['follow_symlinks'])) {
            $this->boom("{$path}.follow_symlinks", 'follow_symlinks must be boolean', $errors);
        }
        $this->rejectUnknownKeys($target, ['base_dir', 'paths', 'follow_symlinks'], $path);

        return [
            'base_dir' => (string)$target['base_dir'],
            'paths' => array_values(array_unique(array_map('strval', $target['paths']))),
            'follow_symlinks' => (bool)($target['follow_symlinks'] ?? false),
        ];
    }

    /* ========================= Type: NETWORK ========================= */

    private function validateNetworkTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        foreach (['hosts', 'methods'] as $req) {
            if (!array_key_exists($req, $target)) {
                $this->boom("{$path}.{$req}", "{$req} is required", $errors);
            }
        }
        if (!$this->isStringList($target['hosts'] ?? null, true)) {
            $this->boom("{$path}.hosts", 'hosts must be a non-empty array of strings', $errors);
        } else {
            foreach ($target['hosts'] as $i => $h) {
                if (!preg_match('/^([a-z0-9.-]+|\*\.[a-z0-9.-]+)$/', $h)) {
                    $this->boom("{$path}.hosts[{$i}]", "invalid host pattern '{$h}'", $errors);
                }
            }
        }
        if (!$this->isStringList($target['methods'] ?? null, true)) {
            $this->boom("{$path}.methods", 'methods must be a non-empty array of strings', $errors);
        } else {
            foreach ($target['methods'] as $i => $m) {
                if (!in_array(strtoupper($m), self::HTTP_METHODS, true)) {
                    $this->boom("{$path}.methods[{$i}]", "method '{$m}' not allowed", $errors);
                }
            }
        }
        if (isset($target['schemes'])) {
            if (!$this->isStringList($target['schemes'])) {
                $this->boom("{$path}.schemes", 'schemes must be an array of strings', $errors);
            } else {
                foreach ($target['schemes'] as $i => $s) {
                    if (!in_array($s, ['https', 'http'], true)) {
                        $this->boom("{$path}.schemes[{$i}]", "scheme '{$s}' not allowed", $errors);
                    }
                }
            }
        }
        if (isset($target['ports'])) {
            if (!is_array($target['ports'])) {
                $this->boom("{$path}.ports", 'ports must be an array of integers', $errors);
            } else {
                foreach ($target['ports'] as $i => $p) {
                    if (!is_int($p) || $p < 1 || $p > 65535) {
                        $this->boom("{$path}.ports[{$i}]", 'port must be an integer between 1 and 65535', $errors);
                    }
                }
            }
        }
        if (isset($target['ips_allowed'])) {
            if (!$this->isStringList($target['ips_allowed'])) {
                $this->boom("{$path}.ips_allowed", 'ips_allowed must be an array of strings', $errors);
            } else {
                foreach ($target['ips_allowed'] as $i => $ip) {
                    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                        $this->boom("{$path}.ips_allowed[{$i}]", "invalid IP '{$ip}'", $errors);
                    }
                }
            }
        }
        if (isset($target['headers_allowed']) && !$this->isStringList($target['headers_allowed'])) {
            $this->boom("{$path}.headers_allowed", 'headers_allowed must be an array of strings', $errors);
        }
        if (isset($target['paths']) && !$this->isStringList($target['paths'])) {
            $this->boom("{$path}.paths", 'paths must be an array of strings', $errors);
        }
        if (isset($target['auth_via_host_secret']) && !is_bool($target['auth_via_host_secret'])) {
            $this->boom("{$path}.auth_via_host_secret", 'auth_via_host_secret must be boolean', $errors);
        }

        $this->rejectUnknownKeys($target, [
            'hosts', 'schemes', 'ports', 'paths', 'methods', 'headers_allowed', 'auth_via_host_secret', 'ips_allowed'
        ], $path);

        return [
            'hosts' => array_values(array_unique(array_map('strval', $target['hosts']))),
            'schemes' => isset($target['schemes']) ? array_values(array_unique(array_map('strval', $target['schemes']))) : null,
            'ports' => isset($target['ports']) ? array_values($target['ports']) : null,
            'paths' => isset($target['paths']) ? array_values(array_unique(array_map('strval', $target['paths']))) : null,
            'methods' => array_values(array_unique(array_map(static fn($m) => strtoupper($m), $target['methods']))),
            'headers_allowed' => isset($target['headers_allowed']) ? array_values(array_unique(array_map('strval', $target['headers_allowed']))) : null,
            'auth_via_host_secret' => (bool)($target['auth_via_host_secret'] ?? true),
            'ips_allowed' => isset($target['ips_allowed']) ? array_values(array_unique(array_map('strval', $target['ips_allowed']))) : null,
        ];
    }

    /* ========================= Type: NOTIFY ========================= */

    private function validateNotifyTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        if (!$this->isStringList($target['channels'] ?? null, true)) {
            $this->boom("{$path}.channels", 'channels must be a non-empty array of strings', $errors);
        } else if ($this->allowedChannels !== []) {
            foreach ($target['channels'] as $i => $c) {
                if (!in_array($c, $this->allowedChannels, true)) {
                    $this->boom("{$path}.channels[{$i}]", "channel '{$c}' is not allowed by host", $errors);
                }
            }
        }
        if (isset($target['templates']) && !$this->isStringList($target['templates'])) {
            $this->boom("{$path}.templates", 'templates must be an array of strings', $errors);
        }
        if (isset($target['recipients']) && !$this->isStringList($target['recipients'])) {
            $this->boom("{$path}.recipients", 'recipients must be an array of strings', $errors);
        }
        $this->rejectUnknownKeys($target, ['channels', 'templates', 'recipients'], $path);

        return [
            'channels' => array_values(array_unique(array_map('strval', $target['channels']))),
            'templates' => isset($target['templates']) ? array_values(array_unique(array_map('strval', $target['templates']))) : null,
            'recipients' => isset($target['recipients']) ? array_values(array_unique(array_map('strval', $target['recipients']))) : null,
        ];
    }

    /* ========================= Type: MODULE ========================= */

    private function validateModuleTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }

        foreach (['plugin', 'apis'] as $req) {
            if (!array_key_exists($req, $target)) {
                $this->boom("{$path}.{$req}", "{$req} is required", $errors);
            }
        }

        // plugin must be a non-empty string
        $pluginDecl = $target['plugin'] ?? null;
        if (!is_string($pluginDecl) || $pluginDecl === '') {
            $this->boom("{$path}.plugin", 'plugin must be a non-empty string', $errors);
        }

        // apis must be a non-empty list of strings
        if (!$this->isStringList($target['apis'] ?? null, true)) {
            $this->boom("{$path}.apis", 'apis must be a non-empty array of strings', $errors);
        }

        $this->rejectUnknownKeys($target, ['plugin', 'apis'], $path);

        // ---- Host catalog check (alias or FQCN) ----
        $alias = null;
        $fqcn = null;
        $docs = null;

        if (array_key_exists($pluginDecl, $this->moduleAliasMap)) {
            // Declared as alias
            $alias = $pluginDecl;
            $fqcn = $this->moduleAliasMap[$alias]['map'];
            $docs = $this->moduleAliasMap[$alias]['docs'];
        } elseif (array_key_exists($pluginDecl, $this->moduleFqcnToAlias)) {
            // Declared as FQCN
            $fqcn = $pluginDecl;
            $alias = $this->moduleFqcnToAlias[$fqcn];
            $docs = $this->moduleAliasMap[$alias]['docs'] ?? null;
        } else if ($this->moduleAliasMap !== []) {
            $this->boom("{$path}.plugin", "unknown module '{$pluginDecl}' (not in host modules map)", $errors);
        } else {
            // No catalog -> accept free-form, but no alias/docs
            $fqcn = $pluginDecl;
        }

        return [
            'plugin' => (string)$pluginDecl,                                      // as declared
            'plugin_alias' => $alias,                                                   // normalized alias (if known)
            'plugin_fqcn' => $fqcn,                                                    // normalized FQCN (if known or free-form)
            'plugin_docs' => $docs,                                                    // host docs URL (if any)
            'apis' => array_values(array_unique(array_map('strval', $target['apis']))),
        ];
    }

    /* ========================= Type: CODEC ========================= */

    private function validateCodecTarget(mixed $target, string $path, array &$errors): ?string
    {
        if (!is_string($target)) {
            $this->boom($path, 'target must be the string "codec"', $errors);
            return null;
        }
        if ($target !== 'codec') {
            $this->boom($path, 'codec rule target must be "codec"', $errors);
        }
        return 'codec';
    }

    /**
     * Validate codec methods/groups + options, and return [resolved_methods, requires_unserialize_guard].
     */
    private function validateCodecMethodsAndGroups(mixed $methods, mixed $groups, mixed $options, string $path, array &$errors): array
    {
        $resolved = [];
        $needsGuard = false;

        // groups (optional)
        if ($groups !== null) {
            if (!$this->isStringList($groups)) {
                $this->boom("{$path}.groups", 'groups must be an array of strings', $errors);
            } else {
                foreach ($groups as $i => $g) {
                    if (!isset($this->codecGroups[$g])) {
                        $this->boom("{$path}.groups[{$i}]", "unknown codec group '{$g}'", $errors);
                        continue;
                    }

                    // If the group includes 'unserialize', require allowlist options
                    if (in_array('unserialize', $this->codecGroups[$g], true)) {
                        $needsGuard = true;
                    }

                    // Append methods directly (avoid array_merge in loop)
                    foreach ($this->codecGroups[$g] as $method) {
                        $resolved[] = $method;
                    }
                }
            }
        }

        // methods (optional | "*" | array)
        $wildcard = false;
        if ($methods !== null) {
            if ($methods === '*') {
                $wildcard = true;
                $needsGuard = true; // wildcard includes 'unserialize'
            } elseif ($this->isStringList($methods)) {
                foreach ($methods as $i => $m) {
                    if (!preg_match('/^[a-z0-9_]+$/', $m)) {
                        $this->boom("{$path}.methods[{$i}]", "invalid method name '{$m}'", $errors);
                    }
                    if ($m === 'unserialize') {
                        $needsGuard = true;
                    }
                    $resolved[] = $m;
                }
            } else {
                $this->boom("{$path}.methods", 'methods must be "*" or an array of strings', $errors);
            }
        }

        if ($methods === null && $groups === null) {
            $this->boom($path, 'codec rule requires one of: methods or groups', $errors);
        }

        // options (required if guard is needed)
        if ($needsGuard) {
            if (!is_array($options) || !array_key_exists('allow_unserialize_classes', $options)) {
                $this->boom("{$path}.options", 'options.allow_unserialize_classes is required when methods="*" or includes "unserialize", or groups include "serialize"', $errors);
            } else if (!is_array($options['allow_unserialize_classes'])) {
                $this->boom("{$path}.options.allow_unserialize_classes", 'must be an array (empty array = no classes allowed)', $errors);
            } else {
                foreach ($options['allow_unserialize_classes'] as $i => $cls) {
                    if (!is_string($cls) || $cls === '') {
                        $this->boom("{$path}.options.allow_unserialize_classes[{$i}]", 'class name must be a non-empty string', $errors);
                    }
                }
            }
        } elseif ($options !== null) {
            $this->rejectUnknownKeys($options, ['allow_unserialize_classes'], "{$path}.options");
        }

        // Normalize resolved methods
        if ($wildcard) {
            $resolved = '*';
        } else {
            $resolved = array_values(array_unique($resolved));
        }

        return [$resolved, $needsGuard];
    }

    /* ========================= Common validators ========================= */

    private function validateAudit(mixed $audit, string $path, array &$errors): void
    {
        if (!is_array($audit)) {
            $this->boom($path, 'audit must be an object', $errors);
            return;
        }
        $this->rejectUnknownKeys($audit, ['log', 'redact_fields', 'tags'], $path);
        if (isset($audit['log']) && !in_array($audit['log'], ['always', 'on_deny', 'never'], true)) {
            $this->boom("{$path}.log", "log must be 'always', 'on_deny' or 'never'", $errors);
        }
        if (isset($audit['redact_fields']) && !$this->isStringList($audit['redact_fields'])) {
            $this->boom("{$path}.redact_fields", 'redact_fields must be an array of strings', $errors);
        }
        if (isset($audit['tags']) && !$this->isStringList($audit['tags'])) {
            $this->boom("{$path}.tags", 'tags must be an array of strings', $errors);
        }
    }

    private function validateConditions(mixed $cond, string $path, array &$errors): ?array
    {
        if (!is_array($cond)) {
            $this->boom($path, 'conditions must be an object', $errors);
            return null;
        }
        $this->rejectUnknownKeys($cond, ['setting_link', 'guard', 'env'], $path);

        $out = ['setting_link' => null, 'guard' => null, 'env' => null];

        if (array_key_exists('setting_link', $cond)) {
            $v = $cond['setting_link'];
            if (!is_string($v) && !is_int($v) && !is_float($v)) {
                $this->boom("{$path}.setting_link", 'setting_link must be a string or number', $errors);
            } else {
                $out['setting_link'] = $v;
            }
        }
        if (array_key_exists('guard', $cond)) {
            if (!is_string($cond['guard']) || $cond['guard'] === '') {
                $this->boom("{$path}.guard", 'guard must be a non-empty string', $errors);
            } else {
                $out['guard'] = $cond['guard'];
            }
        }
        if (array_key_exists('env', $cond)) {
            $env = $cond['env'];
            if (!is_array($env)) {
                $this->boom("{$path}.env", 'env must be an object with allow/deny arrays', $errors);
            } else {
                $this->rejectUnknownKeys($env, ['allow', 'deny'], "{$path}.env");
                foreach (['allow', 'deny'] as $k) {
                    if (isset($env[$k]) && !$this->isStringList($env[$k])) {
                        $this->boom("{$path}.env.{$k}", "{$k} must be an array of strings", $errors);
                    }
                }
                $out['env'] = [
                    'allow' => isset($env['allow']) ? array_values(array_unique(array_map('strval', $env['allow']))) : null,
                    'deny' => isset($env['deny']) ? array_values(array_unique(array_map('strval', $env['deny']))) : null,
                ];
            }
        }

        return $out;
    }

    /* ========================= Helpers ========================= */

    private function decodeJson(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('Manifest root must decode to an object');
        }
        return $data;
    }

    private function isStringList(mixed $v, bool $nonEmpty = false): bool
    {
        if (!is_array($v)) return false;
        if ($nonEmpty && $v === []) return false;
        foreach ($v as $s) {
            if (!is_string($s)) return false;
        }
        return count($v) === count(array_unique($v));
    }

    private function rejectUnknownKeys(array $obj, array $allowed, string $path): void
    {
        $unknown = array_diff(array_keys($obj), $allowed);
        if ($unknown) {
            $keys = implode(', ', $unknown);
            throw new InvalidArgumentException("{$path}: unknown field(s): {$keys}");
        }
    }

    private function boom(string $path, string $msg, array &$errors): void
    {
        $errors[] = "{$path}: {$msg}";
    }

    /** Config helper that safely no-ops outside Laravel. */
    private function readConfig(string $key, mixed $default): mixed
    {
        if (function_exists('config')) {
            return config($key, $default);
        }
        return $default;
    }

    /**
     * Normalize channels list: supports ['email','sms'] or ['email'=>true,'sms'=>true].
     * Returns a unique list of strings.
     */
    private function normalizeChannels(array $channels): array
    {
        return HostConfigNormalizer::notificationChannels($channels);
    }

    /**
     * Normalize models map. Ensures 'map' (FQCN), optional 'relations', and optional
     * 'columns' policy with 'all' and 'writable' (writable ⊆ all).
     * @param array<string,mixed> $models
     * @return array<string,array{map:string,relations:array<string,string>,columns:array{all?:array,writable?:array}}>
     */
    private function normalizeModels(array $models): array
    {
        return HostConfigNormalizer::models($models);
    }

    /**
     * Normalize modules map. Ensures 'map' (FQCN) exists; 'docs' optional string.
     * @param array<string,mixed> $modules
     * @return array<string,array{map:string,docs:?string}>
     */
    private function normalizeModules(array $modules): array
    {
        return HostConfigNormalizer::modules($modules);
    }
}
```

---
#### 15


` File: src/Core/Security/PluginSecurityScanner.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpUndefinedVariableInspection */

/** @noinspection NotOptimalIfConditionsInspection */

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\UnionType;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use Timeax\FortiPlugin\Core\Security\Concerns\ResolvesNames;

/**
 * PluginSecurityScanner: Extensible, policy-driven, always-forbidden-aware PHP plugin validator
 */
class PluginSecurityScanner extends NodeVisitorAbstract
{
    use ResolvesNames;

    protected array $config;
    protected PluginPolicy $policy;
    protected array $aliases = []; // Aliases (from use statements) in this file
    protected array $matches = [];
    /** @var array<string, string[]>  lower(fqcn) => [lower(method)...] */
    private array $classAllowlist = [];
    /** @var array<string,string> $variableTypes // $var => FQCN (no leading \) */
    private array $variableTypes = [];
    /** @var array<string,string> $classNameVars // $var => FQCN (no leading \) */
    private array $classNameVars = [];
    protected CallGraphAnalyzer $callGraphAnalyzer;
    protected mixed $currentFile = null;

    protected array $variableValues = [];
    protected array $superglobals = ['_GET', '_POST', '_REQUEST', '_COOKIE', '_FILES', '_SESSION', '_SERVER', '_ENV'];

    public function __construct(array $config = null, $filePath = null)
    {
        $this->policy = new PluginPolicy($config);
        $this->callGraphAnalyzer = new CallGraphAnalyzer($this->policy);
        $this->config = $config;
        $this->currentFile = $filePath;
    }

    public function getPolicy(): PluginPolicy
    {
        return $this->policy;
    }

    public function setCurrentFile(string $file): static
    {
        $this->currentFile = $file;
        return $this;
    }

    private function initClassAllowlist(): void
    {
        $raw = $this->policy->getBlocklist() ?? [];
        $this->classAllowlist = $this->normalizeBlocklist($raw);
    }

    private function normalizeBlocklist(array $map): array
    {
        $out = [];
        foreach ($map as $class => $methods) {
            if (!is_array($methods)) continue;
            $key = $this->normClassKey($class);
            $out[$key] = array_values(array_unique(array_map('strtolower', $methods)));
        }
        return $out;
    }

    private function normClassKey(?string $name): string
    {
        return strtolower(ltrim((string)$name, '\\'));
    }

    /**
     * Scan a raw PHP source string and return violations.
     * - Runs NameResolver (FQCN / function names)
     * - Connects parent pointers and tags parent_class
     * - Builds call graph (functions/methods) for indirect-return checks
     * - Traverses with this scanner as a visitor
     *
     * @param string $phpSource
     * @param string|null $filePath Optional file path for context in reports
     * @return array                 Flat list of violation records
     */
    public function scanSource(string $phpSource, ?string $filePath = null): array
    {
        if (property_exists($this, 'currentFile')) {
            $this->currentFile = $filePath ?? $this->currentFile ?? '[source]';
        }

        // 1) Parse
        $parser = (new ParserFactory())->createForHostVersion();
        try {
            $ast = $parser->parse($phpSource);
        } catch (Throwable $e) {
            return [[
                'type' => 'parse_error',
                'error' => $e->getMessage(),
                'file' => $filePath ?? '[source]',
                'line' => 0,
                'snippet' => '',
            ]];
        }
        if (!$ast) {
            return [];
        }

        // 2) Name resolution (fully-qualify names), then parent pointers
        $trResolve = new NodeTraverser();
        $trResolve->addVisitor(new NameResolver(options: [
            'preserveOriginalNames' => true,
            'replaceNodes' => true, // rewrite Name nodes to FullyQualified
        ]));
        $trResolve->addVisitor(new ParentConnectingVisitor());
        $ast = $trResolve->traverse($ast);

        $this->initClassAllowlist();

        // 3) Tag every node with its enclosing class ("parent_class") for easy lookups
        $trClassTag = new NodeTraverser();
        $trClassTag->addVisitor(new class extends NodeVisitorAbstract {
            private ?Node\Stmt\Class_ $current = null;

            public function enterNode(Node $node): void
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->current = $node;
                }
                if ($this->current && $node !== $this->current) {
                    $node->setAttribute('parent_class', $this->current);
                }
            }

            public function leaveNode(Node $node): void
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->current = null;
                }
            }
        });
        $ast = $trClassTag->traverse($ast);

        // 4) Build (or reuse) the call graph index for indirect-return checks
        if (!property_exists($this, 'callGraphAnalyzer')) {
            // assumes $this->policy (PluginPolicy) exists on the scanner
            $this->callGraphAnalyzer = new CallGraphAnalyzer($this->policy);
        }
        $this->callGraphAnalyzer->collect([$ast]);

        // 5) Security scan — treat this scanner as a NodeVisitor
        $trScan = new NodeTraverser();
        $trScan->addVisitor($this); // $this must extend NodeVisitorAbstract
        $trScan->traverse($ast);

        // 6) Return flat list of matches (whatever your scanner accumulates)
        return method_exists($this, 'getMatches') ? $this->getMatches() : [];
    }

    /**
     * Parity helper: read a file and delegate to scanSource().
     */
    public function scanFile(string $filePath): array
    {
        $code = @file_get_contents($filePath);
        if ($code === false) {
            return [[
                'type' => 'read_error',
                'file' => $filePath,
                'line' => 0,
                'snippet' => '',
                'issue' => 'Unable to read file',
            ]];
        }

        return $this->scanSource($code, $filePath);
    }

    public function getFileErrors(): array
    {
        $errors = $this->matches;
        $this->matches = [];
        return $errors;
    }

    // Pass in alias map after first pass (see below)
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    /**
     * Track simple variable assignments for:
     *  - string/concat/superglobal values (existing behavior → $this->variableValues)
     *  - instance types from `new \Fqcn(...)` and simple `$b = $a` propagation (→ $this->variableTypes)
     */
    /**
     * Track simple variable assignments for:
     *  - string/concat/superglobal values (→ $this->variableValues)
     *  - class literals via ::class and dynamic-new resolution (→ $this->classNameVars, $this->variableTypes)
     *  - instance types from `new \Fqcn(...)` and simple `$b = $a` propagation (→ $this->variableTypes)
     */
    public function trackAssignments($node): void
    {
        // $x = ...   or   $x =& ...
        if (($node instanceof Assign || $node instanceof AssignRef)
            && $node->var instanceof Variable
            && is_string($node->var->name)) {

            $varName = $node->var->name;
            $expr = $node->expr; // same for AssignRef

            // Reset stale info unless set below
            unset($this->variableValues[$varName], $this->variableTypes[$varName], $this->classNameVars[$varName]);

            // ── value tracking (strings / concat / superglobals)
            if ($expr instanceof String_) {
                $this->variableValues[$varName] = $expr->value;
                return;
            }

            if ($expr instanceof Node\Expr\BinaryOp\Concat) {
                $this->variableValues[$varName] = $this->stringifyDynamic($expr);
                return;
            }

            if ($expr instanceof Node\Expr\ArrayDimFetch
                && $expr->var instanceof Variable
                && is_string($expr->var->name)
                && in_array($expr->var->name, $this->superglobals, true)) {
                $this->variableValues[$varName] = '{superglobal}';
                return;
            }

            // ── class literal: $class = A::class; (imported or FQCN)
            if ($expr instanceof Node\Expr\ClassConstFetch
                && $expr->name instanceof Identifier
                && strtolower($expr->name->toString()) === 'class') {

                $fq = null;
                if ($expr->class instanceof Name) {
                    $fq = $this->fqNameOf($expr->class) ?? $expr->class->toString();
                } /** @noinspection PhpConditionAlreadyCheckedInspection */ elseif (is_string($expr->class)) {
                    $fq = $expr->class;
                }
                if ($fq) {
                    $this->classNameVars[$varName] = ltrim($fq, '\\');
                }
                return;
            }

            // ── dynamic new via class var: $obj = new $class();
            if ($expr instanceof New_
                && $expr->class instanceof Variable
                && is_string($expr->class->name)) {

                $clsVar = $expr->class->name;
                if (isset($this->classNameVars[$clsVar])) {
                    $this->variableTypes[$varName] = $this->classNameVars[$clsVar];
                    return;
                }
            }

            // ── direct instance type: $x = new \Vendor\Class(...);
            if ($expr instanceof New_) {
                $fq = $this->getClassName($expr->class); // resolver-aware in your codebase
                if ($fq) {
                    $this->variableTypes[$varName] = ltrim($fq, '\\');
                }
                return;
            }

            // ── simple propagation: $b = $a;  (carry value/type/class-literal if known)
            if ($expr instanceof Variable && is_string($expr->name)) {
                if (array_key_exists($expr->name, $this->variableValues)) {
                    $this->variableValues[$varName] = $this->variableValues[$expr->name];
                }
                if (array_key_exists($expr->name, $this->variableTypes)) {
                    $this->variableTypes[$varName] = $this->variableTypes[$expr->name];
                }
                if (array_key_exists($expr->name, $this->classNameVars)) {
                    $this->classNameVars[$varName] = $this->classNameVars[$expr->name];
                }
                return;
            }

            // Anything else → leave unset (unknown)
            return;
        }

        // unset($x) — forget tracked info
        if ($node instanceof Node\Stmt\Unset_) {
            foreach ($node->vars as $v) {
                if ($v instanceof Variable && is_string($v->name)) {
                    unset($this->variableValues[$v->name], $this->variableTypes[$v->name], $this->classNameVars[$v->name]);
                }
            }
        }
    }

    /**
     * @throws JsonException
     */
    public function enterNode(Node $node): void
    {
        $this->trackAssignments($node);

        // -- 1. ALWAYS FORBIDDEN CHECKS --
        // A. Functions
        if ($node instanceof Node\Expr\FuncCall) {
            $fname = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;

            if ($fname && $this->policy->isForbiddenFunction($fname)) {
                $this->report('always_forbidden_function', ['function' => $fname], $node);
            }

            // Wrapper stream usage in file ops
            if (in_array($fname, ['fopen', 'file_get_contents', 'file_put_contents', 'file', 'readfile'], true) && !empty($node->args[0])) {
                $arg = $node->args[0]->value;
                if ($arg instanceof String_) {
                    $path = $arg->value;
                    foreach ($this->policy->getForbiddenWrappers() as $prefix) {
                        if (stripos($path, $prefix) === 0) {
                            $this->report('always_forbidden_wrapper_stream', [
                                'function' => $fname, 'value' => $path
                            ], $node);
                        }
                    }
                }
            }
        }

        if ($node instanceof Eval_) {
            $this->report(
                'always_forbidden_function',
                ['function' => 'eval'],
                $node,
                'critical'
            );
        }

        // B. Reflection classes (instantiation, static, instanceof, type hint)
        if (
            ($node instanceof New_ && $this->isReflectionClass($node->class)) ||
            ($node instanceof StaticCall && $this->isReflectionClass($node->class)) ||
            ($node instanceof Node\Expr\Instanceof_ && $this->isReflectionClass($node->class)) ||
            ($node instanceof Node\Param && $node->type && $this->isReflectionClass($node->type))
        ) {
            $class = $this->getClassName($node->class ?? $node->type);
            $this->report('always_forbidden_reflection', ['class' => $class], $node);
        }

        // C. Forbidden magic method definitions
        if ($node instanceof Node\Stmt\ClassMethod) {
            $mname = strtolower($node->name->toString());
            if (in_array($mname, $this->policy->getForbiddenMagicMethods(), true)) {
                $this->report('always_forbidden_magic_method', ['method' => $node->name->toString()], $node);
            }
        }

        // D. Dynamic includes/requires
        if ($node instanceof Node\Expr\Include_) {
            if (!($node->expr instanceof String_)) {
                $this->report('always_forbidden_dynamic_include', [
                    'expr_type' => get_class($node->expr)
                ], $node);
            } else {
                $path = $node->expr->value;
                foreach ($this->policy->getForbiddenWrappers() as $prefix) {
                    if (stripos($path, $prefix) === 0) {
                        $this->report('always_forbidden_wrapper_stream_include', [
                            'value' => $path
                        ], $node);
                    }
                }
            }
        }

        // E. Callback/handler registration with forbidden function
        if ($node instanceof Node\Expr\FuncCall) {
            $regName = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;
            if (in_array($regName, [
                    'register_shutdown_function',
                    'set_error_handler',
                    'set_exception_handler',
                    'register_tick_function'
                ], true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;
                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if (in_array($cbFunc, $this->policy->getForbiddenFunctions(), true)) {
                        $this->report('always_forbidden_callback_to_forbidden_function', [
                            'registration' => $regName, 'callback' => $cbFunc
                        ], $node);
                    }
                }
            }
        }

        // F. Obfuscated eval (eval(obfuscator(...)))
        if ($node instanceof Eval_) {
            $payload = $node->expr;

            // 1) Single-level: eval(obfuscator(...))
            if ($payload instanceof Node\Expr\FuncCall && $payload->name instanceof Node\Name) {
                $inner = strtolower($this->fqNameOf($payload->name) ?? '');
                if ($inner && in_array($inner, $this->policy->getObfuscators(), true)) {
                    $this->report('always_forbidden_obfuscated_eval', [
                        'outer' => 'eval',
                        'inner' => $inner
                    ], $node, 'critical');
                }
            }

            // 2) Nested chains: eval(gzinflate(base64_decode(...)))
            $chain = $this->callGraphAnalyzer->collectFuncCallChain($payload); // ['gzinflate','base64_decode', ...]
            if ($chain && count($chain) > 1 && array_intersect($chain, $this->policy->getObfuscators())) {
                $this->report('always_forbidden_obfuscated_eval', [
                    'outer' => 'eval',
                    'chain' => $chain
                ], $node, 'critical');
            }
        }

        // -- 2. CONFIGURABLE DANGEROUS/POLICY CHECKS --
        // A. Dangerous/risky functions (from config overlays)
        if ($node instanceof Node\Expr\FuncCall) {
            $fname = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;

            if ($fname) {
                $cfgDanger = array_map('strtolower', $this->config['dangerous_functions'] ?? []);
                $cfgTokens = array_map('strtolower', $this->config['tokens'] ?? []);

                if (in_array($fname, $cfgDanger, true)) {
                    $this->report('config_dangerous_function', ['function' => $fname], $node);
                }
                if (in_array($fname, $cfgTokens, true)) {
                    $this->report('config_risky_function', ['function' => $fname], $node);
                }
            }
        }

        // B. Class/method blocklist (effective allowlist)
        if ($node instanceof StaticCall) {
            $class = $this->getClassName($node->class); // keep: already upgraded
            $method = $node->name instanceof Identifier ? strtolower($node->name->toString()) : null;

            if ($class && $method) {
                $blocklist = $this->policy->getBlocklist(); // merged with overrides
                if (isset($blocklist[$class])) {
                    $allowed = $blocklist[$class];
                    if (!in_array('*', $allowed, true) && !in_array($method, $allowed, true)) {
                        $this->report('config_blocked_method', [
                            'class' => $class, 'method' => $method
                        ], $node);
                    }
                }
            }
        }

        // C. Warn on large files (scan_size)
        if (isset($this->config['scan_size']) && $this->currentFile) {
            $ext = strtolower(pathinfo($this->currentFile, PATHINFO_EXTENSION));
            if (isset($this->config['scan_size'][$ext])) {
                $max = (int)$this->config['scan_size'][$ext];
                $size = @filesize($this->currentFile);
                if ($size !== false && $size > $max) {
                    $this->report('config_file_too_large', [
                        'file' => $this->currentFile, 'max_bytes' => $max
                    ], $node);
                }
            }
        }

        // -- 3. ADVANCED BACKDOOR/HEURISTIC CHECKS (SAMPLE) --
        $this->runBlocklist($node);
        $this->runNamespaceCheck($node);
        $this->advancedBackdoorDetection($node);
    }

    // Helper: check if class is Reflection*

    /** Return fully-qualified class-like names from a type-ish node. */
    private function extractTypeNames(Name|Identifier|NullableType|UnionType|IntersectionType|Node|string|null $node): array
    {
        // Fully-qualified or resolved simple names
        if ($node instanceof Name || $node instanceof Identifier) {
            $n = $this->fqNameOf($node);
            return $n ? [ltrim($n, '\\')] : [];
        }

        // ?T
        if ($node instanceof NullableType) {
            return $this->extractTypeNames($node->type);
        }

        // T1|T2
        if ($node instanceof UnionType) {
            $out = [];
            foreach ($node->types as $t) {
                foreach ($this->extractTypeNames($t) as $n) {
                    $out[] = $n;
                }
            }
            return array_values(array_unique($out));
        }

        if ($node instanceof IntersectionType) {
            $out = [];
            foreach ($node->types as $t) {
                foreach ($this->extractTypeNames($t) as $n) {
                    $out[] = $n;
                }
            }
            return array_values(array_unique($out));
        }

        // plain string (rare here)
        if (is_string($node)) {
            return [ltrim($node, '\\')];
        }

        // Anything dynamic (Variable/Expr/etc.) → we can’t resolve safely
        return [];
    }

    /** True if any resolved name is a Reflection* class (policy-driven). */
    private function isReflectionClass(Name|Identifier|NullableType|UnionType|IntersectionType|Node|string|null $node): bool
    {
        foreach ($this->extractTypeNames($node) as $fqcn) {
            if ($this->policy->isForbiddenReflection($fqcn)) {
                return true;
            }
        }
        return false;
    }

    /** Safe, best-effort single class-like name (for reporting). */
    private function safeClassLikeName(Name|Identifier|NullableType|UnionType|IntersectionType|Node|string|null $node): ?string
    {
        $names = $this->extractTypeNames($node);
        return $names[0] ?? null;
    }

    // Helper: get class name from node/identifier/string
    private function getClassName($classNode): ?string
    {
        return $this->fqNameOf($classNode);
    }

    // Resolve class using aliases map
    private function resolveClassName($classNode)
    {
        $class = $this->getClassName($classNode);
        if ($class && isset($this->aliases[$class])) return $this->aliases[$class];
        return $class;
    }

    private function stringifyConcat($expr): string
    {
        // Recursively flatten simple string concat
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return $this->stringifyConcat($expr->left) . $this->stringifyConcat($expr->right);
        }
        if ($expr instanceof String_) {
            return $expr->value;
        }
        return '{dynamic}';
    }

    // Report a finding
    private function report($type, $data, $node, $severity = 'high'): void
    {
        $this->matches[] = array_merge([
            'type' => $type,
            'severity' => $severity,
            'line' => $node->getLine(),
            'file' => $this->currentFile,
        ], $data);
    }

    public function getMatches(): array
    {
        return $this->matches;
    }

    protected function runBlocklist($node): void
    {
        // Static calls: \Vendor\Class::method()
        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $class = $this->getClassName($node->class); // resolver-aware in your code
            $meth = strtolower($node->name->toString());
            $this->enforceClassAllowlist($class, $meth, $node, true);
        }

        // Instance calls: $this->method()
        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $meth = strtolower($node->name->toString());

            // $this->method() → enclosing class
            if ($node->var instanceof Variable && $node->var->name === 'this') {
                $class = $this->enclosingClassName($node);
                $this->enforceClassAllowlist($class, $meth, $node, false);
            }

            // $x->method() → if we know $x is an instance of FQCN
            if ($node->var instanceof Variable && is_string($node->var->name)) {
                $fq = $this->variableTypes[$node->var->name] ?? null;
                if ($fq) {
                    $this->enforceClassAllowlist($fq, $meth, $node, false);
                }
            }
        }

        // Nullsafe calls: $x?->method()
        if ($node instanceof NullsafeMethodCall && $node->name instanceof Identifier) {
            $meth = strtolower($node->name->toString());
            if ($node->var instanceof Variable && is_string($node->var->name)) {
                $fq = $this->variableTypes[$node->var->name] ?? null;
                if ($fq) {
                    $this->enforceClassAllowlist($fq, $meth, $node, false);
                }
            }
        }
    }

    private function enforceClassAllowlist(?string $class, string $method, Node $node, bool $isStatic): void
    {
        if (!$class) return;

        $fqcn = ltrim($class, '\\');
        $key = $this->normClassKey($fqcn);

        // Only enforce for classes present in the policy map
        if (!array_key_exists($key, $this->classAllowlist)) return;

        $allowed = $this->classAllowlist[$key];

        // Semantics: if a class is listed, ONLY these methods are allowed.
        // An empty array => no methods allowed.
        if (!in_array($method, $allowed, true)) {
            $this->report(
                'config_blocked_method',
                ['class' => $fqcn, 'method' => $method, 'call' => $isStatic ? 'static' : 'instance'],
                $node,
                'critical'
            );
        }
    }

    protected function runForbiddenFuncCall($node, $checkReturns = false): void
    {
        $calledName = null;

        // direct eval
        if ($node instanceof Eval_) {
            $this->report('return_forbidden_function', ['function' => 'eval'], $node, 'critical');
            return;
        }

        // plain function call: foo()
        if ($node instanceof Node\Expr\FuncCall) {
            $calledName = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;

            if ($calledName && ($this->policy->isForbiddenFunction($calledName) || $this->policy->isUnsupportedFunction($calledName))) {
                $this->report('return_forbidden_function', ['function' => $calledName], $node, 'critical');
            }

            if ($checkReturns && $calledName && isset($this->callGraphAnalyzer) &&
                $this->callGraphAnalyzer->hasForbiddenReturnChain($calledName)) {
                $this->report('return_indirect_forbidden_chain', ['chain' => $calledName], $node, 'critical');
            }

            return; // handled
        }

        if (!$checkReturns || !isset($this->callGraphAnalyzer)) {
            return;
        }

        // static method call: ClassName::method()
        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $class = $this->getClassName($node->class); // already resolver-aware in your codebase
            $method = strtolower($node->name->toString());

            if ($class && $method &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($class, $method)) {
                $this->report('return_indirect_forbidden_chain', [
                    'chain' => $class . '::' . $method
                ], $node, 'critical');
            }
            return;
        }

        // instance method call on $this: $this->method()
        if ($node instanceof MethodCall && $node->name instanceof Identifier && $node->var instanceof Variable && $node->var->name === 'this') {
            $classNode = $node->getAttribute('parent_class'); // set in scanSource() prepass
            $className = null;
            if ($classNode instanceof Node\Stmt\Class_) {
                // prefer namespacedName from NameResolver; fallback to local name
                $className = isset($classNode->namespacedName)
                    ? ltrim($classNode->namespacedName->toString(), '\\')
                    : ($classNode->name?->toString());
            }

            $method = strtolower($node->name->toString());

            if ($className && $method &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $method)) {
                $this->report('return_indirect_forbidden_chain', [
                    'chain' => $className . '::' . $method
                ], $node, 'critical');
            }
        }
    }

    protected function runNamespaceCheck(Node $node): void
    {
        // Helper: normalized check against policy (after NameResolver)
        $isForbidden = function (?string $ns): bool {
            if (!$ns) return false;
            $ns = ltrim($ns, '\\');
            return $ns !== '' && $this->policy->isForbiddenNamespace($ns);
        };

        // 1) use Foo\Bar;  use function Foo\bar;  use const Foo\BAR;
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                // Note: NameResolver does not set resolvedName for use-imports; build from token.
                $full = ltrim($this->fqNameOf($use->name) ?? $use->name->toString(), '\\');

                $kind = match ($use->type) {
                    Node\Stmt\Use_::TYPE_FUNCTION => 'function',
                    Node\Stmt\Use_::TYPE_CONSTANT => 'const',
                    default => 'class',
                };

                if ($isForbidden($full)) {
                    $this->report(
                        'forbidden_namespace_import' . ($kind !== 'class' ? "_$kind" : ''),
                        ['namespace' => $full, 'kind' => $kind],
                        $node,
                        'critical'
                    );
                }
            }
            return;
        }

        // 1b) use Prefix\{A, B as C, function f, const X};
        if ($node instanceof Node\Stmt\GroupUse) {
            $prefix = $this->fqNameOf($node->prefix) ?? $node->prefix->toString();
            $prefix = rtrim($prefix, '\\');

            foreach ($node->uses as $use) {
                // Each leaf can optionally carry its own type; fall back to group type.
                $type = ($use->type !== 0) ? $use->type : $node->type;
                $kind = match ($type) {
                    Node\Stmt\Use_::TYPE_FUNCTION => 'function',
                    Node\Stmt\Use_::TYPE_CONSTANT => 'const',
                    default => 'class',
                };

                $leaf = $use->name->toString();              // e.g. "DB" or "Route"
                $full = ltrim($prefix . '\\' . $leaf, '\\'); // Prefix\Leaf

                if ($isForbidden($full)) {
                    $this->report(
                        'forbidden_namespace_import' . ($kind !== 'class' ? "_$kind" : ''),
                        ['namespace' => $full, 'kind' => $kind],
                        $node,
                        'critical'
                    );
                }
            }
            return;
        }

        // 1c) Trait imports inside classes: use Some\TraitName;
        if ($node instanceof Node\Stmt\TraitUse) {
            foreach ($node->traits as $t) {
                $full = ltrim($this->fqNameOf($t) ?? $t->toString(), '\\');
                if ($isForbidden($full)) {
                    $this->report(
                        'forbidden_namespace_trait_use',
                        ['namespace' => $full],
                        $node,
                        'critical'
                    );
                }
            }
            // continue scanning other checks below (no early return)
        }

        // 2) References in expressions: new, static call, const fetch, instanceof
        if (
            $node instanceof New_
            || $node instanceof StaticCall
            || $node instanceof Node\Expr\ClassConstFetch
            || $node instanceof Node\Expr\Instanceof_
        ) {
            $class = $this->getClassName($node->class ?? null); // resolver-aware in your codebase
            if ($isForbidden($class)) {
                $this->report(
                    'forbidden_namespace_reference',
                    ['namespace' => $class],
                    $node,
                    'critical'
                );
            }
        }

        // 3) Class extends / implements
        if ($node instanceof Node\Stmt\Class_) {
            if ($node->extends) {
                $parent = ltrim($this->fqNameOf($node->extends) ?? $node->extends->toString(), '\\');
                if ($isForbidden($parent)) {
                    $this->report('forbidden_namespace_extends', ['namespace' => $parent], $node, 'critical');
                }
            }
            foreach ($node->implements as $impl) {
                $iface = ltrim($this->fqNameOf($impl) ?? $impl->toString(), '\\');
                if ($isForbidden($iface)) {
                    $this->report('forbidden_namespace_implements', ['namespace' => $iface], $node, 'critical');
                }
            }
        }

        // 4) String references to classes (e.g. "$c = 'GuzzleHttp\\Client';")
        if ($node instanceof String_) {
            $str = ltrim($node->value, '\\');
            if ($isForbidden($str)) {
                $this->report(
                    'forbidden_namespace_string_reference',
                    ['namespace' => $node->value],
                    $node
                );
            }
        }
    }

    /**
     * @throws JsonException
     */
    public function advancedBackdoorDetection($node): void
    {
        // Backdoor 1 - Variable/dynamic function calls
        if ($node instanceof Node\Expr\FuncCall) {
            // 1. Variable function: $func()
            if ($node->name instanceof Variable) {
                $funcVar = $node->name->name;
                $resolved = $this->resolvedVarString($funcVar);

                $reportType = 'backdoor_variable_function_call';
                $severity = 'high';
                $extra = [
                    'var' => is_string($funcVar) ? $funcVar : json_encode($funcVar, JSON_THROW_ON_ERROR)
                ];

                if ($resolved && $this->policy->isForbiddenFunction($resolved)) {
                    $severity = 'critical';
                    $reportType = 'backdoor_variable_function_call_chain_forbidden';
                    $extra['resolved_function'] = $resolved;
                } elseif ($resolved && isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($resolved)) {
                    $severity = 'critical';
                    $reportType = 'backdoor_variable_function_call_chain_forbidden';
                    $extra['resolved_function'] = $resolved;
                }

                $this->report($reportType, $extra, $node, $severity);
            }

            // 2. Dynamic concat: ("eva"."l")()
            if ($node->name instanceof Node\Expr\BinaryOp\Concat) {
                $exprStr = strtolower($this->stringifyConcat($node->name));
                $severity = 'high';
                $type = 'backdoor_concat_function_call_unknown';

                if ($this->policy->isForbiddenFunction($exprStr)) {
                    $severity = 'critical';
                    $type = 'backdoor_concat_function_call_always_forbidden';
                } elseif ($this->policy->isUnsupportedFunction($exprStr)) {
                    $type = 'backdoor_concat_function_call_unsupported';
                } elseif (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($exprStr)) {
                    $severity = 'critical';
                    $type = 'backdoor_concat_function_call_chain_forbidden';
                }

                $this->report($type, ['expression' => $exprStr], $node, $severity);
            }

            // 3. Direct call by resolved name
            if ($node->name instanceof Node\Name) {
                $name = strtolower($this->fqNameOf($node->name) ?? $node->name->toString());
                if ($this->policy->isForbiddenFunction($name)) {
                    $this->report('always_forbidden_function', ['function' => $name], $node, 'critical');
                } elseif ($this->policy->isUnsupportedFunction($name)) {
                    $this->report('unsupported_function', ['function' => $name], $node);
                } elseif (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                    $this->report('function_call_chain_forbidden', ['function' => $name], $node, 'critical');
                }
            }
        }

        // Backdoor 2 - Closures & callbacks
        if ($node instanceof Node\Expr\FuncCall) {
            $callbackFunctions = array_map('strtolower', $this->policy->getCallbackFunctions());
            $name = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? $node->name->toString()) : null;

            if ($name && in_array($name, $callbackFunctions, true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;

                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if ($this->policy->isForbiddenFunction($cbFunc)) {
                        $this->report('callback_always_forbidden', [
                            'function' => $name, 'callback' => $cbFunc
                        ], $node, 'critical');
                    } elseif ($this->policy->isUnsupportedFunction($cbFunc)) {
                        $this->report('callback_unsupported', [
                            'function' => $name, 'callback' => $cbFunc
                        ], $node);
                    } elseif (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($cbFunc)) {
                        $this->report('callback_user_defined_forbidden_chain', [
                            'function' => $name,
                            'callback_chain' => $cbFunc
                        ], $node, 'critical');
                    }
                } elseif ($cb instanceof Node\Expr\Closure || $cb instanceof Node\Expr\ArrowFunction) {
                    $danger = false;
                    foreach ($cb->getStmts() as $stmt) {
                        $danger = $this->closureScan($stmt);
                        if ($danger) break;
                    }
                    if ($danger) {
                        $this->report('callback_closure_forbidden', [
                            'function' => $name,
                            'closure_dangerous' => true
                        ], $node, 'critical');
                    }
                }
            }
        }

        // Backdoor 3 - Dynamic class instantiation
        if ($node instanceof New_) {
            if ($node->class instanceof Variable) {
                $classVar = $node->class->name;
                $resolved = is_string($classVar) ? ($this->variableValues[$classVar] ?? null) : null;

                if ($resolved === '{superglobal}') {
                    $this->report('backdoor_dynamic_class_instantiation_superglobal', [
                        'expression' => '$' . (is_string($classVar) ? $classVar : '{dynamic}'),
                    ], $node, 'critical');
                } elseif ($resolved) {
                    if ($this->policy->isForbiddenReflection($resolved) || $this->policy->isForbiddenNamespace(ltrim($resolved, '\\'))) {
                        $this->report('backdoor_dynamic_class_instantiation_forbidden', [
                            'resolved_class' => $resolved,
                            'expression' => '$' . (is_string($classVar) ? $classVar : '{dynamic}'),
                        ], $node, 'critical');
                    }
                } else {
                    $this->report('backdoor_dynamic_class_instantiation_unresolved', [
                        'expression' => '$' . (is_string($classVar) ? $classVar : '{dynamic}'),
                    ], $node);
                }
            } elseif (!($node->class instanceof Node\Name)) {
                $classStr = $this->stringifyDynamic($node->class);
                $this->report('backdoor_dynamic_class_instantiation_complex', [
                    'expression' => $classStr,
                ], $node);
            }
        }

        // Backdoor 4 & 11 (unified) - Dynamic member access (method/property)
        if ($node instanceof MethodCall && $node->name instanceof Variable) {
            $methodVar = $node->name->name;
            $resolved = $this->resolvedVarString($methodVar);
            $className = $this->enclosingClassName($node);

            $this->handleDynamicMember('method', $className, $resolved, '$' . (is_string($methodVar) ? $methodVar : '{dynamic}'), $node);
        } elseif ($node instanceof MethodCall && !($node->name instanceof Identifier)) {
            $methodStr = $this->stringifyDynamic($node->name);
            $this->report('backdoor_dynamic_method_call_complex', ['expression' => $methodStr], $node);
        }

        if ($node instanceof Node\Expr\PropertyFetch && $node->name instanceof Variable) {
            $propVar = $node->name->name;
            $resolved = $this->resolvedVarString($propVar);
            $className = $this->enclosingClassName($node);

            $this->handleDynamicMember('property', $className, $resolved, '$obj->$' . (is_string($propVar) ? $propVar : '{dynamic}'), $node);
        }

        if ($node instanceof Node\Expr\StaticPropertyFetch && $node->name instanceof Variable) {
            $var = $node->name->name;
            $this->report('dynamic_static_property_access', [
                'expression' => '::$' . (is_string($var) ? $var : '{dynamic}')
            ], $node);
        }

        if ($node instanceof Variable && is_object($node->name)) {
            $this->report('variable_variable_usage', [
                'expression' => '$$' . $this->stringifyDynamic($node->name)
            ], $node);
        }

        // Backdoor 5 - Forbidden magic methods (scan body)
        if ($node instanceof Node\Stmt\ClassMethod) {
            $name = strtolower($node->name->toString());
            if (in_array($name, $this->policy->getForbiddenMagicMethods(), true)) {
                $dangerInfo = $this->scanMagicMethodBody($node);
                $this->report('magic_method_defined', [
                    'method' => $name,
                    'dangerous_content' => $dangerInfo['danger'],
                    'explanation' => $dangerInfo['explanation']
                ], $node, $dangerInfo['severity']);
            }
        }

        // Backdoor 6 - Reflection usage
        if (
            ($node instanceof New_ && $this->policy->isForbiddenReflection($this->getClassName($node->class))) ||
            ($node instanceof StaticCall && $this->policy->isForbiddenReflection($this->getClassName($node->class))) ||
            ($node instanceof Node\Expr\Instanceof_ && $this->policy->isForbiddenReflection($this->getClassName($node->class))) ||
            ($node instanceof Node\Param && $node->type && $this->policy->isForbiddenReflection($this->getClassName($node->type)))
        ) {
            $class = $this->getClassName($node->class ?? $node->type) ?? '{dynamic}';
            $this->report('reflection_usage', ['class' => $class], $node, 'critical');
        }

        // Backdoor 7 - File includes
        if ($node instanceof Node\Expr\Include_) {
            if ($node->expr instanceof String_) {
                $path = $node->expr->value;
                foreach ($this->policy->getForbiddenWrappers() as $prefix) {
                    if (stripos($path, $prefix) === 0) {
                        $this->report('include_forbidden_wrapper', ['value' => $path], $node, 'critical');
                    }
                }
            } else {
                $exprString = $this->stringifyDynamic($node->expr);
                $resolved = null;
                $varName = null;
                if ($node->expr instanceof Variable && is_string($node->expr->name)) {
                    $varName = $node->expr->name;
                    $resolved = $this->variableValues[$varName] ?? null;
                }

                if ($resolved === '{superglobal}') {
                    $this->report('include_dynamic_path_superglobal', [
                        'expression' => $varName ? ('$' . $varName) : '{dynamic}'
                    ], $node, 'critical');
                } else {
                    $this->report('include_dynamic_path', ['expression' => $exprString], $node);
                }
            }
        }

        // Backdoor 8 - Obfuscators
        if ($node instanceof Node\Expr\FuncCall) {
            $name = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? $node->name->toString()) : null;
            if ($name && in_array($name, $this->policy->getObfuscators(), true)) {
                $this->report('obfuscation_function', ['function' => $name], $node);
            }
        }

        // Backdoor 9 - Anonymous class / closure leakage
        if ($node instanceof New_ && $node->class instanceof Class_) {
            $danger = $this->scanAnonymousClass($node->class);
            $this->report('anonymous_class_leak', ['dangerous_content' => $danger], $node, $danger ? 'critical' : 'info');
        }
        if ($node instanceof Node\Expr\Closure || $node instanceof Node\Expr\ArrowFunction) {
            $danger = $this->scanClosureBody($node);
            $this->report('anonymous_function_leak', ['dangerous_content' => $danger], $node, $danger ? 'critical' : 'info');
        }

        // Backdoor 10 - Assignments to superglobals
        if ($node instanceof Node\Expr\Assign
            && $node->var instanceof Node\Expr\ArrayDimFetch
            && $node->var->var instanceof Variable) {

            $arrayName = $node->var->var->name;
            if (in_array($arrayName, ['GLOBALS', '_SESSION', '_ENV', '_SERVER'], true)) {
                $danger = $this->containsDangerousValue($node->expr);
                $this->report('global_or_session_leak', [
                    'array' => '$' . $arrayName,
                    'dangerous_content' => $danger,
                ], $node, $danger ? 'critical' : 'high');
            }
        }

        if ($node instanceof Node\Stmt\Static_) {
            foreach ($node->vars as $staticVar) {
                $danger = $this->containsDangerousValue($staticVar->default);
                $this->report('static_variable_leak', [
                    'var' => $staticVar->var->name,
                    'dangerous_content' => $danger,
                ], $node, $danger ? 'critical' : 'high');
            }
        }

        // Backdoor 12 - Chained/indirect returns
        if ($node instanceof Node\Stmt\Return_) {
            $expr = $node->expr;

            // Direct: return new ForbiddenClass();
            if ($expr instanceof New_) {
                $class = $this->getClassName($expr->class);
                if ($class && ($this->policy->isForbiddenReflection($class) || $this->policy->isForbiddenNamespace($class))) {
                    $this->report('return_forbidden_class', ['class' => $class], $node, 'critical');
                }
            }

            // Direct/indirect via function
            $this->runForbiddenFuncCall($expr, true);

            // Indirect: return $this->method()
            if ($expr instanceof MethodCall && $expr->name instanceof Identifier) {
                $className = $this->enclosingClassName($node);
                $methName = strtolower($expr->name->toString());
                if ($className && isset($this->callGraphAnalyzer) &&
                    $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $methName)) {
                    $this->report('return_indirect_forbidden_method_chain', [
                        'chain' => $className . '::' . $methName
                    ], $node, 'critical');
                }
            }

            // Indirect: return SomeClass::method()
            if ($expr instanceof StaticCall && $expr->name instanceof Identifier) {
                $className = $this->getClassName($expr->class);
                $methName = strtolower($expr->name->toString());
                if ($className && isset($this->callGraphAnalyzer) &&
                    $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $methName)) {
                    $this->report('return_indirect_forbidden_method_chain', [
                        'chain' => $className . '::' . $methName
                    ], $node, 'critical');
                }
            }
        }
    }

    /* ====================== helpers to DRY Backdoor 4 & 11 ====================== */

    private function enclosingClassName(Node $node): ?string
    {
        $classNode = $node->getAttribute('parent_class');
        if ($classNode instanceof Node\Stmt\Class_) {
            return isset($classNode->namespacedName)
                ? ltrim($classNode->namespacedName->toString(), '\\')
                : ($classNode->name?->toString());
        }
        return null;
    }

    /** Return strtolower($this->variableValues[$name]) or null, safely. */
    private function resolvedVarString($name): ?string
    {
        if (!is_string($name)) return null;
        $v = $this->variableValues[$name] ?? null;
        if (!is_string($v)) return null;
        $v = strtolower($v);
        return $v !== '' ? $v : null;
    }

    /**
     * Unified handler for dynamic member access.
     * $kind: 'method'|'property'
     */
    private function handleDynamicMember(string $kind, ?string $className, ?string $resolved, string $exprLabel, Node $node): void
    {
        if ($resolved === '{superglobal}') {
            $this->report(
                $kind === 'method' ? 'backdoor_dynamic_method_call_superglobal' : 'dynamic_property_access_superglobal',
                ['expression' => $exprLabel],
                $node,
                'critical'
            );
            return;
        }

        if ($resolved === null) {
            $this->report(
                $kind === 'method' ? 'backdoor_dynamic_method_call_unresolved' : 'dynamic_property_access',
                ['expression' => $exprLabel],
                $node
            );
            return;
        }

        if ($kind === 'method') {
            if ($this->policy->isForbiddenFunction($resolved) || $this->policy->isUnsupportedFunction($resolved)) {
                $this->report('backdoor_dynamic_method_call_forbidden', [
                    'resolved_method' => $resolved,
                    'expression' => $exprLabel,
                ], $node, 'critical');
                return;
            }
            if ($className && isset($this->callGraphAnalyzer) &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $resolved)) {
                $this->report('backdoor_dynamic_method_call_chain_forbidden', [
                    'class' => $className,
                    'resolved_method' => $resolved,
                    'expression' => $exprLabel,
                ], $node, 'critical');
                return;
            }

            // else: benign/suspicious dynamic call, no report needed beyond the general one already emitted
            return;
        }

        // property kind — treat resolved property as potential method reference
        if ($className && isset($this->callGraphAnalyzer)) {
            $defs = $this->callGraphAnalyzer->getMethodDefs($className);
            if (isset($defs[strtolower($resolved)]) &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $resolved)) {
                $this->report('dynamic_property_access_chain_forbidden', [
                    'class' => $className,
                    'resolved_property' => $resolved,
                    'expression' => $exprLabel,
                ], $node, 'critical');
                return;
            }
        }

        // Default informational report for dynamic property access
        $this->report('dynamic_property_access', ['expression' => $exprLabel], $node);
    }

    // Helper to scan closure body for forbidden/unsupported function calls
    private function closureScan(Node $node): bool
    {
        // Direct forbidden/unsupported function call
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = strtolower($this->fqNameOf($node->name) ?? $node->name->toString());
            if ($this->policy->isForbiddenFunction($name)) {
                $this->report('closure_calls_always_forbidden', ['function' => $name], $node, 'critical');
                return true;
            }
            if ($this->policy->isUnsupportedFunction($name)) {
                $this->report('closure_calls_unsupported', ['function' => $name], $node);
                return true;
            }
            // Analyzer: user-defined function returns forbidden
            if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                $this->report('closure_calls_forbidden_chain', ['function' => $name], $node, 'critical');
                return true;
            }
        }

        // Recursively scan nested nodes
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node) {
                if ($this->closureScan($child)) return true;
            } elseif (is_array($child)) {
                foreach ($child as $c) {
                    if ($c instanceof Node && $this->closureScan($c)) return true;
                }
            }
        }
        return false;
    }

    private function stringifyDynamic($expr): string
    {
        if ($expr instanceof Variable) {
            return '$' . (is_string($expr->name) ? $expr->name : '{complex}');
        }
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return $this->stringifyDynamic($expr->left) . $this->stringifyDynamic($expr->right);
        }
        if ($expr instanceof String_) {
            return $expr->value;
        }
        if ($expr instanceof Node\Scalar\Encapsed) {
            $parts = [];
            foreach ($expr->parts as $p) {
                if ($p instanceof Node\Scalar\EncapsedStringPart) {
                    $parts[] = $p->value;
                } elseif ($p instanceof Variable) {
                    $parts[] = '$' . (is_string($p->name) ? $p->name : '{var}');
                } else {
                    $parts[] = '{expr}';
                }
            }
            return implode('', $parts);
        }
        return '{dynamic}';
    }

    /**
     * Scan a magic method body for dangerous patterns.
     * @return array{danger: bool, severity: string, explanation: string}
     */
    private function scanMagicMethodBody(Node\Stmt\ClassMethod $node): array
    {
        $danger = false;
        $severity = 'low';
        $explanation = '';

        foreach ($node->getStmts() ?? [] as $stmt) {
            // First pass: generic analyzer for this statement (recursive)
            $check = $this->analyzeMagicBodyStmt($stmt);
            if ($check['danger']) {
                return $check;
            }

            // Analyzer integration: $this->$name() inside magic method
            if ($stmt instanceof MethodCall
                && $stmt->var instanceof Variable
                && $stmt->var->name === 'this'
                && $stmt->name instanceof Variable) {

                $methodVar = $stmt->name->name;
                $resolved = $this->resolvedVarString($methodVar);
                $classNode = $node->getAttribute('parent_class');
                $className = null;

                if ($classNode instanceof Node\Stmt\Class_) {
                    $className = isset($classNode->namespacedName)
                        ? ltrim($classNode->namespacedName->toString(), '\\')
                        : ($classNode->name?->toString());
                }

                if ($resolved && $className && isset($this->callGraphAnalyzer) &&
                    $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $resolved)) {

                    return [
                        'danger' => true,
                        'severity' => 'critical',
                        'explanation' => "Dynamic call to forbidden chain ($className::$resolved) via magic method"
                    ];
                }
            }
        }

        return [
            'danger' => false,
            'severity' => $severity,
            'explanation' => "No dangerous dynamic calls"
        ];
    }

    /**
     * Recursive inspector for magic method statements.
     * @return array{danger: bool, severity: string, explanation: string}
     */
    private function analyzeMagicBodyStmt(Node $node): array
    {
        // Direct forbidden/unsupported function call
        if ($node instanceof Node\Expr\FuncCall) {
            $name = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? $node->name->toString()) : null;

            if ($name && ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name))) {
                return ['danger' => true, 'severity' => 'critical', 'explanation' => 'Direct forbidden/unsupported function called'];
            }

            // call_user_func / call_user_func_array checks
            if (in_array($name, ['call_user_func', 'call_user_func_array'], true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;
                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if ($this->policy->isForbiddenFunction($cbFunc) || $this->policy->isUnsupportedFunction($cbFunc)) {
                        return ['danger' => true, 'severity' => 'critical', 'explanation' => "call_user_func to forbidden/unsupported"];
                    }
                    if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($cbFunc)) {
                        return ['danger' => true, 'severity' => 'critical', 'explanation' => "call_user_func to forbidden chain"];
                    }
                } elseif ($cb instanceof Variable) {
                    return ['danger' => true, 'severity' => 'high', 'explanation' => "call_user_func to unknown variable"];
                }
            }
        }

        // Variable/dynamic method or function name in magic method
        if (
            ($node instanceof MethodCall && !($node->name instanceof Identifier)) ||
            ($node instanceof Node\Expr\FuncCall && !($node->name instanceof Node\Name))
        ) {
            return ['danger' => true, 'severity' => 'high', 'explanation' => "Dynamic method/function call in magic method"];
        }

        // Recurse into children
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node) {
                $check = $this->analyzeMagicBodyStmt($child);
                if ($check['danger']) return $check;
            } elseif (is_array($child)) {
                foreach ($child as $c) {
                    if ($c instanceof Node) {
                        $check = $this->analyzeMagicBodyStmt($c);
                        if ($check['danger']) return $check;
                    }
                }
            }
        }

        return ['danger' => false, 'severity' => 'low', 'explanation' => "No dangerous dynamic calls"];
    }

    private function scanAnonymousClass(Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            if ($this->magicMethodContainsDanger($method)) {
                return true;
            }
        }
        return false;
    }

    private function scanClosureBody($closure): bool
    {
        if (!($closure instanceof Node\Expr\Closure)) return false;
        foreach ($closure->getStmts() ?? [] as $stmt) {
            // Direct forbidden/unsupported function call, or forbidden chain
            if ($stmt instanceof Node\Expr\FuncCall && $stmt->name instanceof Node\Name) {
                $name = strtolower($this->fqNameOf($stmt->name) ?? $stmt->name->toString());
                if ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name)) {
                    return true;
                }
                if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                    return true;
                }
            }
            // Recurse
            foreach ($stmt->getSubNodeNames() as $sub) {
                $child = $stmt->$sub;
                if ($child instanceof Node) {
                    if ($this->scanClosureBody($child)) return true;
                } elseif (is_array($child)) {
                    foreach ($child as $c) {
                        if ($c instanceof Node && $this->scanClosureBody($c)) return true;
                    }
                }
            }
        }
        return false;
    }

    private function magicMethodContainsDanger(Node $node): bool
    {
        // Direct forbidden/unsupported calls
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = strtolower($this->fqNameOf($node->name) ?? $node->name->toString());
            if ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name)) {
                return true;
            }
            if (in_array($name, ['call_user_func', 'call_user_func_array'], true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;
                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if ($this->policy->isForbiddenFunction($cbFunc) || $this->policy->isUnsupportedFunction($cbFunc)) {
                        return true;
                    }
                }
            }
        }

        // Variable function/method calls ($this->{$x}(), $fn(), etc.)
        if ($node instanceof MethodCall || $node instanceof Node\Expr\FuncCall) {
            if (!($node->name instanceof Identifier) && !($node->name instanceof Node\Name)) {
                return true;
            }
        }

        // Recurse
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node && $this->magicMethodContainsDanger($child)) {
                return true;
            }
            if (is_array($child)) {
                foreach ($child as $c) {
                    if ($c instanceof Node && $this->magicMethodContainsDanger($c)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function containsDangerousValue($expr): bool
    {
        // Direct: new {Class}
        if ($expr instanceof New_) {
            $class = $this->getClassName($expr->class);
            return $class && ($this->policy->isForbiddenNamespace($class) || $this->policy->isForbiddenReflection($class));
        }

        // Direct: forbidden/unsupported function call
        if ($expr instanceof Node\Expr\FuncCall) {
            if ($expr->name instanceof Node\Name) {
                $name = strtolower($this->fqNameOf($expr->name) ?? $expr->name->toString());
                if ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name)) {
                    return true;
                }
                if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                    return true;
                }
            } else {
                // Variable/dynamic function in value context — err on safe side
                return true;
            }
        }

        // Closure/arrow fn — scan body
        if ($expr instanceof Node\Expr\Closure || $expr instanceof Node\Expr\ArrowFunction) {
            foreach ($expr->getStmts() ?? [] as $stmt) {
                if ($this->scanClosureBody($stmt)) return true;
            }
        }

        // Array literal — recurse
        if ($expr instanceof Node\Expr\Array_) {
            foreach ($expr->items as $item) {
                if ($item && $this->containsDangerousValue($item->value)) return true;
            }
        }

        // Variable — try resolve a tracked value that might be callable
        if ($expr instanceof Variable && is_string($expr->name)) {
            $resolved = $this->variableValues[$expr->name] ?? null;
            if (is_string($resolved)) {
                $resolvedLc = strtolower($resolved);
                if ($this->policy->isForbiddenFunction($resolvedLc) || $this->policy->isUnsupportedFunction($resolvedLc)) {
                    return true;
                }
                if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($resolvedLc)) {
                    return true;
                }
            }
        }

        return false;
    }
}
```

---
#### 16


` File: src/Core/Security/RouteFileValidator.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Exceptions\DuplicateRouteIdException;

final class RouteFileValidator
{
    /**
     * Validate a single route JSON file:
     * - Decode JSON
     * - (Optionally) validate with JSON Schema externally
     * - Enforce unique "id" per route node within the file
     * - Register IDs globally in $registry to ensure cross-file uniqueness
     *
     * @throws JsonException
     * @throws DuplicateRouteIdException
     */
    public static function validateFile(string $filePath, RouteIdRegistry $registry): void
    {
        $json = file_get_contents($filePath);
        if ($json === false) {
            throw new RuntimeException("Cannot read route file: $filePath");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['routes']) || !is_array($data['routes'])) {
            throw new RuntimeException("Invalid route file (missing 'routes' array): $filePath");
        }

        // Enforce uniqueness within this file.
        $local = [];
        $walk = static function (array $node, string $path) use (&$walk, &$local, $filePath, $registry): void {
            // all route nodes must carry id/desc by schema; be defensive:
            $id = $node['id'] ?? null;
            $desc = $node['desc'] ?? null;
            if (!is_string($id) || $id === '' || !is_string($desc) || $desc === '') {
                throw new RuntimeException("Route at $filePath $path missing required 'id'/'desc'.");
            }

            // Check file-scope uniqueness
            if (isset($local[$id])) {
                $first = $local[$id];
                throw new RuntimeException(
                    "Duplicate route id '$id' within the same file.\n" .
                    " - First at: $filePath $first\n" .
                    " - Again at: $filePath $path"
                );
            }
            $local[$id] = $path;

            // Check plugin-scope uniqueness (across files)
            $registry->register($id, $filePath, $path);

            // Recurse into groups
            if (($node['type'] ?? null) === 'group') {
                $children = $node['routes'] ?? [];
                foreach ($children as $i => $child) {
                    $walk($child, $path . "/routes[$i]");
                }
            }
        };

        foreach ($data['routes'] as $i => $node) {
            $walk($node, "/routes[$i]");
        }
    }
}
```

---
#### 17


` File: src/Core/Security/RouteIdRegistry.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security;

use Timeax\FortiPlugin\Exceptions\DuplicateRouteIdException;

/**
 * Tracks route IDs to enforce uniqueness within a plugin.
 */
final class RouteIdRegistry
{
    /**
     * @var array<string, array{file:string, path:string}>
     */
    private array $seen = [];

    /**
     * @throws DuplicateRouteIdException
     */
    public function register(string $id, string $file, string $jsonPath = ''): void
    {
        $id = trim($id);
        if ($id === '') {
            return; // schema should already require non-empty; be lenient here
        }

        if (isset($this->seen[$id])) {
            $first = $this->seen[$id];
            throw new DuplicateRouteIdException(
                $id,
                $first['file'],
                $first['path'],
                $file,
                $jsonPath
            );
        }

        $this->seen[$id] = ['file' => $file, 'path' => $jsonPath];
    }
}
```

---
#### 18


` File: src/Core/Security/TokenUsageAnalyzer.php`  [↑ Back to top](#index)

```php
<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Security;

use RuntimeException;

/**
 * TokenUsageAnalyzer
 *
 * Analyzes a PHP file for direct (and lightly-obfuscated) usage of forbidden tokens
 * (e.g., eval, exec, shell_exec). Designed to be used as the callback with FileScanner.
 *
 * Detection strategy (fast and with low false positives):
 *  1) Uses token_get_all() to walk PHP tokens and capture T_STRING calls that match
 *     forbidden function names, only when followed by "(" (i.e., actual calls).
 *  2) Detects simple string-concatenation obfuscation of function names, e.g. "ev"."al"(
 *     by concatenating adjacent string literals directly before "(" and comparing.
 *  3) Flags backtick command execution (`...`) by scanning raw code segments (rare in tokenizer).
 *
 * Not a full parser; intentionally lightweight. This should be paired with your AST scanner
 * for deep analysis.
 */
final class TokenUsageAnalyzer
{
    /**
     * Scan a file and return a list of issues for direct/obfuscated token usage.
     *
     * @param  string        $filePath         Absolute path to the PHP file.
     * @param  array<string> $forbiddenTokens  Lowercase function names to flag (e.g., ['eval','exec','shell_exec'])
     * @return array<int,array{
     *     type: string,
     *     token: string,
     *     file: string,
     *     line: int,
     *     snippet: string,
     *     issue: string
     * }>
     * @noinspection ForeachInvariantsInspection
     */
    public static function analyzeFile(string $filePath, array $forbiddenTokens): array
    {
        // Normalize to lowercase set for cheap lookup
        $forbidden = [];
        foreach ($forbiddenTokens as $t) {
            $forbidden[strtolower($t)] = true;
        }

        $code = @file_get_contents($filePath);
        if ($code === false) {
            throw new RuntimeException("Unable to read file: $filePath");
        }

        $lines = preg_split('/\R/u', $code) ?: [];

        // Quick check for backticks: flag any line with non-escaped backtick outside string contexts.
        // (Tokenizer doesn’t give a special token for backticks; this heuristic is acceptable here.)
        $issues = self::scanBackticks($filePath, $lines);

        // Tokenize once
        $tokens = @token_get_all($code, TOKEN_PARSE);

        // Walk tokens and detect:
        //  A) T_STRING function calls that are forbidden and followed by '('
        //  B) String-concatenated function names immediately followed by '(' (e.g., "ev"."al"()
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $tk = $tokens[$i];

            // A) Direct function call: T_STRING '('
            if (is_array($tk) && $tk[0] === T_STRING) {
                $name = strtolower($tk[1]);

                if (isset($forbidden[$name])) {
                    $next = self::skipWhitespaceAndComments($tokens, $i + 1, $count);
                    if ($next < $count && $tokens[$next] === '(') {
                        /** @noinspection PhpConditionAlreadyCheckedInspection */
                        $line = is_array($tk) ? $tk[2] : self::safeLineGuess($lines);
                        $issues[] = self::issueRow($name, $filePath, $line, $lines, 'Direct usage of invalid token');
                    }
                }

                continue;
            }

            // B) Obfuscated via concatenated strings right before '(':
            //    e.g., "ev" . "al" ( ... )
            // Collect a run of [string-literal] (dot [string-literal])* immediately followed by '('
            if (self::isStringLiteral($tk)) {
                [$assembled, $line, $nextIndex] = self::assembleConcatenatedString($tokens, $i, $count);
                if ($assembled !== '') {
                    // Check if immediately followed by '('
                    $after = self::skipWhitespaceAndComments($tokens, $nextIndex, $count);
                    if ($after < $count && $tokens[$after] === '(') {
                        $lower = strtolower($assembled);
                        if (isset($forbidden[$lower])) {
                            $issues[] = self::issueRow($lower, $filePath, $line, $lines, 'Obfuscated usage of invalid token');
                        }
                    }
                }
                // Move the cursor to the end of the processed segment
                $i = max($i, ($nextIndex - 1));
            }
        }

        return $issues;
    }

    /**
     * Create an issue row in the exact structure requested.
     *
     * @param  string        $token
     * @param  string        $file
     * @param  int           $lineNumber
     * @param  array<int,string> $lines
     * @param  string        $message
     * @return array<string,mixed>
     */
    private static function issueRow(string $token, string $file, int $lineNumber, array $lines, string $message): array
    {
        $snippet = isset($lines[$lineNumber - 1]) ? trim($lines[$lineNumber - 1]) : '';
        return [
            'type'    => 'invalid_token_usage',
            'token'   => $token,
            'file'    => $file,
            'line'    => $lineNumber,
            'snippet' => $snippet,
            'issue'   => $message,
        ];
    }

    /**
     * Skip whitespace/comments and return the index of the next significant token.
     */
    private static function skipWhitespaceAndComments(array $tokens, int $i, int $count): int
    {
        for (; $i < $count; $i++) {
            $t = $tokens[$i];
            if (is_string($t)) {
                // Single-char tokens like '(' or ')'
                break;
            }
            $id = $t[0];
            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                break;
            }
        }
        return $i;
    }

    /**
     * True if token is a PHP string literal (single or double quoted).
     */
    private static function isStringLiteral($token): bool
    {
        return is_array($token) && ($token[0] === T_CONSTANT_ENCAPSED_STRING);
    }

    /**
     * Assemble a concatenated string sequence starting at $i:
     *   T_CONSTANT_ENCAPSED_STRING ( (T_WHITESPACE|T_COMMENT|'.') T_CONSTANT_ENCAPSED_STRING )*
     * Returns [assembledString, lineNumberOfFirstLiteral, nextIndexAfterSequence].
     *
     * Only concatenates adjacent literals, ignoring whitespace/comments and literal '.' operators.
     */
    private static function assembleConcatenatedString(array $tokens, int $i, int $count): array
    {
        $assembled = '';
        $line = is_array($tokens[$i]) ? $tokens[$i][2] : 1;
        $idx = $i;

        // First literal
        if (!self::isStringLiteral($tokens[$idx])) {
            return ['', $line, $i];
        }
        $assembled .= self::unquoteLiteral($tokens[$idx][1]);
        $idx++;

        // Zero or more: (whitespace/comment | '.') + literal
        while ($idx < $count) {
            $t = $tokens[$idx];

            // Skip whitespace or comments between pieces
            if (is_array($t) && ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
                $idx++;
                continue;
            }

            // Require '.' operator to continue, otherwise stop
            if ($t !== '.') {
                break;
            }
            $idx++;

            // Skip whitespace/comments after '.'
            while ($idx < $count && is_array($tokens[$idx]) && in_array($tokens[$idx][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $idx++;
            }

            // Next must be a literal
            if ($idx >= $count || !self::isStringLiteral($tokens[$idx])) {
                break;
            }

            $assembled .= self::unquoteLiteral($tokens[$idx][1]);
            $idx++;
        }

        return [$assembled, $line, $idx];
    }

    /**
     * Remove surrounding quotes from a PHP literal and unescape simple escapes.
     * Handles both single- and double-quoted literals conservatively.
     */
    private static function unquoteLiteral(string $literal): string
    {
        $len = strlen($literal);
        if ($len < 2) {
            return $literal;
        }
        $q = $literal[0];
        if (($q !== '\'' && $q !== '"') || $literal[$len - 1] !== $q) {
            return $literal;
        }
        $body = substr($literal, 1, $len - 2);

        // Minimal unescape to cover common cases used for obfuscation
        // (We don’t need full PHP string semantics here)
        return str_replace(
            $q === '\'' ? ["\\'","\\\\"] : ['\\"','\\\\','\n','\r','\t'],
            $q === '\'' ? ["'","\\"]      : ['"','\\',"\n","\r","\t"],
            $body
        );
    }

    /**
     * Heuristic backtick execution detection: flags lines that contain an unescaped backtick
     * outside obvious quoted string contexts. This is intentionally simple and errs on the side of caution.
     *
     * @param  string              $filePath
     * @param  array<int,string>   $lines
     * @return array<int,array<string,mixed>>
     */
    private static function scanBackticks(string $filePath, array $lines): array
    {
        $issues = [];
        foreach ($lines as $i => $line) {
            // quick skip
            if (!str_contains($line, '`')) {
                continue;
            }
            // Very light check: if the line has an odd number of backticks (unbalanced),
            // or any backtick not obviously inside a quoted string, we flag it.
            // We avoid heavy parsing; this is just a heads-up.
            $tickCount = substr_count($line, '`');
            if ($tickCount > 0) {
                $issues[] = [
                    'type'    => 'invalid_token_usage',
                    'token'   => '`',
                    'file'    => $filePath,
                    'line'    => $i + 1,
                    'snippet' => trim($line),
                    'issue'   => 'Backtick shell execution detected',
                ];
            }
        }
        return $issues;
    }

    /**
     * Fallback when a line number is not available (shouldn’t happen with token_get_all).
     */
    private static function safeLineGuess(array $lines): int
    {
        return max(1, count($lines));
    }
}
```

---
#### 19


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
#### 20


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
#### 21


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
#### 22


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
#### 23


` File: src/Exceptions/DuplicateRouteIdException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;

final class DuplicateRouteIdException extends RuntimeException
{
    public function __construct(
        public readonly string $routeId,
        public readonly string $firstFile,
        public readonly string $firstPath,
        public readonly string $dupFile,
        public readonly string $dupPath
    )
    {
        parent::__construct(
            "Duplicate route id '$routeId' found.\n" .
            " - First seen in: $firstFile $firstPath\n" .
            " - Duplicate in:  $dupFile $dupPath"
        );
    }
}
```

---
#### 24


` File: src/Exceptions/PermissionDeniedException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Timeax\FortiPlugin\Models\User;
use Timeax\FortiPlugin\Notifications\PermissionGrantNotification;

class PermissionDeniedException extends RuntimeException
{
    protected string $type;
    protected string $target;
    protected array|string|null $permissions;
    protected ?Request $request;

    public function __construct(
        string $type,
        string $target,
        array|string|null $permissions = null,
        ?Request $request = null,
        string $message = "",
        int $code = 0,
        Throwable $previous = null
    ) {
        $this->type = $type;
        $this->target = $target;
        $this->permissions = $permissions;
        $this->request = $request;
        $message = $message ?: "Permission denied for {$type}:{$target}" . ($permissions ? " (" . implode(',', (array)$permissions) . ")" : '');
        parent::__construct($message, $code, $previous);
    }

    public function render($request = null): Response
    {
        /** @var Request|null $request */
        $request = $request ?: $this->request ?: (function_exists('request') ? request() : null);

        // If no request object (e.g. job, command, fallback context)
        if (!$request) {
            // Notify admins with relevant permissions
            $this->notifyPermissionAdmins();

            // Optionally, just throw a generic 403
            abort(403, "Permission denied. Your request has been forwarded to an administrator for review.");
        }

        // 1. API/axios/JSON requests
        if ($request->expectsJson() || $request->isXmlHttpRequest() || $request->wantsJson()) {
            return response()->json([
                'error' => 'plugin_permission_denied',
                'type' => $this->type,
                'target' => $this->target,
                'permissions' => $this->permissions,
                'message' => $this->getMessage(),
                'can_request_permission' => true,
                'request_data' => $this->getClonedRequestData(),
            ], 403);
        }

        // 2. All browser/inertia/other requests: redirect back with flash data only
        return redirect()->back()->with('plugin_permission_data', [
            'type' => $this->type,
            'target' => $this->target,
            'permissions' => $this->permissions,
            'message' => $this->getMessage(),
            'can_request_permission' => true,
            'request_data' => $this->getClonedRequestData(),
        ]);
    }

    protected function notifyPermissionAdmins(): void
    {
        // Find admins who can grant $this->permissions on $this->target of $this->type
        $admins = User::permission('can_grant_permission', 1)->get();

        foreach ($admins as $admin) {
            $admin->notify(new PermissionGrantNotification([
                'type' => $this->type,
                'target' => $this->target,
                'permissions' => $this->permissions,
                'message' => $this->getMessage(),
                'request_data' => $this->getClonedRequestData(),
                // Add more details as needed
            ]));
        }
    }

    public function getClonedRequestData(): array
    {
        if (!$this->request) return [];
        return [
            'method' => $this->request->method(),
            'uri' => $this->request->getRequestUri(),
            'headers' => $this->request->headers->all(),
            'body' => $this->request->all(),
        ];
    }

    public function getType(): string { return $this->type; }
    public function getTarget(): string { return $this->target; }
    public function getPermissions(): array|string|null { return $this->permissions; }
}
```

---
#### 25


` File: src/Exceptions/PluginContextException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;

class PluginContextException extends RuntimeException {}
```

---
#### 26


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
#### 27


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
#### 28


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
#### 29


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
#### 30


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
#### 31


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
#### 32


` File: src/Installations/Contracts/PluginRepository.php`  [↑ Back to top](#index)

```php
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
```

---
#### 33


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
#### 34


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
#### 35


` File: src/Installations/DTO/ComposerPlan.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TComposerAction 'skip'|'add'|'conflict'
 * @phpstan-type TComposerPlan array{
 *   actions: array<string,TComposerAction>,     # package => action
 *   core_conflicts: list<string>,              # e.g. ['laravel/framework','php']
 * }
 */
final readonly class ComposerPlan implements ArraySerializable
{
    /** @param array<string,'skip'|'add'|'conflict'> $actions */
    public function __construct(
        public array $actions,
        /** @var list<string> */
        public array $core_conflicts,
    ) {}

    /** @param TComposerPlan $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['actions'] ?? [],
            array_values($data['core_conflicts'] ?? []),
        );
    }

    /** @return TComposerPlan */
    public function toArray(): array
    {
        return [
            'actions' => $this->actions,
            'core_conflicts' => $this->core_conflicts,
        ];
    }
}
```

---
#### 36


` File: src/Installations/DTO/DecisionResult.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TDecisionStatus 'installed'|'ask'|'break'
 * @phpstan-type TDecisionTokenMeta array{purpose:'background_scan'|'install_override',expires_at:string}|null
 * @phpstan-type TDecisionCounts array{validation_errors:int,scan_errors:int}
 * @phpstan-type TDecision array{
 *   status: TDecisionStatus,
 *   reason?: string,
 *   at: string,
 *   run_id: string,
 *   zip_id: int|string,
 *   fingerprint: string,
 *   validator_config_hash: string,
 *   file_scan_enabled: bool,
 *   token: TDecisionTokenMeta,
 *   last_error_codes?: list<string>,
 *   counts?: TDecisionCounts
 * }
 * @noinspection PhpUndefinedClassInspection
 */
final readonly class DecisionResult implements ArraySerializable
{
    public function __construct(
        public string     $status,                /** @var TDecisionStatus */
        public string     $at,
        public string     $run_id,
        public int|string $zip_id,
        public string     $fingerprint,
        public string     $validator_config_hash,
        public bool       $file_scan_enabled,
        public ?array     $token = null,         /** @var TDecisionTokenMeta */
        public ?string    $reason = null,
        /** @var list<string>|null */
        public ?array     $last_error_codes = null,
        /** @var array{validation_errors:int,scan_errors:int}|null */
        public ?array     $counts = null,
    ) {}

    /** @param TDecision $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['status'],
            $data['at'],
            $data['run_id'],
            $data['zip_id'],
            $data['fingerprint'],
            $data['validator_config_hash'],
            (bool)$data['file_scan_enabled'],
            $data['token'] ?? null,
            $data['reason'] ?? null,
            $data['last_error_codes'] ?? null,
            $data['counts'] ?? null,
        );
    }

    /** @return TDecision */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'reason' => $this->reason,
            'at' => $this->at,
            'run_id' => $this->run_id,
            'zip_id' => $this->zip_id,
            'fingerprint' => $this->fingerprint,
            'validator_config_hash' => $this->validator_config_hash,
            'file_scan_enabled' => $this->file_scan_enabled,
            'token' => $this->token,
            'last_error_codes' => $this->last_error_codes,
            'counts' => $this->counts,
        ];
    }
}
```

---
#### 37


` File: src/Installations/DTO/InstallerResult.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * Canonical result wrapper for the full Installer pipeline.
 *
 * Status values:
 *  - 'ok'    → installation completed successfully
 *  - 'ask'   → installation paused (e.g., background scans / host decision needed)
 *  - 'break' → hard stop by policy (do not continue)
 *  - 'fail'  → an error occurred during installation
 *
 * Provides convenient inspectors (isAsking, passed, failed, isBreak) and getters.
 *
 * @phpstan-type TInstallerStatus 'ok'|'ask'|'break'|'fail'
 * @phpstan-type TInstallerResult array{
 *   status: TInstallerStatus,
 *   summary?: array|null,
 *   meta?: array<string,mixed>|null,
 *   plugin_id?: int|null,
 *   plugin_version_id?: int|null,
 *   extra?: array<string,mixed>|null
 * }
 */
final readonly class InstallerResult implements ArraySerializable
{
    /**
     * @param TInstallerStatus $status
     * @param InstallSummary|null $summary
     * @param array<string,mixed>|null $meta
     * @param int|null $plugin_id
     * @param int|null $plugin_version_id
     * @param array<string,mixed>|null $extra Arbitrary additional fields the host wants to carry
     */
    public function __construct(
        public string        $status,
        public ?InstallSummary $summary = null,
        public ?array        $meta = null,
        public ?int          $plugin_id = null,
        public ?int          $plugin_version_id = null,
        public ?array        $extra = null,
    ) {}

    /** @param TInstallerResult $data */
    public static function fromArray(array $data): static
    {
        $summary = null;
        if (array_key_exists('summary', $data) && $data['summary'] !== null) {
            $summary = $data['summary'] instanceof InstallSummary
                ? $data['summary']
                : InstallSummary::fromArray((array)$data['summary']);
        }

        return new self(
            status: (string)$data['status'],
            summary: $summary,
            meta: isset($data['meta']) ? (array)$data['meta'] : null,
            plugin_id: isset($data['plugin_id']) ? (int)$data['plugin_id'] : null,
            plugin_version_id: isset($data['plugin_version_id']) ? (int)$data['plugin_version_id'] : null,
            extra: isset($data['extra']) ? (array)$data['extra'] : null,
        );
    }

    /** @return TInstallerResult */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'summary' => $this->summary?->toArray(),
            'meta' => $this->meta,
            'plugin_id' => $this->plugin_id,
            'plugin_version_id' => $this->plugin_version_id,
            'extra' => $this->extra,
        ];
    }

    // ── Inspectors ─────────────────────────────────────────────────────────

    public function isAsking(): bool
    {
        return $this->status === 'ask';
    }

    public function passed(): bool
    {
        return $this->status === 'ok';
    }

    public function failed(): bool
    {
        return $this->status === 'fail';
    }

    public function isBreak(): bool
    {
        return $this->status === 'break';
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function getSummary(): ?InstallSummary
    {
        return $this->summary;
    }

    /** @return array<string,mixed>|null */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function getPluginId(): ?int
    {
        return $this->plugin_id;
    }

    public function getPluginVersionId(): ?int
    {
        return $this->plugin_version_id;
    }

    /**
     * Generic accessor over all stored data (summary/meta/ids/extra), useful for UIs.
     * If $key is null, returns the whole flattened payload.
     *
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    public function getData(?string $key = null, mixed $default = null): mixed
    {
        $all = [
                'status' => $this->status,
                'summary' => $this->summary?->toArray(),
                'meta' => $this->meta,
                'plugin_id' => $this->plugin_id,
                'plugin_version_id' => $this->plugin_version_id,
            ] + ($this->extra ?? []);

        if ($key === null) {
            return $all;
        }
        return $all[$key] ?? $default;
    }

    // ── Factories (optional sugar) ─────────────────────────────────────────

    /** @param array<string,mixed>|null $meta */
    public static function ok(?InstallSummary $summary = null, ?array $meta = null, ?int $pluginId = null, ?int $pluginVersionId = null, ?array $extra = null): self
    {
        return new self('ok', $summary, $meta, $pluginId, $pluginVersionId, $extra);
    }

    /** @param array<string,mixed>|null $meta */
    public static function ask(?InstallSummary $summary = null, ?array $meta = null, ?array $extra = null): self
    {
        return new self('ask', $summary, $meta, null, null, $extra);
    }

    /** @param array<string,mixed>|null $meta */
    public static function break(?InstallSummary $summary = null, ?array $meta = null, ?array $extra = null): self
    {
        return new self('break', $summary, $meta, null, null, $extra);
    }

    /** @param array<string,mixed>|null $meta */
    public static function fail(?InstallSummary $summary = null, ?array $meta = null, ?array $extra = null): self
    {
        return new self('fail', $summary, $meta, null, null, $extra);
    }
}
```

---
#### 38


` File: src/Installations/DTO/InstallMeta.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TInstallPaths array{
 *   staging?: string,
 *   install?: string,
 *   logs?: string
 * }
 * @phpstan-type TInstallMeta array{
 *   psr4_root: string,
 *   placeholder_name: string,
 *   plugin_placeholder_id: int|string,
 *   zip_id: int|string,
 *   actor: string,
 *   paths: TInstallPaths,
 *   started_at: string,
 *   updated_at: string,
 *   fingerprint: string,
 *   validator_config_hash: string
 * }
 */
final readonly class InstallMeta implements ArraySerializable
{
    public function __construct(
        public string     $psr4_root,
        public string     $placeholder_name,
        public int|string $plugin_placeholder_id,
        public int|string $zip_id,
        public string     $actor,
        /** @var array{staging?:string,install?:string,logs?:string} */
        public array      $paths,
        public string     $started_at,
        public string     $updated_at,
        public string     $fingerprint,
        public string     $validator_config_hash,
    ) {}

    /** @param TInstallMeta $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['psr4_root'],
            $data['placeholder_name'],
            $data['plugin_placeholder_id'],
            $data['zip_id'],
            $data['actor'],
            $data['paths'] ?? [],
            $data['started_at'],
            $data['updated_at'],
            $data['fingerprint'],
            $data['validator_config_hash'],
        );
    }

    /** @return TInstallMeta */
    public function toArray(): array
    {
        return [
            'psr4_root' => $this->psr4_root,
            'placeholder_name' => $this->placeholder_name,
            'plugin_placeholder_id' => $this->plugin_placeholder_id,
            'zip_id' => $this->zip_id,
            'actor' => $this->actor,
            'paths' => $this->paths,
            'started_at' => $this->started_at,
            'updated_at' => $this->updated_at,
            'fingerprint' => $this->fingerprint,
            'validator_config_hash' => $this->validator_config_hash,
        ];
    }
}
```

---
#### 39


` File: src/Installations/DTO/InstallSummary.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TSectionStatus 'skipped'|'ok'|'warn'|'fail'|'pending'
 * @phpstan-type TVerificationSection array{
 *   status: TSectionStatus,
 *   errors?: list<string>,
 *   warnings?: list<string>
 * }
 * @phpstan-type TFileScanSection array{
 *   enabled: bool,
 *   status: TSectionStatus,
 *   errors?: list<string>
 * }
 * @phpstan-type TZipGate array{ plugin_zip_status: 'verified'|'pending'|'failed'|'unknown' }
 * @phpstan-type TVendorPolicy array{ mode: 'STRIP_BUNDLED_VENDOR'|'ALLOW_BUNDLED_VENDOR' }
 * @phpstan-type TComposerPlan TComposerPlan
 * @phpstan-type TInstallSummary array{
 *   verification: TVerificationSection,
 *   file_scan: TFileScanSection,
 *   zip_validation?: TZipGate,
 *   vendor_policy?: TVendorPolicy,
 *   composer_plan?: TComposerPlan,
 *   packages?: array<string, array{is_foreign:bool,status:'verified'|'unverified'|'pending'|'failed'}>
 * }
 * @noinspection PhpUndefinedClassInspection
 */
final readonly class InstallSummary implements ArraySerializable
{
    /**
     * @param array $verification
     * @param array $file_scan
     * @param array|null $zip_validation
     * @param array|null $vendor_policy
     * @param array|null $composer_plan
     * @param array|null $packages
     */
    public function __construct(
        public array  $verification,
        public array  $file_scan,
        public ?array $zip_validation = null,
        public ?array $vendor_policy = null,
        public ?array $composer_plan = null,
        public ?array $packages = null,
    ) {}

    /** @param TInstallSummary $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['verification'],
            $data['file_scan'],
            $data['zip_validation'] ?? null,
            $data['vendor_policy'] ?? null,
            $data['composer_plan'] ?? null,
            $data['packages'] ?? null,
        );
    }

    /** @return TInstallSummary */
    public function toArray(): array
    {
        return [
            'verification' => $this->verification,
            'file_scan' => $this->file_scan,
            'zip_validation' => $this->zip_validation,
            'vendor_policy' => $this->vendor_policy,
            'composer_plan' => $this->composer_plan,
            'packages' => $this->packages,
        ];
    }
}
```

---
#### 40


` File: src/Installations/DTO/PackageEntry.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

use Timeax\FortiPlugin\Installations\Enums\PackageStatus;

/**
 * @phpstan-type TPackageEntry array{
 *   name: string,
 *   is_foreign: bool,
 *   status: 'verified'|'unverified'|'pending'|'failed'
 * }
 */
final readonly class PackageEntry implements ArraySerializable
{
    public function __construct(
        public string        $name,
        public bool          $is_foreign,
        public PackageStatus $status,
    )
    {
    }

    /** @param TPackageEntry $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['name'],
            (bool)$data['is_foreign'],
            PackageStatus::from($data['status']),
        );
    }

    /** @return TPackageEntry */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'is_foreign' => $this->is_foreign,
            'status' => $this->status->value,
        ];
    }
}
```

---
#### 41


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
#### 42


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
#### 43


` File: src/Installations/Enums/PackageStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum PackageStatus: string
{
    case VERIFIED = 'verified';
    case UNVERIFIED = 'unverified';
    case PENDING = 'pending';
    case FAILED = 'failed';
}
```

---
#### 44


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
#### 45


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
#### 46


` File: src/Installations/Installer.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations;

use Illuminate\Support\Facades\DB;
use JsonException;
use Random\RandomException;
use RuntimeException;
use Throwable;

use Timeax\FortiPlugin\Installations\DTO\InstallerResult;
use Timeax\FortiPlugin\Installations\Sections\UiConfigValidationSection;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\InstallSummary;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\RouteUiBridge;
use Timeax\FortiPlugin\Installations\Support\ValidatorBridge;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Installations\Sections\ZipValidationGate;
use Timeax\FortiPlugin\Services\ValidatorService;

// Sections (for DI completeness)
use Timeax\FortiPlugin\Installations\Sections\VerificationSection;
use Timeax\FortiPlugin\Installations\Sections\ProviderValidationSection;
use Timeax\FortiPlugin\Installations\Sections\ComposerPlanSection;
use Timeax\FortiPlugin\Installations\Sections\VendorPolicySection;
use Timeax\FortiPlugin\Installations\Sections\RouteWriteSection;
use Timeax\FortiPlugin\Installations\Sections\DbPersistSection;
use Timeax\FortiPlugin\Installations\Sections\InstallFilesSection;

final readonly class Installer
{
    public function __construct(
        private InstallerPolicy           $policy,
        private AtomicFilesystem          $afs,
        private ValidatorBridge           $validatorBridge,   // orchestrates Verification + FileScan
        private VerificationSection       $verification,      // kept for DI completeness (used by bridge)
        private ProviderValidationSection $providerValidation,
        private ComposerPlanSection       $composerPlan,
        private VendorPolicySection       $vendorPolicy,
        private DbPersistSection          $dbPersist,
        private RouteUiBridge             $routeUiBridge,
        private RouteWriteSection         $routeWriterSection, // writer targets STAGING
        private InstallFilesSection       $installFiles,
        private UiConfigValidationSection $uiConfigValidation,
        // NEW: token + logs + zip-gate for resume flow
        private InstallerTokenManager     $tokens,
        private InstallationLogStore      $logStore,
        private ZipValidationGate         $zipGate,
    )
    {
    }

    /**
     * Full install pipeline after validation phases (which are handled by ValidatorBridge),
     * with support for resuming via installer override tokens.
     *
     * @param InstallMeta $meta
     * @param int|string $zipId
     * @param ValidatorService $validator
     * @param array<string,mixed> $validatorConfig
     * @param string $validatorConfigHash
     * @param string $versionTag
     * @param string $actor
     * @param string $runId
     * @param callable|null $emit fn(array $payload): void
     * @param callable|null $onValidationEnd forwarded to ValidatorBridge only
     * @param callable|null $onFileScanError forwarded to ValidatorBridge only
     * @param callable|null $onFinish called once when installation completes successfully (status 'ok')
     * @param string|null $installerToken optional override token when resuming after ASK
     *
     * @return InstallerResult
     * @throws JsonException
     * @throws RandomException|Throwable
     */
    public function run(
        InstallMeta      $meta,
        int|string       $zipId,
        ValidatorService $validator,
        array            $validatorConfig,
        string           $validatorConfigHash,
        string           $versionTag,
        string           $actor,
        string           $runId,
        ?callable        $emit = null,
        ?callable        $onValidationEnd = null,
        ?callable        $onFileScanError = null,
        ?callable        $onFinish = null,
        ?string          $installerToken = null,
    ): InstallerResult
    {
        $pluginDir = (string)($meta->paths['staging'] ?? '');
        if ($pluginDir === '') {
            throw new RuntimeException('InstallMeta.paths.staging is required.');
        }

        $pluginName = $meta->placeholder_name;
        $psr4Root = $this->policy->getPsr4Root();

        // ─────────────────────────────────────────────────────────────
        // 0) PREFLIGHT: resume path via installer override token
        // ─────────────────────────────────────────────────────────────
        if (is_string($installerToken) && $installerToken !== '') {
            $claims = null;
            try {
                $claims = $this->tokens->validate($installerToken);
            } catch (Throwable $e) {
                $emit && $emit([
                    'title' => 'INSTALLER_TOKEN_INVALID',
                    'description' => 'Installer override token invalid or expired',
                    'meta' => ['zip_id' => (string)$zipId, 'reason' => $e->getMessage()],
                ]);
                // Treat as ASK (UI should re-request confirmation or new token)
                return $this->emitAsk($emit, null, ['reason' => 'token_invalid']);
            }

            // Purpose & run parity
            if (($claims->purpose ?? null) !== 'install_override' || ($claims->run_id ?? null) !== $runId) {
                $emit && $emit([
                    'title' => 'INSTALLER_TOKEN_MISMATCH',
                    'description' => 'Token purpose or run_id mismatch',
                    'meta' => ['expected_run' => $runId, 'token_run' => $claims->run_id ?? null, 'purpose' => $claims->purpose ?? null],
                ]);
                return $this->emitAsk($emit, null, ['reason' => 'token_mismatch']);
            }

            // Ensure prior validators ran and produced ASK for this run
            $doc = $this->logStore->read();
            $hasVerificationOk = $this->verificationOk($doc);
            $hasFileScanAsk = $this->hasDecisionAskForRun($doc, $runId);

            if (!$hasVerificationOk || !$hasFileScanAsk) {
                $emit && $emit([
                    'title' => 'RESUME_PRECHECK_FAILED',
                    'description' => 'Logs do not confirm prior verification OK and ASK decision for this run',
                    'meta' => ['verification_ok' => $hasVerificationOk, 'ask_for_run' => $hasFileScanAsk, 'run_id' => $runId],
                ]);
                return $this->emitAsk($emit, null, ['reason' => 'precheck_failed']);
            }

            // Delegate to ZipValidationGate to finalize the gate decision on resume
            $gate = $this->zipGate->run(
                pluginDir: $pluginDir,
                zipId: $zipId,
                actor: $actor,
                runId: $runId,
                validatorConfigHash: $validatorConfigHash,
                installerToken: $installerToken,
            );
            $gateDecision = $gate['decision'] ?? null;
            $gateMeta = $gate['meta'] ?? [];

            if ($gateDecision === Install::ASK) {
                return $this->emitAsk($emit, null, $gateMeta);
            }
            if ($gateDecision === Install::BREAK) {
                return $this->emitBreak($emit, null, ['reason' => 'zip_gate_break'] + $gateMeta);
            }

            // If ZIP gate says INSTALL, we skip ValidatorBridge and continue below at Provider Validation (step 2).
            $summary = new InstallSummary(
                verification: ['status' => 'ok'],
                file_scan: ['enabled' => true, 'status' => 'ask-resumed', 'errors' => []],
                zip_validation: null,
                vendor_policy: null,
                composer_plan: null,
                packages: null
            );
        } else {
            // ─────────────────────────────────────────────────────────
            // 1) VALIDATION (Verification + optional FileScan) via ValidatorBridge
            //    Bridge will call onValidationEnd($summary) exactly once.
            // ─────────────────────────────────────────────────────────
            $vb = $this->validatorBridge->run(
                pluginDir: $pluginDir,
                pluginName: $pluginName,
                zipId: $zipId,
                validator: $validator,
                validatorConfig: $validatorConfig,
                validatorConfigHash: $validatorConfigHash,
                actor: $actor,
                runId: $runId,
                emit: $emit,
                onValidationEnd: $onValidationEnd,
                onFileScanError: $onFileScanError
            );

            $summary = $vb['summary'];
            $gateDecision = $vb['decision'] ?? null;
            $gateMeta = $vb['meta'] ?? null;

            if ($gateDecision instanceof Install) {
                if ($gateDecision === Install::ASK) {
                    return $this->emitAsk($emit, $summary, is_array($gateMeta) ? $gateMeta : []);
                }
                if ($gateDecision === Install::BREAK) {
                    return $this->emitBreak($emit, $summary, []);
                }
                // INSTALL → continue
            }
        }

        // ─────────────────────────────────────────────────────────────
        // 2) PROVIDER VALIDATION (simple existence check in staged tree)
        // ─────────────────────────────────────────────────────────────
        $providers = [];
        try {
            $cfg = $this->afs->fs()->readJson($pluginDir . DIRECTORY_SEPARATOR . 'fortiplugin.json');
            $providers = array_values(array_filter((array)($cfg['providers'] ?? []), 'is_string'));
        } catch (Throwable $_) {
        }

        $prov = $this->providerValidation->run(
            pluginDir: $pluginDir,
            pluginName: $pluginName,
            psr4Root: $psr4Root,
            providers: $providers,
            emit: $emit
        );
        if (($prov['status'] ?? 'ok') !== 'ok') {
            return InstallerResult::fromArray(['status' => 'fail', 'summary' => $summary]);
        }

        // ─────────────────────────────────────────────────────────────
        // 3) VENDOR POLICY + COMPOSER PLAN (advisory; host lock is REQUIRED)
        // ─────────────────────────────────────────────────────────────
        $hostComposerLock = (string)(
        config('fortiplugin.installations.host_composer_lock')
            ?: base_path('composer.lock')
        );

        if (!$this->afs->fs()->exists($hostComposerLock)) {
            throw new RuntimeException("Host composer.lock not found at: $hostComposerLock");
        }

        $vendor = $this->vendorPolicy->run(
            pluginDir: $pluginDir,
            hostComposerLock: $hostComposerLock,
            emit: $emit
        );

        $plan = $this->composerPlan->run(
            pluginDir: $pluginDir,
            hostComposerLock: $hostComposerLock,
            emit: $emit
        );

        $packagesMap = $plan['packages'] ?? null;

        // Refresh summary with advisory info
        $summary = new InstallSummary(
            verification: $summary->verification,
            file_scan: $summary->file_scan,
            zip_validation: null,
            vendor_policy: $vendor['vendor_policy'] ?? null,
            composer_plan: $plan['plan'] ?? null,
            packages: $plan['packages'] ?? null
        );

        // ─────────────────────────────────────────────────────────────
        // 4) DB PERSIST + ROUTE WRITE (to STAGING) — TRANSACTION
        // ─────────────────────────────────────────────────────────────
        $pluginId = null;
        $pluginVersionId = null;

        DB::beginTransaction();
        try {
            $persist = $this->dbPersist->run(
                meta: $meta,
                versionTag: $versionTag,
                zipId: $zipId,
                packages: $packagesMap,
                emit: $emit
            );
            if (($persist['status'] ?? 'fail') !== 'ok') {
                throw new RuntimeException('DB persist failed');
            }
            $pluginId = $persist['plugin_id'] ?? null;
            $pluginVersionId = $persist['plugin_version_id'] ?? null;
            if (!$pluginId) {
                throw new RuntimeException('DB persist did not return plugin_id');
            }

            // Routes: discover + compile JSON, then write PHP into STAGING
            $bundle = $this->routeUiBridge->discoverAndCompile($pluginDir, $emit);
            $compiled = $bundle['compiled'] ?? [];

            if (!empty($compiled)) {
                $plugin = Plugin::query()->findOrFail($pluginId);
                $write = $this->routeWriterSection->run(
                    plugin: $plugin,
                    compiled: $compiled,
                    emit: $emit
                );
                if (($write['status'] ?? 'fail') !== 'ok') {
                    throw new RuntimeException('Route write failed: ' . ($write['reason'] ?? 'unknown'));
                }

                // UI config validation (advisory; logs errors/warnings)
                $hostScheme = (array)config('fortipluginui', []);
                $this->uiConfigValidation->run(
                    meta: $meta,
                    knownRouteIds: $bundle['route_ids'] ?? [],
                    hostScheme: $hostScheme,
                    emit: $emit
                );
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $emit && $emit([
                'title' => 'DB_TRANSACTION_ROLLBACK',
                'description' => 'Persistence or route write failed; rolled back',
                'meta' => ['exception' => $e->getMessage()],
            ]);
            return InstallerResult::fromArray([
                'status' => 'fail',
                'summary' => $summary,
            ]);
        }

        // ─────────────────────────────────────────────────────────────
        // 5) INSTALL FILES (move staged → installed; includes staged routes)
        // ─────────────────────────────────────────────────────────────
        $files = $this->installFiles->run(
            meta: $meta,
            stagingPluginRoot: $pluginDir,
            emit: $emit
        );
        if (($files['status'] ?? 'fail') !== 'ok') {
            $emit && $emit(['title' => 'INSTALL_FILES_FAIL', 'description' => 'Failed moving staged files into place']);
            return InstallerResult::fromArray([
                'status' => 'fail',
                'summary' => $summary,
                'plugin_id' => (int)$pluginId,
                'plugin_version_id' => $pluginVersionId,
            ]);
        }

        // ─────────────────────────────────────────────────────────────
        // 6) FINISH
        // ─────────────────────────────────────────────────────────────
        $result = InstallerResult::fromArray([
            'status' => 'ok',
            'summary' => $summary,
            'plugin_id' => (int)$pluginId,
            'plugin_version_id' => $pluginVersionId,
        ]);

        if (is_callable($onFinish)) {
            try {
                $onFinish($result);
            } catch (Throwable $_) {
            }
        }

        return $result;
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    private function verificationOk(array $doc): bool
    {
        // Accept a few possible shapes from VerificationSection
        // e.g. ['sections'=>['verification'=>['summary'=>['status'=>'ok']]]] or flat.
        $v = $doc['sections']['verification'] ?? $doc['verification'] ?? null;
        if (is_array($v)) {
            $status = $v['summary']['status'] ?? $v['status'] ?? null;
            return $status === 'ok';
        }
        return false;
    }

    private function hasDecisionAskForRun(array $doc, string $runId): bool
    {
        $decisions = $doc['decisions'] ?? [];
        if (!is_array($decisions)) return false;
        foreach ($decisions as $d) {
            if (!is_array($d)) continue;
            if (($d['status'] ?? null) === 'ask' && ($d['run_id'] ?? null) === $runId) {
                return true;
            }
        }
        return false;
    }

    private function emitAsk(?callable $emit, ?InstallSummary $summary, array $meta): InstallerResult
    {
        $payload = [
            'title' => 'INSTALLATION_ASK',
            'description' => 'Installation paused for host decision',
            'meta' => $meta,
        ];
        $emit && $emit($payload);

        return InstallerResult::fromArray([
            'status' => 'ask',
            'summary' => $summary,
            'meta' => $meta,
        ]);
    }

    private function emitBreak(?callable $emit, ?InstallSummary $summary, array $meta): InstallerResult
    {
        $payload = [
            'title' => 'INSTALLATION_BREAK',
            'description' => 'Installation halted by policy',
            'meta' => $meta,
        ];
        $emit && $emit($payload);

        return InstallerResult::fromArray([
            'status' => 'break',
            'summary' => $summary,
            'meta' => $meta,
        ]);
    }
}
```

---
#### 47


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
#### 48


` File: src/Installations/Readme.md`  [↑ Back to top](#index)

```txt
# Installations Module — README (Draft)

> **Scope:** Host-side installer that makes plugin installs safe and predictable without executing plugin code. This module orchestrates verification, optional security scans, vendor policy, Composer planning, atomic file copy, DB persistence, and decision/override flows — while writing a single canonical state file.

---

## 1) Goals & Non‑Goals

**Goals**

* Guarantee **program‑integrity** at install time (PSR‑4 root sync, `fortiplugin.json`, host config, permissions manifest, routes).
* Optionally run security file scans (host decides) and support a human‑in‑the‑loop **ASK** override.
* Keep a **single canonical log/state** file: `Plugins/{slug}/.internal/logs/installation.json`.
* Plan Composer dependencies; the **host** runs Composer — the installer never does.
* Persist final state to DB (`Plugin`, `PluginVersion`, `PluginZip` linkage) and keep a tamper‑evident audit trail.

**Non‑Goals**

* No runtime activation here (Activator is separate and comes later).
* No automatic Composer execution.
* No mutation of validator emissions — **verbatim** logging.

---

## 2) Public API (Installer)

```php
$installer
  ->emitWith(callable $fn)                 // unified emitter for validators + installer sections
  ->enableFileScan()                       // optional (default OFF)
  ->onFileScanError(fn($errors, $tokenCtx): Install) // default returns Install::ASK
  ->onValidationEnd(fn($summary) => void)
  ->install(int|string $plugin_zip_id, ?string $installer_token = null): DecisionResult;
```

**Enum:**

```php
enum Install { case BREAK; case INSTALL; case ASK; }
```

---

## 3) Life‑Cycle Phases (High level)

1. **Preflight (staging)**
   Safe extract, resolve slug, compute fingerprint and validator_config_hash. *(No validation yet.)*

2. **Validation block (everything that uses the Validation service)**

    * Mandatory program‑integrity checks: PSR‑4 root sync, `fortiplugin.json`, host config, permissions, routes.
    * Optional **file scan** (only if `enableFileScan()` was set).
    * Capture **all** validator emissions verbatim into `logs.validation_emits`.
    * Build the full **validation summary** (includes file‑scan results if it ran) and persist it to `installation.json`.

3. **`onValidationEnd($summary)`**
   Invoke your callback **exactly once here**, at the **end of the validation block** and **before** any non‑validation steps.

4. **Zip Validation Gate**
   Read `PluginZip.validation_status`: `verified` → continue; `pending` → issue/extend **background_scan** token and return **ASK**; `failed/unknown` → **BREAK**.

5. **Vendor Policy**
   Choose `STRIP_BUNDLED_VENDOR` (default) or `ALLOW_BUNDLED_VENDOR`; record in `installation.json.vendor_policy`.

6. **Composer Plan (dry)**
   Diff plugin requires vs host lock; mark `skip|add|conflict`; build **packages** map (foreign/non‑foreign) for UI and policy.

7. **Install (atomic)**
   Copy to `Plugins/{slug}/…` then promote pointer; update `installation.json.install`.

8. **DB Persist**
   Upsert `Plugin`, create `PluginVersion`, link `PluginZip` (must be `verified`); mirror `Plugin.meta.packages`.

9. **Decision (return)**
   Return `installed | ask | break` with summary and (where applicable) safe token metadata.

## 4) Directory Structure (module only) (module only)

```
src/Installations/
  Installer.php
  InstallerPolicy.php
  
  Enums/
    Install.php
    VendorMode.php
    ZipValidationStatus.php
    PackageStatus.php

  Contracts/
    Emitter.php
    ZipRepository.php
    PluginRepository.php
    RouteRegistry.php
    PermissionRegistry.php
    LockManager.php
    Clock.php
    Filesystem.php

  DTO/
    InstallContext.php
    InstallSummary.php
    ComposerPlan.php
    PackageEntry.php
    TokenContext.php
    DecisionResult.php

  Sections/
    VerificationSection.php
    ZipValidationGate.php
    FileScanSection.php
    VendorPolicySection.php
    ComposerPlanSection.php
    InstallFilesSection.php
    DbPersistSection.php

  Support/
    InstallationLogStore.php
    InstallerTokenManager.php
    ValidatorBridge.php
    ComposerInspector.php
    Psr4Checker.php
    AtomicFilesystem.php
    PathSecurity.php
    Fingerprint.php
    EmitterMux.php

  Exceptions/
    ValidationFailed.php
    ZipValidationFailed.php
    TokenInvalid.php
    ComposerConflict.php
    FilesystemError.php
    DbPersistError.php
```

> **Activator** will live later in `src/Installations/Activator/` (out of scope in this README draft).

---

## 5) Emissions (Event Contract)

* The Installer **bridges validator `$emit` verbatim** to the unified emitter and to disk logs.
* Installer‑origin events use the same envelope: `{ title, description, error, stats:{filePath,size}, meta:? }`.
* **No mutation** of validator `meta`; installer may add context only to its **own** events.

Typical installer titles:

* `Installer: Zip Validation`
* `Installer: Vendor Policy`
* `Installer: Composer Plan`
* `Installer: Files Copied`
* `Installer: DB Persist`
* `Installer: Decision <install|ask|break>`

---

## 6) Canonical State File

**Path:** `Plugins/{slug}/.internal/logs/installation.json`

**Write discipline:** atomic (tmp → rename), section merge (no clobber), optional short‑lived `.lock`.

**Contents (top‑level):**

* `meta` — slug, zip_id, fingerprint, validator_config_hash, psr4_root, actor, timestamps, paths.
* `verification` — `status`, `errors[]`, `warnings[]`, per‑check details, `finished_at`.
* `zip_validation` — `plugin_zip_status: verified|pending|failed|unknown`.
* `file_scan` — `enabled`, `status: skipped|pass|fail|pending`, `errors[]`.
* `vendor_policy` — `mode: strip_bundled_vendor|allow_bundled_vendor`.
* `composer_plan` — actions (`skip|add|conflict`) + core conflicts.
* `packages` — full map for **all** packages used by the plugin (see §9).
* `decision` — last decision, reason, and **safe** token metadata (no secrets).
* `install` — status, paths, timestamps.
* `activate` — status (will be used by Activator later).
* `logs.validation_emits[]` — validator events **verbatim**.
* `logs.installer_emits[]` — installer events.

---

## 7) Vendor Policy

* **Default:** `STRIP_BUNDLED_VENDOR` — ignore/delete plugin `vendor/` from staging (safer, faster scans).
* **Optional:** `ALLOW_BUNDLED_VENDOR` — keep plugin `vendor/` (higher collision risk). If chosen, file scans can target this directory.

Record decision in `installation.json.vendor_policy.mode`.

---

## 8) Composer Plan (Dry)

* Compare plugin requirements (from plugin’s `composer.json` or `fortiplugin.json` requires) with host `composer.lock`.
* For each package: decide `skip|add|conflict` and collect **core conflicts** (e.g., `laravel/framework`, `php`, `ext-*`).
* Persist under `installation.json.composer_plan`.
* The **host** executes Composer separately at the project root if they approve the plan.

---

## 9) Foreign Package Scanning & Meta

During Composer Plan we produce **full package visibility** and store it in both `installation.json` and (later) `Plugin.meta`.

```ts
interface Meta {
  packages: {
    [name: string]: {
      is_foreign: boolean;                    // true if host lock doesn't satisfy constraint
      status: 'verified'|'unverified'|'pending'|'failed';
    };
  };
}
```

**Defaults**

* Foreign packages → `unverified`.
* Already‑satisfied (from host lock) → `verified`.

**Optional host action:** “Scan foreign packages now?”

* If **Yes**: mark `pending`, run scans against allowed sources, then set `verified` or `failed` and reuse `onFileScanError()` decision path (`ASK|BREAK|INSTALL`).
* If **No**: keep `unverified` and proceed; **Activator** policy can block activation until they’re `verified` or override is granted.

---

## 10) Zip Validation Gate

* Reads `PluginZip.validation_status` right after Verification.
* Actions:

    * `verified` → continue.
    * `pending` → ISSUE/EXTEND **background_scan** token (10‑min TTL, one‑time; bound to zip_id + fingerprint + config hash + actor). Update `decision` snapshot and return **ASK**.
    * `failed` or unknown → BREAK.

Tokens are **encrypted to client** and **hashed server‑side**. No secrets are written to disk logs.

---

## 11) File Scan (Optional)

* Only runs if `enableFileScan()` was called.
* On any hit: call `onFileScanError($errors,$tokenCtx)` → `Install::ASK|BREAK|INSTALL`.
* If `ASK`: issue **install_override** token and return ASK; if accepted later, proceed to Install without re‑calling `onValidationEnd`.

Validator emissions remain verbatim in `logs.validation_emits`.

---

## 12) Install (Atomic) & DB Persist

* **InstallFilesSection**: copy from staging → `Plugins/{slug}/versions/{ver}` (or similar), then promote `current` pointer; update `installation.json.install`.
* **DbPersistSection**: upsert `Plugin` (Prisma model), create `PluginVersion`, link `PluginZip` (must be `verified`), persist `Plugin.meta.packages`, queue providers/routes (not live).

**Plugin model (excerpt)**

* `plugins.name` unique; `plugin_placeholder_id` unique.
* Status typically `active` (or `installed_inactive` if you prefer) — activation is separate.

---

## 13) Error Taxonomy (Installer‑level)

* `COMPOSER_PSR4_MISMATCH | MISSING_ROOT | JSON_READ_ERROR`
* `CONFIG_MISSING | CONFIG_SCHEMA_INVALID | CONFIG_READ_ERROR`
* `HOST_CONFIG_INVALID | HOST_CONFIG_MISSING_FLAG`
* `PERMISSION_MANIFEST_INVALID | PERMISSION_CATEGORY_UNKNOWN | PERMISSION_ACTION_INVALID`
* `ROUTE_SCHEMA_INVALID | ROUTE_ID_DUPLICATE | ROUTE_PATH_INVALID | ROUTE_METHOD_INVALID | ROUTE_CONTROLLER_OUT_OF_ROOT | ROUTE_MIDDLEWARE_NOT_ALLOWED | ROUTE_FILE_READ_ERROR`
* `ZIP_VALIDATION_FAILED | ZIP_VALIDATION_PENDING`
* `SCAN_*` (when optional scanning is enabled)
* `COMPOSER_CORE_CONFLICT`
* `INSTALL_COPY_FAILED | INSTALL_PROMOTION_FAILED`
* `DB_PERSIST_FAILED`
* `TOKEN_ISSUED | TOKEN_EXTENDED | TOKEN_ACCEPTED | TOKEN_INVALID`

All verification errors are **hard BREAK**. File‑scan errors follow `onFileScanError()`’s return.

---

## 14) Concurrency, Atomicity & Idempotency

* Per‑slug install lock via `LockManager`.
* Atomic writes for `installation.json` (tmp → rename) and pointer promotion.
* Re‑install of same fingerprint is a no‑op (idempotent).

---

## 15) Acceptance Criteria (Checklist)

* [ ] Verification runs, logs verbatim validator emits, and persists snapshot to `installation.json`.
* [ ] Any verification error → installer returns `status:"break"` and no files are installed.
* [ ] Zip gate enforces `verified|pending|failed` with correct token behavior.
* [ ] Optional file scan uses `onFileScanError()` to decide `ASK|BREAK|INSTALL` and records decision safely.
* [ ] Vendor policy recorded; default = strip bundled vendor.
* [ ] Composer plan persists actions and full `packages` map.
* [ ] Foreign package scanning path updates per‑package `status` and can block activation by policy.
* [ ] Files are copied/promoted atomically; DB persisted with `Plugin.meta.packages`.
* [ ] `installation.json` contains both `logs.validation_emits[]` (verbatim) and `logs.installer_emits[]`.
* [ ] No plugin code executed during install.

---

## 16) Quick Usage Example

```php
$installer
  ->emitWith($uiEmitter)
  ->enableFileScan() // optional
  ->onFileScanError(fn($errors,$ctx) => Install::ASK)
  ->onValidationEnd(function($summary){ /* update UI, etc. */ })
  ->install($zipId, $maybeToken);
```

Return shape:

```php
new DecisionResult(
  status: 'installed'|'ask'|'break',
  summary: InstallSummary,
  tokenEncrypted?: string,
  expiresAt?: string
);
```

---

## 17) Next (Out‑of‑scope here)

* **Activator** module: preflight (zip verified, routes approved, packages verified or override), write routes/providers, flip active pointer, audit.
* CLI/Jobs to run Composer and to scan foreign packages post‑plan.

---

# Implementation Path & Build Order

> This is a pragmatic, sequential build plan with a **strict order**. Follow it to land the Installations module incrementally while keeping the surface area testable at each step. No business logic should execute plugin code at any stage.

## Phase 0 — Scaffolding & Contracts (Foundation)

**Goal:** Create stable interfaces and utilities so later sections can compile and run with stubs.

**Create first:**

```
src/Installations/
  Installer.php                                   # empty orchestrator skeleton (methods only)
  InstallerPolicy.php                              # default knobs; file-scan OFF, vendor=STRIP

  Enums/
    Install.php                                    # BREAK | INSTALL | ASK
    VendorMode.php                                 # STRIP_BUNDLED_VENDOR | ALLOW_BUNDLED_VENDOR
    ZipValidationStatus.php                        # verified | pending | failed | unknown
    PackageStatus.php                              # verified | unverified | pending | failed

  Contracts/
    Emitter.php                                    # function(array $payload): void
    ZipRepository.php                              # getZip(), getValidationStatus(), etc. (stubs)
    PluginRepository.php                           # upsertPlugin(), createVersion(), linkZip(), saveMeta()
    RouteRegistry.php                              # ensureUniqueGlobalId(), queueRoutes()
    PermissionRegistry.php                         # registerDefinitions()
    LockManager.php                                # acquire(slug), release(slug)
    Clock.php                                      # now(): DateTimeImmutable
    Filesystem.php                                 # safe fs ops interface

  Support/
    InstallationLogStore.php                       # atomic merge-write to installation.json (empty impl)
    EmitterMux.php                                 # fan-out to UI + log store
    AtomicFilesystem.php                           # tmp→rename helpers (no logic yet)
    PathSecurity.php                               # stubs: validateNoTraversal(), validateNoSymlink()
    Fingerprint.php                                # compute(zip), configHash()
    Psr4Checker.php                                # check(host composer.json, psr4_root)
    ValidatorBridge.php                            # pass-through from ValidatorService emit to Installer emitter (verbatim)
```

**Definition of Done:**

* Installer can be constructed with dependencies, `emitWith()` wires to `EmitterMux`, and `installation.json` can be created with a minimal shell (meta only).

---

## Phase 1 — VerificationSection (Program‑Integrity)

**Goal:** Land the mandatory checks and the `onValidationEnd($summary)` callback. Stop on any error.

**Add:**

```
src/Installations/Sections/
  VerificationSection.php                          # PSR-4, fortiplugin.json, host config, permissions, routes
```

**Wire:**

* `Installer::install()` → acquire lock → **VerificationSection::run()** with a **ValidatorService** instance.
* Bridge validator `$emit` via **ValidatorBridge** into **EmitterMux** and **InstallationLogStore** (verbatim).
* Build a `summary` object and persist into `installation.json.verification`.
* **Do not** call `onValidationEnd` yet; it will be invoked **after** the optional file scan completes (end of the validation block).
* If any error exists → return Decision `break` (no further phases).

**Definition of Done:**

* Fails hard on: PSR‑4 mismatch, missing/invalid fortiplugin.json, host config invalid, permission manifest invalid, route errors.
* Logs show **validation_emits** verbatim; installer emits `Installer: Verification complete`.

---

## Phase 3 — ZipValidationGate

**Goal:** Enforce `PluginZip.validation_status` before any optional scanning or copy.

**Add:**

```
src/Installations/Sections/
  ZipValidationGate.php
Support/
  InstallerTokenManager.php                         # encrypted-to-client, hashed server side
```

**Wire:**

* After Verification, read zip status via **ZipRepository**:

    * `failed|unknown` → emit `Installer: Zip Validation (failed)` → Decision `break`.
    * `pending` → issue/extend token (purpose=`background_scan`), persist decision snapshot, emit `ask`, return.
    * `verified` → continue.

**Definition of Done:**

* `installation.json.zip_validation` updated; `decision` reflects ask/break/continue.

---

## Phase 2 — FileScanSection (Optional)

> **Timing note:** `onValidationEnd($summary)` is invoked **after this phase completes**, capturing both the mandatory checks and any file‑scan results.

**Goal:** Allow hosts to opt‑in scanning and control outcome via `onFileScanError()`.

**Add:**

```
src/Installations/Sections/
  FileScanSection.php
```

**Wire:**

* Run only if `enableFileScan()` was called.
* Collect errors; call `onFileScanError($errors,$ctx)` → act on `Install::ASK|BREAK|INSTALL`.
* On `ASK`, issue **install_override** token; persist decision and return.
* Update `installation.json.file_scan` and append verbatim emits to logs.

**Definition of Done:**

* Default path (no enable) skips cleanly. With enable, errors route through ASK/BREAK/INSTALL.

---

## Phase 4 — VendorPolicySection

**Goal:** Decide STRIP vs ALLOW for plugin `vendor/` and record it.

**Add:**

```
src/Installations/Sections/
  VendorPolicySection.php
```

**Wire:**

* Default `VendorMode::STRIP_BUNDLED_VENDOR`.
* Persist `installation.json.vendor_policy.mode`.

**Definition of Done:**

* If STRIP, staging `vendor/` is excluded from later copy. If ALLOW, it remains (no scanning unless host opts in separately).

---

## Phase 5 — ComposerPlanSection (+ Packages Map)

**Goal:** Produce a dry plan and full package visibility (foreign/non‑foreign) for UI & policy.

**Add:**

```
src/Installations/Sections/
  ComposerPlanSection.php
Support/
  ComposerInspector.php                              # read host composer.json/lock; satisfy/skip/conflict
DTO/
  ComposerPlan.php
  PackageEntry.php                                   # { name, is_foreign, status }
```

**Wire:**

* Compute actions: `skip|add|conflict`; detect **core conflicts** (e.g., laravel/framework, php, ext-*).
* Build `packages` map for **all** plugin packages:

    * `is_foreign = true` if host lock doesn’t satisfy constraint → status `unverified`.
    * else status `verified`.
* Persist: `installation.json.composer_plan` and `installation.json.packages`.
* If your policy marks core conflicts as fatal → Decision `break`.

**Definition of Done:**

* UI can display counts (all vs foreign) and offer “Scan foreign packages now?” using this data.

---

## Phase 6 — InstallFilesSection (Atomic Copy & Promote)

**Goal:** Safely place files under `Plugins/{slug}` without code exec.

**Add:**

```
src/Installations/Sections/
  InstallFilesSection.php
```

**Wire:**

* Use **AtomicFilesystem** + **PathSecurity** to copy from staging → `versions/{ver}` (or fingerprint), then promote `current` pointer.
* Persist `installation.json.install` paths + status `installed`.

**Definition of Done:**

* Atomic rename works; failure emits `INSTALL_COPY_FAILED`/`INSTALL_PROMOTION_FAILED` and returns Decision `break`.

---

## Phase 7 — DbPersistSection

**Goal:** Reflect the install in DB and mirror meta (packages) to the Plugin model.

**Add:**

```
src/Installations/Sections/
  DbPersistSection.php
```

**Wire:**

* **PluginRepository**: upsert `Plugin`, create `PluginVersion`, link `PluginZip` (must be `verified`).
* Save `Plugin.meta.packages` exactly from `installation.json.packages`.
* Queue routes/providers (definitions only; not active).

**Definition of Done:**

* DB rows exist and are linked; `installation.json` updated with IDs/paths.

---

## Phase 8 — Decision & Returns

**Goal:** Finalize, unlock, and return a stable result.

**Wire:**

* Emit `Installer: Decision <installed|ask|break>`.
* Release lock; return `DecisionResult { status, summary, token? }`.

**Definition of Done:**

* Re‑invocation with the **same fingerprint** and clean state is idempotent (no duplicate work).

---

## Test Plan (Minimum per Phase)

* **P0:** creates `installation.json` with meta; emitter streams.
* **P1:** each verification error path returns `break`; logs contain validator emits verbatim.
* **P2:** zip `pending` issues token and returns `ask`; `failed` breaks.
* **P3:** file-scan enabled → `ASK` yields token; `INSTALL` proceeds; `BREAK` stops.
* **P4:** vendor STRIP removes staged `vendor/` from copy set.
* **P5:** composer plan marks foreign vs satisfied; core conflict can break.
* **P6:** copy/promote is atomic; failures are surfaced.
* **P7:** DB rows/links created and meta.packages mirrored.
* **P8:** result object stable; lock released.

---

## Suggested Sprinting

* **Sprint 1:** Phases 0–1
* **Sprint 2:** Phases 2–3
* **Sprint 3:** Phases 4–5
* **Sprint 4:** Phases 6–7
* **Sprint 5:** Phase 8 + hardening (locks, idempotency, race tests)

---

## Notes

* **Emitters:** validator payloads remain **verbatim**; installer adds its own under `logs.installer_emits`.
* **Composer:** installer never executes Composer; only plans.
* **Tokens:** encrypted to client, hashed server-side; bound to zip_id + fingerprint + config hash + actor.
* **Activation:** separate module (later).

---

# File‑by‑File Purpose (Detailed)

Below is a concise but comprehensive description of **every file** in the Installations module, what it owns, the inputs/outputs it works with, and any notable side‑effects or failure modes.

## Root

* **Installer.php**
  Orchestrator for the entire install flow. Coordinates phases (staging → verification → zip gate → optional file scan → vendor policy → composer plan → file copy/promote → DB persist → decision). Holds hooks (`emitWith`, `enableFileScan`, `onFileScanError`, `onValidationEnd`) and the public `install($zipId, $token)` entrypoint. Ensures validator emissions are bridged **verbatim**, writes to `installation.json` via `InstallationLogStore`, and guarantees **no plugin code executes**. Main failure modes: validation failure, zip gate failure, composer core conflict, file copy/promote error, DB persist error, invalid/expired token.

* **InstallerPolicy.php**
  Central default configuration and guardrails. Defines defaults (file scan OFF, vendor mode = STRIP, activation gating rules for foreign packages, core conflict handling). Exposes getters used by sections to make consistent decisions without duplicating magic values.

## Enums

* **Enums/Install.php**
  Ternary decision from file‑scan (and similar) callbacks: `BREAK` (abort), `ASK` (pause & issue token), `INSTALL` (proceed). Used by `FileScanSection` and token flows.

* **Enums/VendorMode.php**
  Vendor strategy chosen by host: `STRIP_BUNDLED_VENDOR` (default; ignore/delete plugin `vendor/`) or `ALLOW_BUNDLED_VENDOR` (keep; higher collision risk). Consumed by `VendorPolicySection` and `InstallFilesSection`.

* **Enums/ZipValidationStatus.php**
  Mirrors `PluginZip.validation_status`: `verified`, `pending`, `failed`, `unknown`. Interpreted by `ZipValidationGate` to either continue, ASK with background token, or BREAK.

* **Enums/PackageStatus.php**
  Lifecycle for each dependency in `Meta.packages`: `verified`, `unverified`, `pending`, `failed`. Set by `ComposerPlanSection` (initial), optionally updated by foreign‑package scans.

## Contracts (Interfaces)

* **Contracts/Emitter.php**
  Unified event callback signature: accepts the standard payload `{ title, description, error, stats:{filePath,size}, meta? }`. Implementations may multiplex to UI, logs, metrics.

* **Contracts/ZipRepository.php**
  Accessor for plugin zip records and validation status. Methods typically include: `getZip(zipId)`, `getValidationStatus(zipId)`, `setValidationStatus(zipId, status)`. Used by `ZipValidationGate` and `Installer`.

* **Contracts/PluginRepository.php**
  DB façade for Prisma models: upsert `Plugin`, create `PluginVersion`, link `PluginZip`, persist `Plugin.meta` (including packages), and append audit logs. Used by `DbPersistSection`.

* **Contracts/RouteRegistry.php**
  Read/Write access for global route ID uniqueness checks and registration queue. `ensureUniqueGlobalId(id)`, `queueRoutes(slug, routes)`. Used by `VerificationSection` (validation) and later by Activator for writing.

* **Contracts/PermissionRegistry.php**
  Persists permission definitions extracted from the plugin manifest. Used by `DbPersistSection` to register permissions without granting them.

* **Contracts/LockManager.php**
  Per‑slug install lock: `acquire(slug)`/`release(slug)`. Prevents concurrent installs of the same plugin.

* **Contracts/Clock.php**
  Abstraction for time (e.g., `now()`), enabling deterministic tests and token expiry checks.

* **Contracts/Filesystem.php**
  Safe FS operations used by sections and helpers (exists, readJson, writeAtomic, copyTree, rename, delete). Default implementation should guard against symlinks and traversal via `PathSecurity`.

## DTO

* **DTO/InstallContext.php**
  Immutable context assembled by Installer: zipId, slug, psr4Root, staging & install paths, actor, fingerprint, `validator_config_hash`, vendor mode, policy refs. Passed into sections to avoid parameter bloat.

* **DTO/InstallSummary.php**
  Aggregated, serializable snapshot of the install attempt: statuses/errors for each phase, token metadata (safe), counts, and pointers used for the return value and for writing `installation.json`.

* **DTO/ComposerPlan.php**
  Dry‑run plan: array of actions (`skip|add|conflict`) per package, plus a list of **core conflicts** that may be fatal. Consumed by UI and policy checks.

* **DTO/PackageEntry.php**
  A single entry for `Meta.packages`: `{ name, is_foreign, status }`. `is_foreign = true` when host lock doesn’t satisfy constraint.

* **DTO/TokenContext.php**
  Internal, non‑secret token context: purpose (`install_override` or `background_scan`), expiresAt, zipId, fingerprint, configHash, actor. Stored server‑side (hashed token stored elsewhere), echoed in `installation.json.decision` without secrets.

* **DTO/DecisionResult.php**
  The return type of `Installer::install`: `{ status: installed|ask|break, summary, tokenEncrypted?, expiresAt? }`.

## Sections (Business Logic)

* **Sections/VerificationSection.php**
  Runs **mandatory** program‑integrity checks: PSR‑4 root sync vs host composer, `fortiplugin.json` presence/shape (optionally via schema), Host config expectations, Permission manifest structure/labels, and **Routes** (JSON schema, global unique IDs, path/method rules, controller FQCN sanity, middleware allow‑list, collisions). Bridges ValidatorService emissions verbatim; compiles a `summary` and updates `installation.json.verification`. Any error → Installer returns `break`.

* **Sections/ZipValidationGate.php**
  Checks `PluginZip.validation_status`. `verified` → continue; `pending` → issue/extend **background_scan** token (10‑min TTL, one‑time), update `installation.json.decision`, emit ASK and return; `failed/unknown` → BREAK.

* **Sections/FileScanSection.php**
  Optional deep scans (FileScanner/Content/Token/AST) only if Installer called `enableFileScan()`. On hits, invokes `onFileScanError($errors,$ctx)` which returns `ASK|BREAK|INSTALL`. On `ASK`, issues **install_override** token and returns. Updates `installation.json.file_scan` and logs; validator events stored verbatim.

* **Sections/VendorPolicySection.php**
  Applies vendor strategy. Default: `STRIP_BUNDLED_VENDOR` (ignore plugin `vendor/`). Optional: `ALLOW_BUNDLED_VENDOR` (keep). Records choice in `installation.json.vendor_policy` so subsequent phases honor it (e.g., excluding `vendor/` from copy if STRIP).

* **Sections/ComposerPlanSection.php**
  Reads plugin requirements and host `composer.lock` to compute a dry plan: `skip|add|conflict`. Detects **core conflicts** (e.g., `laravel/framework`, `php`, `ext-*`). Builds the complete `packages` map for visibility (`is_foreign` + initial `status`: foreign→`unverified`, satisfied→`verified`). Persists to `installation.json.composer_plan` and `installation.json.packages`. Policy may BREAK on core conflicts.

* **Sections/InstallFilesSection.php**
  Secure, atomic installation: copies from staging to `Plugins/{slug}/versions/{version|fingerprint}` and promotes the `current` pointer/symlink. Honors vendor policy (skips plugin `vendor/` on STRIP). Updates `installation.json.install`. Failure surfaces `INSTALL_COPY_FAILED` or `INSTALL_PROMOTION_FAILED` and returns `break`.

* **Sections/DbPersistSection.php**
  Persists DB state using `PluginRepository`: upsert `Plugin`, create `PluginVersion`, link `PluginZip` (must be `verified`), persist `Plugin.meta` (including `packages`). Queues routes/providers for later activation. Updates `installation.json` with record IDs/paths. On failure → `DB_PERSIST_FAILED` and `break`.

## Support (Helpers)

* **Support/InstallationLogStore.php**
  Single source of truth for `Plugins/{slug}/.internal/logs/installation.json`. Provides atomic read/merge/write, maintains `logs.validation_emits[]` (verbatim) and `logs.installer_emits[]`, and ensures section updates don’t clobber each other. Handles optional `.lock` and tmp→rename.

* **Support/InstallerTokenManager.php**
  Issues and validates installer tokens. Generates encrypted‑to‑client tokens and stores a server‑side hash bound to `zipId + fingerprint + validator_config_hash + actor`. Supports purposes `install_override` and `background_scan`, one‑time use, TTL extension for pending scans. Never writes secrets to logs.

* **Support/ValidatorBridge.php**
  Thin adapter that forwards ValidatorService `$emit` events to the unified emitter and `InstallationLogStore` **without mutation**. Guarantees validator metadata (`meta`) stays untouched.

* **Support/ComposerInspector.php**
  Parses host `composer.json/lock`, evaluates constraint satisfaction, enumerates existing packages, and flags core packages. Used solely for planning; never runs Composer.

* **Support/Psr4Checker.php**
  Verifies that `env('FORTIPLUGIN_PSR4_ROOT','Plugins')` is mapped in host composer autoload and that the plugin’s intended namespace resolves beneath the root. Returns structured info for errors and for `verification` details.

* **Support/AtomicFilesystem.php**
  High‑level FS ops with atomic guarantees (tmp write + rename, tree copy with rollback). Used by staging extract, install copy, and pointer promotion.

* **Support/PathSecurity.php**
  Guards against path traversal, absolute paths, symlinks, nested phars/zip bombs. Called by extract/copy routines prior to any write.

* **Support/Fingerprint.php**
  Computes a canonical fingerprint (e.g., SHA‑256) of the zip and a `validator_config_hash` for reproducibility and token binding.

* **Support/EmitterMux.php**
  Fan‑out emitter that forwards the same payload to multiple sinks (UI emitter, log store, metrics) without altering content/order.

## Exceptions

* **Exceptions/ValidationFailed.php**
  Thrown when mandatory verification finds any error; caught by Installer to return `break` with a structured summary.

* **Exceptions/ZipValidationFailed.php**
  Raised when `PluginZip.validation_status` is `failed`/`unknown`. Installer converts to a `break` decision.

* **Exceptions/TokenInvalid.php**
  Used for expired/invalid/mismatched installer tokens (purpose, zipId, fingerprint, actor). Leads to `break` with an appropriate installer emit.

* **Exceptions/ComposerConflict.php**
  Signals fatal core conflicts from planning (depending on policy). Installer may choose `break` directly when thrown.

* **Exceptions/FilesystemError.php**
  Wraps copy/promotion/delete failures with safe details (no sensitive paths). Always results in `break`.

* **Exceptions/DbPersistError.php**
  Wraps DB write/link failures. Installer emits failure and returns `break`.

---

## Cross‑Cutting Guarantees

* **No plugin code execution** in any file or phase.
* **Verbatim logging** of validator emissions; installer adds its own events separately.
* **Atomic writes & idempotency** wherever possible (logs, install pointer).
* **Security by construction:** strip plugin `vendor/` by default; host chooses otherwise and can scan foreign packages before activation.

---

## Appendix — Flows (Text Sequence Diagrams)

### A) Clean path (no file-scan hits)

```
Host UI -> Installer               : install(zipId, token? = null)
Installer -> LockManager           : acquire(slug)
Installer -> AtomicFS              : extract to staging
Installer -> ValidatorService      : run(root, emit)  // mandatory checks
ValidatorService -> Installer.emit : (verbatim validator events)
Installer -> InstallationLogStore  : append validation_emits + summary
Installer -> onValidationEnd       : call once with full summary (end of validation block)
Installer -> ZipRepository         : getValidationStatus(zipId)
ZipRepository --> Installer        : "verified"
Installer -> VendorPolicySection   : decide mode (default STRIP)
Installer -> ComposerPlanSection   : build dry plan + packages map
Installer -> InstallFilesSection   : copy to Plugins/{slug}/...; promote pointer
Installer -> DbPersistSection      : upsert Plugin, create PluginVersion, link PluginZip, save meta.packages
Installer -> LockManager           : release(slug)
Installer --> Host UI              : Decision { status: installed, summary }
```

### B) Zip is **pending** (background scan) after validation

```
Host UI -> Installer               : install(zipId)
... (same validation block as A) ...
Installer -> onValidationEnd       : call with summary
Installer -> ZipRepository         : getValidationStatus(zipId)
ZipRepository --> Installer        : "pending"
Installer -> TokenManager          : issue token (purpose = background_scan, TTL 10m)
Installer -> InstallationLogStore  : decision = ask (safe token metadata only)
Installer -> LockManager           : release(slug)
Installer --> Host UI              : Decision { status: ask, token: (encrypted), purpose: background_scan }
```

**Later: resume with background token**

```
Host UI -> Installer               : install(zipId, token=background_scan)
Installer -> TokenManager          : validate token (zipId, fingerprint, configHash, actor)
Installer -> InstallationLogStore  : read installation.json (latest)
[if file_scan.errors exist]
  Installer -> onFileScanError     : ($errors, ctx)  // ASK|BREAK|INSTALL
  alt ASK:
    Installer -> TokenManager      : issue token (install_override)
    Installer --> Host UI          : Decision { status: ask, purpose: install_override }
  alt BREAK:
    Installer --> Host UI          : Decision { status: break }
  alt INSTALL:
    (continue as in A from VendorPolicySection)
[else no file_scan errors]
  (continue as in A from VendorPolicySection)
```

### C) File-scan **enabled** and hits found (zip already verified)

> Note: `onValidationEnd` happens **before** this decision, because file-scan is part of the validation block.

```
Host UI -> Installer               : install(zipId)
... (validation block runs: mandatory checks + file scan) ...
Installer -> onValidationEnd       : call with summary (includes scan results)
Installer -> ZipRepository         : getValidationStatus(zipId) = "verified"
[scan_errors > 0]
  Installer -> onFileScanError     : ($errors, ctx)
  alt ASK:
    Installer -> TokenManager      : issue token (purpose = install_override)
    Installer -> InstallationLog   : decision = ask (safe token metadata)
    Installer -> LockManager       : release
    Installer --> Host UI          : Decision { status: ask, purpose: install_override }
  alt BREAK:
    Installer -> LockManager       : release
    Installer --> Host UI          : Decision { status: break }
  alt INSTALL:
    (continue as in A from VendorPolicySection)
[scan_errors = 0]
  (continue as in A from VendorPolicySection)
```

**Later: resume with install-override token**

```
Host UI -> Installer               : install(zipId, token=install_override)
Installer -> TokenManager          : validate token
// Skip re-running validation and skip onValidationEnd (already completed)
(continue as in A from VendorPolicySection)
```

### D) Emission & logging (applies to all paths)

* **Validator events**: forwarded verbatim to the unified emitter and appended to `installation.json.logs.validation_emits[]`.
* **Installer events**: same envelope, appended to `installation.json.logs.installer_emits[]`.
* **`onValidationEnd`** fires **once**, after the entire validation block (mandatory checks + optional file scan) and before ZipValidationGate.

### E) Token rules (recap)

* **background_scan**: issued only when `PluginZip.validation_status = pending`. On resume, the installer reads `installation.json` and then decides whether to call `onFileScanError` (if scan errors exist) or continue.
* **install_override**: issued only after verified zip **and** file-scan hits; on resume, validation is **not** re-run and `onValidationEnd` is **not** called again—flow proceeds to non-validation phases.
* Tokens are encrypted to the client, hashed server-side, TTL default **10 minutes**, single-use, and bound to `{ zipId, fingerprint, validator_config_hash, actor }`.
```

---
#### 49


` File: src/Installations/Sections/ComposerPlanSection.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use Throwable;
use Timeax\FortiPlugin\Installations\Support\ComposerInspector;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\EmitsEvents;
use Timeax\FortiPlugin\Installations\Support\Events;
use Timeax\FortiPlugin\Installations\Support\ErrorCodes;

/**
 * ComposerPlanSection
 *
 * Responsibilities
 * - Read host composer.lock and plugin composer.json.
 * - Build the per-package “foreign map” (is_foreign + initial UNVERIFIED status).
 * - Compute a conservative Composer plan (add/skip + core_conflicts).
 * - Persist to installation.json under "composer_plan":
 *     {
 *       "packages": { "<name>": { is_foreign, status } ... },
 *       "plan": { actions: {...}, core_conflicts: [...] }
 *     }
 *
 * Notes
 * - This section does NOT execute Composer or modify vendor code.
 * - It merely prepares data for the host UI/flows (and later, optional scans of foreign packages).
 */
final class ComposerPlanSection
{
    use EmitsEvents;

    public function __construct(
        private readonly InstallationLogStore $log,
        private readonly ComposerInspector    $inspector,
    )
    {
    }

    /**
     * @param string $pluginDir Plugin root directory (must contain composer.json)
     * @param string|null $hostComposerLock Absolute path to host composer.lock; if null, defaults to getcwd().'/composer.lock'
     * @param callable|null $emit Optional installer-level emitter fn(array $payload): void
     * @return array{
     *   status: 'ok'|'fail',
     *   packages?: array<string, array{is_foreign:bool,status:string}>,
     *   plan?: array{actions: array<string,string>, core_conflicts: list<string>}
     * }
     */
    public function run(
        string    $pluginDir,
        ?string   $hostComposerLock = null,
        ?callable $emit = null
    ): array
    {
        $emit && $emit(['title' => 'COMPOSER_PLAN_START', 'description' => 'Collecting packages & computing plan']);

        $pluginComposer = rtrim($pluginDir, "\\/") . DIRECTORY_SEPARATOR . 'composer.json';
        $hostLock = $hostComposerLock ?: (getcwd() . DIRECTORY_SEPARATOR . 'composer.lock');

        try {
            // 1) Collect package map (foreign vs host-present)
            $packages = $this->inspector->collectPackages($hostLock, $pluginComposer); // array<string,PackageEntry>

            // 2) Compute plan (actions + core_conflicts)
            $plan = $this->inspector->plan($packages); // ComposerPlan

            // 3) Persist to installation.json under "composer_plan"
            $this->log->writeSection('composer_plan', [
                'packages' => array_map(static fn($e) => $e->toArray(), $packages),
                'plan' => $plan->toArray(),
            ]);

            $emit && $emit(['title' => 'COMPOSER_PLAN_COMPUTED', 'description' => 'Composer plan persisted', 'meta' => [
                'path' => $this->log->path(),
                'packages' => count($packages),
                'core_conflicts' => $plan->core_conflicts,
            ]]);

            return [
                'status' => 'ok',
                'packages' => array_map(static fn($e) => $e->toArray(), $packages),
                'plan' => $plan->toArray(),
            ];
        } catch (Throwable $e) {
            // Emit a concise failure and return
            $this->emitFail(
                Events::COMPOSER_PLAN_FAIL,
                ErrorCodes::FILESYSTEM_READ_FAILED,
                'Failed to compute Composer plan',
                [
                    'exception' => $e->getMessage(),
                    'host_lock' => $hostLock,
                    'plugin_composer' => $pluginComposer,
                ]
            );
            $emit && $emit(['title' => 'COMPOSER_PLAN_FAIL', 'description' => 'Composer plan failed', 'meta' => [
                'error' => $e->getMessage()
            ]]);

            return ['status' => 'fail'];
        }
    }
}
```

---
#### 50


` File: src/Installations/Sections/DbPersistSection.php`  [↑ Back to top](#index)

```php
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
```

---
#### 51


` File: src/Installations/Sections/FileScanSection.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use Random\RandomException;
use Timeax\FortiPlugin\Installations\DTO\DecisionResult;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Services\ValidatorService;
use function count;

/**
 * FileScanSection (no background scans)
 *
 * - Runs scanner-only phase and forwards validator emits verbatim.
 * - Persists raw scan log + a single decision (INSTALL | ASK | BREAK).
 * - ASK is produced *only* when scanner finds issues (shouldFail = true)
 *   and we want the host to explicitly override. In that case we issue an
 *   install-override token (bound to run_id) and let the host decide.
 *
 * NOTE: onValidationEnd() is **owned by ValidatorBridge** and called there once.
 */
final readonly class FileScanSection
{
    public function __construct(
        private InstallerPolicy       $policy,
        private InstallationLogStore  $log,
        private InstallerTokenManager $tokens,
        /** optional installer-level emitter: fn(array $payload): void */
        private mixed                 $emit = null
    )
    {
    }

    /**
     * @param string $pluginDir
     * @param int|string $zipId
     * @param ValidatorService $validator
     * @param string $validatorConfigHash Stable hash of validator config
     * @param string $actor Actor id or 'system'
     * @param string $runId Install run correlation id
     * @param callable|null $onFileScanError fn(array $summary, string $token): Install
     * @param callable|null $emitValidation fn(array $payload): void (verbatim passthrough)
     * @return array{decision: Install, meta: array}
     * @throws JsonException|RandomException
     */
    public function run(
        string           $pluginDir,
        int|string       $zipId,
        ValidatorService $validator,
        string           $validatorConfigHash,
        string           $actor,
        string           $runId,
        ?callable        $onFileScanError = null,
        ?callable        $emitValidation = null
    ): array
    {
        $this->emit && ($this->emit)([
            'title' => 'FILE_SCAN_START',
            'description' => 'Starting file scan',
            'meta' => ['zip_id' => (string)$zipId, 'run_id' => $runId],
        ]);

        $events = [];

        // Forward validator emits verbatim
        $forward = function (array $payload) use (&$events, $emitValidation): void {
            $events[] = $payload;
            if ($emitValidation) {
                $emitValidation($payload);
            }
        };

        // Run scanner only
        $validator->runFileScan($pluginDir, $forward);

        $shouldFail = $validator->shouldFail();
        $logTuples = $validator->getLog();
        $totalIssues = count($logTuples);

        $summaryArray = [
            'should_fail' => $shouldFail,
            'total_issues' => $totalIssues,
        ];

        // Persist raw file_scan block
        $this->log->writeSection('file_scan', [
            'summary' => $summaryArray,
            'events' => $events,
        ]);

        $nowIso = gmdate('c');
        $doc = $this->log->read();
        $fp = $doc['meta']['fingerprint'] ?? null;
        $fpStr = is_string($fp) ? $fp : '';
        $codes = $this->uniqueTypes($logTuples);
        $counts = ['validation_errors' => 0, 'scan_errors' => $totalIssues];
        $enabled = true;

        // Clean → INSTALL
        if (!$shouldFail) {
            $this->log->writeDecision(new DecisionResult(
                status: 'installed',
                at: $nowIso,
                run_id: $runId,
                zip_id: $zipId,
                fingerprint: $fpStr,
                validator_config_hash: $validatorConfigHash,
                file_scan_enabled: $enabled,
                token: null,
                reason: 'file_scan_ok',
                last_error_codes: $codes,
                counts: $counts
            ));

            $this->emitDecision('INSTALL', ['reason' => 'file_scan_ok', 'zip_id' => (string)$zipId]);

            return ['decision' => Install::INSTALL, 'meta' => []];
        }

        // Issues → host override (ASK) or policy BREAK
        $decisionStr = 'ask';
        $reason = 'file_scan_issues_detected';
        $tokenMeta = null;
        $tokenOpaque = null;

        if ($onFileScanError) {
            // Issue install-override token bound to run_id; host decides
            $ttl = $this->policy->getInstallOverrideTtl();
            $tokenOpaque = $this->tokens->issueInstallOverrideToken(
                zipId: $zipId,
                validatorConfigHash: $validatorConfigHash,
                actor: $actor,
                runId: $runId,
                ttlSeconds: $ttl
            );
            $tokenMeta = $this->tokens->summarize('install_override', time() + $ttl);

            $hostDecision = $onFileScanError($summaryArray, $tokenOpaque);
            $decisionStr = $this->mapInstallToDecisionStatus($hostDecision);
            $reason = 'host_decision_on_scan_errors';
        } elseif ($this->policy->shouldBreakOnFileScanErrors()) {
            $decisionStr = 'break';
            $reason = 'policy_break_on_scan_errors';
        }

        $this->log->writeDecision(new DecisionResult(
            status: $decisionStr,
            at: $nowIso,
            run_id: $runId,
            zip_id: $zipId,
            fingerprint: $fpStr,
            validator_config_hash: $validatorConfigHash,
            file_scan_enabled: $enabled,
            token: $tokenMeta,
            reason: $reason,
            last_error_codes: $codes,
            counts: $counts
        ));

        $this->emitDecision(strtoupper($decisionStr), [
            'reason' => $reason,
            'zip_id' => (string)$zipId,
            'token_summary' => $tokenMeta,
        ]);

        return [
            'decision' => $this->mapDecisionStatusToInstallEnum($decisionStr),
            'meta' => array_filter(['token' => $tokenOpaque, 'token_summary' => $tokenMeta]),
        ];
    }

    /** @return list<string> */
    private function uniqueTypes(array $logTuples): array
    {
        $types = [];
        foreach ($logTuples as $t) {
            $type = (string)($t[0] ?? '');
            if ($type !== '') $types[$type] = true;
        }
        return array_keys($types);
    }

    private function mapInstallToDecisionStatus(Install $d): string
    {
        return match ($d) {
            Install::INSTALL => 'installed',
            Install::ASK => 'ask',
            Install::BREAK => 'break',
        };
    }

    private function mapDecisionStatusToInstallEnum(string $s): Install
    {
        return match ($s) {
            'installed' => Install::INSTALL,
            'break' => Install::BREAK,
            default => Install::ASK,
        };
    }

    /** neutral scan-level decision event (Installer handles high-level events) */
    private function emitDecision(string $status, array $meta = []): void
    {
        $this->emit && ($this->emit)([
            'title' => 'FILE_SCAN_DECISION',
            'description' => $status,
            'meta' => $meta,
        ]);
    }
}
```

---
#### 52


` File: src/Installations/Sections/InstallFilesSection.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection PhpUnusedLocalVariableInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Enums\VendorMode;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;

/**
 * InstallFilesSection
 *
 * Responsibilities
 * - Copy staged plugin files into the host install directory.
 * - Respect InstallerPolicy::getVendorMode():
 *    • STRIP_BUNDLED_VENDOR → exclude vendor/ from copy
 *    • ALLOW_BUNDLED_VENDOR → copy vendor/ as-is
 * - Persist a concise "install_files" section into installation.json.
 * - Emit terse installer events (start/ok/fail).
 *
 * Non-goals
 * - Running composer install/update (handled by higher layers).
 * - Activation or DB persistence (handled by other sections).
 */
final readonly class InstallFilesSection
{
    public function __construct(
        private InstallerPolicy      $policy,
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
    ) {}

    /**
     * @param InstallMeta $meta Canonical meta (paths, psr4_root, placeholder_name, etc.)
     * @param string $stagingPluginRoot Absolute path to staged/unpacked plugin root
     * @param callable|null $emit Optional installer-level emitter fn(array $payload): void
     * @return array{status:'ok'|'fail', dest?:string, vendor_mode?:string}
     * @throws JsonException
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        InstallMeta $meta,
        string $stagingPluginRoot,
        ?callable $emit = null
    ): array {
        $dest = (string)($meta->paths['install'] ?? '');
        $vendorMode = $this->policy->getVendorMode();

        // Basic guards
        $emit && $emit([
            'title' => 'INSTALL_FILES_START',
            'description' => 'Copying plugin files into install directory',
            'meta' => [
                'placeholder_name' => $meta->placeholder_name,
                'source' => $stagingPluginRoot,
                'dest' => $dest,
                'vendor_mode' => $vendorMode->value,
            ],
        ]);
        $this->log->appendInstallerEmit([
            'title' => 'INSTALL_FILES_START',
            'description' => 'Copying plugin files into install directory',
            'meta' => [
                'placeholder_name' => $meta->placeholder_name,
                'source' => $stagingPluginRoot,
                'dest' => $dest,
                'vendor_mode' => $vendorMode->value,
            ],
        ]);

        try {
            if ($dest === '') {
                throw new RuntimeException('Install path is missing in meta.paths.install');
            }
            if (!$this->afs->fs()->exists($stagingPluginRoot)) {
                throw new RuntimeException("Staging root not found: $stagingPluginRoot");
            }

            // Ensure destination directory exists
            $this->afs->fs()->ensureDirectory($dest);

            // Build copy filter based on vendor mode (exclude vendor/ when stripping)
            $stripVendor = ($vendorMode === VendorMode::STRIP_BUNDLED_VENDOR);
            $filter = function (string $relativePath) use ($stripVendor): bool {
                if ($stripVendor) {
                    // prevent copying vendor directory and its contents
                    if ($relativePath === 'vendor' || str_starts_with($relativePath, 'vendor/')) {
                        return false;
                    }
                }
                return true;
            };

            // Perform the tree copy
            $this->afs->fs()->copyTree($stagingPluginRoot, $dest, $filter);

            // Persist a concise install_files block
            $this->log->writeSection('install_files', [
                'source'       => $stagingPluginRoot,
                'dest'         => $dest,
                'vendor_mode'  => $vendorMode->value,
                'vendor_stripped' => $stripVendor,
            ]);

            $ok = [
                'title' => 'INSTALL_FILES_OK',
                'description' => 'Plugin files copied successfully',
                'meta' => [
                    'dest' => $dest,
                    'vendor_mode' => $vendorMode->value,
                    'vendor_stripped' => $stripVendor,
                ],
            ];
            $emit && $emit($ok);
            $this->log->appendInstallerEmit($ok);

            return ['status' => 'ok', 'dest' => $dest, 'vendor_mode' => $vendorMode->value];
        } catch (Throwable $e) {
            // Persist failure context (best-effort)
            try {
                $this->log->writeSection('install_files', [
                    'error' => $e->getMessage(),
                    'source' => $stagingPluginRoot,
                    'dest' => $dest,
                    'vendor_mode' => $vendorMode->value,
                ]);
            } catch (Throwable $_) {}

            $fail = [
                'title' => 'INSTALL_FILES_FAIL',
                'description' => 'Failed to copy plugin files',
                'meta' => [
                    'error' => $e->getMessage(),
                    'source' => $stagingPluginRoot,
                    'dest' => $dest,
                    'vendor_mode' => $vendorMode->value,
                ],
            ];
            $emit && $emit($fail);
            $this->log->appendInstallerEmit($fail);

            return ['status' => 'fail'];
        }
    }
}
```

---
#### 53


` File: src/Installations/Sections/ProviderValidationSection.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\Psr4Checker;

/**
 * ProviderValidationSection
 *
 * Responsibilities
 * - If providers are declared, verify their files exist under the staged plugin root.
 * - Resolution rules:
 *    • Full FQCN: "<psr4_root>\<pluginName>\..." → file under <pluginDir>/...\ .php
 *    • Relative:  "Providers\X\Y"               → <pluginDir>/Providers/X/Y.php
 * - Persist "providers_validation" block in installation.json (declared/ok/missing/map).
 * - Emit start/ok/fail installer events (verbatim through the provided emitter).
 *
 * Non-goals
 * - No class loading or inheritance checks.
 */
final readonly class ProviderValidationSection
{
    public function __construct(
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
        private Psr4Checker          $psr4,
    )
    {
    }

    /**
     * Run provider-file presence checks against the staged plugin directory.
     *
     * @param string $pluginDir Absolute path to staged plugin root
     * @param string $pluginName Unique plugin name (namespace segment)
     * @param string $psr4Root Host PSR-4 root (e.g., "Plugins")
     * @param list<string> $providers Values from fortiplugin.json ("providers" array)
     * @param callable|null $emit Optional emitter: fn(array $payload): void
     *
     * @return array{status:'ok'|'fail', missing?:list<string>}
     *
     * @throws JsonException
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        string    $pluginDir,
        string    $pluginName,
        string    $psr4Root,
        array     $providers,
        ?callable $emit = null
    ): array
    {
        $pluginDir = rtrim($pluginDir, "\\/");

        if ($pluginDir === '' || !$this->afs->fs()->isDirectory($pluginDir)) {
            throw new RuntimeException('Provider check: valid $pluginDir is required.');
        }

        // Emit start
        $start = [
            'title' => 'PROVIDERS_CHECK_START',
            'description' => 'Validating declared providers exist in staged plugin',
            'meta' => [
                'plugin' => $pluginName,
                'declared_count' => count($providers),
                'staging' => $pluginDir,
            ],
        ];
        $emit && $emit($start);
        $this->log->appendInstallerEmit($start);

        // Quick exit if no providers declared
        if ($providers === []) {
            $this->log->writeSection('providers_validation', [
                'declared' => 0,
                'ok' => 0,
                'missing' => [],
                'files' => [],
            ]);
            $ok = [
                'title' => 'PROVIDERS_CHECK_OK',
                'description' => 'No providers declared',
                'meta' => ['declared' => 0],
            ];
            $emit && $emit($ok);
            $this->log->appendInstallerEmit($ok);
            return ['status' => 'ok'];
        }

        // Namespace prefix computed from host psr4Root + pluginName
        [$nsPrefix, /* $dirRel */] = $this->psr4->expected($psr4Root, $pluginName);

        $fs = $this->afs->fs();
        $missing = [];
        $fileMap = []; // provider => resolved absolute path

        foreach ($providers as $prov) {
            if (!is_string($prov) || $prov === '') {
                $missing[] = $prov;
                continue;
            }

            $relative = $prov;
            if (str_starts_with($prov, $nsPrefix)) {
                // Strip "<psr4Root>\<pluginName>\"
                $relative = substr($prov, strlen($nsPrefix));
            }
            $relative = ltrim($relative, '\\/');

            $path = $pluginDir . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            $fileMap[$prov] = $path;

            if (!$fs->exists($path) || !$fs->isFile($path)) {
                $missing[] = $prov;
            }
        }

        // Persist results
        $doc = [
            'declared' => count($providers),
            'ok' => count($providers) - count($missing),
            'missing' => $missing,
            'files' => $fileMap,
        ];
        try {
            $this->log->writeSection('providers_validation', $doc);
        } catch (Throwable) {
            // best-effort; keep flowing
        }

        if ($missing !== []) {
            $fail = [
                'title' => 'PROVIDERS_CHECK_FAIL',
                'description' => 'One or more providers missing',
                'meta' => ['missing' => $missing],
            ];
            $emit && $emit($fail);
            $this->log->appendInstallerEmit($fail);
            return ['status' => 'fail', 'missing' => $missing];
        }

        $ok = [
            'title' => 'PROVIDERS_CHECK_OK',
            'description' => 'All providers present in staged plugin',
            'meta' => ['count' => count($providers)],
        ];
        $emit && $emit($ok);
        $this->log->appendInstallerEmit($ok);

        return ['status' => 'ok'];
    }
}
```

---
#### 54


` File: src/Installations/Sections/RouteWriteSection.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\RouteMaterializer;
use Timeax\FortiPlugin\Installations\Support\RouteRegistryStore;
use Timeax\FortiPlugin\Models\Plugin;

/**
 * RouteWriteSection (registry-first)
 *
 * - Reads registry (.internal/routes.registry.json) written by RouteUiBridge.
 * - Materializes per-route PHP files into <staging>/routes/.
 * - Writes aggregator "fortiplugin.route.php" with a health route and requires.
 * - Persists a "routes_write" block (dir, files, aggregator, registry).
 * - Emits start/ok/fail installer events.
 */
final readonly class RouteWriteSection
{
    public function __construct(
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
        private RouteRegistryStore   $registry,
        private RouteMaterializer    $materializer,
    )
    {
    }

    /**
     * @param Plugin $plugin Eloquent Plugin model (slug used for health route)
     * @param array<int, array{
     *   source?: string,
     *   php: string,
     *   routeIds: string[],
     *   slug: string
     * }> $compiled (ignored for writing; kept for compatibility with caller)
     * @param callable|null $emit Optional installer emitter fn(array $payload): void
     *
     * @return array{
     *   status: 'ok'|'fail',
     *   dir?: string,
     *   files?: string[],
     *   aggregator?: string,
     *   registry?: string,
     *   reason?: string
     * }
     *
     * @throws JsonException
     */
    public function run(
        Plugin    $plugin,
        array     $compiled,
        ?callable $emit = null
    ): array
    {
        // Resolve STAGING root from installation log meta
        $doc = $this->log->read();
        $meta = (array)($doc['meta'] ?? []);
        $paths = (array)($meta['paths'] ?? []);
        $stagingRoot = (string)($paths['staging'] ?? '');

        if ($stagingRoot === '') {
            throw new RuntimeException('RouteWriteSection: missing meta.paths.staging in InstallationLogStore.');
        }

        $emit && $emit([
            'title' => 'ROUTES_WRITE_START',
            'description' => 'Materializing routes from registry',
            'meta' => ['staging_root' => $stagingRoot, 'chunks_seen' => count($compiled)],
        ]);
        $this->log->appendInstallerEmit([
            'title' => 'ROUTES_WRITE_START',
            'description' => 'Materializing routes from registry',
            'meta' => ['staging_root' => $stagingRoot],
        ]);

        try {
            $entries = $this->registry->read($stagingRoot);
            if ($entries === []) {
                // Nothing to write (okay)
                $doc = [
                    'dir' => rtrim($stagingRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'routes',
                    'files' => [],
                    'aggregator' => null,
                    'registry' => $this->registry->path($stagingRoot),
                ];
                $this->log->writeSection('routes_write', $doc);

                $emit && $emit([
                    'title' => 'ROUTES_WRITE_OK',
                    'description' => 'No registry entries to write',
                    'meta' => ['dir' => $doc['dir'], 'file_count' => 0],
                ]);
                $this->log->appendInstallerEmit([
                    'title' => 'ROUTES_WRITE_OK',
                    'description' => 'No registry entries to write',
                    'meta' => ['dir' => $doc['dir'], 'file_count' => 0],
                ]);

                return ['status' => 'ok'] + $doc;
            }

            $slug = (string)($plugin->placeholder->slug ?? $plugin->slug ?? 'plugin');
            $mat = $this->materializer->materialize($stagingRoot, $slug, $entries);

            $out = [
                'dir' => $mat['dir'],
                'files' => $mat['files'],
                'aggregator' => $mat['aggregator'],
                'registry' => $this->registry->path($stagingRoot),
            ];

            $this->log->writeSection('routes_write', $out);

            $emit && $emit([
                'title' => 'ROUTES_WRITE_OK',
                'description' => 'Routes registry materialized',
                'meta' => ['dir' => $mat['dir'], 'file_count' => count($mat['files']), 'aggregator' => $mat['aggregator']],
            ]);
            $this->log->appendInstallerEmit([
                'title' => 'ROUTES_WRITE_OK',
                'description' => 'Routes registry materialized',
                'meta' => ['dir' => $mat['dir'], 'file_count' => count($mat['files']), 'aggregator' => $mat['aggregator']],
            ]);

            return ['status' => 'ok'] + $out;
        } catch (Throwable $e) {
            $emit && $emit([
                'title' => 'ROUTES_WRITE_FAIL',
                'description' => 'Materialization error',
                'meta' => ['exception' => $e->getMessage()],
            ]);
            $this->log->appendInstallerEmit([
                'title' => 'ROUTES_WRITE_FAIL',
                'description' => 'Materialization error',
                'meta' => ['exception' => $e->getMessage()],
            ]);

            $this->log->writeSection('routes_write', [
                'error' => 'exception',
                'exception' => $e->getMessage(),
                'registry' => $this->registry->path($stagingRoot),
            ]);

            return ['status' => 'fail', 'reason' => 'exception'];
        }
    }
}
```

---
#### 55


` File: src/Installations/Sections/UiConfigValidationSection.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use Throwable;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\ErrorCodes;

/**
 * UiConfigValidationSection
 *
 * Validates plugin UIConfig against host-defined UIScheme and the plugin's known route IDs.
 * - Never blocks install; only logs to installation.json and emits installer events.
 * - Supports floating UI via sections named "floating.{zoneId}" (zones come from hostScheme['floating']['zones']).
 * - Ensures each item.id matches a known route id from the plugin routes.
 * - Checks section/target extendability and extra prop typing.
 */
final readonly class UiConfigValidationSection
{
    public function __construct(
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
    ) {}

    /**
     * @param InstallMeta $meta Must include paths['staging'] (for fortiplugin.json).
     * @param list<string> $knownRouteIds Route IDs validated earlier (from routes JSON).
     * @param array<string,mixed> $hostScheme Host UIScheme:
     *        [
     *          'sections' => [
     *              'header.main' => ['extendable'=>true,'extraProps'=>[...],'allowUnknownProps'=>bool,'targets'=>[...]],
     *              'sidebar.primary' => [...],
     *              ...
     *          ],
     *          'floating' => [
     *              'zones' => [
     *                  'bottom-right' => ['extendable'=>true,'extraProps'=>[...],'allowUnknownProps'=>bool]
     *              ]
     *          ]
     *        ]
     * @param callable|null $emit Optional: fn(array $payload): void
     *
     * @return array{
     *   status:'ok',
     *   declared:int,
     *   accepted:int,
     *   errors:list<array<string,mixed>>,
     *   warnings:list<array<string,mixed>>
     * }
     * @throws JsonException
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        InstallMeta $meta,
        array       $knownRouteIds,
        array       $hostScheme,
        ?callable   $emit = null
    ): array
    {
        $staging = (string)($meta->paths['staging'] ?? '');
        $cfgPath = rtrim($staging, "\\/") . DIRECTORY_SEPARATOR . 'fortiplugin.json';

        $errors = [];
        $warnings = [];
        $placements = [];
        $declared = 0;
        $accepted = 0;

        // Build the unified section map (includes floating zones as "floating.{zone}")
        $sections = $this->buildSectionsIndex($hostScheme);

        // Start event
        $start = [
            'title' => 'UI_CONFIG_CHECK_START',
            'description' => 'Validating UI configuration',
            'meta' => [
                'staging' => $staging,
                'scheme_sections' => array_keys($sections),
            ],
        ];
        $emit && $emit($start);
        $this->log->appendInstallerEmit($start);

        // If no fortiplugin.json → nothing to validate (OK)
        if (!$this->afs->fs()->exists($cfgPath)) {
            $ok = [
                'title' => 'UI_CONFIG_CHECK_OK',
                'description' => 'No fortiplugin.json found; skipping UIConfig validation',
            ];
            $emit && $emit($ok);
            $this->log->appendInstallerEmit($ok);

            $this->log->writeSection('ui_config', [
                'declared' => 0,
                'accepted' => 0,
                'errors' => [],
                'warnings' => [],
                'placements' => [],
            ]);

            return ['status' => 'ok', 'declared' => 0, 'accepted' => 0, 'errors' => [], 'warnings' => []];
        }

        // Read plugin UIConfig
        $uiItems = [];
        try {
            $cfg = $this->afs->fs()->readJson($cfgPath);
            $uiItems = (array)($cfg['uiConfig']['items'] ?? []);
        } catch (Throwable $e) {
            $warnings[] = ['code' => ErrorCodes::CONFIG_READ_FAILED, 'detail' => 'Cannot read fortiplugin.json', 'exception' => $e->getMessage()];
        }

        $declared = count($uiItems);
        $routeSet = array_fill_keys(array_map('strval', $knownRouteIds), true);

        $seenComposite = []; // de-dup on (section, targetId?, id)

        foreach ($uiItems as $idx => $item) {
            if (!is_array($item)) {
                $warnings[] = ['code' => ErrorCodes::UI_ITEM_INVALID, 'itemIndex' => $idx, 'detail' => 'Item is not an object'];
                continue;
            }

            $section = (string)($item['section'] ?? '');
            $routeId = (string)($item['id'] ?? '');      // IMPORTANT: id is the route id
            $text    = (string)($item['text'] ?? '');
            $icon    = isset($item['icon']) ? (string)$item['icon'] : null;
            $href    = isset($item['href']) ? (string)$item['href'] : null;
            $extend  = (array)($item['extend'] ?? []);
            $props   = (array)($item['props'] ?? []);

            // Section existence
            $sec = $sections[$section] ?? null;
            if ($sec === null) {
                $errors[] = [
                    'code' => ErrorCodes::UI_SECTION_NOT_FOUND,
                    'itemIndex' => $idx,
                    'section' => $section,
                ];
                continue;
            }

            // Determine target context (if any)
            $targetId = isset($extend['targetId']) ? (string)$extend['targetId'] : null;
            $target = null;
            if ($targetId !== null && $targetId !== '') {
                $target = (array)($sec['targets'][$targetId] ?? null);
                if ($target === [] || $target === null) {
                    $errors[] = [
                        'code' => ErrorCodes::UI_TARGET_NOT_FOUND,
                        'itemIndex' => $idx,
                        'section' => $section,
                        'targetId' => $targetId
                    ];
                    continue;
                }
                if (!($target['extendable'] ?? false)) {
                    $errors[] = [
                        'code' => ErrorCodes::UI_TARGET_NOT_EXTENDABLE,
                        'itemIndex' => $idx,
                        'section' => $section,
                        'targetId' => $targetId
                    ];
                    continue;
                }
            } else if (!($sec['extendable'] ?? false)) {
                $warnings[] = [
                    'code' => ErrorCodes::UI_SECTION_NOT_EXTENDABLE,
                    'itemIndex' => $idx,
                    'section' => $section
                ];
                // continue anyway (host may still accept)
            }

            // Route linkage via id
            if ($routeId === '' || !isset($routeSet[$routeId])) {
                $errors[] = [
                    'code' => ErrorCodes::UI_ROUTE_ID_MISSING,
                    'itemIndex' => $idx,
                    'section' => $section,
                    'id' => $routeId
                ];
                continue;
            }

            // Optional href sanity (non-blocking)
            if ($href !== null && $href !== '' && !str_starts_with($href, '/') && !preg_match('#^https?://#i', $href)) {
                $warnings[] = [
                    'code' => ErrorCodes::UI_HREF_SUSPECT,
                    'itemIndex' => $idx,
                    'href' => $href,
                    'detail' => 'Href is not absolute or http(s); host may override from route'
                ];
            }

            // Duplicate composite (section + targetId + id)
            $dupKey = $section . "\n" . ($targetId ?? '') . "\n" . $routeId;
            if (isset($seenComposite[$dupKey])) {
                $warnings[] = [
                    'code' => ErrorCodes::UI_DUPLICATE_ITEM,
                    'itemIndex' => $idx,
                    'section' => $section,
                    'targetId' => $targetId,
                    'id' => $routeId
                ];
                // continue; not fatal
            } else {
                $seenComposite[$dupKey] = true;
            }

            // Extra props typing — merge section+target schemas (target overrides)
            $propSpec = (array)($sec['extraProps'] ?? []);
            if ($target) {
                $propSpec = $this->mergePropSpec($propSpec, (array)($target['extraProps'] ?? []));
            }
            $allowUnknown = (bool)($sec['allowUnknownProps'] ?? true);
            if ($target && array_key_exists('allowUnknownProps', $target)) {
                $allowUnknown = (bool)$target['allowUnknownProps'];
            }

            // Validate props
            $propIssues = $this->validateProps($props, $propSpec, $allowUnknown);
            foreach ($propIssues['errors'] as $e) {
                $e['itemIndex'] = $idx;
                $e['section'] = $section;
                if ($targetId) $e['targetId'] = $targetId;
                $errors[] = $e;
            }
            foreach ($propIssues['warnings'] as $w) {
                $w['itemIndex'] = $idx;
                $w['section'] = $section;
                if ($targetId) $w['targetId'] = $targetId;
                $warnings[] = $w;
            }

            // Record placement snapshot
            $placements[] = [
                'section' => $section,
                'targetId' => $targetId,
                'id' => $routeId,
                'text' => $text,
                'icon' => $icon,
                'props' => $props,
                'kind' => str_starts_with($section, 'floating.') ? 'floating' : 'nav',
            ];

            $accepted++;
        }

        // Persist results
        $block = [
            'declared' => $declared,
            'accepted' => $accepted,
            'errors' => $errors,
            'warnings' => $warnings,
            'placements' => $placements,
        ];
        try {
            $this->log->writeSection('ui_config', $block);
        } catch (Throwable $e) {
            // non-blocking
        }

        // Emit end
        if ($errors !== []) {
            $this->log->appendInstallerEmit([
                'title' => 'UI_CONFIG_CHECK_FAIL',
                'description' => 'UIConfig validation recorded errors (non-blocking)',
                'meta' => ['declared' => $declared, 'accepted' => $accepted, 'errors' => count($errors), 'warnings' => count($warnings)],
            ]);
            $emit && $emit([
                'title' => 'UI_CONFIG_CHECK_FAIL',
                'description' => 'UIConfig validation recorded errors (non-blocking)',
                'meta' => ['declared' => $declared, 'accepted' => $accepted, 'errors' => count($errors), 'warnings' => count($warnings)],
            ]);
        } else {
            $msg = [
                'title' => 'UI_CONFIG_CHECK_OK',
                'description' => 'UIConfig validation completed',
                'meta' => ['declared' => $declared, 'accepted' => $accepted, 'warnings' => count($warnings)],
            ];
            $this->log->appendInstallerEmit($msg);
            $emit && $emit($msg);
        }

        return ['status' => 'ok', 'declared' => $declared, 'accepted' => $accepted, 'errors' => $errors, 'warnings' => $warnings];
    }

    /** Build a flat sections index, expanding floating.zones → "floating.{zoneId}". */
    private function buildSectionsIndex(array $hostScheme): array
    {
        $sections = (array)($hostScheme['sections'] ?? []);
        $floating = (array)($hostScheme['floating']['zones'] ?? []);
        foreach ($floating as $zoneId => $spec) {
            $sections['floating.' . $zoneId] = (array)$spec;
        }
        // Normalize each entry
        foreach ($sections as $k => $v) {
            $v = (array)$v;
            $v['targets'] = (array)($v['targets'] ?? []);
            $sections[$k] = $v;
        }
        return $sections;
    }

    /** Merge section + target extraProps (target overrides). */
    private function mergePropSpec(array $base, array $override): array
    {
        // shallow override per prop name
        foreach ($override as $k => $def) {
            $base[$k] = $def;
        }
        return $base;
    }

    /**
     * Validate props against a simple schema:
     *   $spec = ['badge'=>['type'=>'number'], 'align'=>['type'=>'string','enum'=>['left','right']], ...]
     * Returns arrays of structured issues with codes in ErrorCodes.
     *
     * @return array{errors:list<array<string,mixed>>, warnings:list<array<string,mixed>>}
     */
    private function validateProps(array $props, array $spec, bool $allowUnknown): array
    {
        $errors = [];
        $warnings = [];

        // Unknown prop detection
        if (!$allowUnknown) {
            foreach ($props as $k => $_) {
                if (!array_key_exists($k, $spec)) {
                    $warnings[] = ['code' => ErrorCodes::UI_UNKNOWN_PROP, 'prop' => (string)$k];
                }
            }
        }

        // Type + enum checks
        foreach ($spec as $name => $rule) {
            if (!array_key_exists($name, $props)) {
                // If you ever add "required" to spec, check here
                continue;
            }
            $type = (string)($rule['type'] ?? 'string');
            $val = $props[$name];

            if (!$this->isType($val, $type)) {
                $errors[] = [
                    'code' => ErrorCodes::UI_PROP_TYPE_MISMATCH,
                    'prop' => (string)$name,
                    'expected' => $type,
                    'got' => get_debug_type($val),
                ];
                continue;
            }
            if (isset($rule['enum']) && is_array($rule['enum'])) {
                $allowed = array_map('strval', $rule['enum']);
                if (!in_array((string)$val, $allowed, true)) {
                    $errors[] = [
                        'code' => ErrorCodes::UI_PROP_ENUM_VIOLATION,
                        'prop' => (string)$name,
                        'allowed' => $allowed,
                        'got' => (string)$val,
                    ];
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function isType(mixed $v, string $type): bool
    {
        return match (strtolower($type)) {
            'string'  => is_string($v),
            'number', 'float', 'double' => is_int($v) || is_float($v),
            'integer','int' => is_int($v),
            'boolean','bool' => is_bool($v),
            'array'   => is_array($v),
            'object'  => is_array($v) || is_object($v), // we allow assoc array as "object" here
            default   => true, // unknown rule → don’t block
        };
    }
}
```

---
#### 56


` File: src/Installations/Sections/VendorPolicySection.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Enums\VendorMode;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\ComposerInspector;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\DTO\PackageEntry;
use Timeax\FortiPlugin\Installations\Enums\PackageStatus;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;

/**
 * VendorPolicySection
 *
 * Enforces vendor policy and summarizes package usage.
 * Persists its rich block via InstallationLogStore→writeSection('vendor_policy', …).
 */
final readonly class VendorPolicySection
{
    public function __construct(
        private InstallerPolicy      $policy,
        private AtomicFilesystem     $afs,
        private ComposerInspector    $composer,
        private InstallationLogStore $log
    )
    {
    }

    /**
     * @param string $pluginDir Unpacked plugin root
     * @param string|null $hostComposerLock Absolute path to host composer.lock (optional)
     * @param callable(array):void|null $emit Verbatim emitter
     *
     * @return array{
     *   vendor_policy: array{mode:'STRIP_BUNDLED_VENDOR'|'ALLOW_BUNDLED_VENDOR'},
     *   meta: array<string,mixed>
     * }
     * @throws JsonException
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        string    $pluginDir,
        string   $hostComposerLock = null,
        ?callable $emit = null
    ): array
    {
        $mode = $this->policy->getVendorMode(); // VendorMode enum

        $pluginComposer = rtrim($pluginDir, "\\/") . '/composer.json';

        // ---- Collect package map (PackageEntry[]) using only provided API
        $hostLockPresent = is_string($hostComposerLock)
            && $hostComposerLock !== ''
            && $this->afs->fs()->exists($hostComposerLock);

        $emit && $emit([
            'title' => 'VendorPolicy: Inspect',
            'description' => $hostLockPresent
                ? 'Collecting packages using host composer.lock'
                : 'composer.lock not found — treating all plugin requirements as foreign',
            'error' => null,
            'stats' => [
                'filePath' => $hostLockPresent ? $hostComposerLock : $pluginComposer,
                'size' => $hostLockPresent ? $this->afs->fs()->fileSize($hostComposerLock) : $this->afs->fs()->fileSize($pluginComposer),
            ],
            'meta' => ['phase' => 'vendor_policy', 'op' => 'collect_packages']
        ]);

        /** @var array<string,PackageEntry> $packages */
        if ($hostLockPresent) {
            $packages = $this->composer->collectPackages($hostComposerLock, $pluginComposer);
        } else {
            $packages = $this->fallbackCollectFromPlugin($pluginComposer);
        }

        // ---- Derive lists & persistable meta
        $alreadyPresent = [];
        $foreign = [];
        $packagesMeta = [];

        foreach ($packages as $name => $entry) {
            if (!$entry instanceof PackageEntry) {
                throw new RuntimeException("ComposerInspector must return PackageEntry map");
            }
            if ($entry->is_foreign) $foreign[] = $name;
            else $alreadyPresent[] = $name;

            $packagesMeta[$name] = [
                'is_foreign' => $entry->is_foreign,
                'status' => ($entry->status ?? PackageStatus::UNVERIFIED)->value,
            ];
        }

        // ---- Vendor mode enforcement
        $pluginVendor = rtrim($pluginDir, "\\/") . '/vendor';
        $hasVendorDir = $this->afs->fs()->isDirectory($pluginVendor);
        $stripped = false;
        $notes = [];

        if ($mode === VendorMode::STRIP_BUNDLED_VENDOR && $hasVendorDir) {
            $parkTo = rtrim($pluginDir, "\\/") . '/.internal/stripped/vendor-' . date('YmdHis');
            $this->afs->ensureParentDirectory($parkTo);

            try {
                $this->afs->fs()->rename($pluginVendor, $parkTo);
                $stripped = true;

                $emit && $emit([
                    'title' => 'VendorPolicy: Stripped vendor',
                    'description' => 'Plugin vendor/ moved out per policy',
                    'error' => null,
                    'stats' => ['filePath' => $pluginVendor, 'size' => null],
                    'meta' => ['phase' => 'vendor_policy', 'op' => 'strip_vendor', 'parked_to' => $parkTo]
                ]);
            } catch (RuntimeException $e) {
                $notes[] = 'Failed to move vendor/: ' . $e->getMessage();

                $emit && $emit([
                    'title' => 'VendorPolicy: Strip failed',
                    'description' => $e->getMessage(),
                    'error' => ['detail' => 'rename_failed', 'count' => 1],
                    'stats' => ['filePath' => $pluginVendor, 'size' => null],
                    'meta' => ['phase' => 'vendor_policy', 'op' => 'strip_vendor_failed']
                ]);
            }
        } else {
            $emit && $emit([
                'title' => 'VendorPolicy: Keep vendor',
                'description' => $hasVendorDir ? 'Bundled vendor retained per policy' : 'No bundled vendor found',
                'error' => null,
                'stats' => ['filePath' => $pluginVendor, 'size' => null],
                'meta' => ['phase' => 'vendor_policy', 'op' => 'keep_vendor']
            ]);
        }

        // ---- Build block and persist via InstallationLogStore
        $block = [
            'mode' => $mode->name,  // matches TVendorPolicy
            'plugin_has_vendor' => $hasVendorDir,
            'stripped' => $stripped,
            'packages' => $packagesMeta,       // for UI + later DbPersistSection
            'already_present' => array_values($alreadyPresent),
            'foreign' => array_values($foreign),
            'host_lock_present' => $hostLockPresent,
            'notes' => $notes,
        ];
        $this->log->writeSection('vendor_policy', $block);

        // ---- Return DTO-ready summary + meta
        return [
            'vendor_policy' => ['mode' => $mode->name],
            'meta' => $block,
        ];
    }

    /**
     * Fallback when host composer.lock is unavailable:
     * read plugin composer.json and mark all requires (+dev) as foreign/unverified.
     *
     * @param string $pluginComposerJson
     * @return array<string,PackageEntry>
     */
    private function fallbackCollectFromPlugin(string $pluginComposerJson): array
    {
        $fs = $this->afs->fs();
        if (!$fs->exists($pluginComposerJson)) {
            throw new RuntimeException("plugin composer.json not found at $pluginComposerJson");
        }
        $pj = $fs->readJson($pluginComposerJson);
        $req = array_keys((array)($pj['require'] ?? []));
        $reqD = array_keys((array)($pj['require-dev'] ?? []));
        $names = array_unique(array_merge($req, $reqD));

        $out = [];
        foreach ($names as $name) {
            if ($name === 'php' || str_starts_with($name, 'ext-')) {
                continue;
            }
            $out[$name] = new PackageEntry(
                name: $name,
                is_foreign: true,
                status: PackageStatus::UNVERIFIED
            );
        }
        return $out;
    }
}
```

---
#### 57


` File: src/Installations/Sections/VerificationSection.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use Throwable;
use Timeax\FortiPlugin\Services\ValidatorService;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\Events;
use Timeax\FortiPlugin\Installations\Support\ErrorCodes;
use Timeax\FortiPlugin\Installations\Support\EmitsEvents;
use Timeax\FortiPlugin\Installations\Support\Psr4Checker;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;

/**
 * VerificationSection
 *
 * - PSR-4 mapping assert.
 * - Route files mandatory.
 * - Runs HEADLINE validators only (scanners OFF here).
 * - Streams validator emits verbatim into InstallationLogStore.
 * - Persists a compact "verification" section summary.
 *
 * NOTE: onValidationEnd() is **owned by ValidatorBridge** and called there once.
 */
final class VerificationSection
{
    use EmitsEvents;

    public function __construct(
        private readonly InstallerPolicy      $policy,
        private readonly InstallationLogStore $log,
        private readonly Psr4Checker          $psr4,
    ) {}

    /**
     * @param string           $pluginDir         Plugin root on disk
     * @param string           $pluginName        Unique plugin name
     * @param string           $run_id            Correlation id
     * @param ValidatorService $validator         Validation service
     * @param array            $validatorConfig   Must include headline.route_files[]
     * @param callable|null    $emitValidation    fn(array $payload): void  (validator emits passthrough)
     * @return array{status:'ok'|'fail', summary?:array}
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        string           $pluginDir,
        string           $pluginName,
        string           $run_id,
        ValidatorService $validator,
        array            $validatorConfig,
        ?callable        $emitValidation = null
    ): array {
        // 0) Mandatory route files
        $routeFiles = (array)($validatorConfig['headline']['route_files'] ?? []);

        if ($routeFiles === []) {
            $this->emitFail(
                Events::ROUTES_CHECK_FAIL,
                ErrorCodes::ROUTE_SCHEMA_ERROR,
                'Route files missing: route validation is mandatory',
                ['hint' => 'Provide headline.route_files[]', 'plugin_dir' => $pluginDir]
            );
            return ['status' => 'fail'];
        }

        // 1) PSR-4 assert for this plugin
        $psr4Root     = $this->policy->getPsr4Root();
        $composerJson = $pluginDir . DIRECTORY_SEPARATOR . 'composer.json';

        $this->emitOk(Events::PSR4_CHECK_START, "Checking PSR-4 for $pluginName", [
            'psr4_root' => $psr4Root,
            'plugin'    => $pluginName,
            'composer'  => $composerJson,
        ]);

        try {
            $this->psr4->assertMapping($composerJson, $psr4Root, $pluginName);
            $this->emitOk(Events::PSR4_CHECK_OK, 'PSR-4 mapping OK', ['composer' => $composerJson]);
        } catch (Throwable $e) {
            $this->emitFail(
                Events::PSR4_CHECK_FAIL,
                ErrorCodes::COMPOSER_PSR4_MISSING_OR_MISMATCH,
                'Expected PSR-4 mapping is missing or mismatched',
                ['composer' => $composerJson, 'exception' => $e->getMessage()],
                $composerJson
            );
            return ['status' => 'fail'];
        }

        // 2) Headline validators only (disable scanning stack)
        $validator->setIgnoredValidators(['file_scanner', 'content', 'token', 'ast']);

        // Stream validator emits VERBATIM → log store (+ optional passthrough)
        $forward = function (array $payload) use ($emitValidation): void {
            try { $this->log->appendValidationEmit($payload); } catch (JsonException $_) {}
            if ($emitValidation) $emitValidation($payload);
        };

        $this->emitOk(Events::VALIDATION_START, 'Running headline validators');
        $summary = $validator->run($pluginDir, $forward);
        $this->emitOk(Events::VALIDATION_END, 'Headline validators completed', [
            'total_issues' => $summary['total_issues'] ?? null,
            'files_scanned'=> $summary['files_scanned'] ?? null,
        ]);

        // 3) Persist compact verification section (summary only; emits already recorded)
        try {
            $this->log->writeSection('verification', [
                'summary' => $summary,
                'run_id'  => $run_id,
            ]);
            $this->emitOk(Events::SUMMARY_PERSISTED, 'Verification summary persisted', ['path' => $this->log->path()]);
        } catch (Throwable $e) {
            $this->emitFail(
                Events::SUMMARY_PERSISTED,
                ErrorCodes::FILESYSTEM_WRITE_FAILED,
                'Failed to persist verification summary',
                ['exception' => $e->getMessage(), 'plugin_dir' => $pluginDir]
            );
        }

        // 4) Decide (break if policy says to on headline errors)
        if (($summary['should_fail'] ?? false) && $this->policy->shouldBreakOnVerificationErrors()) {
            return ['status' => 'fail', 'summary' => $summary];
        }

        return ['status' => 'ok', 'summary' => $summary];
    }
}
```

---
#### 58


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
#### 59


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
#### 60


` File: src/Installations/Support/ComposerInspector.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use RuntimeException;
use Timeax\FortiPlugin\Installations\DTO\PackageEntry;
use Timeax\FortiPlugin\Installations\DTO\ComposerPlan;
use Timeax\FortiPlugin\Installations\Enums\PackageStatus;

/**
 * Reads host composer.lock + plugin composer.json to compute foreign package map and plan.
 */
final readonly class ComposerInspector
{
    public function __construct(private AtomicFilesystem $fs)
    {
    }

    /** @return array<string,PackageEntry> */
    public function collectPackages(string $hostComposerLock, string $pluginComposerJson): array
    {
        $fs = $this->fs->fs();
        if (!$fs->exists($hostComposerLock)) {
            throw new RuntimeException("composer.lock not found at $hostComposerLock");
        }
        if (!$fs->exists($pluginComposerJson)) {
            throw new RuntimeException("plugin composer.json not found at $pluginComposerJson");
        }

        $lock = $fs->readJson($hostComposerLock);
        $installed = array_column((array)($lock['packages'] ?? []), 'name');
        $installedDev = array_column((array)($lock['packages-dev'] ?? []), 'name');
        $hostSet = array_fill_keys(array_merge($installed, $installedDev), true);

        $pj = $fs->readJson($pluginComposerJson);
        $requires = array_keys((array)($pj['require'] ?? []));
        $requiresDev = array_keys((array)($pj['require-dev'] ?? []));
        $pluginSet = array_unique(array_merge($requires, $requiresDev));

        $out = [];
        foreach ($pluginSet as $name) {
            $isForeign = !isset($hostSet[$name]);
            $out[$name] = new PackageEntry(
                name: $name,
                is_foreign: $isForeign,
                status: PackageStatus::UNVERIFIED
            );
        }
        return $out;
    }

    public function plan(array $packages): ComposerPlan
    {
        $actions = [];
        $coreConflicts = [];

        foreach ($packages as $name => $entry) {
            if (!$entry instanceof PackageEntry) {
                throw new RuntimeException("Package map must contain PackageEntry instances");
            }
            $actions[$name] = $entry->is_foreign ? 'add' : 'skip';
        }

        // Core collision hints (conservative): flag if plugin references these at all.
        foreach (['php', 'laravel/framework'] as $core) {
            if (isset($actions[$core])) {
                $coreConflicts[] = $core;
                $actions[$core] = 'conflict';
            }
        }

        return new ComposerPlan(actions: $actions, core_conflicts: $coreConflicts);
    }
}
```

---
#### 61


` File: src/Installations/Support/EmitsEvents.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

/**
 * Convenience wrappers around EmitterMux that use EmitPayload to build
 * consistent payloads and guarantee verbatim meta passthrough.
 *
 * Drop this trait into classes that need to emit installer/validation events.
 */
trait EmitsEvents
{
    use EmitPayload;

    /** @var EmitterMux|null */
    protected ?EmitterMux $emitterMux = null;

    public function setEmitterMux(EmitterMux $mux): void
    {
        $this->emitterMux = $mux;
    }

    /**
     * Emit a **success/info** event on the installer channel.
     *
     * @param non-empty-string $title
     * @param string|null      $description
     * @param array            $meta
     */
    protected function emitOk(string $title, ?string $description = null, array $meta = []): void
    {
        if (!$this->emitterMux) return;
        $payload = $this->finalize($this->makePayload($title, $description, $meta));
        $this->emitterMux->emitInstaller($payload);
    }

    /**
     * Emit an **error** event (with standardized error block) on the installer channel.
     *
     * @param non-empty-string $title
     * @param non-empty-string $code   One of ErrorCodes::*
     * @param non-empty-string $message
     * @param array            $extra  Any structured details (kept verbatim)
     * @param string|null      $filePath
     * @param int|null         $size
     * @param array            $meta
     */
    protected function emitFail(
        string $title,
        string $code,
        string $message,
        array $extra = [],
        ?string $filePath = null,
        ?int $size = null,
        array $meta = []
    ): void {
        if (!$this->emitterMux) return;

        $payload = $this->merge(
            $this->makePayload($title, $message, $meta),
            ['error' => $this->error($code, $message, $extra)]
        );

        if ($filePath !== null || $size !== null) {
            $payload['stats'] = $this->stats($filePath, $size);
        }

        $this->emitterMux->emitInstaller($this->finalize($payload));
    }

    /**
     * Emit a **validation-side** event (rarely needed—validators emit directly).
     * Use only when the installer must mirror something into the validation stream.
     *
     * @param non-empty-string $title
     * @param string|null      $description
     * @param array            $meta
     * @param string|null      $filePath
     * @param int|null         $size
     */
    protected function emitValidationSide(
        string $title,
        ?string $description = null,
        array $meta = [],
        ?string $filePath = null,
        ?int $size = null
    ): void {
        if (!$this->emitterMux) return;
        $payload = $this->makePayload($title, $description, $meta);
        if ($filePath !== null || $size !== null) {
            $payload['stats'] = $this->stats($filePath, $size);
        }
        $this->emitterMux->emitValidation($this->finalize($payload));
    }
}
```

---
#### 62


` File: src/Installations/Support/ErrorCodes.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

/**
 * Canonical error codes for $payload['error']['code'] and for summary/decision metadata.
 * Keep these stable—your UI and analytics will rely on them.
 */
final class ErrorCodes
{
    // — Composer / PSR-4
    public const COMPOSER_PSR4_MISSING_OR_MISMATCH = 'composer.psr4_mismatch';
    public const COMPOSER_SCAN_ERROR = 'composer.scan_error';

    // — Validation phases
    public const CONFIG_SCHEMA_ERROR = 'config.schema_error';
    public const HOST_CONFIG_INVALID = 'host.config_invalid';
    public const MANIFEST_INVALID = 'manifest.invalid';
    public const VALIDATION_ERRORS_FOUND = 'validation.errors_found';

    // — Routes
    public const ROUTE_SCHEMA_ERROR = 'route.schema_error';
    public const ROUTE_ID_DUPLICATE = 'route.id_duplicate';
    public const ROUTE_CONTROLLER_NAMESPACE_INVALID = 'route.controller_namespace_invalid';

    // — Security scanners
    public const CONTENT_VALIDATION_ERROR = 'scan.content_violation';
    public const TOKEN_USAGE_VIOLATION = 'scan.token_violation';
    public const AST_VIOLATION = 'scan.ast_violation';
    public const SCANNER_EXCEPTION = 'scan.scanner_exception';
    public const FILE_SCAN_ERRORS_FOUND = 'scan.errors_found';

    // — Zip / status gate
    public const ZIP_VALIDATION_PENDING = 'zip.validation_pending';
    public const ZIP_VALIDATION_FAILED = 'zip.validation_failed';
    public const ZIP_VALIDATION_UNKNOWN = 'zip.validation_unknown';
    public const ZIP_STATUS_FAILED_OR_UNKNOWN = 'zip.failed_or_unknown';

    // — Packages / Composer plan
    public const PACKAGES_FOREIGN_NEED_SCAN = 'packages.foreign_need_scan';
    public const PACKAGES_CORE_CONFLICT = 'packages.core_conflict'; // e.g. php, laravel/framework

    // — Tokens / permissions
    public const INSTALLER_TOKEN_REQUIRED = 'token.required';
    public const TOKEN_INVALID = 'token.invalid';
    public const TOKEN_EXPIRED = 'token.expired';
    public const TOKEN_PURPOSE_MISMATCH = 'token.purpose_mismatch';

    // — System / IO / DB
    public const FILESYSTEM_WRITE_FAILED = 'fs.write_failed';
    public const DB_PERSIST_FAILED = 'db.persist_failed';

    // — Installer outcomes
    public const INSTALLATION_DECISION_REQUIRED = 'install.decision_required';
    public const INSTALLATION_ABORTED = 'install.aborted';
    public const INSTALLATION_SUCCESS = 'install.success';
    public const FILESYSTEM_READ_FAILED = 'fs.read_failed';
    public const UI_PROP_ENUM_VIOLATION = 'ui.prop_enum_violation';
    public const UI_PROP_TYPE_MISMATCH = 'ui.prop_type_mismatch';
    public const UI_UNKNOWN_PROP = 'ui.unknown_prop';
    public const UI_DUPLICATE_ITEM = 'ui.duplicate_item';
    public const UI_HREF_SUSPECT = 'ui.href_suspect';
    public const UI_ROUTE_ID_MISSING = 'ui.route_id_missing';
    public const UI_SECTION_NOT_EXTENDABLE = 'ui.section_not_extendable';
    public const UI_TARGET_NOT_EXTENDABLE = 'ui.target_not_extendable';
    public const UI_TARGET_NOT_FOUND = 'ui.target_not_found';
    public const UI_SECTION_NOT_FOUND = 'ui.section_not_found';
    public const UI_ITEM_INVALID = 'ui.item_invalid';
    public const CONFIG_READ_FAILED = 'config.read_failed';
}
```

---
#### 63


` File: src/Installations/Support/Events.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

/**
 * Canonical event titles for installer + validator streams.
 *
 * Use these for $payload['title'] so the UI and logs are consistent.
 * The payload shape stays the unified emitter contract:
 *   [
 *     'title'       => Events::VALIDATION_START,
 *     'description' => string|null,
 *     'error'       => array|null,               // optional structured error
 *     'stats'       => ['filePath'=>?, 'size'=>?],
 *     'meta'        => array|null                // ALWAYS pass through verbatim if provided by validators
 *   ]
 */
final class Events
{
    // — Lifecycle
    public const INIT                = 'INIT';
    public const VALIDATION_START    = 'VALIDATION_START';
    public const VALIDATION_END      = 'VALIDATION_END';
    public const INSTALLER_START     = 'INSTALLER_START';
    public const INSTALLER_END       = 'INSTALLER_END';

    // — PSR-4 checks
    public const PSR4_CHECK_START    = 'PSR4_CHECK_START';
    public const PSR4_CHECK_OK       = 'PSR4_CHECK_OK';
    public const PSR4_CHECK_FAIL     = 'PSR4_CHECK_FAIL';

    // — Route validation / queuing
    public const ROUTES_CHECK_START  = 'ROUTES_CHECK_START';
    public const ROUTES_CHECK_OK     = 'ROUTES_CHECK_OK';
    public const ROUTES_CHECK_FAIL   = 'ROUTES_CHECK_FAIL';
    public const ROUTES_QUEUED       = 'ROUTES_QUEUED';

    // — Security scanning (when host enables file scan)
    public const FILE_SCAN_START     = 'FILE_SCAN_START';
    public const FILE_SCAN_END       = 'FILE_SCAN_END';
    public const FILE_SCAN_ERRORS    = 'FILE_SCAN_ERRORS';

    // — Zip / status gate
    public const ZIP_STATUS_CHECK    = 'ZIP_STATUS_CHECK';
    public const ZIP_STATUS_PENDING  = 'ZIP_STATUS_PENDING';
    public const ZIP_STATUS_VERIFIED = 'ZIP_STATUS_VERIFIED';
    public const ZIP_STATUS_FAILED   = 'ZIP_STATUS_FAILED';

    // — Composer / packages
    public const COMPOSER_COLLECT    = 'COMPOSER_COLLECT';     // build package map
    public const COMPOSER_PLAN_READY = 'COMPOSER_PLAN_READY';  // dry-run actions built
    public const VENDOR_POLICY       = 'VENDOR_POLICY';        // allow/strip bundled vendor

    // — Tokens (ASK + resumes)
    public const TOKEN_ISSUED        = 'TOKEN_ISSUED';
    public const TOKEN_VALID         = 'TOKEN_VALID';
    public const TOKEN_INVALID       = 'TOKEN_INVALID';

    // — Decision + persistence
    public const DECISION_ASK        = 'DECISION_ASK';
    public const DECISION_INSTALL    = 'DECISION_INSTALL';
    public const DECISION_BREAK      = 'DECISION_BREAK';
    public const SUMMARY_PERSISTED   = 'SUMMARY_PERSISTED';

    // — Activation (reserved for the Activator module)
    public const ACTIVATION_START    = 'ACTIVATION_START';
    public const ACTIVATION_END      = 'ACTIVATION_END';
    public const INSTALL_DECISION = 'INSTALL_DECISION';
    public const ZIP_STATUS_UNKNOWN = 'ZIP_STATUS_UNKNOWN';
    public const TOKEN_EXTENDED = 'TOKEN_EXTENDED';
    public const COMPOSER_PLAN_FAIL = 'COMPOSER_PLAN_FAIL';
}
```

---
#### 64


` File: src/Installations/Support/InstallationLogStore.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\Filesystem;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\InstallSummary;
use Timeax\FortiPlugin\Installations\DTO\DecisionResult;

/**
 * Concrete installation.json store with atomic writes and verbatim validation logs.
 *
 * File shape:
 * {
 *   "meta": {...},
 *   "logs": {
 *     "validation_emits": [ ... ],
 *     "installer_emits":  [ ... ]
 *   },
 *   "summary": {...}|null,
 *   "decision": {...}|null
 * }
 */
final class InstallationLogStore
{
    private AtomicFilesystem $atomFs;
    private Filesystem $fs;
    private string $installationJsonPath;
    /** @var array{meta?:array,logs?:array,summary?:array,decision?:array} */
    private array $doc = [];


    public function __construct(AtomicFilesystem $atomFs, string $installationJsonPath)
    {
        $this->atomFs = $atomFs;
        $this->fs = $atomFs->fs();
        $this->installationJsonPath = $installationJsonPath;
    }

    /**
     * @throws JsonException
     */
    public function init(InstallMeta $meta): string
    {
        $dir = dirname($this->installationJsonPath);
        $this->fs->ensureDirectory($dir);

        $this->doc = [
            'meta' => $meta->toArray(),
            'logs' => [
                'validation_emits' => [],
                'installer_emits' => [],
            ],
            'summary' => null,
            'decision' => null,
        ];
        $this->persist();
        return $this->installationJsonPath;
    }

    /** @param array $payload
     * @throws JsonException
     * @throws JsonException
     */
    public function appendValidationEmit(array $payload): void
    {
        $doc = $this->read();
        $doc['logs']['validation_emits'][] = $payload; // verbatim
        $this->doc = $doc;
        $this->persist();
    }

    /** @param array $payload
     * @throws JsonException
     * @throws JsonException
     */
    public function appendInstallerEmit(array $payload): void
    {
        $doc = $this->read();
        $doc['logs']['installer_emits'][] = $payload; // terse, but verbatim too
        $this->doc = $doc;
        $this->persist();
    }

    /**
     * @throws JsonException
     */
    public function writeSummary(InstallSummary $summary): void
    {
        $doc = $this->read();
        $doc['summary'] = $summary->toArray();
        $this->doc = $doc;
        $this->persist();
    }

    /**
     * @throws JsonException
     */
    public function writeDecision(DecisionResult $decision): void
    {
        $doc = $this->read();
        $doc['decision'] = $decision->toArray();
        $this->doc = $doc;
        $this->persist();
    }

    public function path(): string
    {
        return $this->installationJsonPath;
    }

    /** @return array{meta?:array,logs?:array,summary?:array,decision?:array} */
    public function read(): array
    {
        if ($this->doc !== []) {
            return $this->doc;
        }
        if (!$this->fs->exists($this->installationJsonPath)) {
            throw new RuntimeException("installation.json not initialized at $this->installationJsonPath");
        }
        $this->doc = $this->fs->readJson($this->installationJsonPath);
        // Guards for missing keys if the file was created by older versions
        $this->doc['logs'] = $this->doc['logs'] ?? ['validation_emits' => [], 'installer_emits' => []];
        return $this->doc;
    }

    /**
     * Persist an arbitrary structured section under a top-level key
     * like "vendor_policy", "file_scan", "composer_plan", etc.
     *
     * @throws JsonException
     */
    public function writeSection(string $key, array $block): void
    {
        $doc = $this->read();
        $doc[$key] = $block;
        $this->doc = $doc;
        $this->persist();
    }

    /**
     * Read a previously written section (or null if absent).
     */
    public function readSection(string $key): ?array
    {
        $doc = $this->read();
        $val = $doc[$key] ?? null;
        return is_array($val) ? $val : null;
    }

    /**
     * @throws JsonException
     */
    private function persist(): void
    {
        $this->atomFs->writeJsonAtomic($this->installationJsonPath, $this->doc, true);
    }
}
```

---
#### 65


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
#### 66


` File: src/Installations/Support/Psr4Checker.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use RuntimeException;

/**
 * Verifies per-plugin PSR-4 mapping in host composer.json:
 *   "<psr4_root>\\<Placeholder.name>\\" : "<psr4_root>/<Placeholder.name>/"
 */
final readonly class Psr4Checker
{
    public function __construct(private AtomicFilesystem $fs)
    {
    }

    public function assertMapping(string $composerJsonPath, string $psr4Root, string $placeholderName): void
    {
        $expected = $this->expected($psr4Root, $placeholderName);
        [$ns, $dir] = $expected;

        $composer = $this->fs->fs()->readJson($composerJsonPath);
        $autoload = (array)($composer['autoload']['psr-4'] ?? []);
        $found = $autoload[$ns] ?? null;

        if (!is_string($found) || rtrim($found, '/\\') !== rtrim($dir, '/\\')) {
            throw new RuntimeException("PSR-4 mapping missing or mismatched for {$ns} → expected '{$dir}'");
        }
    }

    /** @return array{0:string,1:string} */
    public function expected(string $psr4Root, string $placeholderName): array
    {
        $ns = rtrim($psr4Root, '\\') . '\\' . $placeholderName . '\\';
        $dir = rtrim($psr4Root, '/\\') . '/' . $placeholderName . '/';
        return [$ns, $dir];
    }
}
```

---
#### 67


` File: src/Installations/Support/RouteMaterializer.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use RuntimeException;

final readonly class RouteMaterializer
{
    public function __construct(private AtomicFilesystem $afs) {}

    /**
     * @param string $pluginRoot
     * @param string $pluginSlug used in health route name & path
     * @param list<array{route:string|array, id:string, content:string, file:string}> $entries
     * @return array{dir:string, files:string[], aggregator:string}
     */
    public function materialize(string $pluginRoot, string $pluginSlug, array $entries): array
    {
        $routesDir = rtrim($pluginRoot, "\\/") . DIRECTORY_SEPARATOR . 'routes';
        if (!$this->afs->fs()->isDirectory($routesDir)) {
            $this->afs->fs()->ensureDirectory($routesDir, 0775);
        }

        $written = [];
        foreach ($entries as $e) {
            $rel  = ltrim((string)$e['file'], '/\\');
            $path = $routesDir . DIRECTORY_SEPARATOR . $rel;
            $this->afs->ensureParentDirectory($path);
            $this->afs->fs()->writeAtomic($path, (string)$e['content']);
            $written[] = $rel;
        }

        $aggregator = $this->writeAggregator($routesDir, $pluginSlug, $written);

        return ['dir' => $routesDir, 'files' => $written, 'aggregator' => $aggregator];
    }

    private function writeAggregator(string $routesDir, string $slug, array $files): string
    {
        if ($routesDir === '' || !$this->afs->fs()->isDirectory($routesDir)) {
            throw new RuntimeException("Invalid routes dir: $routesDir");
        }

        $target = rtrim($routesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'fortiplugin.route.php';

        $lines = [];
        $lines[] = "<?php";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "/** AUTO-GENERATED FortiPlugin aggregator for plugin: {$slug} */";
        $lines[] = "use Illuminate\\Support\\Facades\\Route;";
        $lines[] = "";
        // health endpoint
        $path = '/__plugins/' . $slug . '/health';
        $name = 'fortiplugin.' . $slug . '.health';
        $lines[] = "Route::get(" . var_export($path, true) . ", function () { return response('ok', 200); })->name(" . var_export($name, true) . ");";
        $lines[] = "";
        // includes
        foreach ($files as $rel) {
            $lines[] = "require __DIR__ . '/' . " . var_export($rel, true) . ";";
        }
        $lines[] = "";

        $this->afs->fs()->writeAtomic($target, implode("\n", $lines));

        return $target;
    }
}
```

---
#### 68


` File: src/Installations/Support/RouteRegistryStore.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;

final readonly class RouteRegistryStore
{
    public function __construct(private AtomicFilesystem $afs) {}

    public function path(string $pluginRoot): string
    {
        return rtrim($pluginRoot, "\\/") . DIRECTORY_SEPARATOR . '.internal' . DIRECTORY_SEPARATOR . 'routes.registry.json';
    }

    /** @return list<array{route:string|array, id:string, content:string, file:string}> */
    public function read(string $pluginRoot): array
    {
        $p = $this->path($pluginRoot);
        if (!$this->afs->fs()->exists($p)) return [];
        $doc = $this->afs->fs()->readJson($p);
        return is_array($doc) ? array_values($doc) : [];
    }

    /** @param list<array{route:string|array, id:string, content:string, file:string}> $entries
     * @throws JsonException
     */
    public function write(string $pluginRoot, array $entries): void
    {
        $p = $this->path($pluginRoot);
        $this->afs->ensureParentDirectory($p);
        $this->afs->writeJsonAtomic($p, array_values($entries), true);
    }
}
```

---
#### 69


` File: src/Installations/Support/RouteUiBridge.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Timeax\FortiPlugin\Core\Install\JsonRouteCompiler;

/**
 * RouteUiBridge
 *
 * - Reads routes config from <staging>/fortiplugin.json
 * - Discovers JSON route files using routes.dir + routes.glob (supports **)
 * - Compiles via JsonRouteCompiler:
 *     • legacy "compiled" chunks (compat)
 *     • registry-first entries {route,id,content,file}
 * - Persists registry to <staging>/.internal/routes.registry.json
 *
 * Output shape:
 *   [
 *     'compiled'   => array<int, array{source:string,php:string,routeIds:string[],slug:string}>,
 *     'registry'   => list<array{route:string|array, id:string, content:string, file:string}>,
 *     'route_ids'  => string[],
 *     'files'      => string[],   // discovered JSON files (absolute)
 *     'root'       => string,     // absolute: <staging>/<routes.dir>
 *     'pattern'    => string,     // glob pattern used
 *   ]
 */
final readonly class RouteUiBridge
{
    public function __construct(
        private AtomicFilesystem  $afs,
        private JsonRouteCompiler $compiler,
        private RouteRegistryStore $registryStore,
    ) {}

    /**
     * Discover and compile all route JSON files for a staged plugin.
     *
     * @param string        $stagingRoot Absolute path to staged plugin root (directory containing fortiplugin.json)
     * @param callable|null $emit        Optional emitter: fn(array $payload): void
     * @return array{compiled:array,registry:array,route_ids:array,files:array,root:string,pattern:string}
     * @throws JsonException
     */
    public function discoverAndCompile(string $stagingRoot, ?callable $emit = null): array
    {
        $stagingRoot = rtrim($stagingRoot, "\\/");

        $cfgPath = $stagingRoot . DIRECTORY_SEPARATOR . 'fortiplugin.json';
        $fs = $this->afs->fs();

        if (!$fs->exists($cfgPath) || !$fs->isFile($cfgPath)) {
            throw new RuntimeException("fortiplugin.json not found at $cfgPath");
        }

        $cfg = $fs->readJson($cfgPath);
        $routesCfg = (array)($cfg['routes'] ?? []);
        $dirRel = (string)($routesCfg['dir'] ?? '');
        if ($dirRel === '') {
            throw new RuntimeException("fortiplugin.json: routes.dir is required");
        }

        $glob = (string)($routesCfg['glob'] ?? '**/*.routes.json');
        $root = $stagingRoot . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\'], '/', $dirRel), '/');

        if (!$fs->exists($root) || !$fs->isDirectory($root)) {
            throw new RuntimeException("Routes directory not found: $root");
        }

        $emit && $emit([
            'title' => 'ROUTE_DISCOVERY_START',
            'description' => 'Searching for JSON route files',
            'meta' => ['root' => $root, 'pattern' => $glob],
        ]);

        $files = $this->findRouteFiles($root, $glob);

        $emit && $emit([
            'title' => 'ROUTE_DISCOVERY_DONE',
            'description' => 'Route files discovered',
            'meta' => ['count' => count($files), 'root' => $root],
        ]);

        if ($files === []) {
            return [
                'compiled'  => [],
                'registry'  => [],
                'route_ids' => [],
                'files'     => [],
                'root'      => $root,
                'pattern'   => $glob,
            ];
        }

        $emit && $emit([
            'title' => 'ROUTE_COMPILE_START',
            'description' => 'Compiling route JSON files',
            'meta' => ['count' => count($files)],
        ]);

        // Legacy output for compatibility with existing callers:
        $compiled = $this->compiler->compileFiles($files);

        // Registry-first entries (authoritative per-route units):
        $registryEntries = [];
        $seenIds = [];
        foreach ($files as $file) {
            $r = $this->compiler->compileFileToRegistry($file);
            foreach ($r['entries'] as $entry) {
                $registryEntries[] = $entry;
            }
            foreach ($r['routeIds'] as $rid) {
                $seenIds[(string)$rid] = true;
            }
        }
        $routeIds = array_keys($seenIds);
        sort($routeIds, SORT_STRING);

        // Persist registry to .internal
        $this->registryStore->write($stagingRoot, $registryEntries);

        $emit && $emit([
            'title' => 'ROUTE_COMPILE_DONE',
            'description' => 'Routes compiled',
            'meta' => ['compiled' => count($compiled), 'registry_entries' => count($registryEntries), 'route_ids' => count($routeIds)],
        ]);

        return [
            'compiled'  => $compiled,
            'registry'  => $registryEntries,
            'route_ids' => $routeIds,
            'files'     => $files,
            'root'      => $root,
            'pattern'   => $glob,
        ];
    }

    /**
     * Find route files within $root using a glob-like $pattern (supports **).
     * Returns absolute paths.
     */
    private function findRouteFiles(string $root, string $pattern): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $normPattern = $this->normalizePattern($pattern);

        $out = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS)
        );

        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) continue;

            $abs = $file->getPathname();
            $rel = str_replace('\\', '/', substr($abs, strlen($root) + 1));
            if ($this->globMatch($rel, $normPattern)) {
                $out[] = $abs;
            }
        }

        sort($out, SORT_STRING);
        return $out;
    }

    private function normalizePattern(string $pattern): string
    {
        $p = str_replace('\\', '/', $pattern);
        return $p !== '' ? $p : '**/*.routes.json';
    }

    private function globMatch(string $relForwardSlash, string $pattern): bool
    {
        $quoted = preg_quote($pattern, '~');
        $quoted = str_replace(['\*\*', '\*', '\?'], ['.*', '[^/]*', '[^/]'], $quoted);
        $re = '~^' . $quoted . '$~u';
        return (bool)preg_match($re, $relForwardSlash);
    }
}
```

---
#### 70


` File: src/Installations/Support/ValidatorBridge.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use Random\RandomException;
use Timeax\FortiPlugin\Installations\DTO\InstallSummary;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Sections\FileScanSection;
use Timeax\FortiPlugin\Installations\Sections\VerificationSection;
use Timeax\FortiPlugin\Services\ValidatorService;

/**
 * ValidatorBridge
 *
 * Bridges VerificationSection and FileScanSection:
 *  - Runs Verification first (routes mandatory; PSR-4 checks live in the section).
 *  - If policy enables file scan, runs FileScan next (scanner-only, no background).
 *  - Forwards a single $emit callback to both sections (verbatim).
 *  - Composes a single InstallSummary DTO and calls onValidationEnd($summary) exactly once.
 *
 * NOTE: Sections persist their own raw emits/log blocks. The bridge only orchestrates and composes the DTO.
 */
final readonly class ValidatorBridge
{
    public function __construct(
        private VerificationSection $verification,
        private FileScanSection     $fileScan,
        private InstallerPolicy     $policy,
    )
    {
    }

    /**
     * Orchestrate verification + (optional) file-scan and return merged summary + scan decision/meta.
     *
     * @param string $pluginDir Unpacked plugin root
     * @param string $pluginName Plugin’s unique name (namespace segment)
     * @param int|string $zipId PluginZip id
     * @param ValidatorService $validator Shared validator instance
     * @param array<string,mixed> $validatorConfig Must include headline.route_files[] etc. (used by sections)
     * @param string $validatorConfigHash Stable hash for token binding (passed to file scan)
     * @param string $actor Actor id or 'system'
     * @param string $runId Correlation id for this install run
     * @param callable|null $emit fn(array $payload): void — verbatim passthrough to sections
     * @param callable|null $onValidationEnd fn(InstallSummary $summary): void — called here ONCE
     * @param callable|null $onFileScanError fn(array $summary, string $token): Install — passed to FileScan
     *
     * @return array{
     *   summary: InstallSummary,
     *   decision?: Install,
     *   meta?: array<string,mixed>
     * }
     * @throws JsonException
     * @throws RandomException
     */
    public function run(
        string           $pluginDir,
        string           $pluginName,
        int|string       $zipId,
        ValidatorService $validator,
        array            $validatorConfig,
        string           $validatorConfigHash,
        string           $actor,
        string           $runId,
        ?callable        $emit = null,
        ?callable        $onValidationEnd = null,
        ?callable        $onFileScanError = null
    ): array
    {
        // 1) VERIFICATION
        $ver = $this->verification->run(
            pluginDir: $pluginDir,
            pluginName: $pluginName,
            run_id: $runId,
            validator: $validator,
            validatorConfig: $validatorConfig,
            emitValidation: $emit
        );

        // Section returns ['status'=>'ok'|'fail','summary'=>...] (summary optional)
        $verificationSection = $ver['summary']
            ?? ['status' => $ver['status'] ?? 'fail', 'errors' => [], 'warnings' => []];

        // 2) FILE SCAN (optional per policy)
        /** @noinspection DuplicatedCode */
        $scanEnabled = $this->policy->isFileScanEnabled();

        $fileScanSection = [
            'enabled' => false,
            'status' => 'skipped',
            'errors' => [],
        ];
        $decision = null;
        $meta = null;

        if ($scanEnabled) {
            // Ensure onValidationEnd is called ONLY by the bridge (pass null into section)
            $scan = $this->fileScan->run(
                pluginDir: $pluginDir,
                zipId: $zipId,
                validator: $validator,
                validatorConfigHash: $validatorConfigHash,
                actor: $actor,
                runId: $runId,
                onFileScanError: $onFileScanError,
                emitValidation: $emit
            );

            // FileScanSection returns: ['decision' => Install::*, 'meta' => array]
            $decision = $scan['decision'] ?? null;
            $meta = $scan['meta'] ?? null;

            // Compose a minimal file_scan snapshot from validator state
            $fileScanSection = [
                'enabled' => true,
                'status' => $validator->shouldFail() ? 'fail' : 'ok',
                'errors' => [], // can be enriched later from validator logs if desired
            ];
        }

        // 3) Compose DTO
        $summary = new InstallSummary(
            verification: $verificationSection,
            file_scan: $fileScanSection,
            zip_validation: null,
            vendor_policy: null,
            composer_plan: null,
            packages: null
        );

        // 4) Invoke onValidationEnd ONCE at the boundary
        if (is_callable($onValidationEnd)) {
            $onValidationEnd($summary);
        }

        // 5) Return merged summary plus (optional) scan decision/meta for Installer to act on
        $out = ['summary' => $summary];
        if ($decision instanceof Install) {
            $out['decision'] = $decision;
        }
        if (is_array($meta)) {
            $out['meta'] = $meta;
        }

        return $out;
    }
}
```

---
#### 71


` File: src/Lib/Obfuscator.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection EncryptionInitializationVectorRandomnessInspection */

/** @noinspection SpellCheckingInspection */

namespace Timeax\FortiPlugin\Lib;

use DeflateContext;
use InflateContext;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Exceptions\PermissionDeniedException;
use Timeax\FortiPlugin\Lib\Utils\ObfuscatorUtil;
use Timeax\FortiPlugin\Core\ChecksModulePermission;

/**
 * Permission-gated wrappers for sensitive encoder/decoder / obfuscator functions.
 *
 * Plugins MUST call these methods instead of calling the PHP functions directly.
 */
class Obfuscator
{
    use ObfuscatorUtil;

    protected string $type = 'module';
    protected string $target = 'obfuscator';

    use ChecksModulePermission;

    /**
     * Ensure the plugin has permission to use a given obfuscator function.
     *
     * @throws PermissionDeniedException
     */
    protected function ensurePermission(string $capability): void
    {
        $permission = 'use-obfuscator:' . $capability;
        $this->checkModulePermission($permission);
    }


    // ----------------------
    // Base64
    // ----------------------

    public function encodeBase64(string $input): string
    {
        $this->ensurePermission('base64_encode');
        if (!function_exists('base64_encode')) {
            throw new RuntimeException('base64_encode is not available');
        }
        return base64_encode($input);
    }

    public function decodeBase64(string $input, bool $strict = false): string|false
    {
        $this->ensurePermission('base64_decode');
        if (!function_exists('base64_decode')) {
            throw new RuntimeException('base64_decode is not available');
        }
        return base64_decode($input, $strict);
    }

    // ----------------------
    // JSON
    // ----------------------

    /**
     * @throws JsonException
     */
    public function encodeJson(mixed $input, int $flags = 0, int $depth = 512): string|false
    {
        $this->ensurePermission('json_encode');
        if (!function_exists('json_encode')) {
            throw new RuntimeException('json_encode is not available');
        }
        return json_encode($input, JSON_THROW_ON_ERROR | $flags, $depth);
    }

    /**
     * @throws JsonException
     */
    public function decodeJson(string $input, bool $assoc = false, int $depth = 512, int $flags = 0): mixed
    {
        $this->ensurePermission('json_decode');
        if (!function_exists('json_decode')) {
            throw new RuntimeException('json_decode is not available');
        }
        return json_decode($input, $assoc, $depth, JSON_THROW_ON_ERROR | $flags);
    }

    // ----------------------
    // GZIP / zlib
    // ----------------------

    public function compressGz(string $input, int $level = -1, ?int $encoding = null): string|false
    {
        $this->ensurePermission('gzencode');
        if (!function_exists('gzencode')) {
            throw new RuntimeException('gzencode is not available');
        }
        return gzencode($input, $level, $encoding);
    }

    public function decompressGz(string $input): string|false
    {
        $this->ensurePermission('gzdecode');
        if (!function_exists('gzdecode')) {
            throw new RuntimeException('gzdecode is not available');
        }
        return gzdecode($input);
    }

    public function deflateCompress(string $input, int $level = -1): string|false
    {
        $this->ensurePermission('gzdeflate');
        if (!function_exists('gzdeflate')) {
            throw new RuntimeException('gzdeflate is not available');
        }
        return gzdeflate($input, $level);
    }

    public function deflateDecompress(string $input): string|false
    {
        $this->ensurePermission('gzinflate');
        if (!function_exists('gzinflate')) {
            throw new RuntimeException('gzinflate is not available');
        }
        return gzinflate($input);
    }

    // ----------------------
    // BZ2
    // ----------------------

    public function compressBz(string $input, int $blocksize = 4, int $workfactor = 0): string|false
    {
        $this->ensurePermission('bzcompress');
        if (!function_exists('bzcompress')) {
            throw new RuntimeException('bzcompress is not available');
        }
        return bzcompress($input, $blocksize, $workfactor);
    }

    public function decompressBz(string $input, int $small = 0): string|false
    {
        $this->ensurePermission('bzdecompress');
        if (!function_exists('bzdecompress')) {
            throw new RuntimeException('bzdecompress is not available');
        }
        return bzdecompress($input, $small);
    }

    // ----------------------
    // zlib_encode / zlib_decode
    // ----------------------

    public function zlibEncode(string $input, int $encoding = ZLIB_ENCODING_DEFLATE): string|false
    {
        $this->ensurePermission('zlib_encode');
        if (!function_exists('zlib_encode')) {
            throw new RuntimeException('zlib_encode is not available');
        }
        return zlib_encode($input, $encoding);
    }

    public function zlibDecode(string $input): string|false
    {
        $this->ensurePermission('zlib_decode');
        if (!function_exists('zlib_decode')) {
            throw new RuntimeException('zlib_decode is not available');
        }
        return zlib_decode($input);
    }

    // ----------------------
    // Deflate/Inflate stream helpers (if used)
    // ----------------------

    public function deflateInit(int $mode = ZLIB_ENCODING_DEFLATE, array $options = []): false|DeflateContext
    {
        $this->ensurePermission('deflate_init');
        if (!function_exists('deflate_init')) {
            throw new RuntimeException('deflate_init is not available');
        }
        // signature deflate_init(int $encoding, array $options = ?)
        return deflate_init($mode, $options);
    }

    public function deflateAdd($context, string $data, int $flush = ZLIB_SYNC_FLUSH): string|false
    {
        $this->ensurePermission('deflate_add');
        if (!function_exists('deflate_add')) {
            throw new RuntimeException('deflate_add is not available');
        }
        return deflate_add($context, $data, $flush);
    }

    public function inflateInit(int $encoding, array $options = []): false|InflateContext
    {
        $this->ensurePermission('inflate_init');
        if (!function_exists('inflate_init')) {
            throw new RuntimeException('inflate_init is not available');
        }
        return inflate_init($encoding, $options);
    }

    public function inflateAdd($context, string $data): string|false
    {
        $this->ensurePermission('inflate_add');
        if (!function_exists('inflate_add')) {
            throw new RuntimeException('inflate_add is not available');
        }
        return inflate_add($context, $data);
    }

    // ----------------------
    // ROT13 and simple transforms
    // ----------------------

    public function rot13(string $input): string
    {
        $this->ensurePermission('str_rot13');
        if (!function_exists('str_rot13')) {
            throw new RuntimeException('str_rot13 is not available');
        }
        return str_rot13($input);
    }

    public function reverseString(string $input): string
    {
        $this->ensurePermission('strrev');
        if (!function_exists('strrev')) {
            throw new RuntimeException('strrev is not available');
        }
        return strrev($input);
    }

    public function addSlashes(string $input): string
    {
        $this->ensurePermission('addslashes');
        if (!function_exists('addslashes')) {
            throw new RuntimeException('addslashes is not available');
        }
        return addslashes($input);
    }

    public function stripSlashes(string $input): string
    {
        $this->ensurePermission('stripslashes');
        if (!function_exists('stripslashes')) {
            throw new RuntimeException('stripslashes is not available');
        }
        return stripslashes($input);
    }

    public function quoteMeta(string $input): string
    {
        $this->ensurePermission('quotemeta');
        if (!function_exists('quotemeta')) {
            throw new RuntimeException('quotemeta is not available');
        }
        return quotemeta($input);
    }

    public function stripTags(string $input, ?string $allowed = null): string
    {
        $this->ensurePermission('strip_tags');
        if (!function_exists('strip_tags')) {
            throw new RuntimeException('strip_tags is not available');
        }
        return strip_tags($input, $allowed);
    }

    // ----------------------
    // Hex / binary conversions
    // ----------------------

    public function encodeHex(string $input): string
    {
        $this->ensurePermission('bin2hex');
        if (!function_exists('bin2hex')) {
            throw new RuntimeException('bin2hex is not available');
        }
        return bin2hex($input);
    }

    public function decodeHex(string $input): string|false
    {
        $this->ensurePermission('hex2bin');
        if (!function_exists('hex2bin')) {
            throw new RuntimeException('hex2bin is not available');
        }
        return hex2bin($input);
    }

    // ----------------------
    // chr / ord
    // ----------------------

    public function chr(int $ascii): string
    {
        $this->ensurePermission('chr');
        if (!function_exists('chr')) {
            throw new RuntimeException('chr is not available');
        }
        return chr($ascii);
    }

    public function ord(string $char): int
    {
        $this->ensurePermission('ord');
        if (!function_exists('ord')) {
            throw new RuntimeException('ord is not available');
        }
        return ord($char);
    }

    // ----------------------
    // pack / unpack
    // ----------------------

    /**
     * Pack values according to format.
     * Example: pack('H*', $data)
     *
     * @param string $format
     * @param mixed ...$values
     * @return string
     */
    public function pack(string $format, mixed ...$values): string
    {
        $this->ensurePermission('pack');
        if (!function_exists('pack')) {
            throw new RuntimeException('pack is not available');
        }
        return pack($format, ...$values);
    }

    /**
     * Unpack data according to format.
     *
     * @param string $format
     * @param string $data
     * @return array|false
     */
    public function unpack(string $format, string $data): array|false
    {
        $this->ensurePermission('unpack');
        if (!function_exists('unpack')) {
            throw new RuntimeException('unpack is not available');
        }
        return unpack($format, $data);
    }

    // ----------------------
    // URL encoding
    // ----------------------

    public function encodeUrl(string $input): string
    {
        $this->ensurePermission('urlencode');
        if (!function_exists('urlencode')) {
            throw new RuntimeException('urlencode is not available');
        }
        return urlencode($input);
    }

    public function decodeUrl(string $input): string
    {
        $this->ensurePermission('urldecode');
        if (!function_exists('urldecode')) {
            throw new RuntimeException('urldecode is not available');
        }
        return urldecode($input);
    }

    public function rawEncodeUrl(string $input): string
    {
        $this->ensurePermission('rawurlencode');
        if (!function_exists('rawurlencode')) {
            throw new RuntimeException('rawurlencode is not available');
        }
        return rawurlencode($input);
    }

    public function rawDecodeUrl(string $input): string
    {
        $this->ensurePermission('rawurldecode');
        if (!function_exists('rawurldecode')) {
            throw new RuntimeException('rawurldecode is not available');
        }
        return rawurldecode($input);
    }

    // ----------------------
    // convert_uuencode / convert_uudecode
    // ----------------------

    public function convertUuEncode(string $input): string
    {
        $this->ensurePermission('convert_uuencode');
        if (!function_exists('convert_uuencode')) {
            throw new RuntimeException('convert_uuencode is not available');
        }
        return convert_uuencode($input);
    }

    public function convertUuDecode(string $input): string|false
    {
        $this->ensurePermission('convert_uudecode');
        if (!function_exists('convert_uudecode')) {
            throw new RuntimeException('convert_uudecode is not available');
        }
        return convert_uudecode($input);
    }

    // ----------------------
    // serialize / unserialize
    // ----------------------

    public function encodeSerialize(mixed $input): string
    {
        $this->ensurePermission('serialize');
        if (!function_exists('serialize')) {
            throw new RuntimeException('serialize is not available');
        }
        return serialize($input);
    }

    public function decodeSerialize(string $input, array $options = []): mixed
    {
        $this->ensurePermission('unserialize');
        if (!function_exists('unserialize')) {
            throw new RuntimeException('unserialize is not available');
        }
        // use php's second param options if provided (PHP 7.0+)
        return unserialize($input, $options);
    }

    // ----------------------
    // Hashing (md5, sha1, hash, hmac)
    // ----------------------

    public function md5(string $data, bool $rawOutput = false): string
    {
        $this->ensurePermission('md5');
        if (!function_exists('md5')) {
            throw new RuntimeException('md5 is not available');
        }
        return md5($data, $rawOutput);
    }

    public function sha1(string $data, bool $rawOutput = false): string
    {
        $this->ensurePermission('sha1');
        if (!function_exists('sha1')) {
            throw new RuntimeException('sha1 is not available');
        }
        return sha1($data, $rawOutput);
    }

    public function hash(string $algo, string $data, bool $rawOutput = false): string
    {
        $this->ensurePermission('hash');
        if (!function_exists('hash')) {
            throw new RuntimeException('hash is not available');
        }
        return hash($algo, $data, $rawOutput);
    }

    public function hashHmac(string $algo, string $data, string $key, bool $rawOutput = false): string
    {
        $this->ensurePermission('hash_hmac');
        if (!function_exists('hash_hmac')) {
            throw new RuntimeException('hash_hmac is not available');
        }
        return hash_hmac($algo, $data, $key, $rawOutput);
    }

    // ----------------------
    // OpenSSL
    // ----------------------
    /**
     * Encrypt with OpenSSL and return payload with IV prepended (raw binary).
     *
     * Returns raw binary string: iv || ciphertext (OPENSSL_RAW_DATA).
     */
    public function opensslEncryptWithIv(string $data, string $method, string $key, int $options = OPENSSL_RAW_DATA, ?string $iv = null): string
    {
        $this->ensurePermission('openssl_encrypt');

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('openssl_encrypt is not available');
        }

        $ivLength = openssl_cipher_iv_length($method);
        if ($ivLength === false) {
            throw new RuntimeException("Could not determine IV length for cipher: $method");
        }

        if ($ivLength > 0) {
            if ($iv === null) {
                $iv = $this->generateIv($method); // reuse your generateIv() helper
            }
            if (!is_string($iv) || strlen($iv) !== $ivLength) {
                throw new InvalidArgumentException("Invalid IV length for cipher $method. Expected $ivLength bytes.");
            }
        } else {
            $iv = $iv ?? '';
        }

        $ciphertext = openssl_encrypt($data, $method, $key, $options, $iv);
        if ($ciphertext === false) {
            return false;
        }

        // return iv + ciphertext (raw)
        return $iv . $ciphertext;
    }

    /**
     * Decrypt a payload produced by opensslEncryptWithIv (iv prepended).
     */
    public function opensslDecryptWithIv(string $payload, string $method, string $key, int $options = OPENSSL_RAW_DATA): string|false
    {
        $this->ensurePermission('openssl_decrypt');

        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('openssl_decrypt is not available');
        }

        $ivLength = openssl_cipher_iv_length($method);
        if ($ivLength === false) {
            throw new RuntimeException("Could not determine IV length for cipher: $method");
        }

        if ($ivLength > 0) {
            if (strlen($payload) <= $ivLength) {
                throw new InvalidArgumentException('Payload too short to contain IV + ciphertext');
            }
            $iv = substr($payload, 0, $ivLength);
            $ciphertext = substr($payload, $ivLength);
        } else {
            $iv = '';
            $ciphertext = $payload;
        }

        return openssl_decrypt($ciphertext, $method, $key, $options, $iv);
    }
    // ----------------------
    // mcrypt (legacy) - only if available
    // ----------------------
    /**
     * mcryptEncrypt: generate IV first (via secureRandom), validate, encrypt.
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function mcryptEncrypt(string $cipher, string $data, string $key, string $mode, ?string $iv = null): string|false
    {
        $this->ensurePermission('mcrypt_encrypt');
        $this->warnMcryptDeprecated();

        if (!function_exists('mcrypt_encrypt')) {
            throw new RuntimeException('mcrypt_encrypt is not available on this PHP build');
        }

        $ivSize = $this->ivSizeForMcrypt($cipher, $mode); // <- your utility

        // If IV required and not supplied, generate securely
        if ($ivSize > 0 && $iv === null) {
            $iv = $this->secureRandom($ivSize); // <- your utility
        }

        // Validate IV when required
        if ($ivSize > 0) {
            if (!is_string($iv) || strlen($iv) !== $ivSize) {
                throw new InvalidArgumentException(
                    "Invalid IV for cipher '$cipher' mode '$mode'. Expected $ivSize bytes, got " .
                    (is_string($iv) ? strlen($iv) : gettype($iv))
                );
            }
        } else {
            $iv = $iv ?? '';
        }

        // mcrypt_encrypt(string $cipher, string $key, string $data, string $mode [, string $iv ])
        return mcrypt_encrypt($cipher, $key, $data, $mode, $iv);
    }

    /**
     * mcryptDecrypt: require the same IV the encrypt used (no auto-generation).
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function mcryptDecrypt(string $cipher, string $data, string $key, string $mode, ?string $iv = null): string|false
    {
        $this->ensurePermission('mcrypt_decrypt');
        $this->warnMcryptDeprecated();

        if (!function_exists('mcrypt_decrypt')) {
            throw new RuntimeException('mcrypt_decrypt is not available on this PHP build');
        }

        $ivSize = $this->ivSizeForMcrypt($cipher, $mode);
        if ($ivSize > 0 && $iv === null) {
            $iv = $this->generateLegacyIvForMcrypt($ivSize);
        }

        return mcrypt_decrypt($cipher, $key, $data, $mode, $iv);
    }

    /**
     * mcryptEncryptWithIv: generates IV (secureRandom) and returns ['iv'=>..., 'ciphertext'=>...].
     */
    public function mcryptEncryptWithIv(string $cipher, string $data, string $key, string $mode, ?string $iv = null): array
    {
        $this->ensurePermission('mcrypt_encrypt');
        $this->warnMcryptDeprecated();

        $ivSize = $this->ivSizeForMcrypt($cipher, $mode);
        if ($ivSize > 0 && $iv === null) {
            $iv = $this->generateLegacyIvForMcrypt($ivSize);
        }

        $ciphertext = $this->mcryptEncrypt($cipher, $data, $key, $mode, $iv);

        return ['iv' => $iv ?? '', 'ciphertext' => $ciphertext];
    }

    /**
     * mcryptDecryptWithIv: accepts payload with IV prepended or separate IV.
     * If $ivSize not provided, it is derived via ivSizeForMcrypt().
     */
    public function mcryptDecryptWithIv(string $payload, string $cipher, string $key, string $mode, ?int $ivSize = null): string|false
    {
        $this->ensurePermission('mcrypt_decrypt');
        $this->warnMcryptDeprecated();

        $ivSize = $ivSize ?? $this->ivSizeForMcrypt($cipher, $mode); // <- your utility

        if ($ivSize > 0) {
            if (strlen($payload) <= $ivSize) {
                throw new InvalidArgumentException('Payload too short to contain IV + ciphertext');
            }
            $iv = substr($payload, 0, $ivSize);
            $ciphertext = substr($payload, $ivSize);
        } else {
            $iv = '';
            $ciphertext = $payload;
        }

        return $this->mcryptDecrypt($cipher, $ciphertext, $key, $mode, $iv);
    }

    // ----------------------
    // Convenience: allow callers to list available wrappers
    // ----------------------

    public function available(): array
    {
        return [
            // grouped list of the exposed wrappers and their underlying functions
            'base64_encode' => 'encodeBase64',
            'base64_decode' => 'decodeBase64',
            'json_encode' => 'encodeJson',
            'json_decode' => 'decodeJson',
            'gzencode' => 'compressGz',
            'gzdecode' => 'decompressGz',
            'gzdeflate' => 'deflateCompress',
            'gzinflate' => 'deflateDecompress',
            'bzcompress' => 'compressBz',
            'bzdecompress' => 'decompressBz',
            'zlib_encode' => 'zlibEncode',
            'zlib_decode' => 'zlibDecode',
            'deflate_init' => 'deflateInit',
            'deflate_add' => 'deflateAdd',
            'inflate_init' => 'inflateInit',
            'inflate_add' => 'inflateAdd',
            'str_rot13' => 'rot13',
            'strrev' => 'reverseString',
            'addslashes' => 'addSlashes',
            'stripslashes' => 'stripSlashes',
            'quotemeta' => 'quoteMeta',
            'strip_tags' => 'stripTags',
            'bin2hex' => 'encodeHex',
            'hex2bin' => 'decodeHex',
            'chr' => 'chr',
            'ord' => 'ord',
            'pack' => 'pack',
            'unpack' => 'unpack',
            'urlencode' => 'encodeUrl',
            'urldecode' => 'decodeUrl',
            'rawurlencode' => 'rawEncodeUrl',
            'rawurldecode' => 'rawDecodeUrl',
            'convert_uuencode' => 'convertUuEncode',
            'convert_uudecode' => 'convertUuDecode',
            'serialize' => 'encodeSerialize',
            'unserialize' => 'decodeSerialize',
            'md5' => 'md5',
            'sha1' => 'sha1',
            'hash' => 'hash',
            'hash_hmac' => 'hashHmac',
            'openssl_encrypt' => 'opensslEncrypt',
            'openssl_decrypt' => 'opensslDecrypt',
            'mcrypt_encrypt' => 'mcryptEncrypt',
            'mcrypt_decrypt' => 'mcryptDecrypt',
        ];
    }

    /**
     * Grouped list of available wrappers, categorized by purpose.
     * Static variant of available().
     */
    public static function availableGroups(): array
    {
        return [
            'encoding' => [
                'base64_encode' => 'encodeBase64',
                'base64_decode' => 'decodeBase64',
                'json_encode' => 'encodeJson',
                'json_decode' => 'decodeJson',
                'bin2hex' => 'encodeHex',
                'hex2bin' => 'decodeHex',
                'urlencode' => 'encodeUrl',
                'urldecode' => 'decodeUrl',
                'rawurlencode' => 'rawEncodeUrl',
                'rawurldecode' => 'rawDecodeUrl',
                'convert_uuencode' => 'convertUuEncode',
                'convert_uudecode' => 'convertUuDecode',
                'pack' => 'pack',
                'unpack' => 'unpack',
                'chr' => 'chr',
                'ord' => 'ord',
            ],
            'compression' => [
                'gzencode' => 'compressGz',
                'gzdecode' => 'decompressGz',
                'gzdeflate' => 'deflateCompress',
                'gzinflate' => 'deflateDecompress',
                'bzcompress' => 'compressBz',
                'bzdecompress' => 'decompressBz',
                'zlib_encode' => 'zlibEncode',
                'zlib_decode' => 'zlibDecode',
                'deflate_init' => 'deflateInit',
                'deflate_add' => 'deflateAdd',
                'inflate_init' => 'inflateInit',
                'inflate_add' => 'inflateAdd',
            ],
            'hash' => [
                'md5' => 'md5',
                'sha1' => 'sha1',
                'hash' => 'hash',
                'hash_hmac' => 'hashHmac',
            ],
            'crypto' => [
                'openssl_encrypt' => 'opensslEncryptWithIv',
                'openssl_decrypt' => 'opensslDecryptWithIv',
                'mcrypt_encrypt' => 'mcryptEncrypt',
                'mcrypt_decrypt' => 'mcryptDecrypt',
            ],
            'serialize' => [
                'serialize' => 'encodeSerialize',
                'unserialize' => 'decodeSerialize',
            ],
            'obfuscation' => [
                'str_rot13' => 'rot13',
                'strrev' => 'reverseString',
                'addslashes' => 'addSlashes',
                'stripslashes' => 'stripSlashes',
                'quotemeta' => 'quoteMeta',
                'strip_tags' => 'stripTags',
            ],
        ];
    }
}
```

---
#### 72


` File: src/Lib/Utils/ObfuscatorUtil.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection CryptographicallySecureRandomnessInspection */

/** @noinspection SpellCheckingInspection */

namespace Timeax\FortiPlugin\Lib\Utils;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

trait ObfuscatorUtil
{
    /**
     * Emit deprecation and telemetry for mcrypt usage.
     */
    protected function warnMcryptDeprecated(): void
    {
        // E_USER_DEPRECATED so monitoring/logging systems can pick it up
        @trigger_error('mcrypt is deprecated. Migrate to OpenSSL (openssl_encrypt) or Sodium (sodium_crypto_*).', E_USER_DEPRECATED);

        // Telemetry/logging: record plugin/module name, stack, timestamp
        $this->telemetryLogMcryptUsage();
    }

    /**
     * Telemetry helper for legacy mcrypt usage.
     * Adjust to use your telemetry system or PSR-3 logger.
     */
    protected function telemetryLogMcryptUsage(): void
    {
        try {
            $payload = [
                'module' => static::class,
                'time' => date('c'),
                'caller' => $this->getCallerSummary(),
            ];

            if (class_exists(Log::class)) {
                Log::warning('Legacy mcrypt usage detected', $payload);
            }
        } /** @noinspection PhpUnusedLocalVariableInspection */ catch (Throwable $e) {
            // Never fail telemetry
        }
    }

    /**
     * Return a small caller summary for telemetry (file:line).
     */
    protected function getCallerSummary(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        // skip current method and direct parent calls
        foreach ($trace as $frame) {
            if (isset($frame['file']) && !str_ends_with($frame['file'], __FILE__)) {
                return ($frame['file']) . ':' . ($frame['line'] ?? '0');
            }
        }
        return 'unknown';
    }

    public function urlencode(string $input): string
    {
        return $this->encodeUrl($input);
    }

    public function urldecode(string $input): string
    {
        return $this->decodeUrl($input);
    }

    // inside your class / module
    /**
     * Determine IV size for mcrypt cipher/mode without @-suppression.
     * Returns 0 if the environment cannot determine/does not require an IV.
     */
    protected function ivSizeForMcrypt(string $cipher, string $mode): int
    {
        if (!function_exists('mcrypt_get_iv_size')) {
            // On hosts without ext-mcrypt or when IV isn't used, treat as 0
            return 0;
        }

        $size = mcrypt_get_iv_size($cipher, $mode);
        if ($size === false) {
            throw new RuntimeException("Unable to determine IV size for cipher '$cipher' mode '$mode'");
        }

        return $size;
    }

    /**
     * Generate a cryptographically secure IV for legacy mcrypt usage.
     * Prefer random_bytes(); fall back to mcrypt_create_iv() with MCRYPT_DEV_RANDOM.
     */
    protected function generateLegacyIvForMcrypt(int $ivSize): string
    {
        if ($ivSize <= 0) {
            return '';
        }

        // Preferred modern API (PHP 7+): throws on failure
        if (function_exists('random_bytes')) {
            try {
                $iv = random_bytes($ivSize);
                if (strlen($iv) !== $ivSize) {
                    throw new RuntimeException('random_bytes() returned invalid length');
                }
                return $iv;
            } catch (Throwable $e) {
                throw new RuntimeException('random_bytes() failed to generate IV: ' . $e->getMessage(), 0, $e);
            }
        }

        // Legacy fallback
        if (function_exists('mcrypt_create_iv')) {
            // Prefer MCRYPT_DEV_RANDOM (may block until enough entropy is available)
            if (defined('MCRYPT_DEV_RANDOM')) {
                $source = MCRYPT_DEV_RANDOM;
            } elseif (defined('MCRYPT_DEV_URANDOM')) {
                $source = MCRYPT_DEV_URANDOM; // older PHPs; acceptable if present
            } elseif (defined('MCRYPT_RAND')) {
                $source = MCRYPT_RAND; // weakest; avoid if possible
                @trigger_error('Using MCRYPT_RAND for IV generation (not cryptographically strong).', E_USER_WARNING);
            } else {
                throw new RuntimeException('No suitable MCRYPT constant available for IV generation');
            }

            $iv = mcrypt_create_iv($ivSize, $source);
            if ($iv === false || !is_string($iv) || strlen($iv) !== $ivSize) {
                throw new RuntimeException('mcrypt_create_iv() failed to generate a valid IV');
            }

            return $iv;
        }

        throw new RuntimeException('No secure random generator available (random_bytes() or mcrypt_create_iv()).');
    }

    /**
     * Return cryptographically secure random bytes of $length.
     *
     * Prefer random_bytes() (PHP7+). Fallback to openssl_random_pseudo_bytes()
     * with crypto-strength check if random_bytes() is not available.
     *
     * @param int $length
     * @return string
     * @throws RuntimeException
     */
    protected function secureRandom(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        // Preferred modern API: throws on failure.
        if (function_exists('random_bytes')) {
            try {
                $bytes = random_bytes($length);
            } catch (Throwable $e) {
                throw new RuntimeException('random_bytes() failed: ' . $e->getMessage(), 0, $e);
            }

            if (strlen($bytes) !== $length) {
                throw new RuntimeException('random_bytes() produced invalid output');
            }

            return $bytes;
        }

        // Fallback to openssl_random_pseudo_bytes() and verify crypto-strong flag.
        if (function_exists('openssl_random_pseudo_bytes')) {
            $crypto_strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $crypto_strong);

            /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
            if ($bytes === false || $crypto_strong === false) {
                throw new RuntimeException('openssl_random_pseudo_bytes() failed or is not cryptographically strong');
            }
            if (strlen($bytes) !== $length) {
                throw new RuntimeException('openssl_random_pseudo_bytes() produced invalid output');
            }

            return $bytes;
        }

        throw new RuntimeException('No secure random generator available (random_bytes() or openssl_random_pseudo_bytes()).');
    }

    /**
     * Generate an IV for a given cipher method (OpenSSL) and validate it.
     *
     * @param string $method
     * @return string
     * @throws RuntimeException
     */
    protected function generateIv(string $method): string
    {
        $ivLength = openssl_cipher_iv_length($method);
        if ($ivLength === false) {
            throw new RuntimeException("Could not determine IV length for cipher: $method");
        }

        if ($ivLength === 0) {
            return '';
        }

        $iv = $this->secureRandom($ivLength);

        // Extra sanity check (should be redundant)
        if (strlen($iv) !== $ivLength) {
            throw new RuntimeException("Generated IV has invalid length for $method");
        }

        return $iv;
    }
}
```

---
#### 73


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
		"rotated_at" => "datetime",
	];
}
```

---
#### 74


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
#### 75


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
#### 76


` File: src/Models/PluginVersion.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Timeax\FortiPlugin\Enums\ValidationStatus;

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
#### 77


` File: src/Permissions/Evaluation/Dto/PermissionListResult.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

/**
 * Final list payload: items + summary.
 */
final readonly class PermissionListResult
{
    /** @param PermissionListItem[] $items */
    public function __construct(
        public array                $items,
        public PermissionListSummary $summary
    ) {}

    public function toArray(): array
    {
        return [
            'items'   => array_map(static fn($i) => $i instanceof PermissionListItem ? $i->toArray() : $i, $this->items),
            'summary' => $this->summary->toArray(),
        ];
    }
}
```

---
#### 78


` File: src/Permissions/Support/HostConfigNormalizer.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Support;

/**
 * Single source of truth for host-config normalization.
 *
 * All methods are pure (input → canonicalized output) and
 * safe to use from both catalogs and validators.
 */
final class HostConfigNormalizer
{
    /**
     * Normalize models map.
     *
     * Input shape (host config):
     *   [ alias => [
     *       'map' => FQCN,
     *       'relations' => [ relationName => relatedAlias, ... ] (optional),
     *       'columns' => [
     *         'all' => string[],       // optional
     *         'writable' => string[]   // optional, enforced ⊆ all when both present
     *       ] (optional)
     *   ], ...]
     *
     * Output (canonical):
     *   [ alias => [
     *       'map'       => FQCN,
     *       'relations' => [ relationName => relatedAlias, ... ],
     *       'columns'   => ['all' => ?string[], 'writable' => ?string[]]
     *   ], ...]
     *
     * - Drops invalid entries.
     * - Dedupes/sorts string lists.
     * - Ensures writable ⊆ all (when both present).
     */
    public static function models(array $raw): array
    {
        $out = [];
        foreach ($raw as $alias => $def) {
            if (!is_string($alias) || $alias === '' || !is_array($def)) {
                continue;
            }
            $fqcn = $def['map'] ?? null;
            if (!is_string($fqcn) || $fqcn === '') {
                continue;
            }

            // relations
            $rels = [];
            if (isset($def['relations']) && is_array($def['relations'])) {
                foreach ($def['relations'] as $rel => $relAlias) {
                    if (is_string($rel) && $rel !== '' && is_string($relAlias) && $relAlias !== '') {
                        $rels[$rel] = $relAlias;
                    }
                }
                ksort($rels, SORT_STRING);
            }

            // columns
            $all = null;
            $writable = null;
            if (isset($def['columns']) && is_array($def['columns'])) {
                if (isset($def['columns']['all']) && is_array($def['columns']['all'])) {
                    $all = self::uniqueSortedStrings($def['columns']['all']);
                }
                if (isset($def['columns']['writable']) && is_array($def['columns']['writable'])) {
                    $writable = self::uniqueSortedStrings($def['columns']['writable']);
                }
                // enforce writable ⊆ all when both present
                if ($all !== null && $writable !== null) {
                    $writable = array_values(array_intersect($writable, $all));
                }
            }

            $out[$alias] = [
                'map' => $fqcn,
                'relations' => $rels,
                'columns' => ['all' => $all, 'writable' => $writable],
            ];
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Normalize modules map.
     *
     * Input:  [ alias => ['map' => FQCN, 'docs' => ?string], ...]
     * Output: [ alias => ['map' => FQCN, 'docs' => ?string], ...]
     * - Drops invalid entries, dedupes/sorts.
     */
    public static function modules(array $raw): array
    {
        $out = [];
        foreach ($raw as $alias => $def) {
            if (!is_string($alias) || $alias === '' || !is_array($def)) {
                continue;
            }
            $fqcn = $def['map'] ?? null;
            if (!is_string($fqcn) || $fqcn === '') {
                continue;
            }
            $docs = null;
            if (isset($def['docs']) && is_string($def['docs']) && $def['docs'] !== '') {
                $docs = $def['docs'];
            }
            $out[$alias] = ['map' => $fqcn, 'docs' => $docs];
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Normalize notification channels.
     *
     * Accepts associative or list:
     *   ['email'=>true,'sms'=>true] OR ['email','sms']
     * Returns sorted unique list: ['email','sms']
     */
    public static function notificationChannels(array $raw): array
    {
        // If associative, use keys; else take values.
        $keys = array_keys($raw);
        $isAssoc = array_keys($keys) !== $keys;
        $list = $isAssoc ? array_keys($raw) : array_values($raw);

        return self::uniqueSortedStrings($list);
    }

    /**
     * Normalize codec groups from an Obfuscator-like map.
     *
     * Input (from Obfuscator::availableGroups()):
     *   [ group => [ phpFunctionName => wrapperName, ... ], ... ]
     * Output:
     *   [ group => [ phpFunctionName, ... ], ... ] // methods sorted/unique; groups sorted
     */
    public static function codecGroupsFromObfuscatorMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $group => $map) {
            if (!is_string($group) || $group === '' || !is_array($map)) {
                continue;
            }
            $methods = array_keys($map);
            $methods = self::uniqueSortedStrings($methods);
            $out[$group] = $methods;
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /* ------------------------ helpers ------------------------ */

    /** @return string[] */
    private static function uniqueSortedStrings(array $list): array
    {
        $list = array_values(array_filter($list, static fn($v) => is_string($v) && $v !== ''));
        $list = array_values(array_unique(array_map('strval', $list)));
        sort($list, SORT_STRING);
        return $list;
    }
}
```

---
#### 79


` File: src/Services/HostKeyService.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Services;

use JsonException;
use RuntimeException;
use SodiumException;
use Timeax\FortiPlugin\Models\HostKey;
use Timeax\FortiPlugin\Support\Encryption;

final class HostKeyService
{
    /**
     * Return the current verifying key (public) for installers.
     * @return array{fingerprint:string, public_pem:string}
     */
    public function currentVerifyKey(?string $purpose = null): array
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

    /** Create and persist a new keypair for a purpose. */
    public function generate(string $purpose): HostKey
    {
        $bits = (int)config('fortiplugin.keys.bits', 2048);

        $res = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (!$res) {
            throw new RuntimeException('Unable to generate RSA keypair.');
        }

        if (!openssl_pkey_export($res, $privatePem)) {
            throw new RuntimeException('Unable to export private key.');
        }

        $details = openssl_pkey_get_details($res);
        if (!$details || empty($details['key'])) {
            throw new RuntimeException('Unable to extract public key.');
        }
        $publicPem = $details['key'];

        $fingerprint = $this->fingerprint($publicPem);

        return HostKey::create([
            'purpose' => $purpose,
            'public_pem' => $publicPem,
            'private_pem' => $privatePem, // Consider encrypting or storing in KMS.
            'fingerprint' => $fingerprint,
        ]);
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
#### 80


` File: src/Services/ValidatorService.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpUnused */
/** @noinspection PhpUnusedLocalVariableInspection */

declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;
use Timeax\FortiPlugin\Core\Security\ComposerScan;
use Timeax\FortiPlugin\Core\Security\ConfigValidator;
use Timeax\FortiPlugin\Core\Security\ContentValidator;
use Timeax\FortiPlugin\Core\Security\FileScanner;
use Timeax\FortiPlugin\Core\Security\HostConfigValidator;
use Timeax\FortiPlugin\Core\Security\PermissionManifestValidator;
use Timeax\FortiPlugin\Core\Security\PluginSecurityScanner;
use Timeax\FortiPlugin\Core\Security\RouteFileValidator;
use Timeax\FortiPlugin\Core\Security\RouteIdRegistry;
use Timeax\FortiPlugin\Core\Security\TokenUsageAnalyzer;

/**
 * ValidatorService — Orchestrates headline and scanner-driven validations with telemetry and no hard stops.
 *
 * Config keys (all optional):
 *   headline:
 *     composer_json: string|null               Path to composer.json (defaults to <root>/composer.json)
 *     forti_schema: string|null                Path to fortiplugin.json schema (if set, runs ConfigValidator)
 *     host_config: array|null                  Host config array for HostConfigValidator
 *     permission_manifest: string|array|null   Path to manifest.json or decoded array
 *     route_files: array<int,string>           List of JSON route files to validate for unique IDs
 *
 *   scan:
 *     token_list: array<int,string>            Forbidden tokens for TokenUsageAnalyzer (defaults from policy->getForbiddenFunctions())
 *
 *   fail_policy:
 *     types_blocklist: array<int,string>       If any issue type is in this set → fail
 *     severity_threshold: string|null          Not used by current validators but accepted for future
 *     total_error_limit: int|null              If total issues exceed → fail
 *     per_type_limits: array<string,int>       Map of type => max allowed before fail
 *     file_gates: array<int,string>            fnmatch globs; any issue whose file matches → fail
 */
final class ValidatorService
{
    private PluginPolicy $policy;
    private array $config;

    /** @var list<array{0:string,1:string,2:string|null}> */
    private array $log = [];

    /** Extended log items (optional richer fields) */
    private array $extended = [];

    /** Running counters per phase/validator key */
    private array $counters = [];

    /**
     * Last registered emit callback.
     * @var null|callable
     */
    private $emit;

    /**
     * Validator aliases map for setIgnoredValidators.
     * @var array<string,string>
     */
    private array $aliasMap = [
        'composer' => ComposerScan::class,
        'config' => ConfigValidator::class,
        'host' => HostConfigValidator::class,
        'host_config' => HostConfigValidator::class,
        'permission_manifest' => PermissionManifestValidator::class,
        'manifest' => PermissionManifestValidator::class,
        'route' => RouteFileValidator::class,
        'routes' => RouteFileValidator::class,
        'file_scanner' => FileScanner::class,
        'content' => ContentValidator::class,
        'content_validator' => ContentValidator::class,
        'token' => TokenUsageAnalyzer::class,
        'token_usage' => TokenUsageAnalyzer::class,
        'token_analyzer' => TokenUsageAnalyzer::class,
        'ast' => PluginSecurityScanner::class,
        'ast_scanner' => PluginSecurityScanner::class,
    ];

    /**
     * Normalized set of ignored validators (aliases and FQCNs, all lowercase)
     * @var array<string,bool>
     */
    private array $ignored = [];

    private array $stats = [
        'files_scanned' => 0,
        'total_errors' => 0,
    ];

    public function __construct(PluginPolicy $policy, array $config = [])
    {
        $this->policy = $policy;
        $this->config = $config;
    }

    /**
     * Configure validators to ignore by alias or FQCN. Returns $this for chaining.
     * Example: setIgnoredValidators(['config', ConfigValidator::class])
     */
    public function setIgnoredValidators(array $validators): self
    {
        $ignored = [];
        foreach ($validators as $v) {
            if (!is_string($v) || $v === '') continue;
            $key = strtolower($v);
            $ignored[$key] = true;
            // also map known aliases to their class and vice versa
            if (isset($this->aliasMap[$key])) {
                $ignored[strtolower($this->aliasMap[$key])] = true;
            }
            // and if it's a FQCN that matches an alias, add that alias too
            foreach ($this->aliasMap as $alias => $class) {
                if (strcasecmp($class, $v) === 0) {
                    $ignored[strtolower($alias)] = true;
                }
            }
        }
        $this->ignored = $ignored;
        return $this;
    }

    private function isIgnored(string $alias, string $class): bool
    {
        if ($this->ignored === []) return false;
        $alias = strtolower($alias);
        $class = strtolower($class);
        return isset($this->ignored[$alias]) || isset($this->ignored[$class]);
    }

    public function run(string $root, ?callable $emit = null): array
    {
        $this->reset($emit);
        $root = rtrim($root, "\\/");

        $this->emitEvent('Initialize', 'Starting validation pipeline', null, null, null);

        // Headline phase
        $this->emitEvent('Headline', 'Starting headline validators', null, null, null);
        $this->runHeadline($root);
        $this->emitEvent('Headline', 'Completed headline validators', null, null, null);

        // Scanner phase
        $this->emitEvent('Scan', 'Starting file scan', null, null, null);
        $this->runScanner($root);
        $this->emitEvent('Scan', 'Completed file scan', null, null, null);

        // Finalize
        $summary = [
            'files_scanned' => $this->stats['files_scanned'],
            'total_issues' => $this->stats['total_errors'],
            'should_fail' => $this->shouldFail(),
            'log' => $this->log,
            'extended' => $this->extended,
            'formatted' => $this->getFormattedLog(),
        ];

        $this->emitEvent('Finalize', 'Validation complete', [
            'detail' => 'Summary',
            'count' => $this->stats['total_errors'],
        ], null, null);

        return $summary;
    }

    /** Canonical error tuple log accessor */
    public function getLog(): array
    {
        return $this->log;
    }

    /** Return human-friendly, formatted log entries using ErrorReaderService */
    public function getFormattedLog(): array
    {
        try {
            return (new ErrorReaderService())->formatMany($this->extended);
        } catch (Throwable $e) {
            // Never throw; degrade to minimal tuples with message
            $out = [];
            foreach ($this->extended as $raw) {
                if (is_array($raw)) {
                    $out[] = [
                        'slug' => (string)($raw['type'] ?? 'unknown_error'),
                        'name' => 'Issue',
                        'description' => (string)($raw['issue'] ?? ($raw['message'] ?? '')),
                        'severity' => 'high',
                        'file' => $raw['file'] ?? null,
                        'line' => $raw['line'] ?? null,
                        'column' => $raw['column'] ?? null,
                        'snippet' => $raw['snippet'] ?? null,
                        'extra' => $raw,
                    ];
                }
            }
            return $out;
        }
    }

    /** Compute shouldFail decision based on accumulated logs and config policy */
    public function shouldFail(): bool
    {
        $policy = (array)($this->config['fail_policy'] ?? []);
        $typesBlock = array_map('strval', (array)($policy['types_blocklist'] ?? []));
        $totalLimit = $policy['total_error_limit'] ?? null;
        $perTypeLimits = (array)($policy['per_type_limits'] ?? []);
        $fileGates = (array)($policy['file_gates'] ?? []);

        // Build counts per type
        $byType = [];
        foreach ($this->log as [$type, $_issue, $_file]) {
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            // Type blocklist
            if (in_array($type, $typesBlock, true)) {
                return true;
            }
        }

        // Total limit
        if (is_int($totalLimit) && $totalLimit >= 0 && count($this->log) > $totalLimit) {
            return true;
        }

        // Per type limits
        foreach ($perTypeLimits as $t => $limit) {
            if (is_int($limit) && $limit >= 0 && ($byType[$t] ?? 0) > $limit) {
                return true;
            }
        }

        // File gates
        if ($fileGates) {
            foreach ($this->log as [$type, $issue, $file]) {
                $file = (string)$file;
                foreach ($fileGates as $glob) {
                    if (is_string($glob) && $glob !== '' && fnmatch($glob, $file)) {
                        return true;
                    }
                }
            }
        }

        // Optional: severity threshold (not used yet as validators do not emit severities consistently)
        return false;
    }

    // ───────────────────────────── Internals ─────────────────────────────

    private function reset(?callable $emit): void
    {
        $this->log = [];
        $this->extended = [];
        $this->counters = [];
        $this->stats = ['files_scanned' => 0, 'total_errors' => 0];
        $this->emit = $emit;
    }

    private function runHeadline(string $root): void
    {
        // Composer
        if (!$this->isIgnored('composer', ComposerScan::class)) {
            try {
                $composerPath = $this->config['headline']['composer_json'] ?? ($root . DIRECTORY_SEPARATOR . 'composer.json');
                $scanner = new ComposerScan($this->policy);
                $violations = $scanner->scan($composerPath);
                foreach ($violations as $v) {
                    $this->record('composer.' . ($v['type'] ?? 'violation'), (string)($v['issue'] ?? 'Composer violation'), (string)($v['file'] ?? $composerPath), $v);
                    $this->emitEvent('Headline: Composer', $v['issue'] ?? 'Violation', $this->errorCounter('Headline: Composer', $v['issue'] ?? ''), (string)($v['file'] ?? $composerPath), null);
                }
            } catch (Throwable $e) {
                $this->record('composer.exception', $e->getMessage(), $root . DIRECTORY_SEPARATOR . 'composer.json', ['exception' => $e]);
                $this->emitEvent('Headline: Composer', 'Exception', $this->errorCounter('Headline: Composer', $e->getMessage()), null, null);
            }
        }

        // Config schema (fortiplugin.json)
        $schema = $this->config['headline']['forti_schema'] ?? null;
        if (is_string($schema) && $schema !== '' && !$this->isIgnored('config', ConfigValidator::class)) {
            try {
                $cv = new ConfigValidator();
                $res = $cv->validate($root, $schema);
                if (($res['error'] ?? null) !== null) {
                    $details = (array)($res['details'] ?? []);
                    if (!$details) {
                        $this->record('config.schema', (string)$res['error'], $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', $res);
                        $this->emitEvent('Headline: Config', (string)$res['error'], $this->errorCounter('Headline: Config', (string)$res['error']), $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', null);
                    } else {
                        foreach ($details as $d) {
                            $msg = ($d['path'] ?? '') . ' ' . ($d['message'] ?? 'Schema error');
                            $this->record('config.schema', $msg, $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', $d);
                            $this->emitEvent('Headline: Config', $msg, $this->errorCounter('Headline: Config', $msg), $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', null);
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->record('config.exception', $e->getMessage(), $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', ['exception' => $e]);
                $this->emitEvent('Headline: Config', 'Exception', $this->errorCounter('Headline: Config', $e->getMessage()), null, null);
            }
        }

        // Host config (array provided by caller)
        $hostCfg = $this->config['headline']['host_config'] ?? null;
        if (is_array($hostCfg) && !$this->isIgnored('host_config', HostConfigValidator::class)) {
            try {
                HostConfigValidator::validate($hostCfg);
            } catch (Throwable $e) {
                $this->record('hostconfig.error', $e->getMessage(), '[host-config]', ['exception' => $e]);
                $this->emitEvent('Headline: HostConfig', $e->getMessage(), $this->errorCounter('Headline: HostConfig', $e->getMessage()), null, null);
            }
        }

        // Permission manifest (path or array)
        $perm = $this->config['headline']['permission_manifest'] ?? null;
        if ($perm !== null && !$this->isIgnored('permission_manifest', PermissionManifestValidator::class)) {
            try {
                $pmv = new PermissionManifestValidator();
                // validate() throws on errors; we convert to log via catch
                if (is_string($perm)) {
                    $json = @file_get_contents($perm);
                    if ($json === false) {
                        throw new RuntimeException("Cannot read permission manifest: $perm");
                    }
                    $pmv->validate($json);
                } else {
                    $pmv->validate((array)$perm);
                }
            } catch (Throwable $e) {
                $this->record('manifest.invalid', $e->getMessage(), is_string($perm) ? $perm : '[manifest]', ['exception' => $e]);
                $this->emitEvent('Headline: Permission manifest', $e->getMessage(), $this->errorCounter('Headline: Permission manifest', $e->getMessage()), is_string($perm) ? $perm : null, null);
            }
        }

        // Route files (validate IDs + JSON structure)
        $routeFiles = (array)($this->config['headline']['route_files'] ?? []);
        if ($routeFiles && !$this->isIgnored('route', RouteFileValidator::class)) {
            $registry = new RouteIdRegistry();
            foreach ($routeFiles as $rf) {
                try {
                    RouteFileValidator::validateFile($rf, $registry);
                } catch (Throwable $e) {
                    $this->record('route.invalid', $e->getMessage(), (string)$rf, ['exception' => $e]);
                    $this->emitEvent('Headline: Route file', $e->getMessage(), $this->errorCounter('Headline: Route file', $e->getMessage()), (string)$rf, null);
                }
            }
        }
    }

    private function runScanner(string $root): void
    {
        if ($this->isIgnored('file_scanner', FileScanner::class)) {
            return; // skip entire scanning phase
        }
        $scanner = new FileScanner($this->policy);
        $contentValidator = new ContentValidator($this->policy);

        $emitProxy = function (array $e): void {
            // Bridge from FileScanner emit to requested emit schema
            $title = $e['title'] ?? 'Scan';
            $desc = $e['message'] ?? null;
            $file = $e['path'] ?? null;
            //--- check for extra properties
            $extra = array_filter($e, static fn($key) => Arr::has(['file', 'message', 'path'], $key), ARRAY_FILTER_USE_KEY);
            //---
            $this->emitEvent($title, $desc, null, is_string($file) ? $file : null, null, $extra);
        };

        $callback = function (string $file, array $meta = []) use ($contentValidator): array {
            $this->emitEvent('Scan: File', 'Start', null, $file, $this->safeFilesize($file));
            $issues = [];

            // ContentValidator (fast regex-like)
            if (!$this->isIgnored('content', ContentValidator::class)) {
                try {
                    $cv = $contentValidator->scanFile($file);
                    foreach ($cv as $v) {
                        $issues[] = $v;
                    }
                } catch (Throwable $e) {
                    $issues[] = ['type' => 'content.exception', 'issue' => $e->getMessage(), 'file' => $file];
                }
            }

            // TokenUsageAnalyzer (token_get_all based)
            if (!$this->isIgnored('token', TokenUsageAnalyzer::class)) {
                try {
                    $tokens = $this->config['scan']['token_list'] ?? null;
                    if (!is_array($tokens) || !$tokens) {
                        $tokens = $this->policy->getForbiddenFunctions();
                    }
                    $tu = TokenUsageAnalyzer::analyzeFile($file, array_map('strtolower', $tokens));
                    foreach ($tu as $v) {
                        $issues[] = $v;
                    }
                } catch (Throwable $e) {
                    $issues[] = ['type' => 'token.exception', 'issue' => $e->getMessage(), 'file' => $file];
                }
            }

            // PluginSecurityScanner (AST)
            if (!$this->isIgnored('ast', PluginSecurityScanner::class)) {
                try {
                    $src = @file_get_contents($file);
                    if ($src !== false) {
                        $astScanner = new PluginSecurityScanner($this->policy->getConfig(), $file);
                        $astScanner->scanSource($src, $file);
                        foreach ($astScanner->getMatches() as $match) {
                            $issues[] = [
                                'type' => (string)($match['type'] ?? 'ast.violation'),
                                'issue' => (string)($match['message'] ?? ($match['data']['message'] ?? 'AST violation')),
                                'file' => $file,
                                'line' => $match['line'] ?? null,
                            ];
                        }
                    }
                } catch (Throwable $e) {
                    $issues[] = ['type' => 'ast.exception', 'issue' => $e->getMessage(), 'file' => $file];
                }
            }

            // Log+emit
            foreach ($issues as $v) {
                $type = (string)($v['type'] ?? 'scan.issue');
                $issue = (string)($v['issue'] ?? ($v['message'] ?? 'Issue'));
                $this->record($type, $issue, (string)($v['file'] ?? $file), $v);
                $this->emitEvent('Scan: Security', $issue, $this->errorCounter('Scan: Security', $issue), $file, $this->safeFilesize($file));
            }

            $this->stats['files_scanned']++;
            $this->emitEvent('Scan: File', 'End', null, $file, $this->safeFilesize($file));

            return $issues; // return to allow FileScanner to collect, though we do our own logging
        };

        // Drive scanner
        try {
            $scanner->scan($root, $callback, $emitProxy);
        } catch (Throwable $e) {
            // Even FileScanner threw; log and continue finalize
            $this->record('scanner.exception', $e->getMessage(), $root, ['exception' => $e]);
            $this->emitEvent('Scan', 'Scanner exception', $this->errorCounter('Scan', $e->getMessage()), $root, null);
        }
    }

    private function record(string $type, string $issue, ?string $file, array $extended = []): void
    {
        $this->log[] = [$type, $issue, $file];
        $this->extended[] = $extended + ['type' => $type, 'issue' => $issue, 'file' => $file];
        $this->stats['total_errors']++;
    }

    private function emitEvent(string $title, ?string $description, ?array $error, ?string $filePath, ?int $size, ?array $meta = []): void
    {
        if (!$this->emit) {
            return;
        }
        $payload = [
            'title' => $title,
            'description' => $description,
            'error' => $error,
            'stats' => [
                'filePath' => $filePath,
                'size' => $size,
            ],
            'meta' => $meta
        ];
        try {
            ($this->emit)($payload);
        } catch (Throwable $_) { /* never throw */
        }
    }

    private function errorCounter(string $counterKey, string $detail): array
    {
        $this->counters[$counterKey] = ($this->counters[$counterKey] ?? 0) + 1;
        return ['detail' => $detail, 'count' => $this->counters[$counterKey]];
    }

    private function safeFilesize(?string $file): ?int
    {
        if (!$file || !is_file($file)) return null;
        $s = @filesize($file);
        return $s === false ? null : $s;
    }

    /**
     * Public entry to run only the file scanning phase with a provided emitter.
     * Ensures headline validators are ignored; guarantees the scanner stack runs.
     * Restores previous state afterwards.
     */
    public function runFileScan(string $root, callable $emit): void
    {
        $prevEmit = $this->emit;
        $prevIgnored = $this->ignored; // snapshot current ignore set
        $this->emit = $emit;

        try {
            // Headline validators to keep ignored during a pure file scan
            $headline = [
                'composer',
                'config',
                'host',
                'host_config',
                'permission_manifest',
                'manifest',
                'route',
                'routes',
            ];

            // Preserve caller's existing ignores (aliases + FQCNs)…
            $keep = array_keys($prevIgnored); // already lowercase

            // …but make sure the scanning stack is ENABLED (never ignored)
            $scannerAllow = array_map('strtolower', [
                'file_scanner',
                FileScanner::class,
                'content',
                'content_validator',
                ContentValidator::class,
                'token',
                'token_usage',
                'token_analyzer',
                TokenUsageAnalyzer::class,
                'ast',
                'ast_scanner',
                PluginSecurityScanner::class,
            ]);

            // Build the final ignore list: keep previous + force headline ignores,
            // then remove anything from the scannerAllow set.
            $targetIgnores = array_values(array_diff(
                array_unique(array_merge($keep, $headline)),
                $scannerAllow
            ));

            // Apply ignores (aliases ↔ FQCN normalization handled internally)
            $this->setIgnoredValidators($targetIgnores);

            // Run the scanner phase only
            $this->runScanner(rtrim($root, "\\/"));
        } finally {
            // Restore previous state
            $this->ignored = $prevIgnored;
            $this->emit = $prevEmit;
        }
    }
}
```

---
#### 81


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
#### 82


` File: src/Support/MiddlewareNormalizer.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Support;

final class MiddlewareNormalizer
{
    /** @var string[] */
    private static array $spatie = ['role', 'permission', 'role_or_permission'];

    /**
     * Merge guard from group/route, inject auth:guard, and append guard to Spatie middleware.
     * Route-level guard overrides group guard if provided.
     *
     * @param string|null $groupGuard
     * @param string|null $routeGuard
     * @param string[] $middleware
     * @return string[]
     */
    public static function normalize(?string $groupGuard, ?string $routeGuard, array $middleware): array
    {
        $guard = $routeGuard ?? $groupGuard ?? null;
        $middleware = array_values(array_filter(array_map('strval', $middleware)));

        // Inject guard into Spatie middleware if missing ",guard"
        $middleware = array_map(static fn($m) => self::withSpatieGuard($m, $guard), $middleware);

        // Add auth:guard if a guard exists and no auth middleware is present
        if ($guard && !self::hasAuth($middleware)) {
            array_unshift($middleware, "auth:{$guard}");
        }

        // Deduplicate while preserving order
        $seen = [];
        $out = [];
        foreach ($middleware as $m) {
            if (!isset($seen[$m])) {
                $seen[$m] = true;
                $out[] = $m;
            }
        }
        return $out;
    }

    private static function hasAuth(array $mw): bool
    {
        foreach ($mw as $m) {
            if ($m === 'auth' || str_starts_with($m, 'auth:')) return true;
        }
        return false;
    }

    private static function withSpatieGuard(string $item, ?string $guard): string
    {
        if (!$guard) return $item;

        foreach (self::$spatie as $prefix) {
            $needle = $prefix . ':';
            if (str_starts_with($item, $needle)) {
                $rest = substr($item, strlen($needle));   // e.g. "edit posts|publish posts"
                // If a comma already exists, assume guard explicitly provided.
                if (str_contains($rest, ',')) return $item;
                return $prefix . ':' . $rest . ',' . $guard;  // e.g. "permission:edit...,web"
            }
        }
        return $item;
    }
}
```

---
#### 83


` File: src/Support/PluginContext.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Support;

use Timeax\FortiPlugin\Contracts\ConfigInterface;

/**
 * PluginContext
 *
 * Utility class to detect the calling plugin's base directory, config, and name,
 * by scanning the call stack for the first file inside the configured Plugins directory.
 *
 * - Respects 'secured-plugin.directory' config (default: 'Plugins')
 * - Stack frame scan depth defaults to 10 (configurable, but never less than 10)
 * - No caching for accuracy in multi-plugin requests
 *
 * Usage:
 *   $pluginDir = PluginContext::getCurrentPluginDir();
 *   $configPath = PluginContext::getCurrentConfigPath();
 *   $pluginName = PluginContext::getCurrentPluginName();
 */
class PluginContext
{
    /**
     * Returns the maximum number of call stack frames to scan,
     * always at least 10.
     *
     * @return int
     */
    protected static function getStackDepth(): int
    {
        $extra = (int)config('secured-plugin.stack_depth', 1); // default to 1 if not set
        return (max($extra, 1)) + 9; // always at least 10
    }

    /**
     * Returns the base directory path of the calling plugin, or null if not found.
     *
     * @return string|null
     */
    public static function getCurrentPluginDir(): ?string
    {
        $pluginBase = base_path(config('secured-plugin.directory', 'Plugins'));
        $pluginBase = rtrim($pluginBase, '/\\') . DIRECTORY_SEPARATOR;

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::getStackDepth());

        foreach ($trace as $frame) {
            if (!isset($frame['file'])) continue;
            $file = $frame['file'];
            if (str_starts_with($file, $pluginBase)) {
                // File is inside the plugin base directory
                $relPath = substr($file, strlen($pluginBase));
                $parts = explode(DIRECTORY_SEPARATOR, $relPath);
                if (!empty($parts[0])) {
                    // Return the plugin's root directory (e.g., .../Plugins/MyPlugin)
                    return $pluginBase . $parts[0];
                }
            }
        }
        return null;
    }

    /**
     * Returns the full path to the Config.php of the current plugin, or null if not found.
     *
     * @return string|null
     */
    public static function getCurrentConfigPath(): ?string
    {
        $pluginDir = self::getCurrentPluginDir();
        if ($pluginDir) {
            $configPath = $pluginDir . DIRECTORY_SEPARATOR . '.internal/Config.php';
            return file_exists($configPath) ? $configPath : null;
        }
        return null;
    }

    /**
     * Returns the name (folder) of the current plugin, or null if not found.
     *
     * @return string|null
     */
    public static function getCurrentPluginName(): ?string
    {
        $pluginDir = self::getCurrentPluginDir();
        return $pluginDir ? basename($pluginDir) : null;
    }

    /**
     * Returns the config class FQCN for the current plugin,
     * or null if not found. Use static methods on the returned class name.
     *
     * @return class-string<ConfigInterface>|null
     */
    public static function getCurrentConfigClass(): ?string
    {
        $pluginDir = self::getCurrentPluginDir();
        if (!$pluginDir) return null;

        $pluginName = basename($pluginDir); // Studly class
        $class = "Plugins\\$pluginName\\Internal\\Config";
        return class_exists($class) ? $class : null;
    }

    /**
     * @return object{name:string, directory:string, config: class-string<ConfigInterface>|null, config_path: string}|null
     */
    public static function getCurrentContext(): ?object
    {
        $pluginDir = self::getCurrentPluginDir();
        $pluginName = $pluginDir ? basename($pluginDir) : null;
        $configPath = self::getCurrentConfigPath();
        $config = self::getCurrentConfigClass();

        if (!$pluginDir && !$config && !$pluginName) {
            return null;
        }

        return (object)[
            'name' => $pluginName,
            'directory' => $pluginDir,
            'config' => $config,
            'config_path' => $configPath,
        ];
    }
}
```


---
*Generated with [Prodex](https://github.com/emxhive/prodex) — Codebase decoded.*
<!-- PRODEx v1.4.0 | 2025-11-06T03:07:17.282Z -->