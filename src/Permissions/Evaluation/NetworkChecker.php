<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\NetworkRequest;

final readonly class NetworkChecker implements PermissionCheckerInterface
{
    public function __construct(
        private CapabilityCacheInterface     $cache,
        private ConditionsEvaluatorInterface $conditions
    ) {}

    public function type(): string { return 'network'; }

    public function check(int $pluginId, PermissionRequestInterface $request, array $context = []): array
    {
        if (!$request instanceof NetworkRequest) {
            return $this->deny('bad_request_type');
        }

        $caps = $this->cache->get($pluginId);
        if (!$caps || !isset($caps['network'])) {
            return $this->deny('no_capabilities');
        }

        $method = strtoupper($request->method);
        $url    = $request->url;

        $parts  = parse_url($url) ?: [];
        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        $host   = strtolower((string)($parts['host'] ?? ''));
        $port   = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $path   = (string)($parts['path'] ?? '/');

        foreach ($caps['network'] as $e) {
            if (!($e['active'] ?? true)) continue;
            $row = $e['row'] ?? null;
            if (!$row || empty($row['access'])) continue;
            if (!$this->conditionsOk($e['constraints'] ?? null, $context)) continue;

            if (!$this->matchList($method, (array)($row['methods'] ?? []), true)) continue;
            if (!$this->matchList($scheme, (array)($row['schemes'] ?? ['https']), false)) continue;
            if (!$this->matchHost($host, (array)($row['hosts'] ?? []))) continue;
            if (!$this->matchPort($port, (array)($row['ports'] ?? []), $scheme)) continue;
            if (!$this->matchPath($path, (array)($row['paths'] ?? []))) continue;

            return $this->allow($e['id'], [
                'method' => $method, 'scheme' => $scheme, 'host' => $host, 'port' => $port, 'path' => $path
            ]);
        }

        return $this->deny('no_match', ['url' => $url]);
    }

    private function matchList(string $value, array $allowed, bool $upper): bool
    {
        if ($allowed === []) return true;
        $value = $upper ? strtoupper($value) : strtolower($value);
        foreach ($allowed as $a) {
            $cmp = $upper ? strtoupper((string)$a) : strtolower((string)$a);
            if ($cmp === $value) return true;
        }
        return false;
    }
    private function matchHost(string $host, array $patterns): bool
    {
        if ($patterns === []) return true;
        foreach ($patterns as $p) {
            $p = strtolower((string)$p);
            if (str_starts_with($p, '*.')) {
                $suffix = substr($p, 1); // ".example.com"
                if (str_ends_with($host, $suffix) && substr_count($host, '.') >= substr_count($suffix, '.')) {
                    return true;
                }
            } else if ($host === $p) return true;
        }
        return false;
    }
    private function matchPort(int $port, array $ports, string $scheme): bool
    {
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($ports === [] || $ports === null) {
            $default = $scheme === 'https' ? 443 : 80;
            return $port === $default;
        }
        foreach ($ports as $p) {
            if ((int)$p === $port) return true;
        }
        return false;
    }
    private function matchPath(string $path, array $prefixes): bool
    {
        if ($prefixes === []) return true;
        foreach ($prefixes as $pre) {
            $pre = (string)$pre;
            if ($pre === '' || $pre === '/') return true;
            if (str_starts_with($path, $pre)) return true;
        }
        return false;
    }

    private function conditionsOk(?array $conds, array $ctx): bool
    {
        return !$conds || $this->conditions->matches($conds, $ctx);
    }

    private function allow(int $id, array $ctx = []): array
    {
        return ['allowed' => true, 'reason' => null, 'matched' => ['type' => 'network', 'id' => $id], 'context' => $ctx ?: null];
    }
    private function deny(string $reason, array $ctx = []): array
    {
        return ['allowed' => false, 'reason' => $reason, 'matched' => null, 'context' => $ctx ?: null];
    }
}