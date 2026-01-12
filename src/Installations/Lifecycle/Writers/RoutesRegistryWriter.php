<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Lifecycle\Writers;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\RegistryWriter;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Lifecycle\Writers\Concerns\RegistryWriteHelpers;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Models\Plugin;

final readonly class RoutesRegistryWriter implements RegistryWriter
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
     *  - Read plugin’s installed log to find the routes' aggregator path written by RouteWriteSection.
     *  - Update host registry JSON (configurable path) with [plugin_alias => aggregator].
     *  - Regenerate a single host PHP aggregator that requires all registered aggregators.
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array
    {
        $fs = $this->afs->fs();

        // 1) Locate installation log in installed root
        $logsDir = trim($this->policy->getLogsDirName(), "\\/");
        $logFile = $this->policy->getInstallationLogFilename();
        $logPath = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . $logsDir . DIRECTORY_SEPARATOR . $logFile;

        if (!$fs->exists($logPath)) {
            throw new RuntimeException("activation: installation log not found at $logPath");
        }

        $doc = $fs->readJson($logPath);
        $routesWrite = $doc['routes_write'] ?? null;

        if (!is_array($routesWrite) || empty($routesWrite['aggregator'])) {
            return [
                'commit' => static function (): void {},
                'rollback' => static function (): void {},
                'meta' => ['changed' => false, 'reason' => 'no_routes_aggregator'],
            ];
        }

        $aggregatorRel = (string)$routesWrite['aggregator'];
        $installedAggregator = rtrim($installedPluginRoot, "\\/") . DIRECTORY_SEPARATOR . ltrim($aggregatorRel, "\\/");
        $installedAggregator = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $installedAggregator);

        if ($installedAggregator === '' || !$fs->exists($installedAggregator)) {
            throw new RuntimeException("activation: installed aggregator file not found: $installedAggregator");
        }

        // 2) Host registry paths (configurable)
        $registryPath = (string)(config('fortiplugin.routes.registry_path')
            ?? base_path('routes' . DIRECTORY_SEPARATOR . 'fortiplugin.registry.json'));

        $aggregatorPath = (string)(config('fortiplugin.routes.aggregator_path')
            ?? base_path('routes' . DIRECTORY_SEPARATOR . 'fortiplugin.plugins.php'));

        // 3) Read and update registry JSON (plugin_alias => aggregator)
        $alias = $this->pluginKey($plugin);

        // Stage 1: Update JSON registry
        $jsonStage = $this->stageJsonMutation(
            $registryPath,
            function (array $prev) use ($alias, $installedAggregator) {
                // Normalize: smooth upgrade to relative paths
                $prev = $this->normalizeRegistryPaths($prev);
                $prev[$alias] = $this->makeRelative((string)$installedAggregator);
                return $prev;
            },
            ['plugin_alias' => $alias]
        );

        // If JSON didn't change, we might still need to update PHP if it's missing or stale
        // But to keep it simple, we'll just re-render PHP based on the *new* JSON state.
        // We need the *result* of the JSON mutation to render the PHP.
        // Since stageJsonMutation doesn't return the new state directly, we simulate it:
        $currentRegistry = $this->readJsonSafe($registryPath);
        $currentRegistry = $this->normalizeRegistryPaths($currentRegistry);
        $currentRegistry[$alias] = $this->makeRelative((string)$installedAggregator);
        
        $newAggregatorPhp = $this->renderAggregatorPhp($currentRegistry);

        // Stage 2: Update PHP aggregator
        $phpStage = $this->stageTextWrite($aggregatorPath, $newAggregatorPhp, ['plugin_alias' => $alias]);

        return $this->combineStages($jsonStage, $phpStage, ['plugin_alias' => $alias]);
    }

    /**
     * Deactivation/uninstall helper:
     *  - Remove plugin alias entry from registry JSON.
     *  - Regenerate the host PHP aggregator accordingly.
     */
    public function stageRemove(Plugin $plugin): array
    {
        $registryPath = (string)(config('fortiplugin.routes.registry_path')
            ?? base_path('routes' . DIRECTORY_SEPARATOR . 'fortiplugin.registry.json'));

        $aggregatorPath = (string)(config('fortiplugin.routes.aggregator_path')
            ?? base_path('routes' . DIRECTORY_SEPARATOR . 'fortiplugin.plugins.php'));

        $alias = $this->pluginKey($plugin);

        // Stage 1: Remove from JSON
        $jsonStage = $this->stageJsonRemoveKey($registryPath, $alias, ['plugin_alias' => $alias]);

        // Simulate new state for PHP render
        $currentRegistry = $this->readJsonSafe($registryPath);
        $currentRegistry = $this->normalizeRegistryPaths($currentRegistry);
        if (isset($currentRegistry[$alias])) {
            unset($currentRegistry[$alias]);
        }
        
        $newAggregatorPhp = $this->renderAggregatorPhp($currentRegistry);

        // Stage 2: Update PHP
        $phpStage = $this->stageTextWrite($aggregatorPath, $newAggregatorPhp, ['plugin_alias' => $alias]);

        return $this->combineStages($jsonStage, $phpStage, ['plugin_alias' => $alias]);
    }

    /** @param array<string,string> $registry */
    private function normalizeRegistryPaths(array $registry): array
    {
        foreach ($registry as $k => $p) {
            if (is_string($p)) {
                $registry[$k] = $this->makeRelative($p);
            }
        }
        return $registry;
    }

    private function pluginKey(Plugin $plugin): string
    {
        $alias = $plugin->alias ?? '';
        if ($alias !== '') {
            return $alias;
        }

        // fallback safety (should not happen)
        return (string)($plugin->slug ?? $plugin->id);
    }

    /** @param array<string,string> $registry */
    private function renderAggregatorPhp(array $registry): string
    {
        $lines = [];
        $lines[] = "<?php";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "/** Host aggregator for FortiPlugin routes (auto-generated) */";
        $lines[] = "";

        foreach ($registry as $alias => $file) {
            if (!is_string($file)) continue;

            $fileEsc = var_export($file, true);
            $aliasEsc = var_export($alias, true);

            $lines[] = "// plugin: $aliasEsc";
            $lines[] = "\$path = base_path($fileEsc);";
            $lines[] = "if (is_file(\$path)) { require_once \$path; }";
            $lines[] = "elseif (is_file($fileEsc)) { require_once $fileEsc; }";
            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    private function makeRelative(string $path): string
    {
        $path = str_replace(['\\', '/'], '/', $path);
        $base = str_replace(['\\', '/'], '/', base_path());

        if (str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/');
        }

        return $path;
    }
}