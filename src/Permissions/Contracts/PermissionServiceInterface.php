<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

use Timeax\FortiPlugin\Permissions\Evaluation\Dto\Result;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\IngestSummary;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * Facade-friendly service for ingesting manifests and answering runtime checks.
 *
 * All `can*` methods return a Result DTO.
 * When serialized (Result::toArray()), the shape is:
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
     * @param int $pluginId
     * @param array $manifest Validated manifest (rules already schema-validated/normalized).
     * @return IngestSummary Summary (e.g., created/linked counts, warnings).
     */
    public function ingestManifest(int $pluginId, array $manifest): IngestSummary;

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
     * @param int $pluginId
     * @param string $action One of: select|insert|update|delete|truncate|grouped_queries
     * @param array $target { model?: string, table?: string, columns?: string[] }
     * @param array $context { guard?: string, env?: string, settings?: array, ... }
     * @return Result The standardized result.
     */
    public function canDb(int $pluginId, string $action, array $target, array $context = []): Result;

    /**
     * File check.
     * @param int $pluginId
     * @param string $action One of: read|write|append|delete|mkdir|rmdir|list
     * @param array $target { baseDir: string, path: string }
     * @param array $context
     * @return Result The standardized result.
     */
    public function canFile(int $pluginId, string $action, array $target, array $context = []): Result;

    /**
     * Notification check.
     * @param int $pluginId
     * @param string $action One of: send|receive
     * @param array $target { channel: string, template?: string, recipient?: string }
     * @param array $context
     * @return Result The standardized result.
     */
    public function canNotify(int $pluginId, string $action, array $target, array $context = []): Result;

    /**
     * Host module API check (single action "call", inferred by presence).
     * @param int $pluginId
     * @param array $target { module: string, api: string }
     * @param array $context
     * @return Result The standardized result.
     */
    public function canModule(int $pluginId, array $target, array $context = []): Result;

    /**
     * Network egress check (single action "request").
     * @param int $pluginId
     * @param array $target { method: string, url: string, headers?: array }
     * @param array $context
     * @return Result The standardized result.
     */
    public function canNetwork(int $pluginId, array $target, array $context = []): Result;

    /**
     * Codec/Obfuscator check (single action "invoke").
     * @param int $pluginId
     * @param array $target { method: string, options?: array }
     * @param array $context
     * @return Result The standardized result.
     */
    public function canCodec(int $pluginId, array $target, array $context = []): Result;

    /**
     * Install-time route write approval.
     * @param int $pluginId
     * @param array $target { routeId: string, guard?: string }
     * @param array $context
     * @return Result The standardized result.
     */
    public function canRouteWrite(int $pluginId, array $target, array $context = []): Result;

    /**
     * Determines if a plugin has the specified permission based on the given request and context.
     *
     * @param int $pluginId The unique identifier of the plugin.
     * @param PermissionRequestInterface $request The permission request to evaluate.
     * @param array $context Additional contextual data relevant to the permission check.
     * @return Result The result of the permission evaluation.
     */
    public function can(int $pluginId, PermissionRequestInterface $request, array $context): Result;

    /**
     * Upsert a concrete permission row (by its natural key) and ensure the plugin assignment.
     * Wraps the repository, emits an ingest audit, and refreshes the capability cache.
     *
     * @param int $pluginId
     * @param UpsertDtoInterface $dto   Concrete-type DTO (db/file/notification/module/network/codec)
     * @param array $meta               Optional assignment metadata: ['constraints'=>array, 'audit'=>array, 'active'=>bool, 'justification'=>?string]
     * @return RuleIngestResult
     */
    public function upsert(int $pluginId, UpsertDtoInterface $dto, array $meta = []): RuleIngestResult;
}