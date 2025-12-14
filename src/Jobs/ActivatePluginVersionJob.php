<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Activation\Activator;
use Timeax\FortiPlugin\Models\PluginVersion;

final class ActivatePluginVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        public readonly int $pluginVersionId,
        public readonly int $zipPlaceholderId,
        public readonly string $runId,
        public readonly string $actor,
    ) {}

    public function handle(Activator $activator): void
    {
        $version = PluginVersion::query()
            ->with('plugin.placeholder')
            ->findOrFail($this->pluginVersionId);

        $plugin = $version->plugin;

        if ((int) $plugin->plugin_placeholder_id !== (int) $this->zipPlaceholderId) {
            throw new RuntimeException('ZIP_PLUGIN_MISMATCH');
        }

        $installRoot = (string) config('fortiplugin.directory', 'apps');
        $installDir  = base_path($installRoot . DIRECTORY_SEPARATOR . $plugin->slug);

        $result = $activator->run(
            plugin: $plugin,
            versionId: $version->id,
            installedPluginRoot: $installDir,
            actor: $this->actor,
            runId: $this->runId,
            emit: null,
        );

        cache()->put(
            "fortiplugin:activate:{$this->runId}",
            $this->normalizeResult($result),
            now()->addDay()
        );
    }

    private function normalizeResult(mixed $result): array
    {
        if (is_array($result)) return $result;
        if (is_object($result) && method_exists($result, 'toArray')) {
            return (array) $result->toArray();
        }
        return is_object($result) ? get_object_vars($result) : ['value' => $result];
    }
}
