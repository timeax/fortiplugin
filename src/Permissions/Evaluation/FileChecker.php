<?php /** @noinspection ALL */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\ConditionsEvaluatorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionCheckerInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\FileRequest;
use Timeax\FortiPlugin\Permissions\Matchers\PathMatcher;

final readonly class FileChecker implements PermissionCheckerInterface
{
    public function __construct(
        private CapabilityCacheInterface     $cache,
        private ConditionsEvaluatorInterface $conditions,
        private PathMatcher                  $paths
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
        $candidate = $request->path;

        foreach ($caps['file'] as $e) {
            if (!($e['active'] ?? true)) continue;
            $row = $e['row'] ?? null;
            if (!$row) continue;
            if (!$this->conditionsOk($e['constraints'] ?? null, $context)) continue;

            // 1) action enabled?
            if (!$this->actionEnabled($row, $action)) continue;

            // 2) sandbox & pattern checks
            $base      = (string)($row['base_dir'] ?? '');
            $patterns  = isset($row['paths']) && is_array($row['paths']) ? $row['paths'] : [];
            $follow    = (bool)($row['follow_symlinks'] ?? false);

            $verdict = $this->paths->match($base, $candidate, $patterns, $follow);
            if ($verdict['ok']) {
                return $this->allow(
                    $e['id'],
                    [
                        'action'     => $action,
                        'normalized' => $verdict['normalized'] ?? null,
                        'matched'    => $verdict['matched'] ?? null,
                    ]
                );
            }
        }

        return $this->deny('no_match', ['path' => $candidate]);
    }

    private function actionEnabled(array $row, string $action): bool
    {
        if (isset($row['permissions']) && is_array($row['permissions'])) {
            return !empty($row['permissions'][$action]);
        }
        return !empty($row[$action]);
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