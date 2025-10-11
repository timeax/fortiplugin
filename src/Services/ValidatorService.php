<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpUnused */
/** @noinspection PhpUnusedLocalVariableInspection */

declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;
use Timeax\FortiPlugin\Core\Security\ComposerScan;
use Timeax\FortiPlugin\Core\Security\ConfigValidator;
use Timeax\FortiPlugin\Core\Security\ContentValidator;
use Timeax\FortiPlugin\Core\Security\FileScanner;
use Timeax\FortiPlugin\Core\Security\HostConfigValidator;
use Timeax\FortiPlugin\Core\Security\PermissionManifestValidator;
use Timeax\FortiPlugin\Core\Security\PluginSecurityScanner;
use Timeax\FortiPlugin\Core\Security\RouteFileValidator;
use Timeax\FortiPlugin\Core\Security\RouteIdRegistry;
use Timeax\FortiPlugin\Core\Security\TokenUsageAnalyzer;

/**
 * ValidatorService — Orchestrates headline and scanner-driven validations with telemetry and no hard stops.
 *
 * Config keys (all optional):
 *   headline:
 *     composer_json: string|null               Path to composer.json (defaults to <root>/composer.json)
 *     forti_schema: string|null                Path to fortiplugin.json schema (if set, runs ConfigValidator)
 *     host_config: array|null                  Host config array for HostConfigValidator
 *     permission_manifest: string|array|null   Path to manifest.json or decoded array
 *     route_files: array<int,string>           List of JSON route files to validate for unique IDs
 *
 *   scan:
 *     token_list: array<int,string>            Forbidden tokens for TokenUsageAnalyzer (defaults from policy->getForbiddenFunctions())
 *
 *   fail_policy:
 *     types_blocklist: array<int,string>       If any issue type is in this set → fail
 *     severity_threshold: string|null          Not used by current validators but accepted for future
 *     total_error_limit: int|null              If total issues exceed → fail
 *     per_type_limits: array<string,int>       Map of type => max allowed before fail
 *     file_gates: array<int,string>            fnmatch globs; any issue whose file matches → fail
 */
final class ValidatorService
{
    private PluginPolicy $policy;
    private array $config;

    /** @var list<array{0:string,1:string,2:string|null}> */
    private array $log = [];

    /** Extended log items (optional richer fields) */
    private array $extended = [];

    /** Running counters per phase/validator key */
    private array $counters = [];

    /**
     * Last registered emit callback.
     * @var null|callable
     */
    private $emit;

    private array $stats = [
        'files_scanned' => 0,
        'total_errors' => 0,
    ];

    public function __construct(PluginPolicy $policy, array $config = [])
    {
        $this->policy = $policy;
        $this->config = $config;
    }

    public function run(string $root, ?callable $emit = null): array
    {
        $this->reset($emit);
        $root = rtrim($root, "\\/");

        $this->emitEvent('Initialize', 'Starting validation pipeline', null, null, null);

        // Headline phase
        $this->emitEvent('Headline', 'Starting headline validators', null, null, null);
        $this->runHeadline($root);
        $this->emitEvent('Headline', 'Completed headline validators', null, null, null);

        // Scanner phase
        $this->emitEvent('Scan', 'Starting file scan', null, null, null);
        $this->runScanner($root);
        $this->emitEvent('Scan', 'Completed file scan', null, null, null);

        // Finalize
        $summary = [
            'files_scanned' => $this->stats['files_scanned'],
            'total_issues' => $this->stats['total_errors'],
            'should_fail' => $this->shouldFail(),
            'log' => $this->log,
            'extended' => $this->extended,
            'formatted' => $this->getFormattedLog(),
        ];

        $this->emitEvent('Finalize', 'Validation complete', [
            'detail' => 'Summary',
            'count' => $this->stats['total_errors'],
        ], null, null);

        return $summary;
    }

    /** Canonical error tuple log accessor */
    public function getLog(): array
    {
        return $this->log;
    }

    /** Return human-friendly, formatted log entries using ErrorReaderService */
    public function getFormattedLog(): array
    {
        try {
            return (new ErrorReaderService())->formatMany($this->extended);
        } catch (Throwable $e) {
            // Never throw; degrade to minimal tuples with message
            $out = [];
            foreach ($this->extended as $raw) {
                if (is_array($raw)) {
                    $out[] = [
                        'slug' => (string)($raw['type'] ?? 'unknown_error'),
                        'name' => 'Issue',
                        'description' => (string)($raw['issue'] ?? ($raw['message'] ?? '')),
                        'severity' => 'high',
                        'file' => $raw['file'] ?? null,
                        'line' => $raw['line'] ?? null,
                        'column' => $raw['column'] ?? null,
                        'snippet' => $raw['snippet'] ?? null,
                        'extra' => $raw,
                    ];
                }
            }
            return $out;
        }
    }

    /** Compute shouldFail decision based on accumulated logs and config policy */
    public function shouldFail(): bool
    {
        $policy = (array)($this->config['fail_policy'] ?? []);
        $typesBlock = array_map('strval', (array)($policy['types_blocklist'] ?? []));
        $totalLimit = $policy['total_error_limit'] ?? null;
        $perTypeLimits = (array)($policy['per_type_limits'] ?? []);
        $fileGates = (array)($policy['file_gates'] ?? []);

        // Build counts per type
        $byType = [];
        foreach ($this->log as [$type, $_issue, $_file]) {
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            // Type blocklist
            if (in_array($type, $typesBlock, true)) {
                return true;
            }
        }

        // Total limit
        if (is_int($totalLimit) && $totalLimit >= 0 && count($this->log) > $totalLimit) {
            return true;
        }

        // Per type limits
        foreach ($perTypeLimits as $t => $limit) {
            if (is_int($limit) && $limit >= 0 && ($byType[$t] ?? 0) > $limit) {
                return true;
            }
        }

        // File gates
        if ($fileGates) {
            foreach ($this->log as [$type, $issue, $file]) {
                $file = (string)$file;
                foreach ($fileGates as $glob) {
                    if (is_string($glob) && $glob !== '' && fnmatch($glob, $file)) {
                        return true;
                    }
                }
            }
        }

        // Optional: severity threshold (not used yet as validators do not emit severities consistently)
        return false;
    }

    // ───────────────────────────── Internals ─────────────────────────────

    private function reset(?callable $emit): void
    {
        $this->log = [];
        $this->extended = [];
        $this->counters = [];
        $this->stats = ['files_scanned' => 0, 'total_errors' => 0];
        $this->emit = $emit;
    }

    private function runHeadline(string $root): void
    {
        // Composer
        try {
            $composerPath = $this->config['headline']['composer_json'] ?? ($root . DIRECTORY_SEPARATOR . 'composer.json');
            $scanner = new ComposerScan($this->policy);
            $violations = $scanner->scan($composerPath);
            foreach ($violations as $v) {
                $this->record('composer.' . ($v['type'] ?? 'violation'), (string)($v['issue'] ?? 'Composer violation'), (string)($v['file'] ?? $composerPath), $v);
                $this->emitEvent('Headline: Composer', $v['issue'] ?? 'Violation', $this->errorCounter('Headline: Composer', $v['issue'] ?? ''), (string)($v['file'] ?? $composerPath), null);
            }
        } catch (Throwable $e) {
            $this->record('composer.exception', $e->getMessage(), $root . DIRECTORY_SEPARATOR . 'composer.json', ['exception' => $e]);
            $this->emitEvent('Headline: Composer', 'Exception', $this->errorCounter('Headline: Composer', $e->getMessage()), null, null);
        }

        // Config schema (fortiplugin.json)
        $schema = $this->config['headline']['forti_schema'] ?? null;
        if (is_string($schema) && $schema !== '') {
            try {
                $cv = new ConfigValidator();
                $res = $cv->validate($root, $schema);
                if (($res['error'] ?? null) !== null) {
                    $details = (array)($res['details'] ?? []);
                    if (!$details) {
                        $this->record('config.schema', (string)$res['error'], $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', $res);
                        $this->emitEvent('Headline: Config', (string)$res['error'], $this->errorCounter('Headline: Config', (string)$res['error']), $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', null);
                    } else {
                        foreach ($details as $d) {
                            $msg = ($d['path'] ?? '') . ' ' . ($d['message'] ?? 'Schema error');
                            $this->record('config.schema', $msg, $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', $d);
                            $this->emitEvent('Headline: Config', $msg, $this->errorCounter('Headline: Config', $msg), $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', null);
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->record('config.exception', $e->getMessage(), $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', ['exception' => $e]);
                $this->emitEvent('Headline: Config', 'Exception', $this->errorCounter('Headline: Config', $e->getMessage()), null, null);
            }
        }

        // Host config (array provided by caller)
        $hostCfg = $this->config['headline']['host_config'] ?? null;
        if (is_array($hostCfg)) {
            try {
                HostConfigValidator::validate($hostCfg);
            } catch (Throwable $e) {
                $this->record('hostconfig.error', $e->getMessage(), '[host-config]', ['exception' => $e]);
                $this->emitEvent('Headline: HostConfig', $e->getMessage(), $this->errorCounter('Headline: HostConfig', $e->getMessage()), null, null);
            }
        }

        // Permission manifest (path or array)
        $perm = $this->config['headline']['permission_manifest'] ?? null;
        if ($perm !== null) {
            try {
                $pmv = new PermissionManifestValidator();
                // validate() throws on errors; we convert to log via catch
                if (is_string($perm)) {
                    $json = @file_get_contents($perm);
                    if ($json === false) {
                        throw new RuntimeException("Cannot read permission manifest: $perm");
                    }
                    $pmv->validate($json);
                } else {
                    $pmv->validate((array)$perm);
                }
            } catch (Throwable $e) {
                $this->record('manifest.invalid', $e->getMessage(), is_string($perm) ? $perm : '[manifest]', ['exception' => $e]);
                $this->emitEvent('Headline: Permission manifest', $e->getMessage(), $this->errorCounter('Headline: Permission manifest', $e->getMessage()), is_string($perm) ? $perm : null, null);
            }
        }

        // Route files (validate IDs + JSON structure)
        $routeFiles = (array)($this->config['headline']['route_files'] ?? []);
        if ($routeFiles) {
            $registry = new RouteIdRegistry();
            foreach ($routeFiles as $rf) {
                try {
                    RouteFileValidator::validateFile($rf, $registry);
                } catch (Throwable $e) {
                    $this->record('route.invalid', $e->getMessage(), (string)$rf, ['exception' => $e]);
                    $this->emitEvent('Headline: Route file', $e->getMessage(), $this->errorCounter('Headline: Route file', $e->getMessage()), (string)$rf, null);
                }
            }
        }
    }

    private function runScanner(string $root): void
    {
        $scanner = new FileScanner($this->policy);
        $contentValidator = new ContentValidator($this->policy);

        $emitProxy = function (array $e): void {
            // Bridge from FileScanner emit to requested emit schema
            $title = $e['title'] ?? 'Scan';
            $desc = $e['message'] ?? null;
            $file = $e['path'] ?? null;
            $this->emitEvent($title, $desc, null, is_string($file) ? $file : null, null);
        };

        $callback = function (string $file, array $meta = []) use ($contentValidator): array {
            $this->emitEvent('Scan: File', 'Start', null, $file, $this->safeFilesize($file));
            $issues = [];

            // ContentValidator (fast regex-like)
            try {
                $cv = $contentValidator->scanFile($file);
                foreach ($cv as $v) {
                    $issues[] = $v;
                }
            } catch (Throwable $e) {
                $issues[] = ['type' => 'content.exception', 'issue' => $e->getMessage(), 'file' => $file];
            }

            // TokenUsageAnalyzer (token_get_all based)
            try {
                $tokens = $this->config['scan']['token_list'] ?? null;
                if (!is_array($tokens) || !$tokens) {
                    $tokens = $this->policy->getForbiddenFunctions();
                }
                $tu = TokenUsageAnalyzer::analyzeFile($file, array_map('strtolower', $tokens));
                foreach ($tu as $v) {
                    $issues[] = $v;
                }
            } catch (Throwable $e) {
                $issues[] = ['type' => 'token.exception', 'issue' => $e->getMessage(), 'file' => $file];
            }

            // PluginSecurityScanner (AST)
            try {
                $src = @file_get_contents($file);
                if ($src !== false) {
                    $astScanner = new PluginSecurityScanner($this->policy->getConfig(), $file);
                    $astScanner->scanSource($src, $file);
                    foreach ($astScanner->getMatches() as $match) {
                        $issues[] = [
                            'type' => (string)($match['type'] ?? 'ast.violation'),
                            'issue' => (string)($match['message'] ?? ($match['data']['message'] ?? 'AST violation')),
                            'file' => $file,
                            'line' => $match['line'] ?? null,
                        ];
                    }
                }
            } catch (Throwable $e) {
                $issues[] = ['type' => 'ast.exception', 'issue' => $e->getMessage(), 'file' => $file];
            }

            // Log+emit
            foreach ($issues as $v) {
                $type = (string)($v['type'] ?? 'scan.issue');
                $issue = (string)($v['issue'] ?? ($v['message'] ?? 'Issue'));
                $this->record($type, $issue, (string)($v['file'] ?? $file), $v);
                $this->emitEvent('Scan: Security', $issue, $this->errorCounter('Scan: Security', $issue), $file, $this->safeFilesize($file));
            }

            $this->stats['files_scanned']++;
            $this->emitEvent('Scan: File', 'End', null, $file, $this->safeFilesize($file));

            return $issues; // return to allow FileScanner to collect, though we do our own logging
        };

        // Drive scanner
        try {
            $scanner->scan($root, $callback, $emitProxy);
        } catch (Throwable $e) {
            // Even FileScanner threw; log and continue finalize
            $this->record('scanner.exception', $e->getMessage(), $root, ['exception' => $e]);
            $this->emitEvent('Scan', 'Scanner exception', $this->errorCounter('Scan', $e->getMessage()), $root, null);
        }
    }

    private function record(string $type, string $issue, ?string $file, array $extended = []): void
    {
        $this->log[] = [$type, $issue, $file];
        $this->extended[] = $extended + ['type' => $type, 'issue' => $issue, 'file' => $file];
        $this->stats['total_errors']++;
    }

    private function emitEvent(string $title, ?string $description, ?array $error, ?string $filePath, ?int $size): void
    {
        if (!$this->emit) {
            return;
        }
        $payload = [
            'title' => $title,
            'description' => $description,
            'error' => $error,
            'stats' => [
                'filePath' => $filePath,
                'size' => $size,
            ],
        ];
        try {
            ($this->emit)($payload);
        } catch (Throwable $_) { /* never throw */
        }
    }

    private function errorCounter(string $counterKey, string $detail): array
    {
        $this->counters[$counterKey] = ($this->counters[$counterKey] ?? 0) + 1;
        return ['detail' => $detail, 'count' => $this->counters[$counterKey]];
    }

    private function safeFilesize(?string $file): ?int
    {
        if (!$file || !is_file($file)) return null;
        $s = @filesize($file);
        return $s === false ? null : $s;
    }
}
