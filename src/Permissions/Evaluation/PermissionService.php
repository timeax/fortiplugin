<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Throwable;
use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Cache\KeyBuilder;
use Timeax\FortiPlugin\Permissions\Contracts\AuditEmitterInterface;
use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\{
    Result,
    MorphRef,
    DbRequest,
    FileRequest,
    NotifyRequest,
    ModuleRequest,
    NetworkRequest,
    CodecRequest,
    RouteWriteRequest
};
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\IngestSummary;
use Timeax\FortiPlugin\Permissions\Ingestion\PermissionIngestor;
use Timeax\FortiPlugin\Permissions\Manifest\ManifestNormalizer;
use Timeax\FortiPlugin\Permissions\Manifest\ManifestValidator;
use Timeax\FortiPlugin\Permissions\Policy\TimeWindowEvaluator;
use Timeax\FortiPlugin\Permissions\Registry\PermissionRegistry;

final readonly class PermissionService implements PermissionServiceInterface
{
    use PermissionServiceUpsertTrait, PermissionServiceListTrait;

    public function __construct(
        private PermissionRepositoryInterface $repo,
        private CapabilityCacheInterface      $cache,
        private PermissionIngestor            $ingestor,
        private PermissionRegistry            $registry,
        private TimeWindowEvaluator           $windowEval,
        private AuditEmitterInterface         $audit
    )
    {
    }

    /* ----------------------- ingestion/cache ----------------------- */

    /**
     * @throws JsonException
     */
    public function ingestManifest(int $pluginId, array $manifest): IngestSummary
    {
        $summary = $this->ingestor->ingest($pluginId, ManifestNormalizer::normalize($manifest));
        $this->invalidateCache($pluginId);
        $this->warmCache($pluginId);
        return $summary;
    }

    /**
     * @throws JsonException
     */
    public function warmCache(int $pluginId): void
    {
        $caps = $this->compileCapabilities($pluginId);
        $etag = KeyBuilder::fromCapabilities($caps);
        $this->cache->put($pluginId, $caps, null, $etag);
    }

    public function invalidateCache(int $pluginId): void
    {
        $this->cache->invalidate($pluginId);
    }

    /* ----------------------- typed convenience --------------------- */

    /** @throws JsonException */
    public function canDb(int $pluginId, string $action, array $target, ?array $context = []): Result
    {
        $this->ensureWarm($pluginId);
        $req = new DbRequest(
            action: $action,
            modelAliasOrFqcn: $target['model'] ?? null,
            table: $target['table'] ?? null,
            columns: isset($target['columns']) && is_array($target['columns']) ? $target['columns'] : null
        );
        return Result::fromArray($this->dispatch('db', $pluginId, $req, $context));
    }

    /** @throws JsonException */
    public function canFile(int $pluginId, string $action, array $target, ?array $context = []): Result
    {
        $this->ensureWarm($pluginId);
        $req = new FileRequest($action, (string)($target['baseDir'] ?? ''), (string)($target['path'] ?? ''));
        return Result::fromArray($this->dispatch('file', $pluginId, $req, $context));
    }

    /** @throws JsonException */
    public function canNotify(int $pluginId, string $action, array $target, ?array $context = []): Result
    {
        $this->ensureWarm($pluginId);
        $req = new NotifyRequest(
            action: $action,
            channel: (string)($target['channel'] ?? ''),
            template: isset($target['template']) ? (string)$target['template'] : null,
            recipient: isset($target['recipient']) ? (string)$target['recipient'] : null
        );
        return Result::fromArray($this->dispatch('notification', $pluginId, $req, $context));
    }

    /** @throws JsonException */
    public function canModule(int $pluginId, array $target, ?array $context = []): Result
    {
        $this->ensureWarm($pluginId);
        $req = new ModuleRequest(
            module: (string)($target['module'] ?? ''),
            api: (string)($target['api'] ?? '')
        );
        return Result::fromArray($this->dispatch('module', $pluginId, $req, $context));
    }

    /** @throws JsonException */
    public function canNetwork(int $pluginId, array $target, ?array $context = []): Result
    {
        $this->ensureWarm($pluginId);
        $req = new NetworkRequest(
            method: strtoupper((string)($target['method'] ?? 'GET')),
            url: (string)($target['url'] ?? ''),
            headers: isset($target['headers']) && is_array($target['headers']) ? $target['headers'] : null
        );
        return Result::fromArray($this->dispatch('network', $pluginId, $req, $context));
    }

    /** @throws JsonException */
    public function canCodec(int $pluginId, array $target, ?array $context = []): Result
    {
        $this->ensureWarm($pluginId);
        $req = new CodecRequest(
            method: (string)($target['method'] ?? ''),
            options: isset($target['options']) && is_array($target['options']) ? $target['options'] : null
        );
        return Result::fromArray($this->dispatch('codec', $pluginId, $req, $context));
    }

    /** @throws JsonException */
    public function canRouteWrite(int $pluginId, array $target, ?array $context = []): Result
    {
        $this->ensureWarm($pluginId);
        $req = new RouteWriteRequest(
            routeId: (string)($target['routeId'] ?? ''),
            guard: isset($target['guard']) ? (string)$target['guard'] : null
        );
        return Result::fromArray($this->dispatch('route', $pluginId, $req, $context));
    }

    /* ----------------------- generic DTO entry --------------------- */

    /** @throws JsonException */
    public function can(int $pluginId, PermissionRequestInterface $request, ?array $context = []): Result
    {
        $this->ensureWarm($pluginId);

        $type = $this->detectType($request);
        if ($type === null) {
            return Result::deny('unknown_request_type');
        }

        $raw = $this->dispatch($type, $pluginId, $request, $context);

        $matched = null;
        if (isset($raw['matched']['type'], $raw['matched']['id'])) {
            $matched = new MorphRef((string)$raw['matched']['type'], (int)$raw['matched']['id']);
        }

        return new Result(
            allowed: (bool)($raw['allowed'] ?? false),
            reason: $raw['reason'] ?? null,
            matched: $matched,
            context: $raw['context'] ?? null
        );
    }

    public function validateManifest(array $manifest): array
    {
        return ManifestValidator::tryValidate($manifest);
    }

    /* ----------------------------- internals ----------------------- */

    private function dispatch(string $type, int $pluginId, PermissionRequestInterface $request, array $context): array
    {
        try {
            $checker = $this->registry->checkerFor($type);
        } catch (Throwable) {
            $result = $this->deny(['type' => $type]);
            // no matched assignment → no manifest-driven redactions
            $this->audit->record('check', $type, $pluginId, $request->toArray(), $result, [
                'redact_fields' => [],
                'tags' => ['runtime', 'checker_missing'],
            ]);
            return $result;
        }

        $result = $checker->check($pluginId, $request, $context);

        // Pull redact_fields/tags from the matched assignment’s audit metadata (manifest-driven).
        $options = $this->auditOptionsForMatched($pluginId, $type, $result);

        $this->audit->record('check', $type, $pluginId, $request->toArray(), $result, $options);

        return $result;
    }

    /**
     * Derives ['redact_fields'=>string[], 'tags'=>string[]|null] from the matched capability
     * in the warmed cache. If nothing matches, returns empty defaults.
     */
    private function auditOptionsForMatched(int $pluginId, string $type, array $decision): array
    {
        $redact = [];
        $tags = null;

        $match = $decision['matched'] ?? null;
        if (!is_array($match) || !isset($match['id'])) {
            return ['redact_fields' => $redact, 'tags' => $tags];
        }

        $caps = $this->cache->get($pluginId);
        if (!is_array($caps) || !isset($caps[$type]) || !is_array($caps[$type])) {
            return ['redact_fields' => $redact, 'tags' => $tags];
        }

        $wantedId = (int)$match['id'];

        // Find the capability entry that granted/denied the check.
        foreach ($caps[$type] as $entry) {
            if ((int)($entry['id'] ?? 0) !== $wantedId) {
                continue;
            }
            $audit = $entry['audit'] ?? null;
            if (is_array($audit)) {
                if (!empty($audit['redact_fields']) && is_array($audit['redact_fields'])) {
                    // ensure strings, unique, lower-cased paths for the Redactor
                    $redact = array_values(array_unique(array_map(
                        static fn($s) => strtolower((string)$s),
                        $audit['redact_fields']
                    )));
                }
                if (!empty($audit['tags']) && is_array($audit['tags'])) {
                    $tags = array_values(array_unique(array_map('strval', $audit['tags'])));
                }
            }
            break;
        }

        return ['redact_fields' => $redact, 'tags' => $tags];
    }

    private function detectType(PermissionRequestInterface $request): ?string
    {
        return match (true) {
            $request instanceof DbRequest => 'db',
            $request instanceof FileRequest => 'file',
            $request instanceof NotifyRequest => 'notification',
            $request instanceof ModuleRequest => 'module',
            $request instanceof NetworkRequest => 'network',
            $request instanceof CodecRequest => 'codec',
            $request instanceof RouteWriteRequest => 'route',
            default => null,
        };
    }

    /** @throws JsonException */
    private function ensureWarm(int $pluginId): void
    {
        if ($this->cache->get($pluginId) === null) {
            $this->warmCache($pluginId);
        }
    }

    /**
     * Compile capabilities with:
     *  - precedence: direct > via tag
     *  - active flag respected
     *  - time window filtering via TimeWindowEvaluator
     *  - bulk hydration of concretes
     *
     * Returns:
     * [
     *   'db'   => [ ['id'=>..,'row'=>..,'constraints'=>..,'audit'=>..,'active'=>true], ... ],
     *   'file' => [ ... ],
     *   ...
     * ]
     */
    private function compileCapabilities(int $pluginId): array
    {
        $direct = $this->repo->getDirectMorphs($pluginId);
        $viaTag = $this->repo->getTagMorphs($pluginId);

        // precedence + de-dupe by (type:id)
        $merged = [];
        $put = static function (array $row, string $source) use (&$merged): void {
            $type = (string)($row['type'] ?? '');
            $id = (int)($row['id'] ?? 0);
            if ($type === '' || $id <= 0) return;

            $k = $type . ':' . $id;
            if (isset($merged[$k]) && $merged[$k]['source'] === 'direct' && $source !== 'direct') {
                return; // direct wins
            }
            $row['source'] = $source;
            $merged[$k] = $row;
        };
        foreach ($direct as $row) $put($row, 'direct');
        foreach ($viaTag as $row) $put($row, 'tag');

        if ($merged === []) return [];

        // -------- NEW: pre-filter by window & deactivate direct if expired --------
        $filtered = [];
        foreach ($merged as $a) {
            if (!($a['active'] ?? true)) {
                continue;
            }

            $t = (string)$a['type'];
            $startedAt = $this->parseWhen($a['started_at'] ?? $a['created_at'] ?? null);
            $window = $a['window'] ?? null;
            $isDirect = ($a['source'] ?? '') === 'direct';

            if ($isDirect) {
                // direct: check and (if expired) deactivate + skip
                if (!$this->isDirectAssignmentUsableOrDeactivate($pluginId, $t, $a, $startedAt)) {
                    continue;
                }
            } else if ($window && ($window['limited'] ?? false) && !$this->windowEval->isActive(is_array($window) ? $window : null, $startedAt)) {
                continue; // not usable right now
            }

            $filtered[] = $a;
        }
        // -------------------------------------------------------------------------

        // group target ids by type (only the filtered ones)
        $idsByType = [];
        $assignByType = [];
        foreach ($filtered as $a) {
            $t = (string)$a['type'];
            $id = (int)$a['id'];
            $idsByType[$t][] = $id;
            $assignByType[$t][] = $a;
        }
        foreach ($idsByType as &$list) {
            $list = array_values(array_unique($list));
        }
        unset($list);

        // fetch concretes
        $concreteByType = [];
        foreach ($idsByType as $t => $ids) {
            $concreteByType[$t] = $this->repo->fetchConcreteByType($t, $ids); // id => row
        }

        // assemble
        $caps = [];
        foreach ($assignByType as $t => $rows) {
            $entries = [];
            foreach ($rows as $a) {
                $id = (int)$a['id'];
                $row = $concreteByType[$t][$id] ?? null;
                if ($row === null) continue;

                $entries[] = [
                    'id' => $id,
                    'row' => $row,
                    'constraints' => $a['constraints'] ?? null,
                    'audit' => $a['audit'] ?? null,
                    'active' => true,
                ];
            }

            usort($entries, static fn($A, $B) => ($A['id'] <=> $B['id']));
            if ($entries) {
                $caps[$t] = $entries;
            }
        }

        return $caps;
    }

    private function parseWhen(mixed $v): ?DateTimeInterface
    {
        if (!is_string($v) || $v === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($v);
        } catch (Throwable) {
            return null;
        }
    }

    private function deny(array $ctx = []): array
    {
        return [
            'allowed' => false,
            'reason' => 'checker_unavailable',
            'matched' => null,
            'context' => $ctx ?: null
        ];
    }

    /**
     * Returns true if this direct assignment should be kept; otherwise attempts
     * to deactivate it (idempotent) and returns false.
     */
    private function isDirectAssignmentUsableOrDeactivate(
        int                $pluginId,
        string             $typeValue,       // 'db' | 'file' | ...
        array              $assignment,       // row from repo
        ?DateTimeInterface $startedAt = null
    ): bool
    {
        // hard inactive? skip.
        if (!($assignment['active'] ?? true)) {
            return false;
        }

        // no window? keep.
        $window = $assignment['window'] ?? null;
        if (!$window || !($window['limited'] ?? false)) {
            return true;
        }

        // still active within window? keep.
        if ($this->windowEval->isActive(is_array($window) ? $window : null, $startedAt)) {
            return true;
        }

        // expired → deactivate (idempotent) + audit, then drop from caps.
        try {
            $typeEnum = PermissionType::from($typeValue);
            $permissionId = (int)($assignment['id'] ?? 0);

            $changed = $this->repo->deactivatePluginPermission($pluginId, $typeEnum, $permissionId);

            $this->audit->record(
                'check',
                $typeValue,
                $pluginId,
                [
                    'action' => 'auto_deactivate_on_expiry',
                    'morph' => ['type' => $typeValue, 'id' => $permissionId],
                    'window' => $window,
                ],
                [
                    'allowed' => false,
                    'reason' => 'expired',
                    'matched' => ['type' => $typeValue, 'id' => $permissionId],
                    'context' => ['deactivated' => $changed],
                ],
                ['tags' => ['expiry', 'auto-deactivate']]
            );
        } catch (Throwable $e) {
            $this->audit->record(
                'check',
                $typeValue,
                $pluginId,
                [
                    'action' => 'auto_deactivate_on_expiry',
                    'morph' => ['type' => $typeValue, 'id' => (int)($assignment['id'] ?? 0)],
                    'window' => $window,
                ],
                [
                    'allowed' => false,
                    'reason' => 'expired_deactivation_failed',
                    'matched' => null,
                    'context' => ['error' => $e->getMessage()],
                ],
                ['tags' => ['expiry', 'auto-deactivate', 'error']]
            );
        }

        return false;
    }

    protected function repo(): PermissionRepositoryInterface
    {
        return $this->repo;
    }

    protected function cache(): CapabilityCacheInterface
    {
        return $this->cache;
    }
}