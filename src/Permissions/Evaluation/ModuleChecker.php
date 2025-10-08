<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\ModuleRequest;

final readonly class ModuleChecker implements PermissionCheckerInterface
{
    public function __construct(
        private CapabilityCacheInterface     $cache,
        private ConditionsEvaluatorInterface $conditions
    ) {}

    public function type(): string { return 'module'; }

    public function check(int $pluginId, PermissionRequestInterface $request, array $context = []): array
    {
        if (!$request instanceof ModuleRequest) {
            return $this->deny('bad_request_type');
        }

        $caps = $this->cache->get($pluginId);
        if (!$caps || !isset($caps['module'])) {
            return $this->deny('no_capabilities');
        }

        $module = $request->module;
        $api    = $request->api;

        foreach ($caps['module'] as $e) {
            if (!($e['active'] ?? true)) continue;
            $row = $e['row'] ?? null;
            if (!$row || empty($row['call'])) continue;
            if (!$this->conditionsOk($e['constraints'] ?? null, $context)) continue;

            $decl  = (string)($row['plugin'] ?? '');
            $alias = (string)($row['plugin_alias'] ?? '');
            if (!($module === $decl || ($alias !== '' && $module === $alias))) continue;

            $apis = (array)($row['apis'] ?? []);
            if ($apis && !in_array($api, $apis, true)) continue;

            return $this->allow($e['id'], ['module' => $module, 'api' => $api]);
        }

        return $this->deny('no_match');
    }

    private function conditionsOk(?array $conds, array $ctx): bool
    {
        return !$conds || $this->conditions->matches($conds, $ctx);
    }

    private function allow(int $id, array $ctx = []): array
    {
        return ['allowed' => true, 'reason' => null, 'matched' => ['type' => 'module', 'id' => $id], 'context' => $ctx ?: null];
    }
    private function deny(string $reason): array
    {
        $ctx = [];
        return ['allowed' => false, 'reason' => $reason, 'matched' => null, 'context' => $ctx ?: null];
    }
}