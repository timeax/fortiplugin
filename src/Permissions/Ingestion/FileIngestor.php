<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;
use Timeax\FortiPlugin\Permissions\Ingestion\Traits\HasIngestionMeta;
use Timeax\FortiPlugin\Permissions\Repositories\Dto\FileUpsertDto;

/**
 * Persists type=file rules (fs paths/policies) and ensures assignment (idempotent).
 */
final class FileIngestor implements PermissionIngestorInterface
{
    use HasIngestionMeta;

    public function type(): string
    {
        return 'file';
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
        // Build the Upsert DTO from the already-normalized rule
        $dto = FileUpsertDto::fromNormalized($rule);

        // Assignment-level metadata (stored on the pivot, not the concrete)
        $this->setMetaRule($rule);
        $meta = $this->getMeta();

        // Repo performs: find-or-create concrete by natural key, then ensure pivot
        // Returns: permission_id, permission_type, concrete_id, concrete_type, created, warning
        $res = $repo->upsertForPlugin($pluginId, $dto, $meta);

        return new RuleIngestResult(
            type: 'file',
            natural_key: $dto->naturalKey(),
            concrete_id: (int)($res['concrete_id'] ?? 0),
            concrete_Type: (string)($res['concrete_type'] ?? ''),
            created: (bool)($res['created'] ?? false),
            assigned: true, // ensure() is idempotent
            warning: $res['warning'] ?? null
        );
    }
}