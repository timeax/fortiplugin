<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\InstallEvents;
use Timeax\FortiPlugin\Models\Plugin;

use function Illuminate\Filesystem\join_paths;


final readonly class PublishBuildAssetsSection
{
    public function __construct(
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
    )
    {
    }

    /**
     * Copy plugin Vite build output to host public folder.
     *
     * @return array{status:string, reason?:string, from?:string, to?:string, alias?:string}
     * @throws JsonException
     */
    public function run(InstallMeta $meta, int $pluginId, callable $emit): array
    {
        $installedRoot = rtrim((string)($meta->paths['install'] ?? ''), "\\/");

        $emit([
            'event' => InstallEvents::UI_ASSETS_START,
            'title' => 'UI_BUILD_PUBLISH_START',
            'description' => 'Publishing embed UI public assets to host public folder',
            'meta' => ['plugin_id' => $pluginId, 'installed_root' => $installedRoot],
        ]);

        // 1. Validate Install Root
        if ($installedRoot === '') {
            return $this->fail('missing_install_root', 'Install root is missing', [], $emit);
        }

        if (!$this->afs->fs()->exists($installedRoot)) {
            return $this->fail('install_root_not_found', 'Install root not found', ['installed_root' => $installedRoot], $emit);
        }

        // 2. Resolve Alias
        $alias = $this->resolvePluginAlias($pluginId);
        if ($alias === null) {
            return $this->fail('missing_plugin_alias', 'Plugin alias missing', ['plugin_id' => $pluginId], $emit);
        }

        $sourcePublicDir = join_paths($installedRoot, 'public');
        $integrated_sourceManifest = join_paths($sourcePublicDir, 'build', '.vite', 'manifest.integrations.json');
        $spa_sourceManifest = join_paths($sourcePublicDir, 'build', '.vite', 'manifest.spa.json');

        $manifests = [$integrated_sourceManifest, $spa_sourceManifest];
        
        $validManifest = null;
        $lastResult = ['status' => 'skipped', 'reason' => 'manifest_missing', 'alias' => $alias];

        foreach ($manifests as $manifest) {
            if (!$this->afs->fs()->exists($manifest)) {
                continue;
            }

            $result = $this->compileManifest($manifest, $alias, $emit);

            if ($result['status'] === 'fail') {
                return $result;
            }

            if ($result['status'] === 'ok') {
                $validManifest = $manifest;
                break;
            }
            
            $lastResult = $result;
        }

        if ($validManifest === null) {
            return $lastResult;
        }

        return $this->copyAssets($alias, $sourcePublicDir, $validManifest, $emit);
    }

    /**
     * @return array{status:string, reason?:string, alias?:string}
     * @throws JsonException
     */
    protected function compileManifest(string $source_manifest, string $alias, callable $emit): array
    {
        // 4. Validate Manifest Content
        try {
            $manifest = $this->afs->fs()->readJson($source_manifest);
        } catch (Throwable $e) {
            return $this->fail('manifest_unreadable', 'Vite manifest unreadable', ['alias' => $alias, 'manifest' => $source_manifest, 'exception' => $e->getMessage()], $emit);
        }

        if (str_contains($source_manifest, 'manifest.integrations.json') && !$this->manifestHasEmbedEntries($manifest)) {
            return $this->skip('no_embed_entries', 'Manifest has no embed entries', $alias, [], $emit);
        }

        return ['status' => 'ok', 'alias' => $alias];
    }

    /**
     * @return array{status:string, reason?:string, from?:string, to?:string, alias?:string}
     * @throws JsonException
     */
    protected function copyAssets(string $alias, string $sourcePublicDir, string $manifest, callable $emit): array
    {
        // 5. Perform Copy
        try {
            [$baseTpl, $baseUrlPath, $destDir] = $this->resolveDestinationFromConfig($alias);

            if ($this->afs->fs()->exists($destDir)) {
                $this->afs->fs()->delete($destDir);
            }

            $this->afs->fs()->ensureDirectory($destDir);
            $this->afs->fs()->copyTree($sourcePublicDir, $destDir);

            $this->log->writeSection('ui_build_publish', [
                'status' => 'ok',
                'alias' => $alias,
                'base_tpl' => $baseTpl,
                'base_url_path' => $baseUrlPath,
                'from' => $sourcePublicDir,
                'to' => $destDir,
                'manifest' => $manifest,
            ]);

            $emit([
                'event' => InstallEvents::UI_ASSETS_END,
                'title' => 'UI_BUILD_PUBLISH_OK',
                'description' => 'Embed UI public assets published to host public folder',
                'meta' => ['alias' => $alias, 'from' => $sourcePublicDir, 'to' => $destDir, 'base_url_path' => $baseUrlPath],
            ]);

            return ['status' => 'ok', 'alias' => $alias, 'from' => $sourcePublicDir, 'to' => $destDir];

        } catch (Throwable $e) {
            return $this->fail('copy_failed', 'Failed to publish embed UI public assets', ['alias' => $alias, 'from' => $sourcePublicDir, 'to' => $destDir ?? 'unknown', 'error' => $e->getMessage()], $emit);
        }
    }

    /**
     * Helper to consolidate failure logging, emitting, and return structure.
     * @throws JsonException
     */
    private function fail(string $reason, string $desc, array $meta, callable $emit): array
    {
        $this->log->writeSection('ui_build_publish', array_merge(['status' => 'fail', 'reason' => $reason], $meta));
        $emit(['title' => 'UI_BUILD_PUBLISH_FAIL', 'description' => $desc, 'meta' => $meta]);
        return ['status' => 'fail', 'reason' => $reason];
    }

    /**
     * Helper to consolidate skip logging, emitting, and return structure.
     * @throws JsonException
     */
    private function skip(string $reason, string $desc, string $alias, array $meta, callable $emit): array
    {
        $meta = array_merge(['alias' => $alias], $meta);
        $this->log->writeSection('ui_build_publish', array_merge(['status' => 'skipped', 'reason' => $reason], $meta));
        $emit(['title' => 'UI_BUILD_PUBLISH_SKIPPED', 'description' => $desc, 'meta' => $meta]);
        return ['status' => 'skipped', 'reason' => $reason, 'alias' => $alias];
    }

    private function resolvePluginAlias(int $pluginId): ?string
    {
        /** @var Plugin|null $plugin */
        $plugin = Plugin::query()->find($pluginId);
        if (!$plugin) return null;

        $alias = trim((string)$plugin->alias);
        return $alias !== '' ? $alias : null;
    }

    private function resolveDestinationFromConfig(string $alias): array
    {
        $baseTpl = (string)config('fortiplugin.ui.embed.public_base');
        $baseTpl = trim($baseTpl) === '' ? '/vendor/fortiplugin/{alias}' : trim($baseTpl);

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $baseTpl)) {
            throw new RuntimeException("fortiplugin.ui.embed.public_base must be a URL path, not a full URL.");
        }

        if (!str_contains($baseTpl, '{alias}') && !str_contains($baseTpl, '{slug}') && !str_contains($baseTpl, '{plugin}')) {
            throw new RuntimeException("fortiplugin.ui.embed.public_base must contain {alias}, {slug} or {plugin}.");
        }

        $baseUrlPath = '/' . ltrim(str_replace(['{alias}', '{slug}', '{plugin}'], $alias, $baseTpl), '/');
        $baseUrlPath = rtrim($baseUrlPath, '/');

        if ($baseUrlPath === '' || $baseUrlPath === '/') {
            throw new RuntimeException("fortiplugin.ui.embed.public_base resolved to '/', refusing to publish.");
        }

        if (str_contains($baseUrlPath, '..')) {
            throw new RuntimeException("fortiplugin.ui.embed.public_base contains '..', refusing to publish.");
        }

        return [$baseTpl, $baseUrlPath, join_paths(public_path(), ltrim($baseUrlPath, '/'))];
    }

    private function manifestHasEmbedEntries(array $manifest): bool
    {
        foreach ($manifest as $k => $_) {
            if (is_string($k) && str_starts_with($k, 'resources/embed/ts/')) {
                return true;
            }
        }
        return false;
    }
}