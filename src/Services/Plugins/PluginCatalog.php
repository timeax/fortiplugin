<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Services\Plugins;

use RuntimeException;
use Timeax\FortiPlugin\Models\Plugin;

final class PluginCatalog
{
    /** @var array<int, Plugin> */
    private array $byId = [];

    public function get(int|string $idOrAlias): Plugin
    {
        if (isset($this->byId[$idOrAlias])) {
            return $this->byId[$idOrAlias];
        }

        $plugin = Plugin::query()->find($idOrAlias);

        if (!$plugin) {
            throw new RuntimeException("Plugin #$idOrAlias not found");
        }

        return $this->byId[$idOrAlias] = $plugin;
    }

    public function find(int $pluginId): ?Plugin
    {
        if (isset($this->byId[$pluginId])) {
            return $this->byId[$pluginId];
        }

        $plugin = Plugin::query()->find($pluginId);
        if ($plugin) {
            $this->byId[$pluginId] = $plugin;
        }

        return $plugin;
    }

    /**
     * Keep the return type as `array` for compatibility with the facade docblock.
     *
     * @return array<int, Plugin>
     */
    public function list(): array
    {
        $plugins = Plugin::query()->orderBy('id')->get();

        foreach ($plugins as $plugin) {
            $this->byId[$plugin->id] = $plugin;
        }

        return $plugins->all();
    }

    public function forget(int $pluginId): void
    {
        unset($this->byId[$pluginId]);
    }

    public function prime(Plugin $plugin): void
    {
        $this->byId[$plugin->id] = $plugin;
    }
}