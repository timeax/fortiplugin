<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\CodecRequest;

final readonly class CodecChecker implements PermissionCheckerInterface
{
    public function __construct(
        private CapabilityCacheInterface     $cache,
        private ConditionsEvaluatorInterface $conditions
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

        $method = $request->method;
        $class = is_array($request->options ?? null) ? ($request->options['class'] ?? null) : null;

        foreach ($caps['codec'] as $e) {
            if (!($e['active'] ?? true)) continue;
            $row = $e['row'] ?? null;
            if (!$row || empty($row['invoke'])) continue;
            if (!$this->conditionsOk($e['constraints'] ?? null, $context)) continue;

            $resolved = $row['resolved_methods'] ?? $row['methods'] ?? [];
            if ($resolved === '*') {
                if ($this->unserializeCheck($method, $class, $row)) continue;
                return $this->allow($e['id'], ['method' => $method]);
            }

            $list = is_array($resolved) ? $resolved : [];
            if (!in_array($method, $list, true)) continue;

            if ($this->unserializeCheck($method, $class, $row)) continue;

            return $this->allow($e['id'], ['method' => $method]);
        }

        return $this->deny('no_match', ['method' => $method]);
    }

    public function unserializeCheck($method, $class, $row): bool
    {
        if ($method === 'unserialize' && $class !== null) {
            $allowed = (($row['options']['allow_unserialize_classes'] ?? []) ?: []);
            return ($allowed && !in_array((string)$class, $allowed, true));
        }

        return false;
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