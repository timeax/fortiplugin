<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

use Timeax\FortiPlugin\Permissions\Ingestion\Dto\IngestSummary;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * Per-type ingestor turns one normalized manifest rule into:
 *  - a concrete permission row (find-or-create by natural key),
 *  - a PluginPermission morph assignment (ensure).
 */
interface PermissionIngestorInterface
{
    /**
     * @return string One of: db|file|notification|module|network|codec
     */
    public function type(): string;

    /**
     * Ingest a single normalized rule for a plugin (idempotent).
     *
     * @param int $pluginId
     * @param array $rule Type-specific normalized rule.
     * @param array $catalogs Host catalogs that may be needed (models/modules/channels/codec groups).
     * @return RuleIngestResult Summary: ['concrete_id'=>int,'assigned'=>bool,'created'=>bool,'natural_key'=>string]
     */
    public function ingest(int $pluginId, array $rule, array $catalogs = []): RuleIngestResult;
}