<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionDecisionDetailInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

trait PermissionServiceDecisionTrait
{
    abstract protected function repo(): PermissionRepositoryInterface;
    abstract protected function cache(): CapabilityCacheInterface;

    abstract public function invalidateCache(int $pluginId): void;
    abstract public function warmCache(int $pluginId): void;

    /**
     * Apply an allow/deny decision by producing an UpsertDto (with previous key) and upserting.
     *
     * @throws Throwable
     */
    public function decide(int $pluginId, PermissionDecisionDetailInterface $detail, array $meta = []): RuleIngestResult
    {
        // repo must provide this helper (keeps controller clean)
        $current = $this->repo()->findConcreteByNaturalKey($detail->type()->value, $detail->previous());
        if (!$current) {
            throw new RuntimeException("Permission concrete not found for {$detail->type()->value} previous={$detail->previous()}");
        }

        $dto = $detail->toUpsertDto($current);

        // IMPORTANT: meta here is host-side metadata (constraints/audit/active etc).
        // DO NOT pass justification here.
        $repoResult = $this->repo()->upsertForPlugin($pluginId, $dto, $meta);

        $this->invalidateCache($pluginId);
        $this->warmCache($pluginId);

        return new RuleIngestResult(
            type: $dto->type()->value,
            natural_key: $dto->naturalKey(),
            concrete_id: (int)($repoResult['concrete_id'] ?? 0),
            concrete_Type: (string)($repoResult['concrete_type'] ?? $dto->type()->value),
            created: (bool)($repoResult['created'] ?? false),
            assigned: true,
            warning: $repoResult['warning'] ?? null
        );
    }


}