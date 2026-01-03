<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Models\Plugin;

use function Illuminate\Filesystem\join_paths;


final readonly class PublishBuildAssetsSection
{
    public function __construct(
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
    ) {}

    /**
     * Copy plugin Vite build output to host public folder.
     *
     * @return array{status:string, reason?:string, from?:string, to?:string, slug?:string}
     * @throws JsonException
     */
    public function run(InstallMeta $meta, int $pluginId, callable $emit): array
    {
        $installedRoot = rtrim((string)($meta->paths['install'] ?? ''), "\\/");

        $emit([
            'title' => 'UI_BUILD_PUBLISH_START',
            'description' => 'Publishing embed UI build assets to host public folder',
            'meta' => ['plugin_id' => $pluginId, 'installed_root' => $installedRoot],
        ]);

        // 1. Validate Install Root
        if ($installedRoot === '') {
            return $this->fail('missing_install_root', 'Install root is missing', [], $emit);
        }

        if (!$this->afs->fs()->exists($installedRoot)) {
            return $this->fail('install_root_not_found', 'Install root not found', ['installed_root' => $installedRoot], $emit);
        }

        // 2. Resolve Slug
        $slug = $this->resolvePluginSlug($pluginId);
        if ($slug === null) {
            return $this->fail('missing_plugin_slug', 'Plugin slug missing', ['plugin_id' => $pluginId], $emit);
        }

        $sourceBuildDir = join_paths($installedRoot, 'public', 'build');
        $sourceManifest = join_paths($sourceBuildDir, '.vite','manifest.json');

        // 3. Validate Manifest Existence
        if (!$this->afs->fs()->exists($sourceManifest)) {
            return $this->skip('manifest_missing', 'No Vite manifest found', $slug, ['manifest' => $sourceManifest], $emit);
        }

        // 4. Validate Manifest Content
        try {
            $manifest = $this->afs->fs()->readJson($sourceManifest);
        } catch (Throwable $e) {
            return $this->fail('manifest_unreadable', 'Vite manifest unreadable', ['slug' => $slug, 'manifest' => $sourceManifest, 'exception' => $e->getMessage()], $emit);
        }

        if (!$this->manifestHasEmbedEntries($manifest)) {
            return $this->skip('no_embed_entries', 'Manifest has no embed entries', $slug, [], $emit);
        }

        // 5. Perform Copy
        [$baseTpl, $baseUrlPath, $destDir] = $this->resolveDestinationFromConfig($slug);

        try {
            if ($this->afs->fs()->exists($destDir)) {
                $this->afs->fs()->delete($destDir);
            }

            $this->afs->fs()->ensureDirectory($destDir);
            $this->afs->fs()->copyTree($sourceBuildDir, $destDir);

            $this->log->writeSection('ui_build_publish', [
                'status' => 'ok',
                'slug' => $slug,
                'base_tpl' => $baseTpl,
                'base_url_path' => $baseUrlPath,
                'from' => $sourceBuildDir,
                'to' => $destDir,
                'manifest' => $sourceManifest,
            ]);

            $emit([
                'title' => 'UI_BUILD_PUBLISH_OK',
                'description' => 'Embed UI build assets published to host public folder',
                'meta' => ['slug' => $slug, 'from' => $sourceBuildDir, 'to' => $destDir, 'base_url_path' => $baseUrlPath],
            ]);

            return ['status' => 'ok', 'slug' => $slug, 'from' => $sourceBuildDir, 'to' => $destDir];

        } catch (Throwable $e) {
            return $this->fail('copy_failed', 'Failed to publish embed UI build assets', ['slug' => $slug, 'from' => $sourceBuildDir, 'to' => $destDir, 'error' => $e->getMessage()], $emit);
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
    private function skip(string $reason, string $desc, string $slug, array $meta, callable $emit): array
    {
        $meta = array_merge(['slug' => $slug], $meta);
        $this->log->writeSection('ui_build_publish', array_merge(['status' => 'skipped', 'reason' => $reason], $meta));
        $emit(['title' => 'UI_BUILD_PUBLISH_SKIPPED', 'description' => $desc, 'meta' => $meta]);
        return ['status' => 'skipped', 'reason' => $reason, 'slug' => $slug];
    }

    private function resolvePluginSlug(int $pluginId): ?string
    {
        $plugin = Plugin::query()->with('placeholder')->find($pluginId);
        if (!$plugin) return null;

        $slug = trim((string)($plugin->placeholder->slug ?? $plugin->slug ?? $plugin->id));
        return $slug !== '' ? $slug : null;
    }

    private function resolveDestinationFromConfig(string $slug): array
    {
        $baseTpl = (string)config('fortiplugin.ui.embed.public_base');
        $baseTpl = trim($baseTpl) === '' ? '/vendor/fortiplugin/{slug}/build' : trim($baseTpl);

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $baseTpl)) {
            throw new RuntimeException("fortiplugin.ui.embed.public_base must be a URL path, not a full URL.");
        }

        if (!str_contains($baseTpl, '{slug}') && !str_contains($baseTpl, '{plugin}')) {
            throw new RuntimeException("fortiplugin.ui.embed.public_base must contain {slug} or {plugin}.");
        }

        $baseUrlPath = '/' . ltrim(str_replace(['{slug}', '{plugin}'], $slug, $baseTpl), '/');
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