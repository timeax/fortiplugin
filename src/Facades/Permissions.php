<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Facades;

use Illuminate\Support\Facades\Facade;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\PermissionListOptions;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\Result;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\IngestSummary;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * @method static IngestSummary ingestManifest(int $pluginId, array $manifest)
 * @method static void          warmCache(int $pluginId)
 * @method static void          invalidateCache(int $pluginId)
 *
 * Typed convenience checks (return Result DTOs):
 * @method static Result canDb(int $pluginId, string $action, array $target, array $context = [])
 * @method static Result canFile(int $pluginId, string $action, array $target, array $context = [])
 * @method static Result canNotify(int $pluginId, string $action, array $target, array $context = [])
 * @method static Result canModule(int $pluginId, array $target, array $context = [])
 * @method static Result canNetwork(int $pluginId, array $target, array $context = [])
 * @method static Result canCodec(int $pluginId, array $target, array $context = [])
 * @method static Result canRouteWrite(int $pluginId, array $target, array $context = [])
 *
 * Generic DTO-based check:
 * @method static Result can(int $pluginId, PermissionRequestInterface $request, array $context = [])
 *
 * Service-level upsert wrapper (idempotent):
 * @method static RuleIngestResult upsert(int $pluginId, UpsertDtoInterface $dto, array $meta = [])
 *
 * Manifest validation passthrough:
 * @method static array validateManifest(array $manifest)
 *
 * Permission listing:
 * @method static array listPermissions(int $pluginId, ?PermissionListOptions $options = null)
 */
final class Permissions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PermissionServiceInterface::class;
    }
}