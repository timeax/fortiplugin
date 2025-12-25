<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Autoload;

use Illuminate\Support\Str;
use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class Psr4RegistryWriter implements RegistryWriter
{
    public function __construct(
        private Psr4RegistryStore        $store,
        private PluginAutoloadMapBuilder $builder,
    ) {}

    /**
     * Activation-time PSR-4 registry update.
     *
     * Writes to bootstrap/fortiplugin.autoload_psr4.php (configurable),
     * without touching host composer.json and without dump-autoload.
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array
    {
        if (!config('fortiplugin.autoload_enabled', true)) {
            return [
                'commit' => static function (): void {},
                'rollback' => static function (): void {},
                'meta' => ['changed' => false, 'reason' => 'autoload_disabled'],
            ];
        }

        // Stable registry key (consistent with other activation registries)
        $slug = (string)($plugin->placeholder->slug ?? $plugin->slug ?? $plugin->id);

        // Namespace segment MUST match the plugin composer.json PSR-4 prefix.
        // We normalize to StudlyCase to avoid "My Plugin" vs "MyPlugin" footguns.
        $pluginNameRaw = $plugin->placeholder->name ?? $plugin->name ?? $slug;
        $pluginName = Str::studly($pluginNameRaw);

        $psr4Root = (string)(config('fortiplugin.psr4_root') ?? 'Plugins');
        $psr4Root = trim($psr4Root) !== '' ? $psr4Root : 'Plugins';

        // Build PSR-4 map from plugin's composer.json
        $prefixes = $this->builder->build($installedPluginRoot, $pluginName, $psr4Root);

        // Read + update registry
        $registry = $this->store->read();
        $registry['generated_at'] = function_exists('now') ? now()->toIso8601String() : gmdate('c');
        $registry['plugins'] ??= [];

        $registry['plugins'][$slug] = [
            'version_id' => $versionId,
            'plugin_name' => $pluginName,
            'prefixes' => $prefixes,
        ];

        $finalPath = $this->store->registryPath();

        // ✅ Stage temp file in SAME DIRECTORY as final registry (true atomic rename)
        $tmpPath = $this->store->stage($registry);

        return [
            'commit' => function () use ($tmpPath): void {
                $this->store->commit($tmpPath);
            },
            'rollback' => static function () use ($tmpPath): void {
                if (is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
            },
            'meta' => [
                'changed' => true,
                'registry_path' => $finalPath,
                'plugin_slug' => $slug,
                'plugin_name' => $pluginName,
                'prefixes' => array_keys($prefixes),
            ],
        ];
    }
}
