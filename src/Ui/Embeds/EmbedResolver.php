<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Ui\Embeds;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Timeax\FortiPlugin\Autoload\Psr4RegistryStore;
use Timeax\FortiPlugin\Ui\Support\UiAssetResolver;

final readonly class EmbedResolver
{
    public function __construct(
        private Psr4RegistryStore $psr4Store,
        private CacheRepository   $cache,
        private UiAssetResolver   $assetResolver,
    )
    {
    }

    /**
     * Resolve a single embed spec.
     *
     * @return array{entryUrl:string, css:list<string>, imports:list<string>, versionKey:string}
     * @throws EmbedResolveException
     */
    public function resolve(string $alias, string $name): array
    {
        $alias = trim($alias);
        $name = trim($name);

        if ($alias === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $alias)) {
            throw EmbedResolveException::badRequest("Invalid plugin alias.");
        }

        if ($name === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw EmbedResolveException::badRequest("Invalid embed name.");
        }

        $registry = $this->psr4Store->read();
        $plugins = $registry['plugins'] ?? null;

        if (!is_array($plugins) || !isset($plugins[$alias]) || !is_array($plugins[$alias])) {
            throw EmbedResolveException::notFound("Plugin not found.");
        }

        $meta = $plugins[$alias];
        $studly = $meta['plugin_name'] ?? null;

        $versionId = $meta['version_id'] ?? null;
        $versionId = is_int($versionId) || is_string($versionId) ? (string)$versionId : null;

        if (!is_string($studly) || trim($studly) === '') {
            throw EmbedResolveException::internal("Plugin registry missing plugin_name for '{$alias}'.");
        }

        // Cache key: alias + version_id + embed name
        $cacheKey = 'fortiplugin:embed_spec:' . $alias . ':' . ($versionId ?? 'unknown') . ':' . $name;

        $ttlSeconds = (int)(config('fortiplugin.ui.embed.cache_ttl') ?? 3600);
        if ($ttlSeconds <= 0) {
            return $this->computeSpec($alias, $studly, $name, $versionId);
        }

        /** @var array{entryUrl:string, css:list<string>, imports:list<string>, versionKey:string} $spec */
        $spec = $this->cache->remember($cacheKey, $ttlSeconds, function () use ($alias, $studly, $name, $versionId) {
            return $this->computeSpec($alias, $studly, $name, $versionId);
        });

        return $spec;
    }

    /**
     * Resolve multiple embeds for same plugin.
     *
     * @param list<string> $names
     * @return array<string, array{entryUrl:string, css:list<string>, imports:list<string>, versionKey:string}>
     * @throws EmbedResolveException
     */
    public function resolveMany(string $alias, array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            if (!is_string($name)) continue;
            $out[$name] = $this->resolve($alias, $name);
        }
        return $out;
    }

    /**
     * @return array{entryUrl:string, css:list<string>, imports:list<string>, versionKey:string}
     * @throws EmbedResolveException
     */
    private function computeSpec(string $alias, string $studly, string $name, ?string $versionId): array
    {
        $psr4Root = (string)(
            config('fortiplugin.psr4_root')
            ?? config('fortiplugin.policy.psr4_root')
            ?? 'Plugins'
        );
        $psr4Root = trim($psr4Root) !== '' ? trim($psr4Root) : 'Plugins';

        $configFqcn = $psr4Root . "\\{$studly}\\Internal\\Config";

        // Manually load .internal/Config.php (not PSR-4)
        $appsDir = (string)config('fortiplugin.install_directory', 'apps');
        $appsDir = trim($appsDir, "/\\");
        $pluginRoot = base_path($appsDir . DIRECTORY_SEPARATOR . $alias);

        $internalConfigPath = $pluginRoot
            . DIRECTORY_SEPARATOR . '.internal'
            . DIRECTORY_SEPARATOR . 'Config.php';

        if (is_file($internalConfigPath)) {
            require_once $internalConfigPath;
        }

        if (!class_exists($configFqcn)) {
            throw EmbedResolveException::internal(
                "Plugin Config class not found: {$configFqcn} (looked for {$internalConfigPath})"
            );
        }

        if (!method_exists($configFqcn, 'getViteBuildManifest')) {
            throw EmbedResolveException::internal("Plugin Config missing getViteBuildManifest(): {$configFqcn}");
        }

        /** @var mixed $manifest */
        $manifest = $configFqcn::getViteBuildManifest();

        if (!is_array($manifest) || $manifest === []) {
            throw EmbedResolveException::internal("Manifest empty/unreadable for {$configFqcn}");
        }

        $resolveManifestRef = static function (string $ref) use ($manifest): string {
            $row = $manifest[$ref] ?? null;
            if (is_array($row) && isset($row['file']) && is_string($row['file']) && $row['file'] !== '') {
                return $row['file'];
            }
            return $ref;
        };

        $sourceKey = "resources/embed/ts/{$name}.tsx";
        $entry = $manifest[$sourceKey] ?? null;

        if (!is_array($entry) || empty($entry['file']) || !is_string($entry['file'])) {
            throw EmbedResolveException::notFound("Embed '{$name}' not found in manifest.");
        }

        $css = [];
        if (isset($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $css[] = $this->assetResolver->resolveAssetUrl($alias, $resolveManifestRef($p));
                }
            }
        }

        $imports = [];
        if (isset($entry['imports']) && is_array($entry['imports'])) {
            foreach ($entry['imports'] as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $imports[] = $this->assetResolver->resolveAssetUrl($alias, $resolveManifestRef($p));
                }
            }
        }

        $entryFile = $entry['file'];
        $versionKey = $alias . ':' . ($versionId ?? 'unknown') . ':' . $entryFile;

        return [
            'entryUrl' => $this->assetResolver->resolveAssetUrl($alias, $entryFile),
            'css' => array_values(array_unique($css)),
            'imports' => array_values(array_unique($imports)),
            'versionKey' => $versionKey,
        ];
    }
}
