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
}