<?php /** @noinspection PhpUnusedLocalVariableInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use JsonException;
use Throwable;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

trait PermissionServiceUpsertTrait
{
    abstract protected function repo(): PermissionRepositoryInterface;

    abstract protected function cache(): CapabilityCacheInterface;

    abstract public function invalidateCache(int $pluginId): void;

    abstract public function warmCache(int $pluginId): void;

    /**
     * Thin wrapper around repository upsert using the typed DTOs.
     *
     * @param int $pluginId
     * @param UpsertDtoInterface $dto
     * @param array|null $meta e.g. ['constraints'=>array,'audit'=>array,'active'=>bool,'justification'=>?string]
     * @return RuleIngestResult
     * @throws JsonException
     */
    public function upsert(int $pluginId, UpsertDtoInterface $dto, ?array $meta = []): RuleIngestResult
    {
        // 1) Persist concrete row (by natural key) + ensure plugin assignment
        $repoResult = $this->repo()->upsertForPlugin($pluginId, $dto, $meta);
        // shape:
        // [
        //   'permission_id'   => int,
        //   'permission_type' => string,
        //   'concrete_id'     => int,
        //   'concrete_type'   => string,
        //   'created'         => bool,
        //   'warning'         => ?string,
        // ]

        $type = $dto->type()->value;          // e.g. 'db'
        $naturalKey = $dto->naturalKey();           // deterministic, from DTO
        $concreteId = (int)($repoResult['concrete_id'] ?? 0);
        $concreteTyp = (string)($repoResult['concrete_type'] ?? $type);
        $created = (bool)($repoResult['created'] ?? false);
        $warning = $repoResult['warning'] ?? null;

        // If the repo returns a permission_id, we can treat that as “assigned ensured”
        $assigned = isset($repoResult['permission_id']) && (int)$repoResult['permission_id'] > 0;

        $resultDto = new RuleIngestResult(
            type: $type,
            natural_key: $naturalKey,
            concrete_id: $concreteId,
            concrete_Type: $concreteTyp,
            created: $created,
            assigned: $assigned,
            warning: $warning
        );

        // 2) Emit an ingest audit — we treat this as a successful “decision”
        // Request payload: the DTO’s attributes are the source of truth for what was stored
        $requestPayload = [
            'type' => $type,
            'natural_key' => $naturalKey,
            'attributes' => $dto->attributes(), // normalized by DTO
            'meta' => $meta ?: null,
        ];

        $decisionPayload = [
            'allowed' => true,
            'reason' => null,
            'matched' => ['type' => $type, 'id' => $concreteId],
            'context' => [
                'created' => $created,
                'assigned' => $assigned,
                'permission_id' => (int)($repoResult['permission_id'] ?? 0),
                'permission_type' => (string)($repoResult['permission_type'] ?? $type),
                'warning' => $warning,
            ],
        ];

        $auditOptions = [
            // manifest-driven redaction can be carried through meta['audit']['redact_fields']
            'redact_fields' => isset($meta['audit']['redact_fields']) && is_array($meta['audit']['redact_fields'])
                ? array_values(array_unique(array_map('strval', $meta['audit']['redact_fields'])))
                : [],
            'tags' => ['ingest', 'service_upsert'],
        ];

        try {
            $this->audit->record('ingest', $type, $pluginId, $requestPayload, $decisionPayload, $auditOptions);
        } catch (Throwable $e) {
            // Don’t fail the operation because logging failed; surface as a warning in result if you want
            // (optional) $resultDto = new RuleIngestResult(..., warning: trim(($warning ? "$warning; " : "")."audit_error: ".$e->getMessage()));
        }

        // 3) Refresh cache so runtime checks immediately see the new permission
        $this->invalidateCache($pluginId);
        $this->warmCache($pluginId);

        return $resultDto;
    }
}