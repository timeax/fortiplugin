<?php

namespace Timeax\FortiPlugin\Installations;

use Timeax\FortiPlugin\Installations\Contracts\Emitter;
use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Contracts\ActorResolver;
use Timeax\FortiPlugin\Installations\DTO\DecisionResult;
use Timeax\FortiPlugin\Installations\Support\EmitterMux;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Installations\Support\DefaultActorResolver;

class Installer
{
    private ?EmitterMux $emitterMux = null;
    private bool $fileScanEnabled = false;

    /** @var callable|null */
    private $onFileScanError = null;

    /** @var callable|null */
    private $onValidationEnd = null;

    private ?\Timeax\FortiPlugin\Services\ValidatorService $validator = null;

    public function __construct(
        private readonly InstallerPolicy $policy,
        private readonly InstallationLogStore $logStore,
        private readonly ?\Timeax\FortiPlugin\Installations\Contracts\LockManager $lockManager = null,
        private readonly ?ZipRepository $zipRepository = null,
        private readonly ?InstallerTokenManager $tokenManager = null,
        private readonly ?ActorResolver $actorResolver = null,
    ) {
    }

    public function withValidator(\Timeax\FortiPlugin\Services\ValidatorService $validator): self
    {
        $this->validator = $validator;
        return $this;
    }

    public function emitWith(callable $fn): self
    {
        // Wrap the provided callable in an Emitter implementation
        $emitter = new class($fn) implements Emitter {
            public function __construct(private $fn) {}
            public function __invoke(array $payload): void { ($this->fn)($payload); }
        };
        $this->emitterMux = new EmitterMux($emitter);
        return $this;
    }

    public function enableFileScan(): self
    {
        $this->fileScanEnabled = true;
        $this->policy->enableFileScan(true);
        return $this;
    }

    /**
     * Callback invoked when optional file scanning finds issues.
     */
    public function onFileScanError(callable $cb): self
    {
        $this->onFileScanError = $cb;
        return $this;
    }

    /**
     * Callback invoked after mandatory verification completes.
     */
    public function onValidationEnd(callable $cb): self
    {
        $this->onValidationEnd = $cb;
        return $this;
    }

    /**
     * Phase 2 wiring: run VerificationSection then ZipValidationGate.
     */
    public function install(int|string $plugin_zip_id, ?string $installer_token = null): DecisionResult
    {
        $stagingRoot = (string)(\function_exists('config') ? (config('fortiplugin.staging_root') ?? sys_get_temp_dir()) : sys_get_temp_dir());
        $installRoot = (string)(\function_exists('config') ? (config('fortiplugin.directory') ?? 'Plugins') : 'Plugins');
        $installRoot = rtrim($installRoot, "\\/ ");

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
                $now = new \DateTimeImmutable('now');
                $notExpired = true;
                if ($expiresAt) {
                    try { $notExpired = (new \DateTimeImmutable($expiresAt)) > $now; } catch (\Throwable $_) { $notExpired = false; }
                }

                if (!$notExpired) {
                    return new DecisionResult(status: 'break', summary: ['status' => 'fail', 'errors' => ['TOKEN_INVALID_OR_EXPIRED']]);
                }

                // Minimal validation: ensure last decision zip_id (if present) matches
                $lastZipId = (string)($decision['zip_id'] ?? '');
                if ($lastZipId !== '' && $lastZipId !== (string)$plugin_zip_id) {
                    return new DecisionResult(status: 'break', summary: ['status' => 'fail', 'errors' => ['TOKEN_ZIP_MISMATCH']]);
                }

                $emitCallable = $this->emitterMux ? fn(array $p) => $this->emitterMux->emit($p) : null;

                if ($purpose === 'background_scan') {
                    // Read file_scan errors and route via onFileScanError
                    $fileScan = (array)($state['file_scan'] ?? []);
                    $errors = (array)($fileScan['errors'] ?? []);
                    if ($errors) {
                        $decisionEnum = \Timeax\FortiPlugin\Installations\Enums\Install::ASK;
                        if (is_callable($this->onFileScanError)) {
                            try {
                                $decisionEnum = ($this->onFileScanError)($errors, [
                                    'purpose' => 'install_override',
                                    'zipId' => $plugin_zip_id,
                                    'fingerprint' => (string)($state['meta']['fingerprint'] ?? ''),
                                    'validator_config_hash' => (string)($state['meta']['validator_config_hash'] ?? ''),
                                    'actor' => $this->actorResolver?->resolve() ?? 'system',
                                ]);
                            } catch (\Throwable $_) { $decisionEnum = \Timeax\FortiPlugin\Installations\Enums\Install::ASK; }
                        }
                        if ($decisionEnum === \Timeax\FortiPlugin\Installations\Enums\Install::BREAK) {
                            $this->logStore->setDecision($installRoot, [
                                'status' => 'break',
                                'reason' => 'file_scan_errors',
                            ]);
                            return new DecisionResult(status: 'break', summary: $state);
                        }
                        if ($decisionEnum === \Timeax\FortiPlugin\Installations\Enums\Install::ASK) {
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
                return new DecisionResult(status: 'break', summary: ['status' => 'fail', 'errors' => ['TOKEN_PURPOSE_UNKNOWN']]);
            }

            if (!$this->validator) {
                return new DecisionResult(status: 'break', summary: ['status' => 'fail', 'errors' => ['ValidatorService not provided']]);
            }

            $section = new \Timeax\FortiPlugin\Installations\Sections\VerificationSection();
            $emitter = $this->emitterMux ? fn(array $p) => $this->emitterMux->emit($p) : null;
            $summary = $section->run($this->validator, $stagingRoot, $this->logStore, $installRoot, $emitter);
            if ((($summary['status'] ?? 'pass') === 'fail')) {
                return new DecisionResult(status: 'break', summary: $summary);
            }

            // Phase 2 — Optional File Scan (part of validation block)
            if ($this->fileScanEnabled) {
                $fsSection = new \Timeax\FortiPlugin\Installations\Sections\FileScanSection();
                $emitCallable = $this->emitterMux ? fn(array $p) => $this->emitterMux->emit($p) : null;
                $fingerprint = $summary['meta']['fingerprint'] ?? ($summary['fingerprint'] ?? '');
                $configHash = $summary['meta']['validator_config_hash'] ?? ($summary['validator_config_hash'] ?? '');
                $actor = $this->actorResolver?->resolve() ?? (string)($summary['meta']['actor'] ?? 'system');

                $fs = $fsSection->run(
                    $this->logStore,
                    $installRoot,
                    true,
                    $this->onFileScanError,
                    $this->tokenManager ?? new InstallerTokenManager(),
                    $emitCallable,
                    $plugin_zip_id,
                    is_string($fingerprint) ? $fingerprint : '',
                    is_string($configHash) ? $configHash : '',
                    is_string($actor) ? $actor : 'system',
                    []
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
                    if (is_callable($this->onValidationEnd)) {
                        try { ($this->onValidationEnd)($summary); } catch (\Throwable $_) {}
                    }
                    return new DecisionResult(status: 'ask', summary: $summary, tokenEncrypted: $fs['tokenEncrypted'] ?? null, expiresAt: $fs['expiresAt'] ?? null);
                }
                if (($fs['status'] ?? 'continue') === 'break') {
                    $this->logStore->setDecision($installRoot, [
                        'status' => 'break',
                        'reason' => 'file_scan_errors',
                    ]);
                    // Call onValidationEnd before returning as validation block concluded
                    if (is_callable($this->onValidationEnd)) {
                        try { ($this->onValidationEnd)($summary); } catch (\Throwable $_) {}
                    }
                    return new DecisionResult(status: 'break', summary: $summary);
                }
            }

            // onValidationEnd — exactly once after validation block (mandatory + optional file scan), before ZipValidationGate
            if (is_callable($this->onValidationEnd)) {
                try { ($this->onValidationEnd)($summary); } catch (\Throwable $_) {}
            }

            // Phase 3 — Zip validation gate (after full validation block)
            if ($this->zipRepository) {
                $zipGate = new \Timeax\FortiPlugin\Installations\Sections\ZipValidationGate();
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

            // Continue to next phases (not yet implemented)
            return new DecisionResult(status: 'ask', summary: $summary);
        } finally {
            if ($locked) {
                try { $this->lockManager?->release((string)$plugin_zip_id); } catch (\Throwable $_) {}
            }
        }
    }
}
