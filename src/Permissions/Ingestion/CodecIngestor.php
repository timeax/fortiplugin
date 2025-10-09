<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;
use Timeax\FortiPlugin\Permissions\Ingestion\Traits\HasIngestionMeta;
use Timeax\FortiPlugin\Permissions\Repositories\Dto\CodecUpsertDto;

/**
 * Persists type=codec (obfuscator) rules and ensures assignment.
 * Input rule is assumed already schema-validated & normalized.
 */
final class CodecIngestor implements PermissionIngestorInterface
{
    use HasIngestionMeta;

    public function type(): string
    {
        return 'codec';
    }

    /**
     * @throws JsonException
     */
    public function ingest(
        int                           $pluginId,
        array                         $rule,
        PermissionRepositoryInterface $repo
    ): RuleIngestResult
    {
        // Build the Upsert DTO from the normalized rule
        $dto = CodecUpsertDto::fromNormalized($rule);

        // Assignment-level meta stored at the pivot (not in the concrete row)
        $this->setMetaRule($rule);
        $meta = $this->getMeta();

        // Repo returns: permission_id, permission_type, concrete_id, concrete_type, created, warning
        $res = $repo->upsertForPlugin($pluginId, $dto, $meta);

        return new RuleIngestResult(
            type: 'codec',
            natural_key: $dto->naturalKey(),
            concrete_id: (int)($res['concrete_id'] ?? 0),
            concrete_Type: (string)($res['concrete_type'] ?? ''),
            created: (bool)($res['created'] ?? false),
            assigned: true,                        // ensure() is idempotent; treat as linked
            warning: $res['warning'] ?? null
        );
    }
}