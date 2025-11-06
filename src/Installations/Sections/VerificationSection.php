<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use Throwable;
use Timeax\FortiPlugin\Services\ValidatorService;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\Events;
use Timeax\FortiPlugin\Installations\Support\ErrorCodes;
use Timeax\FortiPlugin\Installations\Support\EmitsEvents;
use Timeax\FortiPlugin\Installations\Support\Psr4Checker;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;

/**
 * VerificationSection
 *
 * - PSR-4 mapping assert.
 * - Route files mandatory.
 * - Runs HEADLINE validators only (scanners OFF here).
 * - Streams validator emits verbatim into InstallationLogStore.
 * - Persists a compact "verification" section summary.
 *
 * NOTE: onValidationEnd() is **owned by ValidatorBridge** and called there once.
 */
final class VerificationSection
{
    use EmitsEvents;

    public function __construct(
        private readonly InstallerPolicy      $policy,
        private readonly InstallationLogStore $log,
        private readonly Psr4Checker          $psr4,
    ) {}

    /**
     * @param string           $pluginDir         Plugin root on disk
     * @param string           $pluginName        Unique plugin name
     * @param string           $run_id            Correlation id
     * @param ValidatorService $validator         Validation service
     * @param array            $validatorConfig   Must include headline.route_files[]
     * @param callable|null    $emitValidation    fn(array $payload): void  (validator emits passthrough)
     * @return array{status:'ok'|'fail', summary?:array}
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        string           $pluginDir,
        string           $pluginName,
        string           $run_id,
        ValidatorService $validator,
        array            $validatorConfig,
        ?callable        $emitValidation = null
    ): array {
        // 0) Mandatory route files
        $routeFiles = (array)($validatorConfig['headline']['route_files'] ?? []);

        if ($routeFiles === []) {
            $this->emitFail(
                Events::ROUTES_CHECK_FAIL,
                ErrorCodes::ROUTE_SCHEMA_ERROR,
                'Route files missing: route validation is mandatory',
                ['hint' => 'Provide headline.route_files[]', 'plugin_dir' => $pluginDir]
            );
            return ['status' => 'fail'];
        }

        // 1) PSR-4 assert for this plugin
        $psr4Root     = $this->policy->getPsr4Root();
        $composerJson = $pluginDir . DIRECTORY_SEPARATOR . 'composer.json';

        $this->emitOk(Events::PSR4_CHECK_START, "Checking PSR-4 for $pluginName", [
            'psr4_root' => $psr4Root,
            'plugin'    => $pluginName,
            'composer'  => $composerJson,
        ]);

        try {
            $this->psr4->assertMapping($composerJson, $psr4Root, $pluginName);
            $this->emitOk(Events::PSR4_CHECK_OK, 'PSR-4 mapping OK', ['composer' => $composerJson]);
        } catch (Throwable $e) {
            $this->emitFail(
                Events::PSR4_CHECK_FAIL,
                ErrorCodes::COMPOSER_PSR4_MISSING_OR_MISMATCH,
                'Expected PSR-4 mapping is missing or mismatched',
                ['composer' => $composerJson, 'exception' => $e->getMessage()],
                $composerJson
            );
            return ['status' => 'fail'];
        }

        // 2) Headline validators only (disable scanning stack)
        $validator->setIgnoredValidators(['file_scanner', 'content', 'token', 'ast']);

        // Stream validator emits VERBATIM → log store (+ optional passthrough)
        $forward = function (array $payload) use ($emitValidation): void {
            try { $this->log->appendValidationEmit($payload); } catch (JsonException $_) {}
            if ($emitValidation) $emitValidation($payload);
        };

        $this->emitOk(Events::VALIDATION_START, 'Running headline validators');
        $summary = $validator->run($pluginDir, $forward);
        $this->emitOk(Events::VALIDATION_END, 'Headline validators completed', [
            'total_issues' => $summary['total_issues'] ?? null,
            'files_scanned'=> $summary['files_scanned'] ?? null,
        ]);

        // 3) Persist compact verification section (summary only; emits already recorded)
        try {
            $this->log->writeSection('verification', [
                'summary' => $summary,
                'run_id'  => $run_id,
            ]);
            $this->emitOk(Events::SUMMARY_PERSISTED, 'Verification summary persisted', ['path' => $this->log->path()]);
        } catch (Throwable $e) {
            $this->emitFail(
                Events::SUMMARY_PERSISTED,
                ErrorCodes::FILESYSTEM_WRITE_FAILED,
                'Failed to persist verification summary',
                ['exception' => $e->getMessage(), 'plugin_dir' => $pluginDir]
            );
        }

        // 4) Decide (break if policy says to on headline errors)
        if (($summary['should_fail'] ?? false) && $this->policy->shouldBreakOnVerificationErrors()) {
            return ['status' => 'fail', 'summary' => $summary];
        }

        return ['status' => 'ok', 'summary' => $summary];
    }
}