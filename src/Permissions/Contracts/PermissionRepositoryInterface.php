<?php /** @noinspection PhpUnused */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

use Timeax\FortiPlugin\Enums\PermissionType;

/**
 * Data access for permission morphs and concrete rows.
 * Keep this thin; business logic lives in checkers/ingestors.
 */
interface PermissionRepositoryInterface
{
    /**
     * Direct assignments (plugin → concrete permission morphs).
     *
     * @param int $pluginId
     * @return array[] Each: ['type'=>string, 'id'=>int, 'active'=>bool, 'window'=>['limited'=>bool,'type'=>?string,'value'=>?string], 'constraints'=>?array, 'audit'=>?array]
     */
    public function getDirectMorphs(int $pluginId): array;

    /**
     * Morphs via tags (plugin → tag → permission morphs).
     *
     * @param int $pluginId
     * @return array[] Same shape as getDirectMorphs(); tag-level windows may be merged by the service.
     */
    public function getTagMorphs(int $pluginId): array;

    /**
     * Batch-load concrete rows by type.
     *
     * @param string $type One of: db|file|notification|module|network|codec
     * @param int[] $ids
     * @return array id => array concrete row fields
     */
    public function fetchConcreteByType(string $type, array $ids): array;

    /**
     * Ensure a plugin has an assignment to a concrete row (idempotent).
     *
     * @param int $pluginId
     * @param string $type
     * @param int $permissionId
     * @param array $meta Optional: ['constraints'=>array,'audit'=>array,'active'=>bool]
     * @return void
     */
    public function ensurePluginAssignment(int $pluginId, string $type, int $permissionId, array $meta = []): void;

    /**
     * Route approval status for a plugin (install-time).
     *
     * @param int $pluginId
     * @param string $routeId
     * @return array|null e.g., ['status'=>'approved','guard'=>?string,'meta'=>?array,'approved_at'=>?string]
     */
    public function routePermission(int $pluginId, string $routeId): ?array;

    // Add to PermissionRepositoryInterface

    /**
     * Upsert a concrete permission (by natural key) and attach it to a plugin.
     * Should be idempotent.
     *
     * @param int $pluginId
     * @param UpsertDtoInterface $dto
     * @param array $meta Optional: ['constraints'=>array,'audit'=>array,'active'=>bool,'justification'=>?string]
     * @return array{permission_id:int,permission_type:string,concrete_id:int,concrete_type:string,created:bool}
     */
    public function upsertForPlugin(
        int    $pluginId,
        UpsertDtoInterface $dto,
        array  $meta = []
    ): array;

    /**
     * Deactivate a direct plugin→permission morph (idempotent).
     * Returns true if a row was updated (i.e., it was active and is now inactive).
     */
    public function deactivatePluginPermission(int $pluginId, PermissionType $type, int $permissionId): bool;
}