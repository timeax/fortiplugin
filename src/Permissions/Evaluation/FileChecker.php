<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\FileRequest;

final readonly class FileChecker implements PermissionCheckerInterface
{
    public function __construct(
        private CapabilityCacheInterface     $cache,
        private ConditionsEvaluatorInterface $conditions
    ) {}

    public function type(): string { return 'file'; }

    public function check(int $pluginId, PermissionRequestInterface $request, array $context = []): array
    {
        if (!$request instanceof FileRequest) {
            return $this->deny('bad_request_type');
        }

        $caps = $this->cache->get($pluginId);
        if (!$caps || !isset($caps['file'])) {
            return $this->deny('no_capabilities');
        }

        $action = $request->action;
        $path   = $request->path;

        foreach ($caps['file'] as $e) {
            if (!($e['active'] ?? true)) continue;
            $row = $e['row'] ?? null;
            if (!$row || empty($row[$action])) continue;
            if (!$this->conditionsOk($e['constraints'] ?? null, $context)) continue;

            $base    = rtrim(str_replace('\\', '/', (string)($row['base_dir'] ?? '')), '/');
            $allowed = isset($row['paths']) && is_array($row['paths']) ? $row['paths'] : [];

            if ($this->isPathAllowed($base, $allowed, $path)) {
                return $this->allow($e['id'], ['action' => $action, 'path' => $path, 'base' => $base]);
            }
        }

        return $this->deny('no_match', ['path' => $path]);
    }

    private function isPathAllowed(string $base, array $allowedPatterns, string $candidate): bool
    {
        $cand = str_replace('\\','/', $candidate);
        foreach ($allowedPatterns as $pat) {
            $pre = ltrim(str_replace('\\','/', (string)$pat), '/');
            $prefix = $base !== '' ? "{$base}/{$pre}" : $pre;
            if ($prefix === '' || $prefix === '/') return true;
            if (str_starts_with($cand, $prefix)) return true;
        }
        return false;
    }

    private function conditionsOk(?array $conds, array $ctx): bool
    {
        return !$conds || $this->conditions->matches($conds, $ctx);
    }

    private function allow(int $id, array $ctx = []): array
    {
        return ['allowed' => true, 'reason' => null, 'matched' => ['type' => 'file', 'id' => $id], 'context' => $ctx ?: null];
    }
    private function deny(string $reason, array $ctx = []): array
    {
        return ['allowed' => false, 'reason' => $reason, 'matched' => null, 'context' => $ctx ?: null];
    }
}