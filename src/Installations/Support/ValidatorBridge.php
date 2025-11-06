<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use Random\RandomException;
use Timeax\FortiPlugin\Installations\DTO\InstallSummary;
use Timeax\FortiPlugin\Installations\Enums\Install;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Sections\FileScanSection;
use Timeax\FortiPlugin\Installations\Sections\VerificationSection;
use Timeax\FortiPlugin\Services\ValidatorService;

/**
 * ValidatorBridge
 *
 * Bridges VerificationSection and FileScanSection:
 *  - Runs Verification first (routes mandatory; PSR-4 checks live in the section).
 *  - If policy enables file scan, runs FileScan next (scanner-only, no background).
 *  - Forwards a single $emit callback to both sections (verbatim).
 *  - Composes a single InstallSummary DTO and calls onValidationEnd($summary) exactly once.
 *
 * NOTE: Sections persist their own raw emits/log blocks. The bridge only orchestrates and composes the DTO.
 */
final readonly class ValidatorBridge
{
    public function __construct(
        private VerificationSection $verification,
        private FileScanSection     $fileScan,
        private InstallerPolicy     $policy,
    )
    {
    }

    /**
     * Orchestrate verification + (optional) file-scan and return merged summary + scan decision/meta.
     *
     * @param string $pluginDir Unpacked plugin root
     * @param string $pluginName Plugin’s unique name (namespace segment)
     * @param int|string $zipId PluginZip id
     * @param ValidatorService $validator Shared validator instance
     * @param array<string,mixed> $validatorConfig Must include headline.route_files[] etc. (used by sections)
     * @param string $validatorConfigHash Stable hash for token binding (passed to file scan)
     * @param string $actor Actor id or 'system'
     * @param string $runId Correlation id for this install run
     * @param callable|null $emit fn(array $payload): void — verbatim passthrough to sections
     * @param callable|null $onValidationEnd fn(InstallSummary $summary): void — called here ONCE
     * @param callable|null $onFileScanError fn(array $summary, string $token): Install — passed to FileScan
     *
     * @return array{
     *   summary: InstallSummary,
     *   decision?: Install,
     *   meta?: array<string,mixed>
     * }
     * @throws JsonException
     * @throws RandomException
     */
    public function run(
        string           $pluginDir,
        string           $pluginName,
        int|string       $zipId,
        ValidatorService $validator,
        array            $validatorConfig,
        string           $validatorConfigHash,
        string           $actor,
        string           $runId,
        ?callable        $emit = null,
        ?callable        $onValidationEnd = null,
        ?callable        $onFileScanError = null
    ): array
    {
        // 1) VERIFICATION
        $ver = $this->verification->run(
            pluginDir: $pluginDir,
            pluginName: $pluginName,
            run_id: $runId,
            validator: $validator,
            validatorConfig: $validatorConfig,
            emitValidation: $emit
        );

        // Section returns ['status'=>'ok'|'fail','summary'=>...] (summary optional)
        $verificationSection = $ver['summary']
            ?? ['status' => $ver['status'] ?? 'fail', 'errors' => [], 'warnings' => []];

        // 2) FILE SCAN (optional per policy)
        /** @noinspection DuplicatedCode */
        $scanEnabled = $this->policy->isFileScanEnabled();

        $fileScanSection = [
            'enabled' => false,
            'status' => 'skipped',
            'errors' => [],
        ];
        $decision = null;
        $meta = null;

        if ($scanEnabled) {
            // Ensure onValidationEnd is called ONLY by the bridge (pass null into section)
            $scan = $this->fileScan->run(
                pluginDir: $pluginDir,
                zipId: $zipId,
                validator: $validator,
                validatorConfigHash: $validatorConfigHash,
                actor: $actor,
                runId: $runId,
                onFileScanError: $onFileScanError,
                emitValidation: $emit
            );

            // FileScanSection returns: ['decision' => Install::*, 'meta' => array]
            $decision = $scan['decision'] ?? null;
            $meta = $scan['meta'] ?? null;

            // Compose a minimal file_scan snapshot from validator state
            $fileScanSection = [
                'enabled' => true,
                'status' => $validator->shouldFail() ? 'fail' : 'ok',
                'errors' => [], // can be enriched later from validator logs if desired
            ];
        }

        // 3) Compose DTO
        $summary = new InstallSummary(
            verification: $verificationSection,
            file_scan: $fileScanSection,
            zip_validation: null,
            vendor_policy: null,
            composer_plan: null,
            packages: null
        );

        // 4) Invoke onValidationEnd ONCE at the boundary
        if (is_callable($onValidationEnd)) {
            $onValidationEnd($summary);
        }

        // 5) Return merged summary plus (optional) scan decision/meta for Installer to act on
        $out = ['summary' => $summary];
        if ($decision instanceof Install) {
            $out['decision'] = $decision;
        }
        if (is_array($meta)) {
            $out['meta'] = $meta;
        }

        return $out;
    }
}