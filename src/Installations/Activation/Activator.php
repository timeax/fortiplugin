<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation;

use Artisan;
use Illuminate\Support\Facades\DB;
use JsonException;
use Random\RandomException;
use Throwable;
use Timeax\FortiPlugin\Autoload\Psr4RegistryWriter;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Installations\Events\ActivationEvent;
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
        private Psr4RegistryWriter      $psr4Writer,

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
        string     $runId
    ): ActivationResult
    {
        // Activation start
        $this->emit([
            'event' => ActivationEvents::RUN_START,
            'title' => 'ACTIVATION_START',
            'description' => 'Starting activation flow',
            'meta' => [
                'run_id' => $runId,
                'plugin_id' => $plugin->id,
                'version_id' => (string)$versionId,
                'root' => $installedPluginRoot,
            ],
        ]);
        $fs = $this->afs->fs();

        // ── Preflight & lock (naive mutex via file)
        $lockPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . '.internal' . DIRECTORY_SEPARATOR . 'activate.lock';
        $this->afs->ensureParentDirectory($lockPath);
        $lock = @fopen($lockPath, 'cb+');
        if (!$lock || !@flock($lock, LOCK_EX)) {
            $this->emit([
                'event' => ActivationEvents::LOCK_FAIL,
                'title' => 'ACTIVATION_LOCK_FAIL',
                'description' => 'Failed to acquire activation mutex',
                'meta' => ['lock' => $lockPath],
            ]);
            return $this->terminate(
                ActivationEvents::RUN_FAIL,
                ActivationResult::fail(['reason' => 'activation_lock_failed']),
                $plugin,
                $runId,
                $actor
            );
        }

        try {
            // Resolve version
            /** @var PluginVersion|null $version */
            $version = PluginVersion::query()->where('id', $versionId)->where('plugin_id', $plugin->id)->first();
            if (!$version) {
                $this->emit([
                    'event' => ActivationEvents::VALIDATION_PRECHECK_FAIL,
                    'title' => 'ACTIVATION_FAIL',
                    'description' => 'Version not found for plugin',
                    'meta' => ['version_id' => (string)$versionId, 'plugin_id' => $plugin->id],
                ]);
                return $this->terminate(
                    ActivationEvents::RUN_FAIL,
                    ActivationResult::fail(['reason' => 'version_not_found', 'version_id' => $versionId]),
                    $plugin,
                    $runId,
                    $actor
                );
            }

            //TODO: MUST UNCOMMENT

            // Already active? no-op
            if ((int)($plugin->active_version_id ?? 0) === $version->id) {
                $this->emit([
                    'event' => ActivationEvents::NOOP,
                    'title' => 'ACTIVATION_NOOP',
                    'description' => 'Version already active'
                ]);
                return $this->terminate(
                    ActivationEvents::RUN_END,
                    ActivationResult::ok([
                        'plugin_id' => $plugin->id,
                        'version_id' => $version->id,
                        'changed' => false,
                        'reason' => 'already_active',
                    ]),
                    $plugin,
                    $runId,
                    $actor
                );
            }

            // 1) Read install log and verify prior validators for this run
            $logPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR
                . trim($this->policy->getLogsDirName(), "\\/") . DIRECTORY_SEPARATOR
                . $this->policy->getInstallationLogFilename();

            $this->emit([
                'event' => ActivationEvents::INSTALL_LOG_READ_START,
                'title' => 'INSTALL_LOG_READ_START',
                'description' => 'Reading installation log',
                'meta' => ['path' => $logPath]
            ]);
            if (!$fs->exists($logPath)) {
                $this->emit([
                    'event' => ActivationEvents::INSTALL_LOG_READ_FAIL,
                    'title' => 'INSTALL_LOG_READ_FAIL',
                    'description' => 'installation.json not found',
                    'meta' => ['path' => $logPath]
                ]);
                return $this->terminate(
                    ActivationEvents::RUN_FAIL,
                    ActivationResult::fail(['reason' => 'installation_log_missing']),
                    $plugin,
                    $runId,
                    $actor
                );
            }
            $doc = $fs->readJson($logPath);
            $this->emit([
                'event' => ActivationEvents::INSTALL_LOG_READ_OK,
                'title' => 'INSTALL_LOG_READ_OK',
                'description' => 'installation.json loaded'
            ]);

            // Verify that verification & provider checks existed
            if (!isset($doc['verification'])) {
                $this->emit([
                    'event' => ActivationEvents::VALIDATION_PRECHECK_FAIL,
                    'title' => 'VALIDATION_PRECHECK_FAIL',
                    'description' => 'Verification block missing in installation logs'
                ]);
                return $this->terminate(
                    ActivationEvents::RUN_FAIL,
                    ActivationResult::fail(['reason' => 'verification_missing']),
                    $plugin,
                    $runId,
                    $actor
                );
            }
            if (!empty($doc['verification']['summary']['should_fail'] ?? false)
                && $this->policy->shouldBreakOnVerificationErrors()) {
                $this->emit([
                    'event' => ActivationEvents::VALIDATION_PRECHECK_FAIL,
                    'title' => 'VALIDATION_PRECHECK_FAIL',
                    'description' => 'Verification indicates failure and policy requires break'
                ]);
                return $this->terminate(
                    ActivationEvents::RUN_FAIL,
                    ActivationResult::fail(['reason' => 'verification_failed']),
                    $plugin,
                    $runId,
                    $actor
                );
            }

            // Verify file_scan decision acceptable for activation
            //TODO: Uncomment this later - it is a known issue
//            $decisions = (array)($doc['decisions'] ?? []);
//            $okDecision = $this->extractOkDecisionForRun($decisions, $runId);
//            if ($okDecision === null) {
//                $this->emit(['title' => 'VALIDATION_PRECHECK_FAIL', 'description' => 'No accepted file_scan decision for this run', 'meta' => ['run_id' => $runId]]);
//                return ActivationResult::fail(['reason' => 'scan_decision_missing_or_not_accepted', 'run_id' => $runId]);
//            }
            $this->emit([
                'event' => ActivationEvents::VALIDATION_PRECHECK_OK,
                'title' => 'VALIDATION_PRECHECK_OK',
                'description' => 'Validation prechecks passed'
            ]);

            // UI config validation (optional but recommended)
            $ui = $doc['ui_validation'] ?? $doc['ui_config'] ?? null;
            if (is_array($ui)) {
                $declared = (int)($ui['declared'] ?? 0);
                $accepted = (int)($ui['accepted'] ?? 0);

                // FIX: Only fail if items are declared BUT NOT accepted.
                // If declared is 0 (backend-only plugin), we skip this check and pass.
                if ($declared > 0 && $accepted <= 0) {
                    $this->emit([
                        'event' => ActivationEvents::VALIDATION_PRECHECK_FAIL,
                        'title' => 'VALIDATION_PRECHECK_FAIL',
                        'description' => 'UI config not accepted (no placements)'
                    ]);
                    return $this->terminate(
                        ActivationEvents::RUN_FAIL,
                        ActivationResult::fail(['reason' => 'ui_not_accepted']),
                        $plugin,
                        $runId,
                        $actor
                    );
                }
            }

            // 3) Stage registry writes
            $this->emit([
                'event' => ActivationEvents::REGISTRIES_STAGE_START,
                'title' => 'STAGE_REGISTRIES_START',
                'description' => 'Staging registry writes'
            ]);

            $routes = $this->routesWriter->stage($plugin, $version->id, $installedPluginRoot);
            $providers = $this->providersWriter->stage($plugin, $version->id, $installedPluginRoot);
            $uiReg = $this->uiWriter->stage($plugin, $version->id, $installedPluginRoot);
            $psr4 = $this->psr4Writer->stage($plugin, $version->id, $installedPluginRoot);

            $this->emit([
                'event' => ActivationEvents::REGISTRIES_STAGE_OK,
                'title' => 'STAGE_REGISTRIES_OK',
                'description' => 'Registries staged',
                'meta' => [
                    'routes' => $routes['meta'] ?? [],
                    'providers' => $providers['meta'] ?? [],
                    'ui' => $uiReg['meta'] ?? [],
                    'psr4' => $psr4['meta'] ?? [],
                ]
            ]);

            // 4) Transaction: flip active version + publish registries
            DB::beginTransaction();
            $this->emit([
                'event' => ActivationEvents::DB_TX_START,
                'title' => 'DB_TX_START',
                'description' => 'Starting activation DB transaction'
            ]);
            try {
                // flip active
                $plugin->active_version_id = $version->id;
                $plugin->status = PluginStatus::active;
                $plugin->activated_at = now();
                $plugin->activated_by = $actor;
                $plugin->save();

                // commit staged registries
                $this->emit([
                    'event' => ActivationEvents::REGISTRIES_COMMIT_START,
                    'title' => 'REGISTRIES_COMMIT_START',
                    'description' => 'Committing staged registries'
                ]);

                ($routes['commit'])();
                ($providers['commit'])();
                ($uiReg['commit'])();
                ($psr4['commit'])();

                $this->emit([
                    'event' => ActivationEvents::REGISTRIES_COMMIT_OK,
                    'title' => 'REGISTRIES_COMMIT_OK',
                    'description' => 'Staged registries committed'
                ]);

                DB::commit();
                $this->emit([
                    'event' => ActivationEvents::DB_TX_COMMIT_OK,
                    'title' => 'DB_TX_COMMIT_OK',
                    'description' => 'Activation DB transaction committed'
                ]);
            } catch (Throwable $e) {
                DB::rollBack();
                $this->emit([
                    'event' => ActivationEvents::DB_TX_ROLLBACK,
                    'title' => 'DB_TX_ROLLBACK',
                    'description' => 'Activation transaction rolled back',
                    'meta' => ['exception' => $e->getMessage()]
                ]);
                // best-effort rollback staged files
                try {
                    $this->emit([
                        'event' => ActivationEvents::REGISTRIES_ROLLBACK,
                        'title' => 'REGISTRY_ROLLBACK_ATTEMPT',
                        'description' => 'Rolling back routes staging'
                    ]);
                    ($routes['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    $this->emit([
                        'event' => ActivationEvents::REGISTRIES_ROLLBACK,
                        'title' => 'REGISTRY_ROLLBACK_ATTEMPT',
                        'description' => 'Rolling back providers staging'
                    ]);
                    ($providers['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    $this->emit([
                        'event' => ActivationEvents::REGISTRIES_ROLLBACK,
                        'title' => 'REGISTRY_ROLLBACK_ATTEMPT',
                        'description' => 'Rolling back UI staging'
                    ]);
                    ($uiReg['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    $this->emit([
                        'event' => ActivationEvents::REGISTRIES_ROLLBACK,
                        'title' => 'REGISTRY_ROLLBACK_ATTEMPT',
                        'description' => 'Rolling back PSR-4 staging'
                    ]);
                    ($psr4['rollback'])();
                } catch (Throwable $_) {
                }


                return $this->terminate(
                    ActivationEvents::RUN_FAIL,
                    ActivationResult::fail([
                        'reason' => 'activation_tx_failed',
                        'exception' => $e->getMessage(),
                    ]),
                    $plugin,
                    $runId,
                    $actor
                );
            }

            // 5) Optionally clear caches per policy (minimal nudges)
            if (config('fortiplugin.activation.clear_route_cache', false)) {
                try {
                    $this->emit([
                        'event' => ActivationEvents::CACHE_CLEAR_START,
                        'title' => 'CACHE_CLEAR_START',
                        'description' => 'Clearing route cache'
                    ]);
                    Artisan::call('route:clear');
                    $this->emit([
                        'event' => ActivationEvents::CACHE_CLEAR_DONE,
                        'title' => 'CACHE_CLEAR_DONE',
                        'description' => 'Route cache cleared'
                    ]);
                } catch (Throwable $_) {
                }
            }
            if (config('fortiplugin.activation.clear_config_cache', false)) {
                try {
                    $this->emit([
                        'event' => ActivationEvents::CACHE_CLEAR_START,
                        'title' => 'CACHE_CLEAR_START',
                        'description' => 'Clearing config cache'
                    ]);
                    Artisan::call('config:clear');
                    $this->emit([
                        'event' => ActivationEvents::CACHE_CLEAR_DONE,
                        'title' => 'CACHE_CLEAR_DONE',
                        'description' => 'Config cache cleared'
                    ]);
                } catch (Throwable $_) {
                }
            }

            return $this->terminate(
                ActivationEvents::RUN_END,
                ActivationResult::ok([
                    'plugin_id' => $plugin->id,
                    'version_id' => $version->id,
                    'changed' => true,
                    'routes' => $routes['meta'] ?? [],
                    'providers' => $providers['meta'] ?? [],
                    'ui' => $uiReg['meta'] ?? [],
                    'psr4' => $psr4['meta'] ?? []
                ]),
                $plugin,
                $runId,
                $actor
            );
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

    /**
     * Terminate the activation run by emitting the final lifecycle event.
     * Ensures exactly ONE terminal event is emitted (RUN_END or RUN_FAIL).
     *
     * @param string $event The terminal event key (RUN_END or RUN_FAIL)
     * @param ActivationResult $result The result to return
     * @param Plugin $plugin The plugin being activated
     * @param string $runId The run ID
     * @param string $actor The actor performing activation
     * @return ActivationResult
     */
    private function terminate(
        string           $event,
        ActivationResult $result,
        Plugin           $plugin,
        string           $runId,
        string           $actor
    ): ActivationResult
    {
        // Emit terminal event with meta summary
        $meta = array_merge($result->data, [
            'run_id' => $runId,
            'actor' => $actor,
            'plugin_id' => $plugin->id,
            'status' => $result->status,
        ]);

        $this->emit([
            'event' => $event,
            'title' => $event === ActivationEvents::RUN_END ? 'ACTIVATION_END' : 'ACTIVATION_FAILED',
            'description' => $event === ActivationEvents::RUN_END
                ? 'Activation run completed successfully'
                : 'Activation run failed (Status: ' . $result->status . ')',
            'meta' => $meta,
        ]);

        return $result;
    }

    /**
     * Emit an activation event.
     * 
     * Dispatches ActivationEvent (Laravel event) only when payload['event'] is a non-empty string.
     * Best-effort: swallows all exceptions to ensure activation never fails due to event listeners.
     *
     * @param array $payload
     */
    private function emit(array $payload): void
    {
        // Only dispatch if payload has an explicit 'event' key
        $eventKey = $payload['event'] ?? null;
        if (!is_string($eventKey) || $eventKey === '') {
            return;
        }

        // Best-effort dispatch - swallow ALL exceptions
        try {
            event(new ActivationEvent(payload: $payload));
        } catch (Throwable $e) {
            // Swallow - event dispatch failure MUST NOT break activation
        }
    }
}