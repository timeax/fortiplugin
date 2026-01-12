<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Lifecycle\Writers;

use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Lifecycle\Writers\Concerns\RegistryWriteHelpers;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class UiRegistryWriter implements RegistryWriter
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
     *  - Read installation log for a persisted UI validation block (written by UiConfigValidationSection).
     *  - If accepted>0, register this plugin’s UI into a host UI registry JSON.
     *  - This only records the “presence”; the host app reads and mounts UI at runtime.
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array
    {
        $fs = $this->afs->fs();

        $logsDir = trim($this->policy->getLogsDirName(), "\\/");
        $logFile = $this->policy->getInstallationLogFilename();
        $logPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . $logsDir . DIRECTORY_SEPARATOR . $logFile;

        if (!$fs->exists($logPath)) {
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'installation_log_missing'],
            ];
        }

        $doc = $fs->readJson($logPath);
        $ui = $doc['ui_validation'] ?? $doc['ui_config'] ?? null; // tolerate either key
        $accepted = is_array($ui) ? (int)($ui['accepted'] ?? 0) : 0;

        if ($accepted <= 0) {
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'ui_not_accepted'],
            ];
        }

        $registryPath = (string)(config('fortiplugin.ui.registry_path')
            ?? base_path('bootstrap/fortiplugin.ui.json'));

        // IMPORTANT: key by alias
        $alias = $plugin->alias ?? '';
        if ($alias === '') {
            $alias = (string)($plugin->slug ?? $plugin->id);
        }

        return $this->stageJsonMutation(
            $registryPath,
            static function (array $prev) use ($alias, $accepted, $versionId): array {
                $prev[$alias] = [
                    'accepted'   => $accepted,
                    'version_id' => $versionId,
                ];
                return $prev;
            },
            [
                'plugin_alias' => $alias,
                'accepted'     => $accepted,
                'version_id'   => $versionId,
            ]
        );
    }

    /**
     * Deactivation/uninstall helper:
     *  - Remove the plugin alias key from the UI registry.
     */
    public function stageRemove(Plugin $plugin): array
    {
        $registryPath = (string)(config('fortiplugin.ui.registry_path')
            ?? base_path('bootstrap/fortiplugin.ui.json'));

        $alias = $plugin->alias ?? '';
        if ($alias === '') {
            $alias = (string)($plugin->slug ?? $plugin->id);
        }

        return $this->stageJsonRemoveKey($registryPath, $alias, ['plugin_alias' => $alias]);
    }
}