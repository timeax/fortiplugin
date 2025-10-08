<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * Persists type=notification rules (channels/templates/recipients)
 * and ensures assignment.
 */
final class NotificationIngestor implements PermissionIngestorInterface
{
    public function type(): string
    {
        return 'notification';
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
        $target = (array)($rule['target'] ?? []);
        $actions = (array)($rule['actions'] ?? []);

        $natural = NaturalKeyBuilder::notification($target, $actions);

        $attrs = [
            'channels' => (array)($target['channels'] ?? []),
            'templates' => (array)($target['templates'] ?? []),
            'recipients' => (array)($target['recipients'] ?? []),
            'send' => in_array('send', $actions, true),
        ];

        $meta = [
            'actions' => $actions,
            'audit' => $rule['audit'] ?? null,
            'conditions' => $rule['conditions'] ?? null,
            'justification' => $rule['justification'] ?? null,
        ];

        $res = $repo->upsertForPlugin($pluginId, 'notification', $natural, $attrs, $meta);

        return new RuleIngestResult(
            'notification',
            $natural,
            (int)($res['concrete_id'] ?? 0),
            (string)($res['concrete_type'] ?? ''),
            (bool)($res['created'] ?? false),
            (bool)($res['assigned'] ?? true),
            null
        );
    }
}