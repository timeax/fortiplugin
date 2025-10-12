<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;

class ZipValidationGate
{
    /**
     * Run zip validation gate. Returns array with keys: status ('continue'|'ask'|'break'),
     * tokenEncrypted?, expiresAt?, zipStatus (enum value string), decision? (array for log).
     */
    public function run(
        ZipRepository $zipRepo,
        InstallationLogStore $logStore,
        string $installRoot,
        int|string $zipId,
        InstallerTokenManager $tokenMgr,
        ?callable $emit = null,
        ?string $fingerprint = null,
        ?string $configHash = null,
        ?string $actor = 'system',
        bool $fileScanEnabled = false,
        array $summary = [],
    ): array {
        $status = $zipRepo->getValidationStatus($zipId);
        $zipValidation = [
            'plugin_zip_status' => $status->value,
        ];
        $logStore->setZipValidation($installRoot, $zipValidation);

        $emit && $emit([
            'title' => 'Installer: Zip Validation',
            'description' => 'Zip status: ' . $status->value,
            'error' => null,
            'stats' => ['filePath' => null, 'size' => null],
            'meta' => ['zipId' => (string)$zipId, 'status' => $status->value],
        ]);

        $decisionCommon = function(string $statusLabel, string $reason) use ($zipId, $fingerprint, $configHash, $fileScanEnabled, $summary): array {
            $now = (new \DateTimeImmutable('now'))->format(DATE_ATOM);
            $runId = bin2hex(random_bytes(8));
            return [
                'status' => $statusLabel,
                'reason' => $reason,
                'timestamp' => $now,
                'run_id' => $runId,
                'zip_id' => (string)$zipId,
                'fingerprint' => (string)($fingerprint ?? ''),
                'validator_config_hash' => (string)($configHash ?? ''),
                'file_scan_enabled' => $fileScanEnabled,
                'last_error_codes' => $summary['errors'] ?? [],
                'counts' => [
                    'validation_errors' => (int)($summary['counts']['validation_errors'] ?? 0),
                    'scan_errors' => (int)($summary['counts']['scan_errors'] ?? 0),
                ],
            ];
        };

        if ($status === ZipValidationStatus::VERIFIED) {
            return ['status' => 'continue', 'zipStatus' => $status->value];
        }

        if ($status === ZipValidationStatus::PENDING) {
            [$token, $expiresAt, $ctx] = $tokenMgr->issueBackgroundScanToken($zipId, $fingerprint ?? '', $configHash ?? '', $actor ?? '');
            $decision = $decisionCommon('ask', 'ZIP_VALIDATION_PENDING');
            $decision['token'] = [
                'purpose' => 'background_scan',
                'expires_at' => $expiresAt,
            ];
            $logStore->setDecision($installRoot, $decision);
            $emit && $emit([
                'title' => 'Installer: Decision ask',
                'description' => 'Zip validation pending',
                'error' => null,
                'stats' => ['filePath' => null, 'size' => null],
                'meta' => $decision,
            ]);
            return ['status' => 'ask', 'zipStatus' => $status->value, 'tokenEncrypted' => $token, 'expiresAt' => $expiresAt, 'decision' => $decision];
        }

        // failed or unknown
        $decision = $decisionCommon('break', 'ZIP_VALIDATION_FAILED');
        $logStore->setDecision($installRoot, $decision);
        $emit && $emit([
            'title' => 'Installer: Decision break',
            'description' => 'Zip validation failed',
            'error' => ['detail' => 'ZIP_VALIDATION_FAILED', 'count' => 1],
            'stats' => ['filePath' => null, 'size' => null],
            'meta' => $decision,
        ]);
        return ['status' => 'break', 'zipStatus' => $status->value, 'decision' => $decision];
    }
}
