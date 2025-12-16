<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;
use JsonException;
use Random\RandomException;
use Throwable;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallerTokenManager;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;

/**
 * ZipValidationGate
 *
 * Gate install based on PluginZip.validation_status, coordinating background_scan tokens.
 * - VERIFIED  → INSTALL
 * - PENDING   → ASK (issue/extend background_scan token)
 * - FAILED    → BREAK
 * - UNKNOWN/UNVERIFIED → BREAK
 */
final readonly class ZipValidationGate
{
    use Decision;
    public function __construct(
        private InstallerPolicy       $policy,
        private InstallerTokenManager $tokens,
        private ZipRepository         $zips,
        private AtomicFilesystem      $afs,
    ) {}

    /**
     * @param string $pluginDir
     * @param int|string $zipId
     * @param string $actor
     * @param string $runId
     * @param string $validatorConfigHash
     * @param string|null $installerToken
     * @return array{decision:Install, meta:array}
     * @throws JsonException
     * @throws RandomException
     */
    public function run(
        string   $pluginDir,
        int|string $zipId,
        string   $actor,
        string   $runId,
        string   $validatorConfigHash,
        ?string  $installerToken = null,
        callable $emit
    ): array {
        $status = $this->zips->getValidationStatus($zipId);

        // Try to validate supplied token (best-effort)
        $tokenPurpose = null;
        if (is_string($installerToken) && $installerToken !== '') {
            try {
                $claims = $this->tokens->validate($installerToken);
                $tokenPurpose = $claims->purpose;
            } catch (Throwable $e) {
                $emit([
                    'title' => 'TOKEN_INVALID',
                    'description' => 'Installer token invalid or expired',
                    'meta' => ['zip_id' => (string)$zipId, 'reason' => $e->getMessage()],
                ]);
            }
        }

        $emit(['title' => 'ZIP_STATUS_CHECK', 'description' => 'Evaluating zip validation status', 'meta' => ['zip_id' => (string)$zipId, 'status' => $status->value]]);

        return match ($status) {
            ZipValidationStatus::VERIFIED => $this->allow($pluginDir, $zipId, $emit),
            ZipValidationStatus::PENDING  => $this->pending($pluginDir, $zipId, $actor, $runId, $validatorConfigHash, $tokenPurpose, $emit),
            ZipValidationStatus::FAILED   => $this->deny($pluginDir, $zipId, 'zip_validation_failed', $emit),
            default                       => $this->deny($pluginDir, $zipId, 'zip_validation_unknown', $emit),
        };
    }

    // ── decisions ──────────────────────────────────────────────────────────

    /**
     * @throws JsonException
     */
    private function allow(string $pluginDir, int|string $zipId, callable $emit): array
    {
        $this->persistGate($pluginDir, 'verified');
        $this->persistDecision($pluginDir, Install::INSTALL, 'zip_verified');
        $emit(['title' => 'INSTALL_DECISION', 'description' => 'INSTALL: zip verified', 'meta' => ['zip_id' => (string)$zipId]]);
        return ['decision' => Install::INSTALL, 'meta' => []];
    }

    /**
     * @throws RandomException
     * @throws JsonException
     */
    private function pending(
        string $pluginDir,
        int|string $zipId,
        string $actor,
        string $runId,
        string $validatorConfigHash,
        ?string $tokenPurpose,
        callable $emit
    ): array {
        // idempotent set
        $this->zips->setValidationStatus($zipId, ZipValidationStatus::PENDING);

        $ttl   = $this->policy->getBackgroundScanTtl();
        $token = $this->tokens->issueBackgroundScanToken($zipId, $validatorConfigHash, $actor, $runId, $ttl);
        $summary = $this->tokens->summarize('background_scan', time() + $ttl);

        $this->persistGate($pluginDir, 'pending', $summary);
        $this->persistDecision($pluginDir, Install::ASK, 'background_scans_pending', $summary);
        $emit(['title' => 'INSTALL_DECISION', 'description' => 'ASK: waiting on background scans', 'meta' => ['zip_id' => (string)$zipId]]);

        return ['decision' => Install::ASK, 'meta' => ['token' => $token, 'token_summary' => $summary]];
    }

    /**
     * @throws JsonException
     */
    private function deny(string $pluginDir, int|string $zipId, string $reason, callable $emit): array
    {
        $this->persistGate($pluginDir, $reason === 'zip_validation_failed' ? 'failed' : 'unknown');
        $this->persistDecision($pluginDir, Install::BREAK, $reason);
        $emit(['title' => 'INSTALL_DECISION', 'description' => 'BREAK: zip not eligible', 'meta' => ['zip_id' => (string)$zipId, 'reason' => $reason]]);
        return ['decision' => Install::BREAK, 'meta' => []];
    }

    // ── persistence helpers ────────────────────────────────────────────────

    /**
     * @throws JsonException
     */
    private function persistGate(string $pluginDir, string $status, ?array $tokenSummary = null): void
    {
        $path = $this->installationLogPath($pluginDir);
        $this->afs->ensureParentDirectory($path);

        $doc = $this->afs->fs()->exists($path) ? $this->afs->fs()->readJson($path) : [];
        $doc['zip_gate'] = array_filter([
            'status' => $status,
            'token'  => $tokenSummary, // { purpose, expires_at }
        ]);
        $this->afs->writeJsonAtomic($path, $doc, true);
    }

    private function installationLogPath(string $pluginDir): string
    {
        return rtrim($pluginDir, "\\/") . DIRECTORY_SEPARATOR
            . trim($this->policy->getLogsDirName(), "\\/") . DIRECTORY_SEPARATOR
            . $this->policy->getInstallationLogFilename();
    }
}