<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations;

use Illuminate\Support\Facades\DB;
use JsonException;
use Random\RandomException;
use RuntimeException;
use Throwable;

use Timeax\FortiPlugin\Installations\DTO\InstallerResult;
use Timeax\FortiPlugin\Installations\Sections\UiConfigValidationSection;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\InstallSummary;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\RouteUiBridge;
use Timeax\FortiPlugin\Installations\Support\ValidatorBridge;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Installations\Sections\ZipValidationGate;
use Timeax\FortiPlugin\Services\ValidatorService;

// Sections (for DI completeness)
use Timeax\FortiPlugin\Installations\Sections\VerificationSection;
use Timeax\FortiPlugin\Installations\Sections\ProviderValidationSection;
use Timeax\FortiPlugin\Installations\Sections\ComposerPlanSection;
use Timeax\FortiPlugin\Installations\Sections\VendorPolicySection;
use Timeax\FortiPlugin\Installations\Sections\RouteWriteSection;
use Timeax\FortiPlugin\Installations\Sections\DbPersistSection;
use Timeax\FortiPlugin\Installations\Sections\InstallFilesSection;

final readonly class Installer
{
    public function __construct(
        private InstallerPolicy           $policy,
        private AtomicFilesystem          $afs,
        private ValidatorBridge           $validatorBridge,   // orchestrates Verification + FileScan
        private VerificationSection       $verification,      // kept for DI completeness (used by bridge)
        private ProviderValidationSection $providerValidation,
        private ComposerPlanSection       $composerPlan,
        private VendorPolicySection       $vendorPolicy,
        private DbPersistSection          $dbPersist,
        private RouteUiBridge             $routeUiBridge,
        private RouteWriteSection         $routeWriterSection, // writer targets STAGING
        private InstallFilesSection       $installFiles,
        private UiConfigValidationSection $uiConfigValidation,
        // NEW: token + logs + zip-gate for resume flow
        private InstallerTokenManager     $tokens,
        private InstallationLogStore      $logStore,
        private ZipValidationGate         $zipGate,
    )
    {
    }

    /**
     * Full install pipeline after validation phases (which are handled by ValidatorBridge),
     * with support for resuming via installer override tokens.
     *
     * @param InstallMeta $meta
     * @param int|string $zipId
     * @param ValidatorService $validator
     * @param array<string,mixed> $validatorConfig
     * @param string $validatorConfigHash
     * @param string $versionTag
     * @param string $actor
     * @param string $runId
     * @param callable|null $emit fn(array $payload): void
     * @param callable|null $onValidationEnd forwarded to ValidatorBridge only
     * @param callable|null $onFileScanError forwarded to ValidatorBridge only
     * @param callable|null $onFinish called once when installation completes successfully (status 'ok')
     * @param string|null $installerToken optional override token when resuming after ASK
     *
     * @return InstallerResult
     * @throws JsonException
     * @throws RandomException|Throwable
     */
    public function run(
        InstallMeta      $meta,
        int|string       $zipId,
        ValidatorService $validator,
        array            $validatorConfig,
        string           $validatorConfigHash,
        string           $versionTag,
        string           $actor,
        string           $runId,
        ?callable        $emit = null,
        ?callable        $onValidationEnd = null,
        ?callable        $onFileScanError = null,
        ?callable        $onFinish = null,
        ?string          $installerToken = null,
    ): InstallerResult
    {
        $pluginDir = (string)($meta->paths['staging'] ?? '');
        if ($pluginDir === '') {
            throw new RuntimeException('InstallMeta.paths.staging is required.');
        }

        $pluginName = $meta->placeholder_name;
        $psr4Root = $this->policy->getPsr4Root();

        // ─────────────────────────────────────────────────────────────
        // 0) PREFLIGHT: resume path via installer override token
        // ─────────────────────────────────────────────────────────────
        if (is_string($installerToken) && $installerToken !== '') {
            $claims = null;
            try {
                $claims = $this->tokens->validate($installerToken);
            } catch (Throwable $e) {
                $emit && $emit([
                    'title' => 'INSTALLER_TOKEN_INVALID',
                    'description' => 'Installer override token invalid or expired',
                    'meta' => ['zip_id' => (string)$zipId, 'reason' => $e->getMessage()],
                ]);
                // Treat as ASK (UI should re-request confirmation or new token)
                return $this->emitAsk($emit, null, ['reason' => 'token_invalid']);
            }

            // Purpose & run parity
            if (($claims->purpose ?? null) !== 'install_override' || ($claims->run_id ?? null) !== $runId) {
                $emit && $emit([
                    'title' => 'INSTALLER_TOKEN_MISMATCH',
                    'description' => 'Token purpose or run_id mismatch',
                    'meta' => ['expected_run' => $runId, 'token_run' => $claims->run_id ?? null, 'purpose' => $claims->purpose ?? null],
                ]);
                return $this->emitAsk($emit, null, ['reason' => 'token_mismatch']);
            }

            // Ensure prior validators ran and produced ASK for this run
            $doc = $this->logStore->read();
            $hasVerificationOk = $this->verificationOk($doc);
            $hasFileScanAsk = $this->hasDecisionAskForRun($doc, $runId);

            if (!$hasVerificationOk || !$hasFileScanAsk) {
                $emit && $emit([
                    'title' => 'RESUME_PRECHECK_FAILED',
                    'description' => 'Logs do not confirm prior verification OK and ASK decision for this run',
                    'meta' => ['verification_ok' => $hasVerificationOk, 'ask_for_run' => $hasFileScanAsk, 'run_id' => $runId],
                ]);
                return $this->emitAsk($emit, null, ['reason' => 'precheck_failed']);
            }

            // Delegate to ZipValidationGate to finalize the gate decision on resume
            $gate = $this->zipGate->run(
                pluginDir: $pluginDir,
                zipId: $zipId,
                actor: $actor,
                runId: $runId,
                validatorConfigHash: $validatorConfigHash,
                installerToken: $installerToken,
            );
            $gateDecision = $gate['decision'] ?? null;
            $gateMeta = $gate['meta'] ?? [];

            if ($gateDecision === Install::ASK) {
                return $this->emitAsk($emit, null, $gateMeta);
            }
            if ($gateDecision === Install::BREAK) {
                return $this->emitBreak($emit, null, ['reason' => 'zip_gate_break'] + $gateMeta);
            }

            // If ZIP gate says INSTALL, we skip ValidatorBridge and continue below at Provider Validation (step 2).
            $summary = new InstallSummary(
                verification: ['status' => 'ok'],
                file_scan: ['enabled' => true, 'status' => 'ask-resumed', 'errors' => []],
                zip_validation: null,
                vendor_policy: null,
                composer_plan: null,
                packages: null
            );
        } else {
            // ─────────────────────────────────────────────────────────
            // 1) VALIDATION (Verification + optional FileScan) via ValidatorBridge
            //    Bridge will call onValidationEnd($summary) exactly once.
            // ─────────────────────────────────────────────────────────
            $vb = $this->validatorBridge->run(
                pluginDir: $pluginDir,
                pluginName: $pluginName,
                zipId: $zipId,
                validator: $validator,
                validatorConfig: $validatorConfig,
                validatorConfigHash: $validatorConfigHash,
                actor: $actor,
                runId: $runId,
                emit: $emit,
                onValidationEnd: $onValidationEnd,
                onFileScanError: $onFileScanError
            );

            $summary = $vb['summary'];
            $gateDecision = $vb['decision'] ?? null;
            $gateMeta = $vb['meta'] ?? null;

            if ($gateDecision instanceof Install) {
                if ($gateDecision === Install::ASK) {
                    return $this->emitAsk($emit, $summary, is_array($gateMeta) ? $gateMeta : []);
                }
                if ($gateDecision === Install::BREAK) {
                    return $this->emitBreak($emit, $summary, []);
                }
                // INSTALL → continue
            }
        }

        // ─────────────────────────────────────────────────────────────
        // 2) PROVIDER VALIDATION (simple existence check in staged tree)
        // ─────────────────────────────────────────────────────────────
        $providers = [];
        try {
            $cfg = $this->afs->fs()->readJson($pluginDir . DIRECTORY_SEPARATOR . 'fortiplugin.json');
            $providers = array_values(array_filter((array)($cfg['providers'] ?? []), 'is_string'));
        } catch (Throwable $_) {
        }

        $prov = $this->providerValidation->run(
            pluginDir: $pluginDir,
            pluginName: $pluginName,
            psr4Root: $psr4Root,
            providers: $providers,
            emit: $emit
        );
        if (($prov['status'] ?? 'ok') !== 'ok') {
            return InstallerResult::fromArray(['status' => 'fail', 'summary' => $summary]);
        }

        // ─────────────────────────────────────────────────────────────
        // 3) VENDOR POLICY + COMPOSER PLAN (advisory; host lock is REQUIRED)
        // ─────────────────────────────────────────────────────────────
        $hostComposerLock = (string)(
        config('fortiplugin.installations.host_composer_lock')
            ?: base_path('composer.lock')
        );

        if (!$this->afs->fs()->exists($hostComposerLock)) {
            throw new RuntimeException("Host composer.lock not found at: $hostComposerLock");
        }

        $vendor = $this->vendorPolicy->run(
            pluginDir: $pluginDir,
            hostComposerLock: $hostComposerLock,
            emit: $emit
        );

        $plan = $this->composerPlan->run(
            pluginDir: $pluginDir,
            hostComposerLock: $hostComposerLock,
            emit: $emit
        );

        $packagesMap = $plan['packages'] ?? null;

        // Refresh summary with advisory info
        $summary = new InstallSummary(
            verification: $summary->verification,
            file_scan: $summary->file_scan,
            zip_validation: null,
            vendor_policy: $vendor['vendor_policy'] ?? null,
            composer_plan: $plan['plan'] ?? null,
            packages: $plan['packages'] ?? null
        );

        // ─────────────────────────────────────────────────────────────
        // 4) DB PERSIST + ROUTE WRITE (to STAGING) — TRANSACTION
        // ─────────────────────────────────────────────────────────────
        $pluginId = null;
        $pluginVersionId = null;

        DB::beginTransaction();
        try {
            $persist = $this->dbPersist->run(
                meta: $meta,
                versionTag: $versionTag,
                zipId: $zipId,
                packages: $packagesMap,
                emit: $emit
            );
            if (($persist['status'] ?? 'fail') !== 'ok') {
                throw new RuntimeException('DB persist failed');
            }
            $pluginId = $persist['plugin_id'] ?? null;
            $pluginVersionId = $persist['plugin_version_id'] ?? null;
            if (!$pluginId) {
                throw new RuntimeException('DB persist did not return plugin_id');
            }

            // Routes: discover + compile JSON, then write PHP into STAGING
            $bundle = $this->routeUiBridge->discoverAndCompile($pluginDir, $emit);
            $compiled = $bundle['compiled'] ?? [];

            if (!empty($compiled)) {
                $plugin = Plugin::query()->findOrFail($pluginId);
                $write = $this->routeWriterSection->run(
                    plugin: $plugin,
                    compiled: $compiled,
                    emit: $emit
                );
                if (($write['status'] ?? 'fail') !== 'ok') {
                    throw new RuntimeException('Route write failed: ' . ($write['reason'] ?? 'unknown'));
                }

                // UI config validation (advisory; logs errors/warnings)
                $hostScheme = (array)config('fortipluginui', []);
                $this->uiConfigValidation->run(
                    meta: $meta,
                    knownRouteIds: $bundle['route_ids'] ?? [],
                    hostScheme: $hostScheme,
                    emit: $emit
                );
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $emit && $emit([
                'title' => 'DB_TRANSACTION_ROLLBACK',
                'description' => 'Persistence or route write failed; rolled back',
                'meta' => ['exception' => $e->getMessage()],
            ]);
            return InstallerResult::fromArray([
                'status' => 'fail',
                'summary' => $summary,
            ]);
        }

        // ─────────────────────────────────────────────────────────────
        // 5) INSTALL FILES (move staged → installed; includes staged routes)
        // ─────────────────────────────────────────────────────────────
        $files = $this->installFiles->run(
            meta: $meta,
            stagingPluginRoot: $pluginDir,
            emit: $emit
        );
        if (($files['status'] ?? 'fail') !== 'ok') {
            $emit && $emit(['title' => 'INSTALL_FILES_FAIL', 'description' => 'Failed moving staged files into place']);
            return InstallerResult::fromArray([
                'status' => 'fail',
                'summary' => $summary,
                'plugin_id' => (int)$pluginId,
                'plugin_version_id' => $pluginVersionId,
            ]);
        }

        // ─────────────────────────────────────────────────────────────
        // 6) FINISH
        // ─────────────────────────────────────────────────────────────
        $result = InstallerResult::fromArray([
            'status' => 'ok',
            'summary' => $summary,
            'plugin_id' => (int)$pluginId,
            'plugin_version_id' => $pluginVersionId,
        ]);

        if (is_callable($onFinish)) {
            try {
                $onFinish($result);
            } catch (Throwable $_) {
            }
        }

        return $result;
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    private function verificationOk(array $doc): bool
    {
        // Accept a few possible shapes from VerificationSection
        // e.g. ['sections'=>['verification'=>['summary'=>['status'=>'ok']]]] or flat.
        $v = $doc['sections']['verification'] ?? $doc['verification'] ?? null;
        if (is_array($v)) {
            $status = $v['summary']['status'] ?? $v['status'] ?? null;
            return $status === 'ok';
        }
        return false;
    }

    private function hasDecisionAskForRun(array $doc, string $runId): bool
    {
        $decisions = $doc['decisions'] ?? [];
        if (!is_array($decisions)) return false;
        foreach ($decisions as $d) {
            if (!is_array($d)) continue;
            if (($d['status'] ?? null) === 'ask' && ($d['run_id'] ?? null) === $runId) {
                return true;
            }
        }
        return false;
    }

    private function emitAsk(?callable $emit, ?InstallSummary $summary, array $meta): InstallerResult
    {
        $payload = [
            'title' => 'INSTALLATION_ASK',
            'description' => 'Installation paused for host decision',
            'meta' => $meta,
        ];
        $emit && $emit($payload);

        return InstallerResult::fromArray([
            'status' => 'ask',
            'summary' => $summary,
            'meta' => $meta,
        ]);
    }

    private function emitBreak(?callable $emit, ?InstallSummary $summary, array $meta): InstallerResult
    {
        $payload = [
            'title' => 'INSTALLATION_BREAK',
            'description' => 'Installation halted by policy',
            'meta' => $meta,
        ];
        $emit && $emit($payload);

        return InstallerResult::fromArray([
            'status' => 'break',
            'summary' => $summary,
            'meta' => $meta,
        ]);
    }
}