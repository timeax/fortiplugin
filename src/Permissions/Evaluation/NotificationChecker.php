<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\NotifyRequest;

final readonly class NotificationChecker implements PermissionCheckerInterface
{
    public function __construct(
        private CapabilityCacheInterface     $cache,
        private ConditionsEvaluatorInterface $conditions
    ) {}

    public function type(): string { return 'notification'; }

    public function check(int $pluginId, PermissionRequestInterface $request, array $context = []): array
    {
        if (!$request instanceof NotifyRequest) {
            return $this->deny('bad_request_type');
        }

        $caps = $this->cache->get($pluginId);
        if (!$caps || !isset($caps['notification'])) {
            return $this->deny('no_capabilities');
        }

        $action   = $request->action;
        $channel  = $request->channel;
        $template = $request->template;
        $recipient= $request->recipient;

        foreach ($caps['notification'] as $e) {
            if (!($e['active'] ?? true)) continue;
            $row = $e['row'] ?? null;
            if (!$row || $action !== 'send' || empty($row['send'])) continue;
            if (!$this->conditionsOk($e['constraints'] ?? null, $context)) continue;

            if (!in_array($channel, (array)($row['channels'] ?? []), true)) continue;

            if ($template !== null) {
                $ts = (array)($row['templates'] ?? []);
                if ($ts && !in_array($template, $ts, true)) continue;
            }
            if ($recipient !== null) {
                $rs = (array)($row['recipients'] ?? []);
                if ($rs && !in_array($recipient, $rs, true)) continue;
            }

            return $this->allow($e['id'], ['channel' => $channel, 'template' => $template, 'recipient' => $recipient]);
        }

        return $this->deny('no_match');
    }

    private function conditionsOk(?array $conds, array $ctx): bool
    {
        return !$conds || $this->conditions->matches($conds, $ctx);
    }

    private function allow(int $id, array $ctx = []): array
    {
        return ['allowed' => true, 'reason' => null, 'matched' => ['type' => 'notification', 'id' => $id], 'context' => $ctx ?: null];
    }
    private function deny(string $reason): array
    {
        $ctx = [];
        return ['allowed' => false, 'reason' => $reason, 'matched' => null, 'context' => $ctx ?: null];
    }
}