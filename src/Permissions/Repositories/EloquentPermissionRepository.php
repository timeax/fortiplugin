<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;
use Timeax\FortiPlugin\Permissions\Cache\KeyBuilder;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Models\{
    PluginPermission,
    PluginPermissionTag,
    PermissionTagItem,
    PluginRoutePermission,
    DbPermission,
    FilePermission,
    NotificationPermission,
    ModulePermission,
    NetworkPermission,
    CodecPermission
};
use Timeax\FortiPlugin\Enums\{
    PermissionType
};
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;

final class EloquentPermissionRepository implements PermissionRepositoryInterface
{
    /* ================================================================
     * Assignments (direct)
     * ================================================================ */
    public function getDirectMorphs(int $pluginId): array
    {
        $rows = PluginPermission::query()
            ->where('plugin_id', $pluginId)
            ->get([
                'permission_type',
                'permission_id',
                'active',
                'limited',
                'limit_type',
                'limit_value',
                'constraints',
                'audit',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'type' => $r->permission_type->value,      // enum cast → string value
                'id' => (int)$r->permission_id,
                'active' => (bool)$r->active,
                'window' => $this->windowObj($r->limited, $r->limit_type, $r->limit_value),
                'constraints' => $r->constraints,                 // assignment-level (direct)
                'audit' => $r->audit,                       // assignment-level (direct)
            ];
        }
        return $out;
    }

    /* ================================================================
     * Assignments (via tags)
     *  - window comes from PluginPermissionTag
     *  - constraints/audit from PermissionTagItem
     * ================================================================ */
    public function getTagMorphs(int $pluginId): array
    {
        // Active plugin→tag pivots (carry the window)
        $tagPivots = PluginPermissionTag::query()
            ->where('plugin_id', $pluginId)
            ->where('active', true)
            ->get(['tag_id', 'limited', 'limit_type', 'limit_value']);

        if ($tagPivots->isEmpty()) {
            return [];
        }

        $tagIds = $tagPivots->pluck('tag_id')->all();
        $windowByTag = [];
        foreach ($tagPivots as $p) {
            $windowByTag[$p->tag_id] = $this->windowObj($p->limited, $p->limit_type, $p->limit_value);
        }

        // Tag items (carry constraints/audit per item)
        $items = PermissionTagItem::query()
            ->whereIn('tag_id', $tagIds)
            ->get([
                'tag_id',
                'permission_type',
                'permission_id',
                'constraints',
                'audit',
            ]);

        $out = [];
        foreach ($items as $it) {
            $out[] = [
                'type' => $it->permission_type->value,
                'id' => (int)$it->permission_id,
                'active' => true,                                  // tag is active → item is active
                'window' => $windowByTag[$it->tag_id] ?? null,     // window sourced from PluginPermissionTag
                'constraints' => $it->constraints,                      // item-level
                'audit' => $it->audit,                            // item-level
            ];
        }

        return $out;
    }

    /* ================================================================
     * Concrete fetches (batch by type)
     * ================================================================ */
    public function fetchConcreteByType(string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) return [];

        $model = $this->modelForType($type);
        if ($model === null) return [];

        $rows = $model::query()->whereIn('id', $ids)->get();
        $out = [];
        foreach ($rows as $r) {
            // Your Eloquent models already define casts, so toArray() is good.
            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            $out[(int)$r->id] = $r->toArray();
        }
        return $out;
    }

    /* ================================================================
     * Ensure plugin assignment (idempotent)
     * ================================================================ */
    /**
     * @throws Throwable
     */
    public function ensurePluginAssignment(int $pluginId, string $type, int $permissionId, array $meta = []): void
    {
        DB::transaction(static function () use ($pluginId, $type, $permissionId, $meta) {
            $enum = PermissionType::from($type);

            /** @var PluginPermission $row */
            $row = PluginPermission::query()->firstOrNew([
                'plugin_id' => $pluginId,
                'permission_type' => $enum,
                'permission_id' => $permissionId,
            ]);

            $row->active = (bool)($meta['active'] ?? true);

            if (array_key_exists('constraints', $meta)) {
                $row->constraints = $meta['constraints'];
            }
            if (array_key_exists('audit', $meta)) {
                $row->audit = $meta['audit'];
            }

            $row->save();
        });
    }

    /* ================================================================
     * Route approval lookup
     * ================================================================ */
    public function routePermission(int $pluginId, string $routeId): ?array
    {
        $r = PluginRoutePermission::query()
            ->where('plugin_id', $pluginId)
            ->where('route_id', $routeId)
            ->first();

        if (!$r) return null;

        return [
            'status' => $r->status->value, // enum cast
            'guard' => $r->guard,
            'meta' => $r->meta,
            'approved_at' => $r->approved_at?->format('c'),
        ];
    }

    /* ================================================================
     * Upsert concrete by natural_key (ALL TYPES) + ensure assignment
     * ================================================================ */
    /**
     * @throws Throwable
     */
    public function upsertForPlugin(int $pluginId, UpsertDtoInterface $dto, array $meta = []): array
    {
        $enum = $dto->type();
        $naturalKey = $dto->naturalKey();
        $attrs = $dto->attributes();
        $identityFields = $dto->identityFields();
        $mutableFields = $dto->mutableFields();
        $modelClass = $dto->concreteModelClass();

        return DB::transaction(function () use ($pluginId, $enum, $naturalKey, $attrs, $identityFields, $mutableFields, $modelClass, $meta) {
            $concrete = $modelClass::query()->where('natural_key', $naturalKey)->first();

            $created = false;
            $warning = null;

            if (!$concrete) {
                /** @var Model $concrete */
                $concrete = new $modelClass();
                $concrete->setAttribute('natural_key', $naturalKey);

                // First insert: identity + allowed mutables are supplied by DTO
                $concrete->fill($attrs);
                $concrete->save();
                $created = true;
            } else {
                // Verify identity fields
                $mismatches = [];
                foreach ($identityFields as $k) {
                    if (!array_key_exists($k, $attrs)) continue; // DTO may omit non-applicable identity fields
                    $new = $this->canonForCompare($attrs[$k]);
                    $old = $this->canonForCompare($concrete->getAttribute($k));
                    if ($new !== $old) $mismatches[] = $k;
                }
                if ($mismatches) {
                    $warning = 'attribute_mismatch_for_natural_key: ' . implode(', ', $mismatches);
                }

                // Apply mutable fields only (if present in attributes)
                $toUpdate = [];
                foreach ($mutableFields as $k) {
                    if (array_key_exists($k, $attrs)) {
                        $toUpdate[$k] = $attrs[$k];
                    }
                }
                if (!empty($toUpdate)) {
                    $concrete->fill($toUpdate);
                    $concrete->save();
                }
            }

            // Ensure plugin assignment (pivot)
            /** @var PluginPermission $assignment */
            $assignment = PluginPermission::query()
                ->where('plugin_id', $pluginId)
                ->where('permission_type', $enum)
                ->where('permission_id', (int)$concrete->getKey())
                ->first();

            if (!$assignment) {
                $assignment = new PluginPermission();
                $assignment->plugin_id = $pluginId;
                $assignment->permission_type = $enum;
                $assignment->permission_id = (int)$concrete->getKey();
            }

            if (array_key_exists('active', $meta)) {
                $assignment->active = (bool)$meta['active'];
            }
            if (array_key_exists('constraints', $meta)) {
                $assignment->constraints = $meta['constraints'];
            }
            if (array_key_exists('audit', $meta)) {
                $assignment->audit = $meta['audit'];
            }

            $assignment->save();

            return [
                'permission_id' => (int)$concrete->getKey(),
                'permission_type' => $enum->value,
                'concrete_id' => (int)$concrete->getKey(),
                'concrete_type' => $enum->value,
                'created' => $created,
                'warning' => $warning,
            ];
        });
    }

    /**
     * @throws Throwable
     */
    public function deactivatePluginPermission(int $pluginId, PermissionType $type, int $permissionId): bool
    {
        return DB::transaction(static function () use ($pluginId, $type, $permissionId): bool {
            $row = PluginPermission::query()
                ->where('plugin_id', $pluginId)
                ->where('permission_type', $type) // enum cast on model
                ->where('permission_id', $permissionId)
                ->first();

            if (!$row || $row->active === false) {
                return false; // idempotent
            }

            $row->active = false;
            $row->save();

            return true;
        });
    }


    /**
     * @throws JsonException
     */
    private function canonForCompare(mixed $v): string
    {
        if (is_array($v) || is_object($v)) {
            $v = $this->normalize($v);
            return json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string)$v;
    }

    /**
     * @throws JsonException
     */
    private function normalize(mixed $v): mixed
    {
        return KeyBuilder::normalize($v);
    }

    /* ================================================================
     * Helpers
     * ================================================================ */
    private function windowObj(bool $limited, ?string $type, ?string $value): ?array
    {
        if (!$limited) return null;
        return ['limited' => true, 'type' => $type, 'value' => $value];
    }

    /** @return class-string<Model>|null */
    private function modelForType(string $type): ?string
    {
        return match ($type) {
            'db' => DbPermission::class,
            'file' => FilePermission::class,
            'notification' => NotificationPermission::class,
            'module' => ModulePermission::class,
            'network' => NetworkPermission::class,
            'codec' => CodecPermission::class,
            default => null,
        };
    }
}