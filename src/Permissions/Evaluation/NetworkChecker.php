<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\NetworkRequest;
use Timeax\FortiPlugin\Permissions\Matchers\HostMatcher;

final readonly class NetworkChecker implements PermissionCheckerInterface
{
    public function __construct(
        private CapabilityCacheInterface     $cache,
        private ConditionsEvaluatorInterface $conditions,
        private HostMatcher                  $hostMatcher
    )
    {
    }

    public function type(): string
    {
        return 'network';
    }

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
        $url = $request->url;

        $parts = parse_url($url) ?: [];
        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        $host = strtolower((string)($parts['host'] ?? ''));
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $path = (string)($parts['path'] ?? '/');

        foreach ($caps['network'] as $e) {
            if (!($e['active'] ?? true)) continue;
            $row = $e['row'] ?? null;
            if (!$row || empty($row['access'])) continue;
            if (!$this->conditionsOk($e['constraints'] ?? null, $context)) continue;

            $ok = $this->hostMatcher->match(
                method: $method,
                scheme: $scheme,
                host: $host,
                port: $port,
                path: $path,
                methods: (array)($row['methods'] ?? []),
                schemes: $row['schemes'] ?? null,
                ports: $row['ports'] ?? null,
                paths: $row['paths'] ?? null,
                hosts: $row['hosts'] ?? null,
            );

            if ($ok) {
                return $this->allow($e['id'], [
                    'method' => $method, 'scheme' => $scheme, 'host' => $host, 'port' => $port, 'path' => $path
                ]);
            }
        }

        return $this->deny('no_match', ['url' => $url]);
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