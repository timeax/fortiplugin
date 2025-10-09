<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\{
    PermissionListItem,
    PermissionListOptions,
    PermissionListResult,
    PermissionListSummary
};

trait PermissionServiceListTrait
{
    abstract protected function repo(): PermissionRepositoryInterface;
    abstract protected function cache(): CapabilityCacheInterface;

    public function listPermissions(int $pluginId, ?PermissionListOptions $options = null): PermissionListResult
    {
        $options ??= new PermissionListOptions();

        // 1) Load assignments
        $direct = $this->repo()->getDirectMorphs($pluginId); // shape: ['type','id','active','window','constraints','audit',...]
        $viaTags = $this->repo()->getTagMorphs($pluginId);   // shape: ['type','id','tag'=>['id','name'], 'constraints','audit', 'active'=>bool]

        // 2) Merge by (type, id)
        $byTypeIds = [];
        $groups = []; // key => ['type'=>, 'id'=>, 'direct'=>[], 'tags'=>[]]
        $add = static function(array $row, bool $isDirect) use (&$groups, &$byTypeIds): void {
            $t  = (string)($row['type'] ?? '');
            $id = (int)($row['id'] ?? 0);
            if ($t === '' || $id <= 0) return;

            $key = $t . ':' . $id;
            if (!isset($groups[$key])) {
                $groups[$key] = ['type' => $t, 'id' => $id, 'direct' => [], 'tags' => []];
            }
            if ($isDirect) {
                $groups[$key]['direct'][] = $row;
            } else {
                $groups[$key]['tags'][] = $row;
            }
            $byTypeIds[$t][$id] = true;
        };

        foreach ($direct as $r)  { $add($r, true); }
        foreach ($viaTags as $r) { $add($r, false); }

        // 3) Batch-load concretes per type
        $concretes = [];
        foreach ($byTypeIds as $type => $idMap) {
            $ids = array_map('intval', array_keys($idMap));
            $concretes[$type] = $this->repo()->fetchConcreteByType($type, $ids); // [id => row]
        }

        // 4) Build items
        $items = [];
        $countsByType = [];
        $requiredTotal = 0;
        $requiredSatisfied = 0;
        foreach ($groups as $g) {
            $type = $g['type'];
            $id   = $g['id'];
            $row  = $concretes[$type][$id] ?? null;
            if (!$row) continue;

            // presentation & actions
            [$presentation, $actions, $naturalKey] = $this->extractPresentationAndActions($type, $row);

            // sources
            $sourcesDirect = array_values(array_map(static function(array $d) {
                return [
                    'assignment_id' => $d['assignment_id'] ?? null,
                    'active'        => (bool)($d['active'] ?? true),
                    'window'        => $d['window'] ?? null,
                    'constraints'   => $d['constraints'] ?? null,
                    'audit'         => $d['audit'] ?? null,
                    'required'      => (bool)($d['constraints']['required'] ?? false),
                ];
            }, $g['direct']));

            $sourcesTags = array_values(array_map(static function(array $t) {
                $tag = $t['tag'] ?? null;
                return [
                    'tag_id'      => $tag['id']   ?? ($t['tag_id'] ?? null),
                    'tag_name'    => $tag['name'] ?? ($t['tag_name'] ?? null),
                    'active'      => (bool)($t['active'] ?? true),
                    'constraints' => $t['constraints'] ?? null,
                    'audit'       => $t['audit'] ?? null,
                ];
            }, $g['tags']));

            // derived flags
            $required = false;
            foreach ($sourcesDirect as $sd) {
                if (!empty($sd['required'])) { $required = true; break; }
            }

            $activeEffective = false;
            foreach ($sourcesDirect as $sd) {
                if (!empty($sd['active'])) { $activeEffective = true; break; }
            }
            if (!$activeEffective) {
                foreach ($sourcesTags as $st) {
                    if (!empty($st['active'])) { $activeEffective = true; break; }
                }
            }

            if ($required) {
                $requiredTotal++;
                if ($activeEffective) $requiredSatisfied++;
            }

            $item = new PermissionListItem(
                type: $type,
                concreteId: $id,
                naturalKey: is_string($naturalKey) ? $naturalKey : null,
                presentation: $presentation,
                effectiveActions: $actions,
                concrete: $row,
                sourcesDirect: $sourcesDirect,
                sourcesTags: $sourcesTags,
                required: $required,
                activeEffective: $activeEffective
            );

            // Filter (in-memory for now)
            if ($options->type && $item->type !== $options->type) {
                continue;
            }
            if ($options->requiredOnly !== null && $item->required !== $options->requiredOnly) {
                continue;
            }
            if ($options->activeOnly !== null && $item->activeEffective !== $options->activeOnly) {
                continue;
            }
            if ($options->source) {
                $hasDirect = (bool)count($item->sourcesDirect);
                $hasTag    = (bool)count($item->sourcesTags);
                $ok = match ($options->source) {
                    'direct' => $hasDirect,
                    'tag'    => $hasTag,
                    'both'   => $hasDirect && $hasTag,
                    default  => true,
                };
                if (!$ok) continue;
            }
            if ($options->tagId) {
                $ok = false;
                foreach ($item->sourcesTags as $st) {
                    if ((int)$st['tag_id'] === $options->tagId) { $ok = true; break; }
                }
                if (!$ok) continue;
            }

            $items[] = $item;
            $countsByType[$type] = ($countsByType[$type] ?? 0) + 1;
        }

        // 5) Summary
        $activeCount   = 0;
        $inactiveCount = 0;
        foreach ($items as $it) {
            if ($it->activeEffective) $activeCount++; else $inactiveCount++;
        }
        $summary = new PermissionListSummary(
            byType: $countsByType,
            total: count($items),
            active: $activeCount,
            inactive: $inactiveCount,
            requiredTotal: $requiredTotal,
            requiredSatisfied: $requiredSatisfied,
            requiredPending: max(0, $requiredTotal - $requiredSatisfied)
        );

        return new PermissionListResult($items, $summary);
    }

    /**
     * Maps a concrete row into a presentation slice + effective action flags + natural_key (if present).
     *
     * @return array{0:array,1:array,2:mixed}
     */
    private function extractPresentationAndActions(string $type, array $row): array
    {
        $natural = $row['natural_key'] ?? null;

        // If a JSON "permissions" map exists, prefer it as actions.
        $permMap = [];
        if (isset($row['permissions']) && is_array($row['permissions'])) {
            $permMap = array_map(static fn($v) => (bool)$v, $row['permissions']);
        }

        switch ($type) {
            case 'db':
                $presentation = [
                    'model'   => $row['model'] ?? null,
                    'table'   => $row['table'] ?? null,
                    'columns' => $row['columns'] ?? ($row['readable_columns'] ?? null),
                ];
                $actions = $permMap ?: [
                    'select'           => (bool)($row['select'] ?? false),
                    'insert'           => (bool)($row['insert'] ?? false),
                    'update'           => (bool)($row['update'] ?? false),
                    'delete'           => (bool)($row['delete'] ?? false),
                    'truncate'         => (bool)($row['truncate'] ?? false),
                    'grouped_queries'  => (bool)($row['grouped_queries'] ?? false),
                ];
                break;

            case 'file':
                $presentation = [
                    'base_dir'        => $row['base_dir'] ?? '',
                    'paths'           => $row['paths'] ?? [],
                    'follow_symlinks' => $row['follow_symlinks'] ?? false,
                ];
                $actions = $permMap ?: [
                    'read'   => (bool)($row['read'] ?? false),
                    'write'  => (bool)($row['write'] ?? false),
                    'append' => (bool)($row['append'] ?? false),
                    'delete' => (bool)($row['delete'] ?? false),
                    'mkdir'  => (bool)($row['mkdir'] ?? false),
                    'rmdir'  => (bool)($row['rmdir'] ?? false),
                    'list'   => (bool)($row['list'] ?? false),
                ];
                break;

            case 'notification':
                // Support both legacy single 'channel' and new arrays if present.
                $presentation = [
                    'channel'    => $row['channel'] ?? null,
                    'channels'   => $row['channels'] ?? ($row['channel'] ? [$row['channel']] : null),
                    'templates'  => $row['templates_allowed'] ?? ($row['templates'] ?? null),
                    'recipients' => $row['recipients_allowed'] ?? ($row['recipients'] ?? null),
                ];
                $actions = $permMap ?: [
                    'send'    => (bool)($row['send'] ?? false),
                    'receive' => (bool)($row['receive'] ?? false),
                ];
                break;

            case 'module':
                $presentation = [
                    'plugin'       => $row['plugin_alias'] ?? ($row['plugin'] ?? null),
                    'plugin_fqcn'  => $row['plugin'] ?? null,
                    'apis'         => $row['apis'] ?? [],
                    'plugin_docs'  => $row['plugin_docs'] ?? null,
                ];
                $actions = $permMap ?: [
                    'call'      => (bool)($row['call'] ?? ($row['access'] ?? false)),
                    'publish'   => (bool)($row['publish'] ?? false),
                    'subscribe' => (bool)($row['subscribe'] ?? false),
                ];
                break;

            case 'network':
                $presentation = [
                    'hosts'               => $row['hosts'] ?? [],
                    'methods'             => $row['methods'] ?? [],
                    'schemes'             => $row['schemes'] ?? null,
                    'ports'               => $row['ports'] ?? null,
                    'paths'               => $row['paths'] ?? null,
                    'headers_allowed'     => $row['headers_allowed'] ?? null,
                    'ips_allowed'         => $row['ips_allowed'] ?? null,
                    'auth_via_host_secret'=> (bool)($row['auth_via_host_secret'] ?? true),
                ];
                $actions = $permMap ?: [
                    'request' => (bool)($row['request'] ?? ($row['access'] ?? false)),
                ];
                break;

            case 'codec':
                $presentation = [
                    'module'  => $row['module'] ?? 'codec',
                    'allowed' => $row['allowed'] ?? null,
                    'methods' => $row['methods'] ?? null,           // if you materialize
                    'groups'  => $row['groups'] ?? null,            // if you materialize
                    'options' => $row['options'] ?? null,           // if you materialize
                ];
                $actions = $permMap ?: [
                    'invoke' => (bool)($row['invoke'] ?? ($row['access'] ?? false)),
                ];
                break;

            default:
                $presentation = $row;
                $actions = $permMap ?: [];
        }

        return [$presentation, $actions, $natural];
    }
}