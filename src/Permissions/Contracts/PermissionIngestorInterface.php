<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * Contract for per-type manifest ingestors.
 *
 * Each ingestor:
 *  - computes a natural key for the concrete permission row (dedupe),
 *  - upserts the concrete row,
 *  - ensures the plugin→permission assignment (morph),
 *  - returns a small result DTO for telemetry/audit.
 */
interface PermissionIngestorInterface
{
    /**
     * The manifest rule type this ingestor handles.
     * One of: 'db' | 'file' | 'notification' | 'module' | 'network' | 'codec'
     */
    public function type(): string;

    /**
     * Ingest a single (already validated & normalized) rule for a plugin.
     * Should be idempotent.
     *
     * @param int                             $pluginId  Target plugin id
     * @param array<string,mixed>             $rule      Type-specific normalized rule payload
     * @param PermissionRepositoryInterface   $repo      Persistence boundary (concrete upsert + assignment)
     *
     * @return RuleIngestResult  Contains natural_key, concrete_id, created flag, assigned flag, etc.
     */
    public function ingest(
        int $pluginId,
        array $rule,
        PermissionRepositoryInterface $repo
    ): RuleIngestResult;
}