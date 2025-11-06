<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation;

use Artisan;
use Illuminate\Support\Facades\DB;
use JsonException;
use Random\RandomException;
use Throwable;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Installations\Activation\Writers\ProvidersRegistryWriter;
use Timeax\FortiPlugin\Installations\Activation\Writers\RoutesRegistryWriter;
use Timeax\FortiPlugin\Installations\Activation\Writers\UiRegistryWriter;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Sections\ZipValidationGate;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginVersion;

final readonly class Activator
{
    public function __construct(
        private InstallerPolicy         $policy,
        private AtomicFilesystem        $afs,
        private ZipValidationGate       $zipGate,
        private RoutesRegistryWriter    $routesWriter,
        private ProvidersRegistryWriter $providersWriter,
        private UiRegistryWriter        $uiWriter,
    )
    {
    }

    /**
     * Activate an already-installed plugin version (stand-alone, not wired to Installer).
     *
     * @param Plugin $plugin
     * @param int|string $versionId
     * @param string $installedPluginRoot Absolute path to the plugin's installed root
     * @param string $actor
     * @param string $runId Correlates with the original installation run
     * @param callable|null $emit Optional domain emits: fn(array $payload): void
     * @return ActivationResult
     * @throws Throwable
     * @throws JsonException
     * @throws RandomException
     */
    public function run(
        Plugin     $plugin,
        int|string $versionId,
        string     $installedPluginRoot,
        string     $actor,
        string     $runId,
        ?callable  $emit = null
    ): ActivationResult
    {
        $fs = $this->afs->fs();

        // ── Preflight & lock (naive mutex via file)
        $lockPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . '.internal' . DIRECTORY_SEPARATOR . 'activate.lock';
        $this->afs->ensureParentDirectory($lockPath);
        $lock = @fopen($lockPath, 'cb+');
        if (!$lock || !@flock($lock, LOCK_EX)) {
            return ActivationResult::fail(['reason' => 'activation_lock_failed']);
        }

        try {
            // Resolve version
            /** @var PluginVersion|null $version */
            $version = PluginVersion::query()->where('id', $versionId)->where('plugin_id', $plugin->id)->first();
            if (!$version) {
                return ActivationResult::fail(['reason' => 'version_not_found', 'version_id' => $versionId]);
            }

            // Already active? no-op
            if ((int)($plugin->active_version_id ?? 0) === $version->id) {
                $emit && $emit(['title' => 'ACTIVATION_NOOP', 'description' => 'Version already active']);
                return ActivationResult::ok([
                    'plugin_id' => $plugin->id,
                    'version_id' => $version->id,
                    'changed' => false,
                    'reason' => 'already_active',
                ]);
            }

            // 1) Read install log and verify prior validators for this run
            $logPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR
                . trim($this->policy->getLogsDirName(), "\\/") . DIRECTORY_SEPARATOR
                . $this->policy->getInstallationLogFilename();

            if (!$fs->exists($logPath)) {
                return ActivationResult::fail(['reason' => 'installation_log_missing']);
            }
            $doc = $fs->readJson($logPath);

            // Verify that verification & provider checks existed
            if (!isset($doc['verification'])) {
                return ActivationResult::fail(['reason' => 'verification_missing']);
            }
            if (!empty($doc['verification']['summary']['should_fail'] ?? false)
                && $this->policy->shouldBreakOnVerificationErrors()) {
                return ActivationResult::fail(['reason' => 'verification_failed']);
            }

            // Verify file_scan decision acceptable for activation
            $decisions = (array)($doc['decisions'] ?? []);
            $okDecision = $this->extractOkDecisionForRun($decisions, $runId);
            if ($okDecision === null) {
                return ActivationResult::fail(['reason' => 'scan_decision_missing_or_not_accepted', 'run_id' => $runId]);
            }

            // UI config validation (optional but recommended)
            $ui = $doc['ui_validation'] ?? $doc['ui_config'] ?? null;
            if (is_array($ui)) {
                $accepted = (int)($ui['accepted'] ?? 0);
                if ($accepted <= 0) {
                    return ActivationResult::fail(['reason' => 'ui_not_accepted']);
                }
            }

            // 3) Stage registry writes
            $routes = $this->routesWriter->stage($plugin, $version->id, $installedPluginRoot);
            $providers = $this->providersWriter->stage($plugin, $version->id, $installedPluginRoot);
            $uiReg = $this->uiWriter->stage($plugin, $version->id, $installedPluginRoot);

            // 4) Transaction: flip active version + publish registries
            DB::beginTransaction();
            try {
                // flip active
                $plugin->active_version_id = $version->id;
                $plugin->status = PluginStatus::active;
                $plugin->activated_at = now();
                $plugin->activated_by = $actor;
                $plugin->save();

                // commit staged registries
                ($routes['commit'])();
                ($providers['commit'])();
                ($uiReg['commit'])();

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                // best-effort rollback staged files
                try {
                    ($routes['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    ($providers['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    ($uiReg['rollback'])();
                } catch (Throwable $_) {
                }

                return ActivationResult::fail([
                    'reason' => 'activation_tx_failed',
                    'exception' => $e->getMessage(),
                ]);
            }

            // 5) Optionally clear caches per policy (minimal nudges)
            if (config('fortiplugin.activation.clear_route_cache', false)) {
                try {
                    Artisan::call('route:clear');
                } catch (Throwable $_) {
                }
            }
            if (config('fortiplugin.activation.clear_config_cache', false)) {
                try {
                    Artisan::call('config:clear');
                } catch (Throwable $_) {
                }
            }

            $emit && $emit([
                'title' => 'ACTIVATION_OK',
                'description' => 'Plugin version activated',
                'meta' => [
                    'plugin_id' => $plugin->id,
                    'version_id' => $version->id,
                    'routes' => $routes['meta'] ?? [],
                    'providers' => $providers['meta'] ?? [],
                    'ui' => $uiReg['meta'] ?? [],
                ],
            ]);

            return ActivationResult::ok([
                'plugin_id' => $plugin->id,
                'version_id' => $version->id,
                'changed' => true,
                'routes' => $routes['meta'] ?? [],
                'providers' => $providers['meta'] ?? [],
                'ui' => $uiReg['meta'] ?? [],
            ]);
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /**
     * Acceptable decision for activation:
     *  - status 'installed' (clean scan), or
     *  - status 'ask' resolved by host override for the SAME run_id.
     * @param array<int,array<string,mixed>> $decisions
     */
    private function extractOkDecisionForRun(array $decisions, string $runId): ?array
    {
        // Find the latest decision matching runId
        $filtered = array_values(array_filter($decisions, static function ($d) use ($runId) {
            return is_array($d) && ($d['run_id'] ?? null) === $runId;
        }));
        if ($filtered === []) return null;

        $last = end($filtered);
        $status = (string)($last['status'] ?? '');
        // 'installed' is always ok; 'ask' only ok if reason shows host decision override
        if ($status === 'installed') return $last;
        if ($status === 'ask' && ($last['reason'] ?? '') === 'host_decision_on_scan_errors') {
            return $last;
        }
        return null;
    }
}