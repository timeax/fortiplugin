<?php

namespace Timeax\FortiPlugin\Contracts;
interface ConfigInterface
{
    /**
     * Returns all parsed plugin config values.
     *
     * @return array<string, mixed>
     */
    public static function all(): array;

    /**
     * Get a config key value or fallback.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed;

    /**
     * Plugin's declared name.
     *
     * @return string
     */
    public static function getName(): string;

    /**
     * Plugin alias, used to resolve runtime behaviors.
     *
     * @return string
     */
    public static function getAlias(): string;

    /**
     * Plugin version (declared in config).
     *
     * @return string
     */
    public static function getVersion(): string;

    /**
     * Optional plugin description.
     *
     * @return string|null
     */
    public static function getDescription(): ?string;

    /**
     * Optional plugin author name.
     *
     * @return string|null
     */
    public static function getAuthor(): ?string;

    /**
     * UI config block (if defined).
     *
     * @return array<string, mixed>|null
     */
    public static function getUiConfig(): ?array;

    /**
     * API config block (if defined).
     *
     * @return array<string, mixed>|null
     */
    public static function getHostConfig(): ?array;

    /**
     * Runtime plugin info (set internally at install time).
     *
     * Example: ['id' => 4, 'alias' => 'my-plugin']
     *
     * @return array{id: int|null, alias: string|null, name:string}
     */
    public static function getInfo(): array;

    /**
     * Installed plugin's ID (as saved in database), or null.
     *
     * @return int|null
     */
    public static function getPluginId(): ?int;

    /**
     * Installed plugin alias (even if ID is missing).
     *
     * @return string|null
     */
    public static function getInstalledAlias(): ?string;


    /**
     * Returns the required permissions (declared in manifest.json),
     * along with their current grant status.
     *
     * @return array<array{
     *     type: string,           // e.g., "db", "file", "notify", "module"
     *     target: string,         // e.g., "users", "tmp/files", "email"
     *     permissions: string[],  // e.g., ["select", "insert"]
     *     granted: string[]       // subset of permissions that were granted
     * }>
     */
    public static function getRequiredPermissions(): array;

    /**
     * Whether this plugin has been granted all required permissions in the database.
     */
    public static function hasRequiredPermissions(): bool;

    /**
     * Extra permissions granted to this plugin that go beyond its manifest requirements.
     * These are fetched from database and grouped by type.
     *
     * @return array{
     *     db: array<array{model: string, select: bool, insert: bool, update: bool, delete: bool}>,
     *     file: array<array{file_path: string, read: bool, write: bool, execute: bool}>,
     *     module: array<array{module: string, access: bool}>,
     *     notify: array<array{channel: string, send: bool, receive: bool}>
     * }
     */
    public static function getExtraPermissions(): array;

    /**
     * Check whether specific permissions are granted for a plugin asset.
     *
     * @param string $type One of: db, file, notify, module
     * @param string $target The object of the permission:
     *                         - model name (for db)
     *                         - file path (for file)
     *                         - channel (for notify)
     *                         - module (for module)
     * @param string|string[]|null $permissions
     *        Optional:
     *        - For `db`: select, insert, update, delete
     *        - For `file`: read, write, execute
     *        - For `notify`: send, receive
     *        - For `module`: null or 'access'
     *        Accepts string (e.g., "select,update") or array ["select", "update"]
     *
     * @return bool True if **all** specified permissions are granted
     */
    public static function getPermission(string $type, string $target, string|array|null $permissions = null): bool;

    /**
     * Returns the content of the `.internal/Signed` file, if it exists.
     *
     * @return string|null The raw signature payload, or null if not found.
     */
    public static function getSignature(): ?string;
}



