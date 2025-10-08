<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use JsonException;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

/**
 * Persists type=network rules (egress allowlist) and ensures assignment.
 * Natural key is your stable rule_key.
 */
final class NetworkIngestor implements PermissionIngestorInterface
{
    public function type(): string
    {
        return 'network';
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

        $natural = NaturalKeyBuilder::network($target, $actions); // deterministic rule_key

        $attrs = [
            'natural_key' => $natural,
            'access' => in_array('request', $actions, true),
            'hosts' => (array)($target['hosts'] ?? []),
            'methods' => array_values(array_map('strtoupper', (array)($target['methods'] ?? []))),
            'schemes' => (array)($target['schemes'] ?? ['https']),
            'ports' => (array)($target['ports'] ?? []),
            'paths' => (array)($target['paths'] ?? []),
            'headers_allowed' => (array)($target['headers_allowed'] ?? []),
            'ips_allowed' => (array)($target['ips_allowed'] ?? []),
            'auth_via_host_secret' => (bool)($target['auth_via_host_secret'] ?? true),
        ];

        $meta = [
            'actions' => $actions,
            'audit' => $rule['audit'] ?? null,
            'conditions' => $rule['conditions'] ?? null,
            'justification' => $rule['justification'] ?? null,
        ];

        $res = $repo->upsertForPlugin($pluginId, 'network', $natural, $attrs, $meta);

        return new RuleIngestResult(
            'network',
            $natural,
            (int)($res['concrete_id'] ?? 0),
            (string)($res['concrete_type'] ?? ''),
            (bool)($res['created'] ?? false),
            (bool)($res['assigned'] ?? true),
            null
        );
    }
}