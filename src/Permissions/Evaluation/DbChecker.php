<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\DbRequest;

final readonly class DbChecker implements PermissionCheckerInterface
{
    public function __construct(
        private CapabilityCacheInterface     $cache,
        private ConditionsEvaluatorInterface $conditions
    ) {}

    public function type(): string { return 'db'; }

    public function check(int $pluginId, PermissionRequestInterface $request, array $context = []): array
    {
        if (!$request instanceof DbRequest) {
            return $this->deny('bad_request_type');
        }

        $caps = $this->cache->get($pluginId);
        if (!$caps || !isset($caps['db'])) {
            return $this->deny('no_capabilities');
        }

        $action  = $request->action;
        $wantMod = $request->modelAliasOrFqcn;
        $wantTab = $request->table;
        $wantCol = $request->columns ? array_values(array_unique(array_map('strval', $request->columns))) : null;

        foreach ($caps['db'] as $e) {
            if (!($e['active'] ?? true)) continue;
            $row = $e['row'] ?? null;
            if (!$row || empty($row[$action])) continue;
            if (!$this->conditionsOk($e['constraints'] ?? null, $context)) continue;

            if ($wantMod && isset($row['model']) && $row['model'] !== $wantMod) continue;
            if ($wantTab && isset($row['table']) && $row['table'] !== $wantTab) continue;

            if ($wantCol && isset($row['columns']) && is_array($row['columns']) && $row['columns'] !== []) {
                $allowed = array_values(array_unique(array_map('strval', $row['columns'])));
                if (array_diff($wantCol, $allowed)) continue;
            }

            return $this->allow($e['id'], ['action' => $action, 'model' => $wantMod, 'table' => $wantTab]);
        }

        return $this->deny('no_match');
    }

    private function conditionsOk(?array $conds, array $ctx): bool
    {
        return !$conds || $this->conditions->matches($conds, $ctx);
    }

    private function allow(int $id, array $ctx = []): array
    {
        return ['allowed' => true, 'reason' => null, 'matched' => ['type' => 'db', 'id' => $id], 'context' => $ctx ?: null];
    }
    private function deny(string $reason): array
    {
        $ctx = [];
        return ['allowed' => false, 'reason' => $reason, 'matched' => null, 'context' => $ctx ?: null];
    }
}