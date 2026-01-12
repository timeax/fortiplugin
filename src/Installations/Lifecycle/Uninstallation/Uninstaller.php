<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Lifecycle\Uninstallation;

use Illuminate\Support\Facades\DB;
use Throwable;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;
use Timeax\FortiPlugin\Installations\Events\ActivationEvent;
use Timeax\FortiPlugin\Installations\Lifecycle\Deactivation\Deactivator;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class Uninstaller
{
    public function __construct(
        private Deactivator      $deactivator,
        private AtomicFilesystem $afs,
        private PluginRepository $pluginRepository,
    )
    {
    }

    /**
     * Uninstall a plugin:
     * 1. Deactivate if active.
     * 2. Delete files.
     * 3. Delete DB record.
     *
     * @param Plugin $plugin
     * @param string $actor
     * @param string $runId
     * @return UninstallResult
     */
    public function run(
        Plugin $plugin,
        string $actor,
        string $runId
    ): UninstallResult
    {
        $this->emit([
            'event' => UninstallEvents::RUN_START,
            'title' => 'UNINSTALL_START',
            'description' => 'Starting uninstallation flow',
            'meta' => [
                'run_id' => $runId,
                'plugin_id' => $plugin->id,
                'plugin_name' => $plugin->name,
            ],
        ]);

        $installRoot = (string)config('fortiplugin.install_directory', 'apps');
        $installedPluginRoot = base_path($installRoot . DIRECTORY_SEPARATOR . $plugin->name);

        // 1. Deactivate if active
        if ($plugin->status === PluginStatus::active) {
            $this->emit([
                'event' => UninstallEvents::DEACTIVATION_START,
                'title' => 'UNINSTALL_DEACTIVATION_START',
                'description' => 'Plugin is active, deactivating first',
            ]);

            try {
                $deactivationResult = $this->deactivator->run(
                    plugin: $plugin,
                    installedPluginRoot: $installedPluginRoot,
                    actor: $actor,
                    runId: $runId
                );

                if ($deactivationResult->isFail()) {
                    $this->emit([
                        'event' => UninstallEvents::DEACTIVATION_FAIL,
                        'title' => 'UNINSTALL_DEACTIVATION_FAIL',
                        'description' => 'Deactivation failed, aborting uninstall',
                        'meta' => $deactivationResult->getData(),
                    ]);
                    return $this->terminate(
                        UninstallEvents::RUN_FAIL,
                        UninstallResult::fail(['reason' => 'deactivation_failed', 'details' => $deactivationResult->getData()]),
                        $plugin,
                        $runId,
                        $actor
                    );
                }

                $this->emit([
                    'event' => UninstallEvents::DEACTIVATION_OK,
                    'title' => 'UNINSTALL_DEACTIVATION_OK',
                    'description' => 'Plugin deactivated successfully',
                ]);
            } catch (Throwable $e) {
                return $this->terminate(
                    UninstallEvents::RUN_FAIL,
                    UninstallResult::fail(['reason' => 'deactivation_exception', 'exception' => $e->getMessage()]),
                    $plugin,
                    $runId,
                    $actor
                );
            }
        }

        // 2. Delete files
        $this->emit([
            'event' => UninstallEvents::FILES_DELETE_START,
            'title' => 'UNINSTALL_FILES_DELETE_START',
            'description' => 'Deleting plugin files',
            'meta' => ['path' => $installedPluginRoot],
        ]);

        try {
            if ($this->afs->fs()->exists($installedPluginRoot)) {
                $this->afs->fs()->delete($installedPluginRoot);
            }
            $this->emit([
                'event' => UninstallEvents::FILES_DELETE_OK,
                'title' => 'UNINSTALL_FILES_DELETE_OK',
                'description' => 'Plugin files deleted',
            ]);
        } catch (Throwable $e) {
            $this->emit([
                'event' => UninstallEvents::FILES_DELETE_FAIL,
                'title' => 'UNINSTALL_FILES_DELETE_FAIL',
                'description' => 'Failed to delete plugin files',
                'meta' => ['exception' => $e->getMessage()],
            ]);
            return $this->terminate(
                UninstallEvents::RUN_FAIL,
                UninstallResult::fail(['reason' => 'files_delete_failed', 'exception' => $e->getMessage()]),
                $plugin,
                $runId,
                $actor
            );
        }

        // 3. Delete DB record
        $this->emit([
            'event' => UninstallEvents::DB_DELETE_START,
            'title' => 'UNINSTALL_DB_DELETE_START',
            'description' => 'Deleting plugin database record',
        ]);

        try {
            DB::transaction(function () use ($plugin) {
                $this->pluginRepository->delete($plugin->id);
            });

            $this->emit([
                'event' => UninstallEvents::DB_DELETE_OK,
                'title' => 'UNINSTALL_DB_DELETE_OK',
                'description' => 'Plugin database record deleted',
            ]);
        } catch (Throwable $e) {
            $this->emit([
                'event' => UninstallEvents::DB_DELETE_FAIL,
                'title' => 'UNINSTALL_DB_DELETE_FAIL',
                'description' => 'Failed to delete plugin database record',
                'meta' => ['exception' => $e->getMessage()],
            ]);
            return $this->terminate(
                UninstallEvents::RUN_FAIL,
                UninstallResult::fail(['reason' => 'db_delete_failed', 'exception' => $e->getMessage()]),
                $plugin,
                $runId,
                $actor
            );
        }

        return $this->terminate(
            UninstallEvents::RUN_END,
            UninstallResult::ok(['plugin_id' => $plugin->id]),
            $plugin,
            $runId,
            $actor
        );
    }

    private function terminate(
        string          $event,
        UninstallResult $result,
        Plugin          $plugin,
        string          $runId,
        string          $actor
    ): UninstallResult
    {
        $meta = array_merge($result->data, [
            'run_id' => $runId,
            'actor' => $actor,
            'plugin_id' => $plugin->id,
            'status' => $result->status,
        ]);

        $this->emit([
            'event' => $event,
            'title' => $event === UninstallEvents::RUN_END ? 'UNINSTALL_END' : 'UNINSTALL_FAILED',
            'description' => $event === UninstallEvents::RUN_END
                ? 'Uninstallation run completed successfully'
                : 'Uninstallation run failed (Status: ' . $result->status . ')',
            'meta' => $meta,
        ]);

        return $result;
    }

    private function emit(array $payload): void
    {
        // Only dispatch if payload has an explicit 'event' key
        $eventKey = $payload['event'] ?? null;
        if (!is_string($eventKey) || $eventKey === '') {
            return;
        }

        // Best-effort dispatch - reusing ActivationEvent as a generic carrier
        try {
            event(new ActivationEvent(payload: $payload));
        } catch (Throwable $e) {
            // Swallow
        }
    }
}