<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * Persists type=codec (obfuscator) rules and ensures assignment.
 */
final class CodecIngestor implements PermissionIngestorInterface
{
    public function type(): string
    {
        return 'codec';
    }

    /**
     * @throws JsonException
     */
    public function ingest(
        int                           $pluginId,
        array                         $rule,
        PermissionRepositoryInterface $repo
    ): RuleIngestResult
    {
        $actions = (array)($rule['actions'] ?? []);

        $natural = NaturalKeyBuilder::codec($rule);

        $attrs = [
            'invoke' => in_array('invoke', $actions, true),
            'methods' => $rule['methods'] ?? null, // "*" or string[]
            'groups' => $rule['groups'] ?? null, // string[]
            'resolved_methods' => $rule['resolved_methods'] ?? null, // "*" or string[]
            'options' => $rule['options'] ?? null, // e.g. allow_unserialize_classes
        ];

        $meta = [
            'actions' => $actions,
            'audit' => $rule['audit'] ?? null,
            'conditions' => $rule['conditions'] ?? null,
            'justification' => $rule['justification'] ?? null,
        ];

        $res = $repo->upsertForPlugin($pluginId, 'codec', $natural, $attrs, $meta);

        return new RuleIngestResult(
            'codec',
            $natural,
            (int)($res['concrete_id'] ?? 0),
            (string)($res['concrete_type'] ?? ''),
            (bool)($res['created'] ?? false),
            (bool)($res['assigned'] ?? true),
            null
        );
    }
}