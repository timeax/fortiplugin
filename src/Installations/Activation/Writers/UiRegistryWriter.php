<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation\Writers;

use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class UiRegistryWriter implements RegistryWriter
{
    public function __construct(
        private AtomicFilesystem $afs,
        private InstallerPolicy  $policy,
    ) {}

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

        $registryPath = (string)(config('fortiplugin.ui.registry_path') ?? base_path('bootstrap/fortiplugin.ui.json'));
        $json = $fs->exists($registryPath) ? $fs->readJson($registryPath) : [];
        if (!is_array($json)) $json = [];

        $slug = (string)($plugin->placeholder->slug ?? $plugin->slug ?? $plugin->id);
        $json[$slug] = ['accepted' => $accepted, 'version_id' => $versionId];

        $newJson = $json;

        return [
            'commit' => function () use ($registryPath, $newJson): void {
                $this->afs->writeJsonAtomic($registryPath, $newJson, true);
            },
            'rollback' => static function (): void {},
            'meta' => [
                'changed'       => true,
                'registry_path' => $registryPath,
                'accepted'      => $accepted,
            ],
        ];
    }
}