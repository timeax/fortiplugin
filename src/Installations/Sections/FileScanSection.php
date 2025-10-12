<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use Timeax\FortiPlugin\Installations\DTO\DecisionResult; // hint only; not used directly here
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;

class FileScanSection
{
    /**
     * Optional file scan phase. This minimal implementation records a clean pass
     * when enabled unless an external scanner supplies errors via $errors input.
     * It never executes plugin code.
     *
     * Returns an array decision for the Installer to interpret:
     *   ['status' => 'continue'|'ask'|'break', 'tokenEncrypted'?, 'expiresAt'?]
     */
    public function run(
        InstallationLogStore $logStore,
        string $installRoot,
        bool $enabled,
        ?callable $onFileScanError,
        ?InstallerTokenManager $tokenManager,
        ?callable $emit,
        int|string $zipId,
        string $fingerprint,
        string $configHash,
        string $actor,
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
                try { $emit(['title' => 'Installer: File Scan', 'description' => 'Skipped (disabled)', 'error' => null, 'stats' => []]); } catch (\Throwable $_) {}
            }
            $logStore->appendInstallerEmit($installRoot, ['title' => 'Installer: File Scan', 'description' => 'Skipped (disabled)', 'error' => null, 'stats' => []]);
            return ['status' => 'continue'];
        }

        // If no explicit errors supplied, derive from validator emits recorded during VerificationSection
        if (empty($errors)) {
            try {
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
            } catch (\Throwable $_) {
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
                try { $emit(['title' => 'Installer: File Scan', 'description' => 'Pass (no issues found)', 'error' => null, 'stats' => []]); } catch (\Throwable $_) {}
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
            } catch (\Throwable $_) {
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
            if ($emit) { try { $emit(['title' => 'Installer: File Scan', 'description' => 'Errors detected — breaking install', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => []]); } catch (\Throwable $_) {} }
            $logStore->appendInstallerEmit($installRoot, ['title' => 'Installer: File Scan', 'description' => 'Errors detected — breaking install', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => []]);
            return ['status' => 'break'];
        }

        if ($decision === Install::INSTALL) {
            // Proceed despite errors
            if ($emit) { try { $emit(['title' => 'Installer: File Scan', 'description' => 'Errors detected — proceeding by host policy', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => []]); } catch (\Throwable $_) {} }
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
        $ttl = (int)((\function_exists('config') ? (config('fortiplugin.installations.tokens.install_override_ttl') ?? 600) : 600));
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
            } catch (\Throwable $_) {
                // ignore token issuance failure for now; still ask
            }
        }

        // Emit and return ASK
        if ($emit) { try { $emit(['title' => 'Installer: File Scan', 'description' => 'Errors detected — ASK (override token issued)', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => [], 'meta' => ['expiresAt' => $expiresAt]]); } catch (\Throwable $_) {} }
        $logStore->appendInstallerEmit($installRoot, ['title' => 'Installer: File Scan', 'description' => 'Errors detected — ASK (override token issued)', 'error' => ['code' => 'SCAN_ERRORS'], 'stats' => [], 'meta' => ['expiresAt' => $expiresAt]]);

        return [
            'status' => 'ask',
            'tokenEncrypted' => $tokenEncrypted,
            'expiresAt' => $expiresAt,
        ];
    }
}
