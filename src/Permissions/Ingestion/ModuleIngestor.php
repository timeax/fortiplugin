<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * Persists type=module rules (module plugin + apis) and ensures assignment.
 */
final class ModuleIngestor implements PermissionIngestorInterface
{
    public function type(): string { return 'module'; }

    /**
     * @throws JsonException
     */
    public function ingest(
        int $pluginId,
        array $rule,
        PermissionRepositoryInterface $repo
    ): RuleIngestResult {
        $target  = (array)($rule['target'] ?? []);
        $actions = (array)($rule['actions'] ?? []);

        $natural = NaturalKeyBuilder::module($target, $actions);

        $attrs = [
            'plugin'       => (string)($target['plugin_fqcn'] ?? $target['plugin'] ?? ''),
            'plugin_alias' => $target['plugin_alias'] ?? null,
            'plugin_docs'  => $target['plugin_docs'] ?? null,
            'apis'         => (array)($target['apis'] ?? []),

            'call'         => in_array('call',      $actions, true),
            'publish'      => in_array('publish',   $actions, true),
            'subscribe'    => in_array('subscribe', $actions, true),
        ];

        $meta = [
            'actions'       => $actions,
            'audit'         => $rule['audit'] ?? null,
            'conditions'    => $rule['conditions'] ?? null,
            'justification' => $rule['justification'] ?? null,
        ];

        $res = $repo->upsertForPlugin($pluginId, 'module', $natural, $attrs, $meta);

        return new RuleIngestResult(
            'module',
            $natural,
            (int)($res['concrete_id'] ?? 0),
            (string)($res['concrete_type'] ?? ''),
            (bool)($res['created'] ?? false),
            (bool)($res['assigned'] ?? true),
            null
        );
    }
}