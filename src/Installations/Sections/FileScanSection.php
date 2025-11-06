<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use Random\RandomException;
use Timeax\FortiPlugin\Installations\DTO\DecisionResult;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Services\ValidatorService;
use function count;

/**
 * FileScanSection (no background scans)
 *
 * - Runs scanner-only phase and forwards validator emits verbatim.
 * - Persists raw scan log + a single decision (INSTALL | ASK | BREAK).
 * - ASK is produced *only* when scanner finds issues (shouldFail = true)
 *   and we want the host to explicitly override. In that case we issue an
 *   install-override token (bound to run_id) and let the host decide.
 *
 * NOTE: onValidationEnd() is **owned by ValidatorBridge** and called there once.
 */
final readonly class FileScanSection
{
    public function __construct(
        private InstallerPolicy       $policy,
        private InstallationLogStore  $log,
        private InstallerTokenManager $tokens,
        /** optional installer-level emitter: fn(array $payload): void */
        private mixed                 $emit = null
    )
    {
    }

    /**
     * @param string $pluginDir
     * @param int|string $zipId
     * @param ValidatorService $validator
     * @param string $validatorConfigHash Stable hash of validator config
     * @param string $actor Actor id or 'system'
     * @param string $runId Install run correlation id
     * @param callable|null $onFileScanError fn(array $summary, string $token): Install
     * @param callable|null $emitValidation fn(array $payload): void (verbatim passthrough)
     * @return array{decision: Install, meta: array}
     * @throws JsonException|RandomException
     */
    public function run(
        string           $pluginDir,
        int|string       $zipId,
        ValidatorService $validator,
        string           $validatorConfigHash,
        string           $actor,
        string           $runId,
        ?callable        $onFileScanError = null,
        ?callable        $emitValidation = null
    ): array
    {
        $this->emit && ($this->emit)([
            'title' => 'FILE_SCAN_START',
            'description' => 'Starting file scan',
            'meta' => ['zip_id' => (string)$zipId, 'run_id' => $runId],
        ]);

        $events = [];

        // Forward validator emits verbatim
        $forward = function (array $payload) use (&$events, $emitValidation): void {
            $events[] = $payload;
            if ($emitValidation) {
                $emitValidation($payload);
            }
        };

        // Run scanner only
        $validator->runFileScan($pluginDir, $forward);

        $shouldFail = $validator->shouldFail();
        $logTuples = $validator->getLog();
        $totalIssues = count($logTuples);

        $summaryArray = [
            'should_fail' => $shouldFail,
            'total_issues' => $totalIssues,
        ];

        // Persist raw file_scan block
        $this->log->writeSection('file_scan', [
            'summary' => $summaryArray,
            'events' => $events,
        ]);

        $nowIso = gmdate('c');
        $doc = $this->log->read();
        $fp = $doc['meta']['fingerprint'] ?? null;
        $fpStr = is_string($fp) ? $fp : '';
        $codes = $this->uniqueTypes($logTuples);
        $counts = ['validation_errors' => 0, 'scan_errors' => $totalIssues];
        $enabled = true;

        // Clean → INSTALL
        if (!$shouldFail) {
            $this->log->writeDecision(new DecisionResult(
                status: 'installed',
                at: $nowIso,
                run_id: $runId,
                zip_id: $zipId,
                fingerprint: $fpStr,
                validator_config_hash: $validatorConfigHash,
                file_scan_enabled: $enabled,
                token: null,
                reason: 'file_scan_ok',
                last_error_codes: $codes,
                counts: $counts
            ));

            $this->emitDecision('INSTALL', ['reason' => 'file_scan_ok', 'zip_id' => (string)$zipId]);

            return ['decision' => Install::INSTALL, 'meta' => []];
        }

        // Issues → host override (ASK) or policy BREAK
        $decisionStr = 'ask';
        $reason = 'file_scan_issues_detected';
        $tokenMeta = null;
        $tokenOpaque = null;

        if ($onFileScanError) {
            // Issue install-override token bound to run_id; host decides
            $ttl = $this->policy->getInstallOverrideTtl();
            $tokenOpaque = $this->tokens->issueInstallOverrideToken(
                zipId: $zipId,
                validatorConfigHash: $validatorConfigHash,
                actor: $actor,
                runId: $runId,
                ttlSeconds: $ttl
            );
            $tokenMeta = $this->tokens->summarize('install_override', time() + $ttl);

            $hostDecision = $onFileScanError($summaryArray, $tokenOpaque);
            $decisionStr = $this->mapInstallToDecisionStatus($hostDecision);
            $reason = 'host_decision_on_scan_errors';
        } elseif ($this->policy->shouldBreakOnFileScanErrors()) {
            $decisionStr = 'break';
            $reason = 'policy_break_on_scan_errors';
        }

        $this->log->writeDecision(new DecisionResult(
            status: $decisionStr,
            at: $nowIso,
            run_id: $runId,
            zip_id: $zipId,
            fingerprint: $fpStr,
            validator_config_hash: $validatorConfigHash,
            file_scan_enabled: $enabled,
            token: $tokenMeta,
            reason: $reason,
            last_error_codes: $codes,
            counts: $counts
        ));

        $this->emitDecision(strtoupper($decisionStr), [
            'reason' => $reason,
            'zip_id' => (string)$zipId,
            'token_summary' => $tokenMeta,
        ]);

        return [
            'decision' => $this->mapDecisionStatusToInstallEnum($decisionStr),
            'meta' => array_filter(['token' => $tokenOpaque, 'token_summary' => $tokenMeta]),
        ];
    }

    /** @return list<string> */
    private function uniqueTypes(array $logTuples): array
    {
        $types = [];
        foreach ($logTuples as $t) {
            $type = (string)($t[0] ?? '');
            if ($type !== '') $types[$type] = true;
        }
        return array_keys($types);
    }

    private function mapInstallToDecisionStatus(Install $d): string
    {
        return match ($d) {
            Install::INSTALL => 'installed',
            Install::ASK => 'ask',
            Install::BREAK => 'break',
        };
    }

    private function mapDecisionStatusToInstallEnum(string $s): Install
    {
        return match ($s) {
            'installed' => Install::INSTALL,
            'break' => Install::BREAK,
            default => Install::ASK,
        };
    }

    /** neutral scan-level decision event (Installer handles high-level events) */
    private function emitDecision(string $status, array $meta = []): void
    {
        $this->emit && ($this->emit)([
            'title' => 'FILE_SCAN_DECISION',
            'description' => $status,
            'meta' => $meta,
        ]);
    }
}