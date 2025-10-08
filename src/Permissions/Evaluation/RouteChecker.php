<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\RouteWriteRequest;

final readonly class RouteChecker implements PermissionCheckerInterface
{
    public function __construct(private PermissionRepositoryInterface $repo)
    {
    }

    public function type(): string
    {
        return 'route';
    }

    public function check(int $pluginId, PermissionRequestInterface $request, array $context = []): array
    {
        if (!$request instanceof RouteWriteRequest) {
            return $this->deny('bad_request_type');
        }

        $row = $this->repo->routePermission($pluginId, $request->routeId);
        if (!$row) {
            return $this->deny('route_not_declared', ['routeId' => $request->routeId]);
        }
        if (($row['status'] ?? 'pending') !== 'approved') {
            return $this->deny('route_not_approved', ['routeId' => $request->routeId, 'status' => $row['status'] ?? 'pending']);
        }
        $locked = $row['guard'] ?? null;
        if ($locked && $request->guard && $locked !== $request->guard) {
            return $this->deny('guard_mismatch', ['routeId' => $request->routeId, 'locked' => $locked, 'requested' => $request->guard]);
        }

        return [
            'allowed' => true,
            'reason' => null,
            'matched' => ['type' => 'route', 'id' => 0],
            'context' => ['routeId' => $request->routeId, 'guard' => $request->guard, 'status' => 'approved'],
        ];
    }

    private function deny(string $reason, array $ctx = []): array
    {
        return ['allowed' => false, 'reason' => $reason, 'matched' => null, 'context' => $ctx ?: null];
    }
}