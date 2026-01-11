<?php /** @noinspection NotOptimalIfConditionsInspection */
/** @noinspection GrazieInspection */
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
use Timeax\FortiPlugin\Installations\Sections\IngestSection;
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
use Timeax\FortiPlugin\Installations\Support\EmitterMux;
use Timeax\FortiPlugin\Installations\Support\Events;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallEvents;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Installations\Support\RouteUiBridge;
use Timeax\FortiPlugin\Installations\Support\ValidatorBridge;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Permissions\Evaluation\PermissionService;
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
        private PermissionService          $permissionService,
        private IngestSection              $ingestSection
    )
    {
    }

    /**
     * Full install pipeline after validation phases (which are handled by ValidatorBridge),
     * with support for resuming via installer override tokens.
     *
     * All installation emits flow through EmitterMux; Laravel events are dispatched
     * only for emits with an explicit event key.
     *
     * @param InstallMeta $meta
     * @param int|string $zipId
     * @param ValidatorService $validator
     * @param array<string,mixed> $validatorConfig
     * @param string $validatorConfigHash
     * @param string $versionTag
     * @param string $actor
     * @param string $runId
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

        $logsDir = (string)($meta->paths['logs'] ?? '');
        if ($logsDir === '') {
            // fallback: staging/.internal/logs (uses policy dir name)
            $logsDir = $pluginDir . DIRECTORY_SEPARATOR . $this->policy->getLogsDirName();
        }

        $installationJsonPath = rtrim($logsDir, '/\\') . DIRECTORY_SEPARATOR . $this->policy->getInstallationLogFilename();

        // Must happen BEFORE resume-token read() and before sections append emits
        $this->logStore->openOrInit($meta, $installationJsonPath);

        // ─────────────────────────────────────────────────────────────
        // EmitterMux: Single gate for all installer and validation emits
        // ─────────────────────────────────────────────────────────────
        $emitterMux = new EmitterMux($this->logStore);
        $emitInstaller = $emitterMux->installerCallable();
        $emitValidation = $emitterMux->validationCallable();

        // Wire sections that use EmitsEvents trait
        $this->verification->setEmitterMux($emitterMux);

        $emitInstaller([
            'event' => InstallEvents::RUN_START,
            'title' => Events::INSTALLER_START,
            'description' => 'Starting installation run',
            'meta' => ['zip_id' => (string)$zipId, 'run_id' => $runId, 'actor' => $actor],
        ]);


        $summary = null;
        $pluginId = null;
        $pluginVersionId = null;

        try {
            // ─────────────────────────────────────────────────────────────
            // 0) PREFLIGHT: resume path via installer override token
            // ─────────────────────────────────────────────────────────────
            if (is_string($installerToken) && $installerToken !== '') {
                $claims = null;
                try {
                    $claims = $this->tokens->validate($installerToken);
                    $emitInstaller([
                        'event' => InstallEvents::TOKEN_VALID,
                        'title' => Events::TOKEN_VALID,
                        'description' => 'Installer override token validated',
                        'meta' => ['zip_id' => (string)$zipId, 'run_id' => $claims->run_id ?? null],
                    ]);
                } catch (Throwable $e) {
                    $emitInstaller([
                        'event' => InstallEvents::TOKEN_INVALID,
                        'title' => Events::TOKEN_INVALID,
                        'description' => 'Installer override token invalid or expired',
                        'meta' => ['zip_id' => (string)$zipId, 'reason' => $e->getMessage()],
                    ]);
                    // Treat as ASK (UI should re-request confirmation or new token)
                    return $this->emitAsk($emitInstaller, null, ['reason' => 'token_invalid'], $runId, $actor, $zipId);
                }

                // Purpose & run parity
                if (($claims->purpose ?? null) !== 'install_override' || ($claims->run_id ?? null) !== $runId) {
                    $emitInstaller([
                        'title' => 'INSTALLER_TOKEN_MISMATCH',
                        'description' => 'Token purpose or run_id mismatch',
                        'meta' => ['expected_run' => $runId, 'token_run' => $claims->run_id ?? null, 'purpose' => $claims->purpose ?? null],
                    ]);
                    return $this->emitAsk($emitInstaller, null, ['reason' => 'token_mismatch'], $runId, $actor, $zipId);
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
                    return $this->emitAsk($emitInstaller, null, ['reason' => 'precheck_failed'], $runId, $actor, $zipId);
                }

                // Delegate to ZipValidationGate to finalize the gate decision on resume
                $gate = $this->zipGate->run(
                    meta: $meta,
                    runId: $runId,
                    installerToken: $installerToken,
                    emit: $emitInstaller,
                );
                $gateDecision = $gate['decision'] ?? null;
                $gateMeta = $gate['meta'] ?? [];

                if ($gateDecision === Install::ASK) {
                    return $this->emitAsk($emitInstaller, null, $gateMeta, $runId, $actor, $zipId);
                }
                if ($gateDecision === Install::BREAK) {
                    return $this->emitBreak($emitInstaller, null, ['reason' => 'zip_gate_break'] + $gateMeta, $runId, $actor, $zipId);
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
                        return $this->emitAsk($emitInstaller, $summary, is_array($gateMeta) ? $gateMeta : [], $runId, $actor, $zipId);
                    }
                    if ($gateDecision === Install::BREAK) {
                        return $this->emitBreak($emitInstaller, $summary, [], $runId, $actor, $zipId);
                    }
                    // INSTALL → continue
                }
            }

            // ─────────────────────────────────────────────────────────────
            // 2) PROVIDER VALIDATION (simple existence check in staged tree)
            // ─────────────────────────────────────────────────────────────
            $providers = [];
            $permission_manifest = [];

            try {
                $configPath = $pluginDir . DIRECTORY_SEPARATOR . 'fortiplugin.json';

                $emitInstaller([
                    'event' => 'PLUGIN_CONFIG_READ_START',
                    'title' => 'PLUGIN_CONFIG_READ_START',
                    'description' => 'Reading plugin config (fortiplugin.json)',
                    'meta' => ['path' => $configPath, 'zip_id' => (string)$zipId, 'run_id' => $runId],
                ]);

                $cfg = $this->afs->fs()->readJson($configPath);
                $rawConfig = $cfg;

                $emitInstaller([
                    'event' => 'PLUGIN_CONFIG_READ_OK',
                    'title' => 'PLUGIN_CONFIG_READ_OK',
                    'description' => 'Plugin config loaded',
                    'meta' => ['path' => $configPath],
                ]);

                // ─────────────────────────────────────────────────────────
                // Permission manifest (optional): read + validate (HARD FAIL if declared but invalid)
                // ─────────────────────────────────────────────────────────
                $permission_manifest_path = $cfg['permission_manifest'] ?? null;

                if (is_string($permission_manifest_path) && trim($permission_manifest_path) !== '') {
                    $rel = trim($permission_manifest_path);

                    $emitInstaller([
                        'event' => 'PERMISSION_MANIFEST_DECLARED',
                        'title' => 'PERMISSION_MANIFEST_DECLARED',
                        'description' => 'Plugin declares a permission manifest',
                        'meta' => ['permission_manifest' => $rel],
                    ]);

                    // Basic path hardening: must be a relative path within staging
                    $isAbsolute =
                        str_starts_with($rel, '/') ||
                        str_starts_with($rel, '\\') ||
                        preg_match('/^[A-Za-z]:[\/\\\\]/', $rel);

                    $hasTraversal = (bool)preg_match('#(^|[\/\\\\])\.\.([\/\\\\]|$)#', $rel);

                    if ($isAbsolute || $hasTraversal) {
                        $emitInstaller([
                            'event' => 'PERMISSION_MANIFEST_PATH_INVALID',
                            'title' => 'PERMISSION_MANIFEST_PATH_INVALID',
                            'description' => 'Permission manifest path must be relative and may not contain ".."',
                            'meta' => ['permission_manifest' => $rel],
                        ]);

                        return $this->terminate(
                            InstallEvents::RUN_FAIL,
                            new InstallerResult('fail', $summary, ['reason' => 'permission_manifest_path_invalid', 'path' => $rel]),
                            $emitInstaller,
                            $runId,
                            $actor,
                            $zipId
                        );
                    }

                    $manifestPath = $pluginDir . DIRECTORY_SEPARATOR . ltrim($rel, "/\\");
                    $baseReal = realpath($pluginDir) ?: rtrim($pluginDir, "/\\");
                    $manifestReal = realpath($manifestPath);

                    // If file doesn't exist, realpath() returns false; check exists before enforcing base containment.
                    if (!$this->afs->fs()->exists($manifestPath) || $manifestReal === false) {
                        $emitInstaller([
                            'event' => 'PERMISSION_MANIFEST_MISSING',
                            'title' => 'PERMISSION_MANIFEST_MISSING',
                            'description' => 'Declared permission manifest file not found in staging',
                            'meta' => ['permission_manifest' => $rel, 'resolved_path' => $manifestPath],
                        ]);

                        return $this->terminate(
                            InstallEvents::RUN_FAIL,
                            new InstallerResult('fail', $summary, ['reason' => 'permission_manifest_missing', 'path' => $rel]),
                            $emitInstaller,
                            $runId,
                            $actor,
                            $zipId
                        );
                    }

                    // Enforce "must be inside plugin staging"
                    $basePrefix = rtrim($baseReal, "/\\") . DIRECTORY_SEPARATOR;
                    if (!str_starts_with($manifestReal, $basePrefix)) {
                        $emitInstaller([
                            'event' => 'PERMISSION_MANIFEST_OUTSIDE_STAGING',
                            'title' => 'PERMISSION_MANIFEST_OUTSIDE_STAGING',
                            'description' => 'Permission manifest resolves outside the staging directory',
                            'meta' => ['permission_manifest' => $rel, 'resolved_path' => $manifestReal, 'staging_root' => $baseReal],
                        ]);

                        return $this->terminate(
                            InstallEvents::RUN_FAIL,
                            new InstallerResult('fail', $summary, ['reason' => 'permission_manifest_outside_staging', 'path' => $rel]),
                            $emitInstaller,
                            $runId,
                            $actor,
                            $zipId
                        );
                    }

                    $emitInstaller([
                        'event' => 'PERMISSION_MANIFEST_READ_START',
                        'title' => 'PERMISSION_MANIFEST_READ_START',
                        'description' => 'Reading permission manifest JSON',
                        'meta' => ['path' => $manifestReal],
                    ]);

                    try {
                        $permission_manifest = $this->afs->fs()->readJson($manifestReal);
                    } catch (Throwable $e) {
                        $emitInstaller([
                            'event' => 'PERMISSION_MANIFEST_READ_FAIL',
                            'title' => 'PERMISSION_MANIFEST_READ_FAIL',
                            'description' => 'Failed to read permission manifest JSON',
                            'meta' => ['path' => $manifestReal, 'exception' => $e->getMessage()],
                        ]);

                        return $this->terminate(
                            InstallEvents::RUN_FAIL,
                            new InstallerResult('fail', $summary, ['reason' => 'permission_manifest_read_failed', 'exception' => $e->getMessage(), 'path' => $rel]),
                            $emitInstaller,
                            $runId,
                            $actor,
                            $zipId
                        );
                    }

                    // Build a small summary for UI/logs
                    $req = $permission_manifest['required_permissions'] ?? [];
                    $opt = $permission_manifest['optional_permissions'] ?? [];
                    $reqCount = is_array($req) ? count($req) : 0;
                    $optCount = is_array($opt) ? count($opt) : 0;

                    $typeCounts = [];
                    foreach ([$req, $opt] as $bucket) {
                        if (!is_array($bucket)) continue;
                        foreach ($bucket as $rule) {
                            if (!is_array($rule)) continue;
                            $t = $rule['type'] ?? null;
                            if (!is_string($t) || $t === '') continue;
                            $typeCounts[$t] = ($typeCounts[$t] ?? 0) + 1;
                        }
                    }

                    $emitInstaller([
                        'event' => 'PERMISSION_MANIFEST_VALIDATE_START',
                        'title' => 'PERMISSION_MANIFEST_VALIDATE_START',
                        'description' => 'Validating permission manifest schema and rules',
                        'meta' => [
                            'path' => $manifestReal,
                            'required_count' => $reqCount,
                            'optional_count' => $optCount,
                            'type_counts' => $typeCounts,
                        ],
                    ]);

                    try {
                        $res = $this->permissionService->validateManifest($permission_manifest);

                        $ok = (bool)($res['ok'] ?? false);
                        $errors = is_array($res['errors'] ?? null) ? $res['errors'] : [];
                        $errorCount = count($errors);

                        $emitInstaller([
                            'event' => $ok ? 'PERMISSION_MANIFEST_VALIDATE_OK' : 'PERMISSION_MANIFEST_VALIDATE_FAIL',
                            'title' => $ok ? 'PERMISSION_MANIFEST_VALIDATE_OK' : 'PERMISSION_MANIFEST_VALIDATE_FAIL',
                            'description' => $ok
                                ? 'Permission manifest validated successfully'
                                : 'Permission manifest validation failed',
                            'meta' => [
                                'path' => $manifestReal,
                                'ok' => $ok,
                                'errors_count' => $errorCount,
                                // keep it UI-safe: first few only
                                'errors_preview' => array_slice($errors, 0, 10),
                            ],
                        ]);

                        if (!$ok) {
                            return $this->terminate(
                                InstallEvents::RUN_FAIL,
                                new InstallerResult('fail', $summary, [
                                    'reason' => 'permission_manifest_validation_failed',
                                    'errors_count' => $errorCount,
                                    'errors' => array_slice($errors, 0, 50),
                                    'path' => $rel,
                                ]),
                                $emitInstaller,
                                $runId,
                                $actor,
                                $zipId
                            );
                        }
                    } catch (Throwable $e) {
                        $emitInstaller([
                            'event' => 'PERMISSION_MANIFEST_VALIDATE_EXCEPTION',
                            'title' => 'PERMISSION_MANIFEST_VALIDATE_EXCEPTION',
                            'description' => 'Exception thrown while validating permission manifest',
                            'meta' => ['path' => $manifestReal, 'exception' => $e->getMessage()],
                        ]);

                        return $this->terminate(
                            InstallEvents::RUN_FAIL,
                            new InstallerResult('fail', $summary, [
                                'reason' => 'permission_manifest_validation_exception',
                                'exception' => $e->getMessage(),
                                'path' => $rel,
                            ]),
                            $emitInstaller,
                            $runId,
                            $actor,
                            $zipId
                        );
                    }
                } else {
                    $emitInstaller([
                        'event' => 'PERMISSION_MANIFEST_NOT_DECLARED',
                        'title' => 'PERMISSION_MANIFEST_NOT_DECLARED',
                        'description' => 'No permission manifest declared; continuing without manifest validation',
                        'meta' => ['config' => $configPath],
                    ]);
                }

                // Providers list (best-effort parse)
                $providers = array_values(array_filter((array)($cfg['providers'] ?? []), 'is_string'));

            } catch (Throwable $e) {
                // Keep behavior as best-effort (providers remain empty), but emit a proper event
                $emitInstaller([
                    'event' => 'PLUGIN_CONFIG_READ_FAIL',
                    'title' => 'PLUGIN_CONFIG_READ_FAIL',
                    'description' => 'Failed to read plugin config (fortiplugin.json); continuing with empty providers',
                    'meta' => [
                        'path' => $pluginDir . DIRECTORY_SEPARATOR . 'fortiplugin.json',
                        'exception' => $e->getMessage(),
                    ],
                ]);
            }

            $prov = $this->providerValidation->run(
                pluginDir: $pluginDir,
                pluginName: $pluginName,
                psr4Root: $psr4Root,
                providers: $providers,
                emit: $emitInstaller
            );
            if (($prov['status'] ?? 'ok') !== 'ok') {
                return $this->terminate(InstallEvents::RUN_FAIL, new InstallerResult('fail', $summary, ['reason' => 'provider_validation_failed']), $emitInstaller, $runId, $actor, $zipId);
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
            DB::beginTransaction();
            try {
                $persist = $this->dbPersist->run(
                    meta: $meta,
                    versionTag: $versionTag,
                    zipId: $zipId,
                    emit: $emitInstaller,
                    packages: $packagesForDb
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

                // ─────────────────────────────────────────────────────────────
                // 5) INSTALL FILES (move staged → installed; includes staged routes)
                // ─────────────────────────────────────────────────────────────
                $file_result = $this->installFiles->run(
                    meta: $meta,
                    stagingPluginRoot: $pluginDir,
                    emit: $emitInstaller
                );
                if (($file_result['status'] ?? 'fail') !== 'ok') {
                    throw new RuntimeException('Install files failed: ' . ($file_result['reason'] ?? 'unknown'));
                }

                // ─────────────────────────────────────────────────────────────
                // 6) PUBLISH UI ASSETS (copy installed public/ → host public/)
                // ─────────────────────────────────────────────────────────────
                $pub = $this->publishBuildAssets->run(
                    meta: $meta,
                    pluginId: (int)$pluginId,
                    emit: $emitInstaller
                );
                if (($pub['status'] ?? 'fail') === 'fail') {
                    throw new RuntimeException('Publish assets failed: ' . ($pub['reason'] ?? 'unknown'));
                }

                $this->dbPersist->plugins->setPluginRoot($pluginId, $file_result['dest']);
            } catch (Throwable $e) {
                DB::rollBack();
                $emitInstaller([
                    'title' => 'DB_TRANSACTION_ROLLBACK',
                    'description' => 'Persistence or filesystem write failed; rolled back',
                    'meta' => ['exception' => $e->getMessage()],
                ]);
                return $this->terminate(InstallEvents::RUN_FAIL, new InstallerResult('fail', $summary, ['exception' => $e->getMessage()], (int)$pluginId, $pluginVersionId), $emitInstaller, $runId, $actor, $zipId);
            }

            // ─────────────────────────────────────────────────────────────
            // Permission manifest ingestion (AFTER dbPersist sets $pluginId)
            // ─────────────────────────────────────────────────────────────
            try {
                $this->ingestSection->ingestPermissions(
                    permission_manifest: $permission_manifest,
                    pluginId: $pluginId,
                    runId: $runId,
                    zipId: $zipId,
                    emitInstaller: $emitInstaller
                );
            } catch (Throwable $e) {
                DB::rollBack();
                return $this->terminate(InstallEvents::RUN_FAIL, new InstallerResult('fail', $summary, ['exception' => $e->getMessage()], (int)$pluginId, $pluginVersionId), $emitInstaller, $runId, $actor, $zipId);
            }

            try {
                $this->ingestSection->ingestSettings(
                    pluginId: $pluginId,
                    pluginConfig: $rawConfig ?? [],
                    runId: $runId,
                    zipId: $zipId,
                    emitInstaller: $emitInstaller
                );
            } catch (Throwable $th) {
                DB::rollBack();
                return $this->terminate(InstallEvents::RUN_FAIL, new InstallerResult('fail', $summary, ['exception' => $th->getMessage()], (int)$pluginId, $pluginVersionId), $emitInstaller, $runId, $actor, $zipId);
            }

            DB::commit();
            // ─────────────────────────────────────────────────────────────
            // 7) FINISH
            // ─────────────────────────────────────────────────────────────
            $result = new InstallerResult('ok', $summary, null, (int)$pluginId, $pluginVersionId);

            if (is_callable($onFinish)) {
                try {
                    $onFinish($result);
                } catch (Throwable $_) {
                }
            }

            return $this->terminate(InstallEvents::RUN_END, $result, $emitInstaller, $runId, $actor, $zipId);

        } catch (Throwable $e) {
            return $this->terminate(InstallEvents::RUN_FAIL, new InstallerResult('fail', $summary, ['exception' => $e->getMessage()], (int)$pluginId, $pluginVersionId), $emitInstaller, $runId, $actor, $zipId);
        }
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

    private function emitAsk(callable $emit, ?InstallSummary $summary, array $meta, string $runId, string $actor, int|string $zipId): InstallerResult
    {
        $payload = [
            'event' => InstallEvents::DECISION_ASK,
            'title' => 'INSTALLATION_ASK',
            'description' => 'Installation paused for host decision',
            'meta' => $meta,
        ];
        $emit($payload);

        $result = InstallerResult::ask($summary, $meta);
        return $this->terminate(InstallEvents::RUN_FAIL, $result, $emit, $runId, $actor, $zipId);
    }

    private function emitBreak(callable $emit, ?InstallSummary $summary, array $meta, string $runId, string $actor, int|string $zipId): InstallerResult
    {
        $payload = [
            'event' => InstallEvents::DECISION_BREAK,
            'title' => 'INSTALLATION_BREAK',
            'description' => 'Installation halted by policy',
            'meta' => $meta,
        ];
        $emit($payload);

        $result = InstallerResult::break($summary, $meta);
        return $this->terminate(InstallEvents::RUN_FAIL, $result, $emit, $runId, $actor, $zipId);
    }

    /**
     * Terminate the run by persisting the result and emitting the final lifecycle event.
     * Ensure exactly ONE terminal event is emitted.
     */
    private function terminate(
        string          $event,
        InstallerResult $result,
        callable        $emit,
        ?string         $runId = null,
        ?string         $actor = null,
        int|string|null $zipId = null,
    ): InstallerResult
    {
        // 1) Persist structured final result section once and only once
        try {
            $data = $result->toArray();
            $data['run_id'] = $runId;
            $data['zip_id'] = $zipId;
            $data['actor'] = $actor;
            $this->logStore->writeSection('result', $data);
        } catch (Throwable) {
            // Best effort persistence
        }

        // 2) Emit terminal event with meta summary
        $meta = $result->meta ?? [];
        $meta['run_id'] = $runId;
        $meta['zip_id'] = $zipId;
        $meta['actor'] = $actor;
        $meta['status'] = $result->status;
        if ($result->plugin_id) {
            $meta['plugin_id'] = $result->plugin_id;
        }

        $emit([
            'event' => $event,
            'title' => $event === InstallEvents::RUN_END ? Events::INSTALLER_END : 'INSTALLATION_FAILED',
            'description' => $event === InstallEvents::RUN_END
                ? 'Installation run completed successfully'
                : 'Installation run failed or was interrupted (Status: ' . $result->status . ')',
            'meta' => $meta,
        ]);

        return $result;
    }
}