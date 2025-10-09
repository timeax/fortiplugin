<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;
use Timeax\FortiPlugin\Permissions\Ingestion\Traits\HasIngestionMeta;
use Timeax\FortiPlugin\Permissions\Repositories\Dto\DbUpsertDto;

/**
 * Turns a normalized type=db rule into a concrete DB permission row
 * and ensures the plugin→permission assignment (idempotent).
 */
final class DbIngestor implements PermissionIngestorInterface
{
    use HasIngestionMeta;

    public function type(): string { return 'db'; }

    /**
     * @throws JsonException
     */
    public function ingest(
        int $pluginId,
        array $rule,
        PermissionRepositoryInterface $repo
    ): RuleIngestResult {
        // Build the Upsert DTO directly from the already-normalized rule
        $dto = DbUpsertDto::fromNormalized($rule);

        // Build assignment-level metadata via shared trait
        $this->setMetaRule($rule);
        $meta = $this->getMeta();

        // Repo performs: find-or-create concrete by natural key, then ensure pivot
        // Returns: permission_id, permission_type, concrete_id, concrete_type, created, warning
        $res = $repo->upsertForPlugin($pluginId, $dto, $meta);

        return new RuleIngestResult(
            type:          'db',
            natural_key:   $dto->naturalKey(),
            concrete_id:   (int)($res['concrete_id'] ?? 0),
            concrete_Type: (string)($res['concrete_type'] ?? ''),
            created:       (bool)($res['created'] ?? false),
            assigned:      true, // ensure() is idempotent
            warning:       $res['warning'] ?? null
        );
    }
}