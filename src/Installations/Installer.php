<?php /** @noinspection PhpSameParameterValueInspection */
/** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */

/** @noinspection PhpUnusedLocalVariableInspection */

namespace Timeax\FortiPlugin\Installations;

use DateTimeImmutable;
use JsonException;
use Throwable;
use Timeax\FortiPlugin\Facades\Validator;
use Timeax\FortiPlugin\Installations\Contracts\Emitter;
use Timeax\FortiPlugin\Installations\Contracts\LockManager;
use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;
use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Contracts\ActorResolver;
use Timeax\FortiPlugin\Installations\DTO\DecisionResult;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\Sections\ComposerPlanSection;
use Timeax\FortiPlugin\Installations\Sections\DbPersistSection;
use Timeax\FortiPlugin\Installations\Sections\FileScanSection;
use Timeax\FortiPlugin\Installations\Sections\InstallFilesSection;
use Timeax\FortiPlugin\Installations\Sections\VendorPolicySection;
use Timeax\FortiPlugin\Installations\Sections\VerificationSection;
use Timeax\FortiPlugin\Installations\Sections\ZipValidationGate;
use Timeax\FortiPlugin\Installations\Support\ComposerInspector;
use Timeax\FortiPlugin\Installations\Support\EmitterMux;
use Timeax\FortiPlugin\Installations\Support\Fingerprint;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Installations\Support\Psr4Checker;
use Timeax\FortiPlugin\Services\ValidatorService;
use function function_exists;

class Installer
{
    private ?EmitterMux $emitterMux = null;
    private bool $fileScanEnabled = false;

    /** @var callable|null */
    private $_onFileScanError;

    /** @var callable|null */
    private $_onValidationEnd;

    private ValidatorService $validator;

    public function __construct(
        private readonly InstallerPolicy        $policy,
        private readonly InstallationLogStore   $logStore,
        private readonly ?LockManager           $lockManager = null,
        private readonly ?ZipRepository         $zipRepository = null,
        private readonly ?InstallerTokenManager $tokenManager = null,
        private readonly ?ActorResolver         $actorResolver = null,
        private readonly ?PluginRepository      $pluginRepository = null,
    )
    {
        // Resolve a fresh ValidatorService; scanning will be controlled per-phase.
        $this->validator = Validator::getInstance();
    }

    /**
     * Phase 8 helper: persist decision and emit a unified installer decision event.
     */
    private function emitDecisionBlock(string $installRoot, string $status, ?string $reason = null, ?array $token = null): void
    {
        $decision = [
            'status' => $status,
            'reason' => $reason,
        ];
        if ($token) {
            $decision['token'] = $token;
        }
        try {
            $this->logStore->setDecision($installRoot, $decision);
        } catch (Throwable $_) {
        }

        if ($this->emitterMux) {
            try {
                $this->emitterMux->emit([
                    'title' => 'Installer: Decision ' . $status,
                    'description' => $reason ?: null,
                    'error' => $status === 'break' ? ['detail' => $reason ?? ''] : null,
                    'stats' => ['filePath' => null, 'size' => null],
                    'meta' => $decision,
                ]);
            } catch (Throwable $_) {
            }
        }
    }

    public function emitWith(callable $fn): self
    {
        // Wrap the provided callable in an Emitter implementation
        $emitter = new class($fn) implements Emitter {
            public function __construct(private $fn)
            {
            }

            public function __invoke(array $payload): void
            {
                ($this->fn)($payload);
            }
        };
        $this->emitterMux = new EmitterMux($emitter);
        return $this;
    }

    public function enableFileScan(): self
    {
        $this->fileScanEnabled = true;
        $this->policy->enableFileScan();
        return $this;
    }

    /**
     * Callback invoked when optional file scanning finds issues.
     */
    public function onFileScanError(callable $cb): self
    {
        $this->_onFileScanError = $cb;
        return $this;
    }

    /**
     * Callback invoked after mandatory verification completes.
     */
    public function onValidationEnd(callable $cb): self
    {
        $this->_onValidationEnd = $cb;
        return $this;
    }

    /**
     * Phase 2 wiring: run VerificationSection then ZipValidationGate.
     * @throws JsonException
     */
    public function install(int|string $plugin_zip_id, ?string $installer_token = null): DecisionResult
    {
        $stagingRoot = (string)(function_exists('config') ? (config('fortiplugin.staging_root') ?? sys_get_temp_dir()) : sys_get_temp_dir());
        $installRoot = (string)(function_exists('config') ? (config('fortiplugin.directory') ?? 'Plugins') : 'Plugins');
        $installRoot = rtrim($installRoot, "\\/ ");

        // Resolve actor and zip meta for early meta writing and PSR-4 check
        $actor = $this->actorResolver?->resolve() ?? 'system';
        $zipRec = $this->zipRepository?->getZip($plugin_zip_id) ?? [];
        $placeholderId = $zipRec['placeholder_id'] ?? null;
        $placeholderName = $zipRec['meta']['placeholder_name'] ?? $zipRec['meta']['name'] ?? null;
        $psr4Root = (string)(function_exists('config') ? (config('fortiplugin.psr4_root') ?? 'Plugins') : 'Plugins');

        // Compute fingerprint/config hash if possible
        $zipPath = (string)($zipRec['path'] ?? (string)$plugin_zip_id);
        $fingerprint = (new Fingerprint())->compute($zipPath);
        $validatorConfig = (array)(function_exists('config') ? (config('fortiplugin.validator') ?? []) : []);
        $validatorConfigHash = (new Fingerprint())->configHash($validatorConfig);

        // Write early meta (must exist before any token issuance)
        try {
            $this->logStore->writeMeta($installRoot, [
                'zip_id' => (string)$plugin_zip_id,
                'plugin_placeholder_id' => $placeholderId,
                'placeholder_name' => $placeholderName,
                'psr4_root' => $psr4Root,
                'actor' => $actor,
                'paths' => [
                    'staging_root' => $stagingRoot,
                    'install_root' => $installRoot,
                ],
                'timestamps' => [
                    'started_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
                ],
                'fingerprint' => $fingerprint,
                'validator_config_hash' => $validatorConfigHash,
            ]);
        } catch (Throwable $_) {}

        // Enforce PSR-4 root sync for this plugin mapping
        if ($placeholderName) {
            $checker = new Psr4Checker();
            $projectRoot = function_exists('base_path') ? base_path() : getcwd();
            $psr4 = $checker->check((string)$projectRoot, $psr4Root, (string)$placeholderName);
            if (!$psr4['ok']) {
                // Persist a minimal verification snapshot and break
                $this->logStore->setVerification($installRoot, [
                    'status' => 'fail',
                    'errors' => $psr4['errors'],
                    'warnings' => [],
                    'details' => $psr4['details'] ?? [],
                    'finished_at' => (new DateTimeImmutable())->format(DATE_ATOM),
                ]);
                // End of validation block for this run
                if (is_callable($this->_onValidationEnd)) {
                    try { ($this->_onValidationEnd)(['status' => 'fail'] + $psr4); } catch (Throwable $_) {}
                }
                $this->emitDecisionBlock($installRoot, 'break', 'COMPOSER_PSR4_MISMATCH');
                return new DecisionResult(status: 'break', summary: ['status' => 'fail', 'errors' => $psr4['errors']]);
            }
        }

        $locked = false;
        if ($this->lockManager) {
            // Using zip id as lock key placeholder until slug is resolved
            $locked = $this->lockManager->acquire((string)$plugin_zip_id);
        }

        try {
            // Token resume flow (background_scan or install_override)
            if (is_string($installer_token) && $installer_token !== '') {
                $state = $this->logStore->getCurrent($installRoot);
                $decision = (array)($state['decision'] ?? []);
                $tokenMeta = (array)($decision['token'] ?? []);
                $purpose = (string)($tokenMeta['purpose'] ?? '');
                $expiresAt = (string)($tokenMeta['expires_at'] ?? ($tokenMeta['expiresAt'] ?? ''));
                $now = new DateTimeImmutable('now');
                $notExpired = true;
                if ($expiresAt) {
                    try {
                        $notExpired = (new DateTimeImmutable($expiresAt)) > $now;
                    } catch (Throwable $_) {
                        $notExpired = false;
                    }
                }

                if (!$notExpired) {
                    $this->emitDecisionBlock($installRoot, 'break', 'TOKEN_INVALID_OR_EXPIRED');
                    return new DecisionResult(status: 'break', summary: ['status' => 'fail', 'errors' => ['TOKEN_INVALID_OR_EXPIRED']]);
                }

                // Minimal validation: ensure last decision zip_id (if present) matches
                $lastZipId = (string)($decision['zip_id'] ?? '');
                if ($lastZipId !== '' && $lastZipId !== (string)$plugin_zip_id) {
                    $this->emitDecisionBlock($installRoot, 'break', 'TOKEN_ZIP_MISMATCH');
                    return new DecisionResult(status: 'break', summary: ['status' => 'fail', 'errors' => ['TOKEN_ZIP_MISMATCH']]);
                }

                $emitCallable = $this->emitterMux ? fn(array $p) => $this->emitterMux->emit($p) : null;

                if ($purpose === 'background_scan') {
                    // Read file_scan errors and route via onFileScanError
                    $fileScan = (array)($state['file_scan'] ?? []);
                    $errors = (array)($fileScan['errors'] ?? []);
                    if ($errors) {
                        $decisionEnum = Install::ASK;
                        if (is_callable($this->_onFileScanError)) {
                            try {
                                $decisionEnum = ($this->_onFileScanError)($errors, [
                                    'purpose' => 'install_override',
                                    'zipId' => $plugin_zip_id,
                                    'fingerprint' => (string)($state['meta']['fingerprint'] ?? ''),
                                    'validator_config_hash' => (string)($state['meta']['validator_config_hash'] ?? ''),
                                    'actor' => $this->actorResolver?->resolve() ?? 'system',
                                ]);
                            } catch (Throwable $_) {
                                $decisionEnum = Install::ASK;
                            }
                        }
                        if ($decisionEnum === Install::BREAK) {
                            $this->logStore->setDecision($installRoot, [
                                'status' => 'break',
                                'reason' => 'file_scan_errors',
                            ]);
                            $this->emitDecisionBlock($installRoot, 'break', 'file_scan_errors');
                            return new DecisionResult(status: 'break', summary: $state);
                        }
                        if ($decisionEnum === Install::ASK) {
                            // Issue install_override token
                            [$tok, $exp] = ($this->tokenManager ?? new InstallerTokenManager())->issueToken(
                                'install_override', $plugin_zip_id,
                                (string)($state['meta']['fingerprint'] ?? ''),
                                (string)($state['meta']['validator_config_hash'] ?? ''),
                                $this->actorResolver?->resolve() ?? 'system'
                            );
                            $this->logStore->setDecision($installRoot, [
                                'status' => 'ask',
                                'reason' => 'file_scan_errors',
                                'token' => ['purpose' => 'install_override', 'expires_at' => $exp],
                            ]);
                            $emitCallable && $emitCallable(['title' => 'Installer: Decision ask', 'description' => 'File scan issues — install override requested', 'error' => null, 'stats' => []]);
                            return new DecisionResult(status: 'ask', summary: $state, tokenEncrypted: $tok, expiresAt: $exp);
                        }
                        // INSTALL: fallthrough continue to non-validation phases (not yet implemented)
                        return new DecisionResult(status: 'ask', summary: $state);
                    }
                    // No scan errors; proceed to non-validation phases (not yet implemented)
                    return new DecisionResult(status: 'ask', summary: $state);
                }

                if ($purpose === 'install_override') {
                    // Resume accepted override: skip validation and proceed
                    return new DecisionResult(status: 'ask', summary: $state);
                }

                // Unknown purpose
                $this->emitDecisionBlock($installRoot, 'break', 'TOKEN_PURPOSE_UNKNOWN');
                return new DecisionResult(status: 'break', summary: ['status' => 'fail', 'errors' => ['TOKEN_PURPOSE_UNKNOWN']]);
            }

            if (!$this->validator) {
                $this->emitDecisionBlock($installRoot, 'break', 'VALIDATOR_MISSING');
                return new DecisionResult(status: 'break', summary: ['status' => 'fail', 'errors' => ['ValidatorService not provided']]);
            }

            $section = new VerificationSection();
            $emitter = $this->emitterMux ? fn(array $p) => $this->emitterMux->emit($p) : null;
            // Ensure scanners are not run in the mandatory verification step
            try { Validator::setIgnoredValidators(['file_scanner']); } catch (Throwable $_) {}
            $summary = $section->run($this->validator, $stagingRoot, $this->logStore, $installRoot, $emitter);
            if ((($summary['status'] ?? 'pass') === 'fail')) {
                $this->emitDecisionBlock($installRoot, 'break', 'VERIFICATION_FAILED');
                return new DecisionResult(status: 'break', summary: $summary);
            }

            // Phase 2 — Optional File Scan (part of validation block)
            if ($this->fileScanEnabled) {
                $fsSection = new FileScanSection();
                $emitCallable = $this->emitterMux ? fn(array $p) => $this->emitterMux->emit($p) : null;
                $fingerprint = $summary['meta']['fingerprint'] ?? ($summary['fingerprint'] ?? '');
                $configHash = $summary['meta']['validator_config_hash'] ?? ($summary['validator_config_hash'] ?? '');
                $actor = $this->actorResolver?->resolve() ?? (string)($summary['meta']['actor'] ?? 'system');

                $fs = $fsSection->run(
                    logStore: $this->logStore,
                    stagingRoot: $stagingRoot,
                    installRoot: $installRoot,
                    enabled: true,
                    onFileScanError: $this->_onFileScanError,
                    tokenManager: $this->tokenManager ?? new InstallerTokenManager(),
                    emit: $emitCallable,
                    zipId: $plugin_zip_id,
                    fingerprint: is_string($fingerprint) ? $fingerprint : '',
                    configHash: is_string($configHash) ? $configHash : '',
                    actor: is_string($actor) ? $actor : 'system',
                    validator: $this->validator
                );

                if (($fs['status'] ?? 'continue') === 'ask') {
                    // Persist decision snapshot and return
                    $this->logStore->setDecision($installRoot, [
                        'status' => 'ask',
                        'reason' => 'file_scan_errors',
                        'token' => [
                            'purpose' => 'install_override',
                            'expiresAt' => $fs['expiresAt'] ?? null,
                        ],
                    ]);
                    // Call onValidationEnd once at the end of validation block (includes file scan)
                    if (is_callable($this->_onValidationEnd)) {
                        try {
                            ($this->_onValidationEnd)($summary);
                        } catch (Throwable $_) {
                        }
                    }
                    return new DecisionResult(status: 'ask', summary: $summary, tokenEncrypted: $fs['tokenEncrypted'] ?? null, expiresAt: $fs['expiresAt'] ?? null);
                }
                if (($fs['status'] ?? 'continue') === 'break') {
                    $this->logStore->setDecision($installRoot, [
                        'status' => 'break',
                        'reason' => 'file_scan_errors',
                    ]);
                    // Call onValidationEnd before returning as validation block concluded
                    if (is_callable($this->_onValidationEnd)) {
                        try {
                            ($this->_onValidationEnd)($summary);
                        } catch (Throwable $_) {
                        }
                    }
                    return new DecisionResult(status: 'break', summary: $summary);
                }
            }

            // _onValidationEnd — exactly once after validation block (mandatory + optional file scan), before ZipValidationGate
            if (is_callable($this->_onValidationEnd)) {
                try {
                    ($this->_onValidationEnd)($summary);
                } catch (Throwable $_) {
                }
            }

            // Phase 3 — Zip validation gate (after full validation block)
            if ($this->zipRepository) {
                $zipGate = new ZipValidationGate();
                $fingerprint = $summary['meta']['fingerprint'] ?? ($summary['fingerprint'] ?? '');
                $configHash = $summary['meta']['validator_config_hash'] ?? ($summary['validator_config_hash'] ?? '');
                $actor = $this->actorResolver?->resolve() ?? (string)($summary['meta']['actor'] ?? 'system');
                $emitCallable = $this->emitterMux ? fn(array $p) => $this->emitterMux->emit($p) : null;

                $gate = $zipGate->run(
                    $this->zipRepository,
                    $this->logStore,
                    $installRoot,
                    $plugin_zip_id,
                    $this->tokenManager ?? new InstallerTokenManager(),
                    $emitCallable,
                    is_string($fingerprint) ? $fingerprint : '',
                    is_string($configHash) ? $configHash : '',
                    is_string($actor) ? $actor : 'system',
                    $this->fileScanEnabled,
                    is_array($summary) ? $summary : [],
                );

                if ($gate['status'] === 'ask') {
                    return new DecisionResult(status: 'ask', summary: $summary, tokenEncrypted: $gate['tokenEncrypted'] ?? null, expiresAt: $gate['expiresAt'] ?? null);
                }
                if ($gate['status'] === 'break') {
                    return new DecisionResult(status: 'break', summary: $summary);
                }
            }

            // Phase 4 — Vendor Policy
            $emitCallable = $this->emitterMux ? fn(array $p) => $this->emitterMux->emit($p) : null;
            $vendorSection = new VendorPolicySection();
            $vendorSection->run($this->policy, $this->logStore, $installRoot, $emitCallable);

            // Phase 5 — Composer Plan (+ Packages Map)
            $composerPlan = (new ComposerPlanSection())
                ->run(new ComposerInspector(), $this->logStore, $stagingRoot, $installRoot, $emitCallable);

            // Phase 6 — Install Files (atomic copy & promote)
            $installResult = (new InstallFilesSection())
                ->run($this->logStore, $stagingRoot, $installRoot, $emitCallable);

            if (($installResult['status'] ?? '') !== 'installed') {
                // Failure in copy/promote must break per DoD
                $this->emitDecisionBlock($installRoot, 'break', 'INSTALL_COPY_OR_PROMOTION_FAILED');
                return new DecisionResult(status: 'break', summary: $summary);
            }

            // Phase 7 — DB Persist
            $emitCallable = $this->emitterMux ? fn(array $p) => $this->emitterMux->emit($p) : null;
            if ($this->pluginRepository) {
                $db = (new DbPersistSection())
                    ->run($this->pluginRepository, $this->logStore, $installRoot, $plugin_zip_id, $emitCallable);
                if (($db['status'] ?? 'failed') !== 'ok') {
                    $this->emitDecisionBlock($installRoot, 'break', 'DB_PERSIST_FAILED');
                    return new DecisionResult(status: 'break', summary: $summary);
                }
            } else {
                // No repository bound — emit a skip note
                $emitCallable && $emitCallable([
                    'title' => 'Installer: DB Persist',
                    'description' => 'Skipped (no PluginRepository bound)',
                    'error' => null,
                    'stats' => ['filePath' => null, 'size' => null],
                    'meta' => [],
                ]);
            }

            // Phase 7 done
            $this->emitDecisionBlock($installRoot, 'installed');
            return new DecisionResult(status: 'installed', summary: $summary);
        } finally {
            if ($locked) {
                try {
                    $this->lockManager?->release((string)$plugin_zip_id);
                } catch (Throwable $_) {
                }
            }
        }
    }
}
