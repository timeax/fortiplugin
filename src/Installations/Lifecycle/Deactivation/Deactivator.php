<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Lifecycle\Deactivation;

use Artisan;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;
use Timeax\FortiPlugin\Autoload\Psr4RegistryWriter;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Installations\Events\ActivationEvent;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Lifecycle\Writers\ProvidersRegistryWriter;
use Timeax\FortiPlugin\Installations\Lifecycle\Writers\RoutesRegistryWriter;
use Timeax\FortiPlugin\Installations\Lifecycle\Writers\UiRegistryWriter;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\DeactivationLogStore;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class Deactivator
{
    public function __construct(
        private InstallerPolicy         $policy,
        private AtomicFilesystem        $afs,
        private RoutesRegistryWriter    $routesWriter,
        private ProvidersRegistryWriter $providersWriter,
        private UiRegistryWriter        $uiWriter,
        private Psr4RegistryWriter      $psr4Writer,
        private DeactivationLogStore    $logStore,
    )
    {
    }

    /**
     * Deactivate an active plugin (stand-alone, not wired to Installer).
     *
     * @param Plugin $plugin
     * @param string $installedPluginRoot Absolute path to the plugin's installed root
     * @param string $actor
     * @param string $runId Correlates with the original installation run
     * @return DeactivationResult
     * @throws Throwable
     * @throws JsonException
     */
    public function run(
        Plugin $plugin,
        string $installedPluginRoot,
        string $actor,
        string $runId
    ): DeactivationResult
    {
        $logsDir = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . trim($this->policy->getLogsDirName(), "\\/");
        $deactivationJsonPath = $logsDir . DIRECTORY_SEPARATOR . $this->policy->getDeactivationLogFilename();

        // Initialize deactivation log store
        $this->logStore->openOrInit([
            'run_id' => $runId,
            'plugin_id' => $plugin->id,
            'action' => 'deactivate',
            'root' => $installedPluginRoot,
            'actor' => $actor,
            'started_at' => now()->toIso8601String(),
        ], $deactivationJsonPath);

        // Deactivation start
        $this->emit([
            'event' => DeactivationEvents::RUN_START,
            'title' => 'DEACTIVATION_START',
            'description' => 'Starting deactivation flow',
            'meta' => [
                'run_id' => $runId,
                'plugin_id' => $plugin->id,
                'root' => $installedPluginRoot,
            ],
        ]);

        // ── Preflight & lock (naive mutex via file)
        $lockPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . '.internal' . DIRECTORY_SEPARATOR . 'activate.lock';
        $this->afs->ensureParentDirectory($lockPath);
        $lock = @fopen($lockPath, 'cb+');
        if (!$lock || !@flock($lock, LOCK_EX)) {
            $this->emit([
                'event' => DeactivationEvents::LOCK_FAIL,
                'title' => 'DEACTIVATION_LOCK_FAIL',
                'description' => 'Failed to acquire deactivation mutex',
                'meta' => ['lock' => $lockPath],
            ]);
            return $this->terminate(
                DeactivationEvents::RUN_FAIL,
                DeactivationResult::fail(['reason' => 'deactivation_lock_failed']),
                $plugin,
                $runId,
                $actor
            );
        }

        try {
            // Already inactive? no-op
            if ($plugin->status !== PluginStatus::active) {
                $this->emit([
                    'event' => DeactivationEvents::NOOP,
                    'title' => 'DEACTIVATION_NOOP',
                    'description' => 'Plugin already inactive'
                ]);
                return $this->terminate(
                    DeactivationEvents::RUN_END,
                    DeactivationResult::ok([
                        'plugin_id' => $plugin->id,
                        'changed' => false,
                        'reason' => 'already_inactive',
                    ]),
                    $plugin,
                    $runId,
                    $actor
                );
            }

            // 1) Stage registry removals
            $this->emit([
                'event' => DeactivationEvents::REGISTRIES_STAGE_START,
                'title' => 'STAGE_REGISTRIES_START',
                'description' => 'Staging registry removals'
            ]);

            $routes = $this->routesWriter->stageRemove($plugin);
            $providers = $this->providersWriter->stageRemove($plugin);
            $uiReg = $this->uiWriter->stageRemove($plugin);
            $psr4 = $this->psr4Writer->stageRemove($plugin);

            $this->emit([
                'event' => DeactivationEvents::REGISTRIES_STAGE_OK,
                'title' => 'STAGE_REGISTRIES_OK',
                'description' => 'Registries removal staged',
                'meta' => [
                    'routes' => $routes['meta'] ?? [],
                    'providers' => $providers['meta'] ?? [],
                    'ui' => $uiReg['meta'] ?? [],
                    'psr4' => $psr4['meta'] ?? [],
                ]
            ]);

            // 2) Transaction: flip status + commit registry removals
            DB::beginTransaction();
            $this->emit([
                'event' => DeactivationEvents::DB_TX_START,
                'title' => 'DB_TX_START',
                'description' => 'Starting deactivation DB transaction'
            ]);
            try {
                // flip inactive
                $plugin->active_version_id = null;
                $plugin->status = PluginStatus::inactive; // Revert to installed
                $plugin->activated_at = null;
                $plugin->activated_by = null;
                $plugin->save();

                // commit staged registries
                $this->emit([
                    'event' => DeactivationEvents::REGISTRIES_COMMIT_START,
                    'title' => 'REGISTRIES_COMMIT_START',
                    'description' => 'Committing staged registry removals'
                ]);

                ($routes['commit'])();
                ($providers['commit'])();
                ($uiReg['commit'])();
                ($psr4['commit'])();

                $this->emit([
                    'event' => DeactivationEvents::REGISTRIES_COMMIT_OK,
                    'title' => 'REGISTRIES_COMMIT_OK',
                    'description' => 'Staged registry removals committed'
                ]);

                DB::commit();
                $this->emit([
                    'event' => DeactivationEvents::DB_TX_COMMIT_OK,
                    'title' => 'DB_TX_COMMIT_OK',
                    'description' => 'Deactivation DB transaction committed'
                ]);
            } catch (Throwable $e) {
                DB::rollBack();
                $this->emit([
                    'event' => DeactivationEvents::DB_TX_ROLLBACK,
                    'title' => 'DB_TX_ROLLBACK',
                    'description' => 'Deactivation transaction rolled back',
                    'meta' => ['exception' => $e->getMessage()]
                ]);
                // best-effort rollback staged files
                try {
                    $this->emit([
                        'event' => DeactivationEvents::REGISTRIES_ROLLBACK,
                        'title' => 'REGISTRY_ROLLBACK_ATTEMPT',
                        'description' => 'Rolling back routes staging'
                    ]);
                    ($routes['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    $this->emit([
                        'event' => DeactivationEvents::REGISTRIES_ROLLBACK,
                        'title' => 'REGISTRY_ROLLBACK_ATTEMPT',
                        'description' => 'Rolling back providers staging'
                    ]);
                    ($providers['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    $this->emit([
                        'event' => DeactivationEvents::REGISTRIES_ROLLBACK,
                        'title' => 'REGISTRY_ROLLBACK_ATTEMPT',
                        'description' => 'Rolling back UI staging'
                    ]);
                    ($uiReg['rollback'])();
                } catch (Throwable $_) {
                }
                try {
                    $this->emit([
                        'event' => DeactivationEvents::REGISTRIES_ROLLBACK,
                        'title' => 'REGISTRY_ROLLBACK_ATTEMPT',
                        'description' => 'Rolling back PSR-4 staging'
                    ]);
                    ($psr4['rollback'])();
                } catch (Throwable $_) {
                }


                return $this->terminate(
                    DeactivationEvents::RUN_FAIL,
                    DeactivationResult::fail([
                        'reason' => 'deactivation_tx_failed',
                        'exception' => $e->getMessage(),
                    ]),
                    $plugin,
                    $runId,
                    $actor
                );
            }

            // 3) Optionally clear caches per policy (minimal nudges)
            if (config('fortiplugin.activation.clear_route_cache', false)) {
                try {
                    $this->emit([
                        'event' => DeactivationEvents::CACHE_CLEAR_START,
                        'title' => 'CACHE_CLEAR_START',
                        'description' => 'Clearing route cache'
                    ]);
                    Artisan::call('route:clear');
                    $this->emit([
                        'event' => DeactivationEvents::CACHE_CLEAR_DONE,
                        'title' => 'CACHE_CLEAR_DONE',
                        'description' => 'Route cache cleared'
                    ]);
                } catch (Throwable $_) {
                }
            }
            if (config('fortiplugin.activation.clear_config_cache', false)) {
                try {
                    $this->emit([
                        'event' => DeactivationEvents::CACHE_CLEAR_START,
                        'title' => 'CACHE_CLEAR_START',
                        'description' => 'Clearing config cache'
                    ]);
                    Artisan::call('config:clear');
                    $this->emit([
                        'event' => DeactivationEvents::CACHE_CLEAR_DONE,
                        'title' => 'CACHE_CLEAR_DONE',
                        'description' => 'Config cache cleared'
                    ]);
                } catch (Throwable $_) {
                }
            }

            return $this->terminate(
                DeactivationEvents::RUN_END,
                DeactivationResult::ok([
                    'plugin_id' => $plugin->id,
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
     * Terminate the deactivation run by emitting the final lifecycle event.
     * Ensures exactly ONE terminal event is emitted (RUN_END or RUN_FAIL).
     *
     * @param string $event The terminal event key (RUN_END or RUN_FAIL)
     * @param DeactivationResult $result The result to return
     * @param Plugin $plugin The plugin being deactivated
     * @param string $runId The run ID
     * @param string $actor The actor performing deactivation
     * @return DeactivationResult
     */
    private function terminate(
        string             $event,
        DeactivationResult $result,
        Plugin             $plugin,
        string             $runId,
        string             $actor
    ): DeactivationResult
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
            'title' => $event === DeactivationEvents::RUN_END ? 'DEACTIVATION_END' : 'DEACTIVATION_FAILED',
            'description' => $event === DeactivationEvents::RUN_END
                ? 'Deactivation run completed successfully'
                : 'Deactivation run failed (Status: ' . $result->status . ')',
            'meta' => $meta,
        ]);

        // Persist result to deactivation log
        try {
            $this->logStore->writeResult($result);
        } catch (Throwable) {
            // Best effort persistence
        }

        return $result;
    }

    /**
     * Emit a deactivation event.
     *
     * Dispatches ActivationEvent (Laravel event) - reusing ActivationEvent for now as it's generic enough payload wrapper
     * only when payload['event'] is a non-empty string.
     * Best-effort: swallows all exceptions.
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

        // Log to deactivation.json
        try {
            $this->logStore->appendDeactivationEmit($payload);
        } catch (Throwable) {
            // Best effort logging
        }

        // Best-effort dispatch - swallow ALL exceptions
        try {
            event(new ActivationEvent(payload: $payload));
        } catch (Throwable $e) {
            // Swallow
        }
    }
}