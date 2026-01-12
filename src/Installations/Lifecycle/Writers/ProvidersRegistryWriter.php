<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Lifecycle\Writers;

use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Lifecycle\Writers\Concerns\RegistryWriteHelpers;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class ProvidersRegistryWriter implements RegistryWriter
{
    use RegistryWriteHelpers;

    public function __construct(
        private AtomicFilesystem $afs,
        private InstallerPolicy  $policy,
    ) {}

    protected function afs(): AtomicFilesystem
    {
        return $this->afs;
    }

    /**
     * Strategy:
     *  - Read fortiplugin.json in installed root for "providers" array.
     *  - Merge into a host providers registry JSON (configurable path).
     *  - Host bootstrapping can include this registry to auto-register providers.
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array
    {
        $fs = $this->afs->fs();

        $cfgPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . 'fortiplugin.json';
        if (!$fs->exists($cfgPath)) {
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'fortiplugin.json_missing'],
            ];
        }

        $cfg = $fs->readJson($cfgPath);

        $providers = array_values(array_unique(array_filter(
            (array)($cfg['providers'] ?? []),
            static fn ($v) => is_string($v) && trim($v) !== ''
        )));

        if ($providers === []) {
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'no_providers_declared'],
            ];
        }

        $registryPath = (string)(config('fortiplugin.providers.registry_path')
            ?? base_path('bootstrap/fortiplugin.providers.json'));

        // IMPORTANT: key by plugin alias (matches your registry shape)
        $alias = $plugin->alias ?? '';
        if ($alias === '') {
            // fallback if alias ever missing (shouldn't happen)
            $alias = (string)($plugin->slug ?? $plugin->id);
        }

        return $this->stageJsonMutation(
            $registryPath,
            static function (array $prev) use ($alias, $providers): array {
                $prev[$alias] = $providers;
                return $prev;
            },
            [
                'plugin_alias' => $alias,
                'providers'    => $providers,
            ]
        );
    }

    /**
     * Deactivation/uninstall helper:
     *  - Remove the plugin alias key from the registry.
     *  - Returns commit/rollback just like stage().
     *
     * NOTE: Not part of RegistryWriter interface, but your lifecycle can call it directly.
     */
    public function stageRemove(Plugin $plugin): array
    {
        $registryPath = (string)(config('fortiplugin.providers.registry_path')
            ?? base_path('bootstrap/fortiplugin.providers.json'));

        $alias = $plugin->alias ?? '';
        if ($alias === '') {
            $alias = (string)($plugin->slug ?? $plugin->id);
        }

        return $this->stageJsonRemoveKey($registryPath, $alias, ['plugin_alias' => $alias]);
    }
}