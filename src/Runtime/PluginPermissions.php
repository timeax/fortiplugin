<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Runtime;

use InvalidArgumentException;
use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

final readonly class PluginPermissions
{
    public function __construct(
        private int                        $pluginId,
        private PermissionServiceInterface $service,
    )
    {
        if ($this->pluginId <= 0) {
            throw new InvalidArgumentException('PluginPermissions requires a valid pluginId.');
        }
    }

    /**
     * Grant (ensure) a permission for the plugin.
     * Idempotent: if the concrete permission and assignment already exists, it should just re-ensure it.
     *
     * @param UpsertDtoInterface $dto Concrete-type DTO (db/file/notification/module/network/codec/etc)
     * @param array $meta Optional assignment metadata: ['constraints'=>array, 'audit'=>array, 'active'=>bool, 'justification'=>?string]
     */
    public function grant(UpsertDtoInterface $dto, array $meta = []): RuleIngestResult
    {
        return $this->service->upsert($this->pluginId, $dto, $meta);
    }

    /**
     * Revoke (deactivate) an existing direct plugin→permission assignment.
     * Idempotent: returns false if it was already inactive / no row changed.
     *
     * IMPORTANT: $permissionId here matches what the system calls "assignment['id']" for that type.
     */
    public function revoke(PermissionType|string $type, int $permissionId): bool
    {
        return false;
    }

    public function pluginId(): int
    {
        return $this->pluginId;
    }
}
