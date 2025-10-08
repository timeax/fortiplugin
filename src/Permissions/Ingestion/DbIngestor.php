<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * Turns a normalized type=db rule into a concrete DB permission row
 * and ensures the plugin→permission assignment.
 */
final class DbIngestor implements PermissionIngestorInterface
{
    public function type(): string { return 'db'; }

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

        $natural = NaturalKeyBuilder::db($target, $actions);

        $attrs = [
            'model'       => $target['model']  ?? null,    // FQCN if present
            'table'       => $target['table']  ?? null,    // when table-based
            'columns'     => $target['columns']?? null,    // string[]|null
            'select'      => in_array('select',      $actions, true),
            'insert'      => in_array('insert',      $actions, true),
            'update'      => in_array('update',      $actions, true),
            'delete'      => in_array('delete',      $actions, true),
            'truncate'    => in_array('truncate',    $actions, true),
            'transaction' => in_array('transaction', $actions, true),
        ];

        $meta = [
            'actions'       => $actions,
            'audit'         => $rule['audit'] ?? null,
            'conditions'    => $rule['conditions'] ?? null,
            'justification' => $rule['justification'] ?? null,
        ];

        $res = $repo->upsertForPlugin($pluginId, 'db', $natural, $attrs, $meta);

        return new RuleIngestResult(
            'db',
            $natural,
            (int)($res['concrete_id'] ?? 0),
            (string)($res['concrete_type'] ?? ''),
            (bool)($res['created'] ?? false),
            (bool)($res['assigned'] ?? true),
            null
        );
    }
}