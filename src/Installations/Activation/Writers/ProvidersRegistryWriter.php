<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation\Writers;

use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class ProvidersRegistryWriter implements RegistryWriter
{
    public function __construct(
        private AtomicFilesystem $afs,
        private InstallerPolicy  $policy,
    ) {}

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
            // No config — nothing to write
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'fortiplugin.json_missing'],
            ];
        }

        $cfg = $fs->readJson($cfgPath);
        $providers = array_values(array_filter((array)($cfg['providers'] ?? []), 'is_string'));
        if ($providers === []) {
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'no_providers_declared'],
            ];
        }

        $registryPath = (string)(config('fortiplugin.providers.registry_path') ?? base_path('bootstrap/fortiplugin.providers.json'));
        $json = $fs->exists($registryPath) ? $fs->readJson($registryPath) : [];
        if (!is_array($json)) $json = [];

        $slug = (string)($plugin->placeholder->slug ?? $plugin->slug ?? $plugin->id);
        $json[$slug] = $providers;

        $newJson = $json;

        return [
            'commit' => function () use ($registryPath, $newJson): void {
                $this->afs->writeJsonAtomic($registryPath, $newJson, true);
            },
            'rollback' => static function (): void {},
            'meta' => [
                'changed'       => true,
                'registry_path' => $registryPath,
                'providers'     => $providers,
            ],
        ];
    }
}