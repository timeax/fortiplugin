<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Lib\Obfuscator;
use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\CodecRequest;
use Timeax\FortiPlugin\Permissions\Matchers\CodecGuard;

final readonly class CodecChecker implements PermissionCheckerInterface
{
    public function __construct(
        private CapabilityCacheInterface     $cache,
        private ConditionsEvaluatorInterface $conditions,
        private CodecGuard                   $guard
    )
    {
    }

    public function type(): string
    {
        return 'codec';
    }

    public function check(int $pluginId, PermissionRequestInterface $request, array $context = []): array
    {
        if (!$request instanceof CodecRequest) {
            return $this->deny('bad_request_type');
        }

        $caps = $this->cache->get($pluginId);
        if (!$caps || !isset($caps['codec'])) {
            return $this->deny('no_capabilities');
        }

        $method = (string)$request->method;
        $class = is_array($request->options ?? null) ? ($request->options['class'] ?? null) : null;

        foreach ($caps['codec'] as $e) {
            if (!($e['active'] ?? true)) continue;
            $row = $e['row'] ?? null;
            if (!$row || empty($row['access'])) continue; // action == invoke
            if (!$this->conditionsOk($e['constraints'] ?? null, $context)) continue;

            $allowed = $row['allowed'] ?? null; // ['methods'=>..., 'groups'=>..., 'options'=>...]
            if (!$this->methodAllowed($method, $allowed)) continue;

            // Dangerous method guard
            if ($this->guard->requiresAllowList($method)) {
                $ver = $this->guard->validateConcreteFor($method, is_array($allowed) ? $allowed : null);
                if (!($ver['ok'] ?? false)) {
                    return $this->deny($ver['reason'] ?? 'unserialize_guard_failed');
                }
                if ($class !== null && $ver['allowed_classes'] && !$this->guard->classAllowed((string)$class, $ver['allowed_classes'])) {
                    return $this->deny('unserialize_class_not_allowed', ['class' => (string)$class]);
                }
            }

            return $this->allow($e['id'], ['method' => $method]);
        }

        return $this->deny('no_match', ['method' => $method]);
    }

    private function methodAllowed(string $method, mixed $allowed): bool
    {
        // No "allowed" block means nothing is allowed
        if (!is_array($allowed)) return false;

        // "*" -> allow all (guard still applied for unserialize)
        if (($allowed['methods'] ?? null) === '*') {
            return true;
        }

        // methods list
        if (isset($allowed['methods']) && is_array($allowed['methods']) && in_array($method, $allowed['methods'], true)) {
            return true;
        }

        // groups → resolve via Obfuscator
        if (isset($allowed['groups']) && is_array($allowed['groups']) && $allowed['groups'] !== []) {
            $groups = $this->resolvedGroupMethods($allowed['groups']);
            return in_array($method, $groups, true);
        }

        return false;
    }

    private function resolvedGroupMethods(array $groups): array
    {
        if (!class_exists(Obfuscator::class)
            || !method_exists(Obfuscator::class, 'availableGroups')) {
            return [];
        }

        // Build (and cache) group=>[methods...] once.
        static $groupToList = null;
        if ($groupToList === null) {
            $groupToList = [];
            $catalog = Obfuscator::availableGroups(); // group => [method => wrapper]
            foreach ($catalog as $group => $map) {
                if (is_array($map)) {
                    // store as a list (keys of the map)
                    $groupToList[(string)$group] = array_keys($map);
                }
            }
        }

        // Dedup groups and accumulate methods via a key-set.
        $methodsSet = [];
        foreach (array_unique(array_map('strval', $groups)) as $g) {
            $list = $groupToList[$g] ?? null;
            if (!$list) continue;
            foreach ($list as $m) {
                $methodsSet[$m] = true; // set add
            }
        }

        return array_keys($methodsSet); // stable insertion order
    }

    private function conditionsOk(?array $conds, array $ctx): bool
    {
        return !$conds || $this->conditions->matches($conds, $ctx);
    }

    private function allow(int $id, array $ctx = []): array
    {
        return ['allowed' => true, 'reason' => null, 'matched' => ['type' => 'codec', 'id' => $id], 'context' => $ctx ?: null];
    }

    private function deny(string $reason, array $ctx = []): array
    {
        return ['allowed' => false, 'reason' => $reason, 'matched' => null, 'context' => $ctx ?: null];
    }
}