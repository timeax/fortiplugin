<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

/**
 * Facade-friendly service for ingesting manifests and answering runtime checks.
 *
 * All `can*` methods return a result array:
 * ```
 * [
 *   'allowed'  => bool,
 *   'reason'   => string|null,          // short machine-friendly reason (optional)
 *   'matched'  => ['type' => string, 'id' => int]|null, // concrete permission morph that granted/failed
 *   'context'  => array|null,           // optional extra data (e.g., normalized URL, compiled path)
 * ]
 *```
 * `$context` is free-form and may include guard/env/settings as needed by your host.
 */
interface PermissionServiceInterface
{
    /**
     * Ingest a validated manifest for a plugin (idempotent).
     *
     * @param int   $pluginId
     * @param array $manifest Validated manifest (rules already schema-validated/normalized).
     * @return array Summary (e.g., created/linked counts, warnings).
     */
    public function ingestManifest(int $pluginId, array $manifest): array;

    /**
     * Warm (build) the capability cache for a plugin.
     * @param int $pluginId
     * @return void
     */
    public function warmCache(int $pluginId): void;

    /**
     * Invalidate any cached capabilities for a plugin.
     * @param int $pluginId
     * @return void
     */
    public function invalidateCache(int $pluginId): void;

    /**
     * DB check.
     * @param int    $pluginId
     * @param string $action   One of: select|insert|update|delete|truncate|grouped_queries
     * @param array  $target   { model?: string, table?: string, columns?: string[] }
     * @param array  $context  { guard?: string, env?: string, settings?: array, ... }
     * @return array See header doc for result shape.
     */
    public function canDb(int $pluginId, string $action, array $target, array $context = []): array;

    /**
     * File check.
     * @param int    $pluginId
     * @param string $action   One of: read|write|append|delete|mkdir|rmdir|list
     * @param array  $target   { baseDir: string, path: string }
     * @param array  $context
     * @return array
     */
    public function canFile(int $pluginId, string $action, array $target, array $context = []): array;

    /**
     * Notification check.
     * @param int    $pluginId
     * @param string $action   One of: send|receive
     * @param array  $target   { channel: string, template?: string, recipient?: string }
     * @param array  $context
     * @return array
     */
    public function canNotify(int $pluginId, string $action, array $target, array $context = []): array;

    /**
     * Host module API check (single action "call", inferred by presence).
     * @param int   $pluginId
     * @param array $target    { module: string, api: string }
     * @param array $context
     * @return array
     */
    public function canModule(int $pluginId, array $target, array $context = []): array;

    /**
     * Network egress check (single action "request").
     * @param int   $pluginId
     * @param array $target    { method: string, url: string, headers?: array }
     * @param array $context
     * @return array
     */
    public function canNetwork(int $pluginId, array $target, array $context = []): array;

    /**
     * Codec/Obfuscator check (single action "invoke").
     * @param int   $pluginId
     * @param array $target    { method: string, options?: array }
     * @param array $context
     * @return array
     */
    public function canCodec(int $pluginId, array $target, array $context = []): array;

    /**
     * Install-time route write approval.
     * @param int   $pluginId
     * @param array $target    { routeId: string, guard?: string }
     * @param array $context
     * @return array
     */
    public function canRouteWrite(int $pluginId, array $target, array $context = []): array;
}