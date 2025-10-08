<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * Persists type=file rules (fs paths/policies) and ensures assignment.
 */
final class FileIngestor implements PermissionIngestorInterface
{
    public function type(): string { return 'file'; }

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

        $natural = NaturalKeyBuilder::file($target, $actions);

        $attrs = [
            'base_dir'        => (string)($target['base_dir'] ?? ''),
            'paths'           => (array)($target['paths'] ?? []),
            'follow_symlinks' => (bool)($target['follow_symlinks'] ?? false),

            'read'   => in_array('read',   $actions, true),
            'write'  => in_array('write',  $actions, true),
            'append' => in_array('append', $actions, true),
            'delete' => in_array('delete', $actions, true),
            'mkdir'  => in_array('mkdir',  $actions, true),
            'rmdir'  => in_array('rmdir',  $actions, true),
            'list'   => in_array('list',   $actions, true),
        ];

        $meta = [
            'actions'       => $actions,
            'audit'         => $rule['audit'] ?? null,
            'conditions'    => $rule['conditions'] ?? null,
            'justification' => $rule['justification'] ?? null,
        ];

        $res = $repo->upsertForPlugin($pluginId, 'file', $natural, $attrs, $meta);

        return new RuleIngestResult(
            'file',
            $natural,
            (int)($res['concrete_id'] ?? 0),
            (string)($res['concrete_type'] ?? ''),
            (bool)($res['created'] ?? false),
            (bool)($res['assigned'] ?? true),
            null
        );
    }
}