<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;
use Timeax\FortiPlugin\Permissions\Ingestion\Traits\HasIngestionMeta;
use Timeax\FortiPlugin\Permissions\Repositories\Dto\NotificationUpsertDto;

/**
 * Persists type=notification rules (channels/templates/recipients) and ensures assignment.
 * Uses NotificationUpsertDto so attributes & natural key are canonicalized in one place.
 */
final class NotificationIngestor implements PermissionIngestorInterface
{
    use HasIngestionMeta;

    public function type(): string
    {
        return 'notification';
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
        // Build DTO from the already-normalized rule (validator has shaped target/actions)
        $dto = NotificationUpsertDto::fromNormalized($rule);

        // Pivot-level metadata (not stored on the concrete)
        $this->setMetaRule($rule);
        $meta = $this->getMeta();

        // Repo returns: permission_id, permission_type, concrete_id, concrete_type, created, warning
        $res = $repo->upsertForPlugin($pluginId, $dto, $meta);

        return new RuleIngestResult(
            type: 'notification',
            natural_key: $dto->naturalKey(),
            concrete_id: (int)($res['concrete_id'] ?? 0),
            concrete_Type: (string)($res['concrete_type'] ?? ''),
            created: (bool)($res['created'] ?? false),
            assigned: true,
            warning: $res['warning'] ?? null
        );
    }
}