<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Ui\Embeds;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Timeax\FortiPlugin\Autoload\Psr4RegistryStore;

final readonly class EmbedResolver
{
    public function __construct(
        private Psr4RegistryStore $psr4Store,
        private CacheRepository   $cache,
    )
    {
    }

    /**
     * Resolve a single embed spec.
     *
     * @return array{entryUrl:string, css:list<string>, imports:list<string>, versionKey:string}
     * @throws EmbedResolveException
     */
    public function resolve(string $slug, string $name): array
    {
        $slug = trim($slug);
        $name = trim($name);

        if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $slug)) {
            throw EmbedResolveException::badRequest("Invalid plugin slug.");
        }

        if ($name === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw EmbedResolveException::badRequest("Invalid embed name.");
        }

        $registry = $this->psr4Store->read();
        $plugins = $registry['plugins'] ?? null;

        if (!is_array($plugins) || !isset($plugins[$slug]) || !is_array($plugins[$slug])) {
            throw EmbedResolveException::notFound("Plugin not found.");
        }

        $meta = $plugins[$slug];
        $studly = $meta['plugin_name'] ?? null;

        $versionId = $meta['version_id'] ?? null;
        $versionId = is_int($versionId) || is_string($versionId) ? (string)$versionId : null;

        if (!is_string($studly) || trim($studly) === '') {
            throw EmbedResolveException::internal("Plugin registry missing plugin_name for '{$slug}'.");
        }

        // Cache key: slug + version_id + embed name
        $cacheKey = 'fortiplugin:embed_spec:' . $slug . ':' . ($versionId ?? 'unknown') . ':' . $name;

        $ttlSeconds = (int)(config('fortiplugin.ui_embed_cache_ttl') ?? 3600);
        if ($ttlSeconds <= 0) {
            return $this->computeSpec($slug, $studly, $name, $versionId);
        }

        /** @var array{entryUrl:string, css:list<string>, imports:list<string>, versionKey:string} $spec */
        $spec = $this->cache->remember($cacheKey, $ttlSeconds, function () use ($slug, $studly, $name, $versionId) {
            return $this->computeSpec($slug, $studly, $name, $versionId);
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
    public function resolveMany(string $slug, array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            if (!is_string($name)) continue;
            $out[$name] = $this->resolve($slug, $name);
        }
        return $out;
    }

    /**
     * @return array{entryUrl:string, css:list<string>, imports:list<string>, versionKey:string}
     * @throws EmbedResolveException
     */
    private function computeSpec(string $slug, string $studly, string $name, ?string $versionId): array
    {
        $psr4Root = (string)(
            config('fortiplugin.psr4_root')
            ?? config('fortiplugin.policy.psr4_root')
            ?? 'Plugins'
        );
        $psr4Root = trim($psr4Root) !== '' ? trim($psr4Root) : 'Plugins';

        $configFqcn = $psr4Root . "\\{$studly}\\Internal\\Config";

        // Manually load .internal/Config.php (not PSR-4)
        $appsDir = (string)config('fortiplugin.directory', 'apps');
        $appsDir = trim($appsDir, "/\\");
        $pluginRoot = base_path($appsDir . DIRECTORY_SEPARATOR . $studly);

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

        $baseTpl = (string)config('fortiplugin.ui_embed_public_base', '/__plugins/{slug}/build');
        $baseTpl = trim($baseTpl) !== '' ? $baseTpl : '/__plugins/{slug}/build';

        $baseUrl = str_replace(['{slug}', '{plugin}'], $slug, $baseTpl);
        $baseUrl = '/' . ltrim($baseUrl, '/');
        $baseUrl = rtrim($baseUrl, '/');

        $assetOrigin = (string)config('fortiplugin.ui_embed_asset_origin', config('app.url'));
        $assetOrigin = rtrim(trim($assetOrigin), '/');

        if ($assetOrigin !== '' && str_starts_with($baseUrl, '/')) {
            $baseUrl = $assetOrigin . $baseUrl;
        }

        $toUrl = static fn(string $p): string => $baseUrl . '/' . ltrim($p, '/');

        $css = [];
        if (isset($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $css[] = $toUrl($resolveManifestRef($p));
                }
            }
        }

        $imports = [];
        if (isset($entry['imports']) && is_array($entry['imports'])) {
            foreach ($entry['imports'] as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $imports[] = $toUrl($resolveManifestRef($p));
                }
            }
        }

        $entryFile = $entry['file'];
        $versionKey = $slug . ':' . ($versionId ?? 'unknown') . ':' . $entryFile;

        return [
            'entryUrl' => $toUrl($entryFile),
            'css' => array_values(array_unique($css)),
            'imports' => array_values(array_unique($imports)),
            'versionKey' => $versionKey,
        ];
    }
}
