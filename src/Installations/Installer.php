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
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\InstallSummary;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\Sections\ComposerPlanSection;
use Timeax\FortiPlugin\Installations\Sections\DbPersistSection;
use Timeax\FortiPlugin\Installations\Sections\InstallFilesSection;
use Timeax\FortiPlugin\Installations\Sections\InternalConfigWriteSection;
use Timeax\FortiPlugin\Installations\Sections\ProviderValidationSection;
use Timeax\FortiPlugin\Installations\Sections\PublishBuildAssetsSection;
use Timeax\FortiPlugin\Installations\Sections\RouteWriteSection;
use Timeax\FortiPlugin\Installations\Sections\UiConfigValidationSection;
use Timeax\FortiPlugin\Installations\Sections\VendorPolicySection;
use Timeax\FortiPlugin\Installations\Sections\VerificationSection;
use Timeax\FortiPlugin\Installations\Sections\ZipValidationGate;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Installations\Support\RouteUiBridge;
use Timeax\FortiPlugin\Installations\Support\ValidatorBridge;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Services\ValidatorService;

// Sections (for DI completeness)

final readonly class Installer
{
    public function __construct(
        private InstallerPolicy            $policy,
        private AtomicFilesystem           $afs,
        private ValidatorBridge            $validatorBridge,   // orchestrates Verification + FileScan
        private VerificationSection        $verification,      // kept for DI completeness (used by bridge)
        private ProviderValidationSection  $providerValidation,
        private ComposerPlanSection        $composerPlan,
        private VendorPolicySection        $vendorPolicy,
        private DbPersistSection           $dbPersist,
        private RouteUiBridge              $routeUiBridge,
        private RouteWriteSection          $routeWriterSection, // writer targets STAGING

        private InternalConfigWriteSection $internalConfig,
        private InstallFilesSection        $installFiles,
        private PublishBuildAssetsSection  $publishBuildAssets,
        private UiConfigValidationSection  $uiConfigValidation,

        private InstallerTokenManager      $tokens,
        private InstallationLogStore       $logStore,
        private ZipValidationGate          $zipGate,
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
        $cliTee = app()->runningInConsole()
            ? static function (array $p): void {
                $title = $p['title'] ?? 'EVENT';
                $desc = $p['description'] ?? '';
                fwrite(STDOUT, "[$title] $desc\n");
            }
            : null;

        // 1) installer emitter: persist + tee + forward
        $emitInstaller = $this->logStore->makeInstallerEmitter(
            forward: $emit,     // original caller emitter
            tee: $cliTee
        );

        // 2) validation emitter: tee + forward only (NO persistence here)
        $emitValidation = function (array $p) use ($emit, $cliTee): void {
            if ($cliTee) $cliTee($p);
            if ($emit) $emit($p);
        };

        $pluginDir = (string)($meta->paths['staging'] ?? '');
        if ($pluginDir === '') {
            throw new RuntimeException('InstallMeta.paths.staging is required.');
        }

        $pluginName = $meta->placeholder_name;
        $psr4Root = $this->policy->getPsr4Root();

        $logsDir = (string)($meta->paths['logs'] ?? '');
        if ($logsDir === '') {
            // fallback: staging/.internal/logs (uses policy dir name)
            $logsDir = $pluginDir . DIRECTORY_SEPARATOR . $this->policy->getLogsDirName();
        }

        $installationJsonPath = rtrim($logsDir, '/\\') . DIRECTORY_SEPARATOR . $this->policy->getInstallationLogFilename();

        // Must happen BEFORE resume-token read() and before sections append emits
        $this->logStore->openOrInit($meta, $installationJsonPath);


        // ─────────────────────────────────────────────────────────────
        // 0) PREFLIGHT: resume path via installer override token
        // ─────────────────────────────────────────────────────────────
        if (is_string($installerToken) && $installerToken !== '') {
            $claims = null;
            try {
                $claims = $this->tokens->validate($installerToken);
            } catch (Throwable $e) {
                $emitInstaller([
                    'title' => 'INSTALLER_TOKEN_INVALID',
                    'description' => 'Installer override token invalid or expired',
                    'meta' => ['zip_id' => (string)$zipId, 'reason' => $e->getMessage()],
                ]);
                // Treat as ASK (UI should re-request confirmation or new token)
                return $this->emitAsk($emitInstaller, null, ['reason' => 'token_invalid']);
            }

            // Purpose & run parity
            if (($claims->purpose ?? null) !== 'install_override' || ($claims->run_id ?? null) !== $runId) {
                $emitInstaller([
                    'title' => 'INSTALLER_TOKEN_MISMATCH',
                    'description' => 'Token purpose or run_id mismatch',
                    'meta' => ['expected_run' => $runId, 'token_run' => $claims->run_id ?? null, 'purpose' => $claims->purpose ?? null],
                ]);
                return $this->emitAsk($emitInstaller, null, ['reason' => 'token_mismatch']);
            }

            // Ensure prior validators ran and produced ASK for this run
            $doc = $this->logStore->read();
            $hasVerificationOk = $this->verificationOk($doc);
            $hasFileScanAsk = $this->hasDecisionAskForRun($doc, $runId);

            if (!$hasVerificationOk || !$hasFileScanAsk) {
                $emitInstaller([
                    'title' => 'RESUME_PRECHECK_FAILED',
                    'description' => 'Logs do not confirm prior verification OK and ASK decision for this run',
                    'meta' => ['verification_ok' => $hasVerificationOk, 'ask_for_run' => $hasFileScanAsk, 'run_id' => $runId],
                ]);
                return $this->emitAsk($emitInstaller, null, ['reason' => 'precheck_failed']);
            }

            // Delegate to ZipValidationGate to finalize the gate decision on resume
            $gate = $this->zipGate->run(
                pluginDir: $pluginDir,
                zipId: $zipId,
                actor: $actor,
                runId: $runId,
                validatorConfigHash: $validatorConfigHash,
                installerToken: $installerToken,
                emit: $emitInstaller,
            );
            $gateDecision = $gate['decision'] ?? null;
            $gateMeta = $gate['meta'] ?? [];

            if ($gateDecision === Install::ASK) {
                return $this->emitAsk($emitInstaller, null, $gateMeta);
            }
            if ($gateDecision === Install::BREAK) {
                return $this->emitBreak($emitInstaller, null, ['reason' => 'zip_gate_break'] + $gateMeta);
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
                emit: $emitValidation,
                onValidationEnd: $onValidationEnd,
                onFileScanError: $onFileScanError
            );

            $summary = $vb['summary'];
            $gateDecision = $vb['decision'] ?? null;
            $gateMeta = $vb['meta'] ?? null;

            if ($gateDecision instanceof Install) {
                if ($gateDecision === Install::ASK) {
                    return $this->emitAsk($emitInstaller, $summary, is_array($gateMeta) ? $gateMeta : []);
                }
                if ($gateDecision === Install::BREAK) {
                    return $this->emitBreak($emitInstaller, $summary, []);
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
            emit: $emitInstaller
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
            emit: $emitInstaller
        );

        $plan = $this->composerPlan->run(
            pluginDir: $pluginDir,
            hostComposerLock: $hostComposerLock,
            emit: $emitInstaller
        );

        $packagesForDb = $plan['packages_dto'] ?? $vendor['packages_dto'] ?? null;

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
                packages: $packagesForDb,
                emit: $emitInstaller
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
            $bundle = $this->routeUiBridge->discoverAndCompile($pluginDir, $emitInstaller);
            $compiled = $bundle['compiled'] ?? [];

            if (!empty($compiled)) {
                $plugin = Plugin::query()->findOrFail($pluginId);
                $write = $this->routeWriterSection->run(
                    plugin: $plugin,
                    compiled: $compiled,
                    emit: $emitInstaller
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
                    emit: $emitInstaller
                );
            } else {
                $emitInstaller([
                    'title' => 'ROUTES_NONE_DISCOVERED',
                    'description' => 'No route files discovered or compiled',
                    'meta' => ['plugin_dir' => $pluginDir],
                ]);
            }

            DB::commit();

            // Write .internal/Config.php into STAGING so InstallFiles copies it to INSTALL.

            $cfg = $this->internalConfig->run(
                meta: $meta,
                stagingPluginRoot: $pluginDir,
                pluginId: (int)$pluginId,
                emit: $emitInstaller
            );
            if (($cfg['status'] ?? 'fail') !== 'ok') {
                throw new RuntimeException('Internal config write failed');
            }


        } catch (Throwable $e) {
            DB::rollBack();
            $emitInstaller([
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
            emit: $emitInstaller
        );
        if (($files['status'] ?? 'fail') !== 'ok') {
            $emitInstaller(['title' => 'INSTALL_FILES_FAIL', 'description' => 'Failed moving staged files into place']);
            return InstallerResult::fromArray([
                'status' => 'fail',
                'summary' => $summary,
                'plugin_id' => (int)$pluginId,
                'plugin_version_id' => $pluginVersionId,
            ]);
        }

        // ─────────────────────────────────────────────────────────────
        // 6) PUBLISH UI BUILD (copy installed public/build → host public/)
        // ─────────────────────────────────────────────────────────────
        $pub = $this->publishBuildAssets->run(
            meta: $meta,
            pluginId: (int)$pluginId,
            emit: $emitInstaller
        );

        if (($pub['status'] ?? 'fail') === 'fail') {
            $emitInstaller([
                'title' => 'UI_BUILD_PUBLISH_FAIL',
                'description' => 'Failed publishing embed UI build assets',
                'meta' => ['plugin_id' => (int)$pluginId],
            ]);

            return InstallerResult::fromArray([
                'status' => 'fail',
                'summary' => $summary,
                'plugin_id' => (int)$pluginId,
                'plugin_version_id' => $pluginVersionId,
            ]);
        }


        // ─────────────────────────────────────────────────────────────
        // 7) FINISH
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

    private function emitAsk(callable $emit, ?InstallSummary $summary, array $meta): InstallerResult
    {
        $payload = [
            'title' => 'INSTALLATION_ASK',
            'description' => 'Installation paused for host decision',
            'meta' => $meta,
        ];
        $emit($payload);

        return InstallerResult::fromArray([
            'status' => 'ask',
            'summary' => $summary,
            'meta' => $meta,
        ]);
    }

    private function emitBreak(callable $emit, ?InstallSummary $summary, array $meta): InstallerResult
    {
        $payload = [
            'title' => 'INSTALLATION_BREAK',
            'description' => 'Installation halted by policy',
            'meta' => $meta,
        ];
        $emit($payload);

        return InstallerResult::fromArray([
            'status' => 'break',
            'summary' => $summary,
            'meta' => $meta,
        ]);
    }
}