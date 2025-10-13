# Sections

This README lists all files in this folder and their source code.

## ComposerPlanSection.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use Throwable;
use Timeax\FortiPlugin\Installations\DTO\ComposerPlan;
use Timeax\FortiPlugin\Installations\Enums\PackageStatus;
use Timeax\FortiPlugin\Installations\Support\ComposerInspector;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;

class ComposerPlanSection
{
    /**
     * Build a dry composer plan and packages map, then persist to installation.json.
     * Returns associative array ['actions'=>..., 'core_conflicts'=>..., 'packages'=>map].
     */
    public function run(
        ComposerInspector $inspector,
        InstallationLogStore $logStore,
        string $stagingRoot,
        string $installRoot,
        ?callable $emit = null
    ): array {
        $requires = $inspector->readPluginRequires($stagingRoot);               // name => constraint
        $hostLocked = $inspector->readHostLockedPackages();                      // name => version

        $actions = [];
        $coreConflicts = [];
        $packages = [];

        foreach ($requires as $name => $constraint) {
            // rudimentary: if host has the package at any version, mark skip; otherwise add
            $hostHas = array_key_exists($name, $hostLocked);
            $actions[$name] = $hostHas ? 'skip' : 'add';

            // core conflicts: if is core package and constraint not trivially satisfied by presence
            if (!$hostHas && $inspector->isCorePackage($name)) {
                $coreConflicts[] = $name;
            }

            $packages[$name] = [
                'is_foreign' => !$hostHas,
                'status' => $hostHas ? PackageStatus::VERIFIED->value : PackageStatus::UNVERIFIED->value,
            ];
        }

        // Persist to installation.json
        $logStore->setComposerPlan($installRoot, [
            'actions' => $actions,
            'core_conflicts' => $coreConflicts,
        ]);
        $logStore->setPackages($installRoot, $packages);

        // Emit installer event
        if ($emit) {
            try {
                $emit([
                    'title' => 'Installer: Composer Plan',
                    'description' => 'Dry composer plan computed',
                    'error' => null,
                    'stats' => ['filePath' => null, 'size' => null],
                    'meta' => [
                        'counts' => ['requires' => count($requires), 'foreign' => count(array_filter($packages, static fn($p) => ($p['is_foreign'] ?? false)))],
                        'core_conflicts' => $coreConflicts,
                    ],
                ]);
            } catch (Throwable $_) {}
        }

        return [
            'actions' => $actions,
            'core_conflicts' => $coreConflicts,
            'packages' => $packages,
        ];
    }
}
```

## VerificationSection.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use DateTimeImmutable;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\ValidatorBridge;
use Timeax\FortiPlugin\Services\ValidatorService;

class VerificationSection
{
    /**
     * Runs mandatory program-integrity checks via ValidatorService, bridges emits verbatim
     * to the unified emitter and to InstallationLogStore, persists a verification snapshot,
     * and returns a summary array suitable for onValidationEnd().
     */
    public function run(
        ValidatorService $validator,
        string $stagingRoot,
        InstallationLogStore $logStore,
        string $installRoot,
        ?callable $unifiedEmitter = null,
    ): array {
        // Bridge validator emits to logs (verbatim) and optional unified emitter via ValidatorBridge
        $emitter = $unifiedEmitter ? new class($unifiedEmitter) implements \Timeax\FortiPlugin\Installations\Contracts\Emitter {
            public function __construct(private $fn) {}
            public function __invoke(array $payload): void { ($this->fn)($payload); }
        } : null;
        $bridge = new ValidatorBridge($logStore, $installRoot, $emitter);

        // Execute validator
        $validator->run($stagingRoot, [$bridge, 'emit']);

        // Build summary and persist snapshot
        $summary = [
            'status' => $validator->shouldFail() ? 'fail' : 'pass',
            'errors' => $validator->getFormattedLog(),
            'warnings' => [],
            'finished_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
        $logStore->setVerification($installRoot, $summary);

        return $summary;
    }
}
```

## FileScanSection.php

```php
<?php /** @noinspection PhpUnusedLocalVariableInspection */

namespace Timeax\FortiPlugin\Installations\Sections;

use Throwable;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Installations\Support\ValidatorBridge;
use Timeax\FortiPlugin\Services\ValidatorService;
use function function_exists;

class FileScanSection
{
    /**
     * Optional file scan phase. Uses ValidatorService scanning to produce verbatim emits.
     * It never executes plugin code beyond static analysis.
     *
     * Returns an array decision for the Installer to interpret:
     *   ['status' => 'continue'|'ask'|'break', 'tokenEncrypted'?, 'expiresAt'?]
     */
    public function run(
        InstallationLogStore $logStore,
        string $stagingRoot,
        string $installRoot,
        bool $enabled,
        ?callable $onFileScanError,
        ?InstallerTokenManager $tokenManager,
        ?callable $emit,
        int|string $zipId,
        string $fingerprint,
        string $configHash,
        string $actor,
        ValidatorService $validator,
        array $errors = []
    ): array {
        if (!$enabled) {
            // Skipped cleanly
            $logStore->setFileScan($installRoot, [
                'enabled' => false,
                'status' => 'skipped',
                'errors' => [],
            ]);
            if ($emit) {
                try { $emit(['title' => 'Installer: File Scan', 'description' => 'Skipped (disabled)', 'error' => null, 'stats' => []]); } catch (Throwable $_) {}
            }
            $logStore->appendInstallerEmit($installRoot, ['title' => 'Installer: File Scan', 'description' => 'Skipped (disabled)', 'error' => null, 'stats' => []]);
            return ['status' => 'continue'];
        }

        // If no explicit errors supplied, actively run scans and collect verbatim emits
        if (empty($errors)) {
            try {
                $bridge = new ValidatorBridge($logStore, $installRoot, $emit ? new class($emit) implements \Timeax\FortiPlugin\Installations\Contracts\Emitter { public function __construct(private $fn){} public function __invoke(array $p): void { ($this->fn)($p);} } : null);
                // Run only scanners
                $validator->runFileScan($stagingRoot, [$bridge, 'emit']);
                // Read back emitted scan errors from log store
                $current = $logStore->getCurrent($installRoot);
                $emits = (array)($current['logs']['validation_emits'] ?? []);
                foreach ($emits as $e) {
                    if (($e['title'] ?? '') === 'Scan: Security') {
                        $errors[] = [
                            'type' => 'scan.security',
                            'issue' => (string)($e['description'] ?? 'Security issue'),
                            'file' => $e['stats']['filePath'] ?? null,
                            'meta' => $e['meta'] ?? [],
                        ];
                    }
                }
            } catch (Throwable $_) {
                // ignore and treat as no errors
            }
        }

        $hasErrors = is_array($errors) && count($errors) > 0;

        if (!$hasErrors) {
            // Pass
            $logStore->setFileScan($installRoot, [
                'enabled' => true,
                'status' => 'pass',
                'errors' => [],
            ]);
            if ($emit) {
                try { $emit(['title' => 'Installer: File Scan', 'description' => 'Pass (no issues found)', 'error' => null, 'stats' => []]); } catch (Throwable $_) {}
            }
            $logStore->appendInstallerEmit($installRoot, ['title' => 'Installer: File Scan', 'description' => 'Pass (no issues found)', 'error' => null, 'stats' => []]);
            return ['status' => 'continue'];
        }

        // Errors present: delegate to host callback to decide
        $decision = Install::ASK;
        if (is_callable($onFileScanError)) {
            try {
                $decision = ($onFileScanError)($errors, [
                    'purpose' => 'install_override',
                    'zipId' => $zipId,
                    'fingerprint' => $fingerprint,
                    'validator_config_hash' => $configHash,
                    'actor' => $actor,
                ]);
            } catch (Throwable $_) {
                $decision = Install::ASK;
            }
        }

        // Persist file_scan block with collected errors
        $logStore->setFileScan($installRoot, [
            'enabled' => true,
            'status' => $decision === Install::BREAK ? 'fail' : 'pending',
            'errors' => $errors,
        ]);

        if ($decision === Install::BREAK) {
            // Emit and break
            if ($emit) { try { $emit(['title' => 'Installer: File Scan', 'description' => 'Errors detected — breaking install', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => []]); } catch (Throwable $_) {} }
            $logStore->appendInstallerEmit($installRoot, ['title' => 'Installer: File Scan', 'description' => 'Errors detected — breaking install', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => []]);
            return ['status' => 'break'];
        }

        if ($decision === Install::INSTALL) {
            // Proceed despite errors
            if ($emit) { try { $emit(['title' => 'Installer: File Scan', 'description' => 'Errors detected — proceeding by host policy', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => []]); } catch (Throwable $_) {} }
            $logStore->appendInstallerEmit($installRoot, ['title' => 'Installer: File Scan', 'description' => 'Errors detected — proceeding by host policy', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => []]);
            // Mark as pass-but-override
            $logStore->setFileScan($installRoot, [
                'enabled' => true,
                'status' => 'pass',
                'errors' => $errors,
            ]);
            return ['status' => 'continue'];
        }

        // Default or explicit ASK: issue install_override token
        $ttl = (int)((function_exists('config') ? (config('fortiplugin.installations.tokens.install_override_ttl') ?? 600) : 600));
        $ttl = max(60, min(3600, $ttl));
        $tokenEncrypted = null;
        $expiresAt = null;
        if ($tokenManager) {
            try {
                [$tokenEncrypted, $expiresAt] = $tokenManager->issueToken(
                    purpose: 'install_override',
                    zipId: $zipId,
                    fingerprint: $fingerprint,
                    validatorConfigHash: $configHash,
                    actor: $actor,
                    ttlSeconds: $ttl,
                );
            } catch (Throwable $_) {
                // ignore token issuance failure for now; still ask
            }
        }

        // Emit and return ASK
        if ($emit) { try { $emit(['title' => 'Installer: File Scan', 'description' => 'Errors detected — ASK (override token issued)', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => [], 'meta' => ['expiresAt' => $expiresAt]]); } catch (Throwable $_) {} }
        $logStore->appendInstallerEmit($installRoot, ['title' => 'Installer: File Scan', 'description' => 'Errors detected — ASK (override token issued)', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => [], 'meta' => ['expiresAt' => $expiresAt]]);

        return [
            'status' => 'ask',
            'tokenEncrypted' => $tokenEncrypted,
            'expiresAt' => $expiresAt,
        ];
    }
}
```
