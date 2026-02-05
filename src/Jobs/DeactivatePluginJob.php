<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Enums\ProcessStatus;
use Timeax\FortiPlugin\Installations\Lifecycle\Deactivation\Deactivator;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginProcess;

final class DeactivatePluginJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        public readonly int        $pluginId,
        public readonly string     $runId,
        public readonly int|string $actor,
    )
    {
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function handle(Deactivator $deactivator): void
    {
        $plugin = Plugin::query()->findOrFail($this->pluginId);

        $installDir = base_path($plugin->plugin_path);

        $result = $deactivator->run(
            plugin: $plugin,
            installedPluginRoot: $installDir,
            actor: (string)$this->actor,
            runId: $this->runId
        );

        $process = PluginProcess::where('run_id', $this->runId)->firstOrFail();

        if ($result->isOk()) {
            $process->status = ProcessStatus::success;
        } elseif ($result->isFail()) {
            $process->status = ProcessStatus::failed;
        }

        if ($process->isDirty()) $process->save();

        cache()->put(
            "fortiplugin:deactivate:{$this->runId}",
            $result->toArray(),
            now()->addDay()
        );
    }

    private function normalizeResult(mixed $result): array
    {
        if (is_array($result)) return $result;
        if (is_object($result) && method_exists($result, 'toArray')) {
            return (array)$result->toArray();
        }
        // DeactivationResult has public properties status and data, but no toArray()
        if (is_object($result) && property_exists($result, 'status') && property_exists($result, 'data')) {
            return [
                'status' => $result->status,
                'data' => $result->data,
            ];
        }
        return is_object($result) ? get_object_vars($result) : ['value' => $result];
    }
}