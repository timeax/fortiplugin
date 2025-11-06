<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation\Writers;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class RoutesRegistryWriter implements RegistryWriter
{
    public function __construct(
        private AtomicFilesystem $afs,
        private InstallerPolicy  $policy,
    ) {}

    /**
     * Strategy:
     *  - Read plugin’s installed log to find the routes' aggregator path written by RouteWriteSection.
     *  - Update host registry JSON (configurable path) with [plugin_slug => aggregator].
     *  - Regenerate a single host PHP aggregator that requires all registered aggregators.
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array
    {
        $fs = $this->afs->fs();

        // 1) Locate installation log in installed root
        $logsDir   = trim($this->policy->getLogsDirName(), "\\/");
        $logFile   = $this->policy->getInstallationLogFilename();
        $logPath   = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . $logsDir . DIRECTORY_SEPARATOR . $logFile;

        if (!$fs->exists($logPath)) {
            throw new RuntimeException("activation: installation log not found at $logPath");
        }
        $doc = $fs->readJson($logPath);
        $routesWrite = $doc['routes_write'] ?? null;
        if (!is_array($routesWrite) || empty($routesWrite['aggregator'])) {
            // No routes for this plugin — nothing to publish
            return [
                'commit'   => static function (): void {},
                'rollback' => static function (): void {},
                'meta'     => ['changed' => false, 'reason' => 'no_routes_aggregator'],
            ];
        }

        $aggregator = (string)$routesWrite['aggregator'];
        if ($aggregator === '' || !$fs->exists($aggregator)) {
            throw new RuntimeException("activation: aggregator file not found: $aggregator");
        }

        // 2) Host registry paths (configurable)
        $registryPath   = (string) (config('fortiplugin.routes.registry_path')    ?? base_path('routes/fortiplugin.registry.json'));
        $aggregatorPath = (string) (config('fortiplugin.routes.aggregator_path')  ?? base_path('routes/fortiplugin.plugins.php'));

        // 3) Read and update registry JSON (plugin_slug => aggregator)
        $slug  = (string)($plugin->placeholder->slug ?? $plugin->slug ?? $plugin->id);
        $json  = $fs->exists($registryPath) ? $fs->readJson($registryPath) : [];
        if (!is_array($json)) $json = [];
        $json[$slug] = $aggregator;

        // Staged contents
        $newRegistryJson = $json;
        $newAggregatorPhp = $this->renderAggregatorPhp($newRegistryJson);

        // 4) Return commit/rollback closures (atomic writes)
        return [
            'commit' => function () use ($registryPath, $aggregatorPath, $newRegistryJson, $newAggregatorPhp): void {
                $this->afs->writeJsonAtomic($registryPath, $newRegistryJson, true);
                $this->afs->fs()->writeAtomic($aggregatorPath, $newAggregatorPhp);
            },
            'rollback' => static function (): void { /* best effort noop */ },
            'meta' => [
                'changed'         => true,
                'registry_path'   => $registryPath,
                'aggregator_path' => $aggregatorPath,
            ],
        ];
    }

    /** @param array<string,string> $registry */
    private function renderAggregatorPhp(array $registry): string
    {
        $lines = [];
        $lines[] = "<?php";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "/** Host aggregator for FortiPlugin routes (auto-generated) */";
        $lines[] = "";
        foreach ($registry as $slug => $file) {
            $fileEsc = var_export($file, true);
            $slugEsc = var_export($slug, true);
            $lines[] = "// plugin: $slugEsc";
            $lines[] = "if (file_exists($fileEsc)) { require $fileEsc; }";
        }
        $lines[] = "";
        return implode("\n", $lines);
    }
}