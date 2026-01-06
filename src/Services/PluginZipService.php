<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Jobs\InstallPluginZipJob;
use Timeax\FortiPlugin\Models\PluginZip;

final readonly class PluginZipService
{
    public function __construct(private AtomicFilesystem $afs) {}

    public function list(int $limit = 100): Collection
    {
        return PluginZip::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     */
    public function install(
        int $zipId,
        ?string $installerToken = null,
        ?string $runId = null,
        ?string $actor = null,
        string $dispatchMode = 'auto' // 'auto' | 'sync' | 'queue'
    ): array {
        $zip = PluginZip::query()->with('placeholder')->find($zipId);

        if (!$zip) {
            throw new RuntimeException("PluginZip #{$zipId} not found");
        }

        $fs = $this->afs->fs();
        $zipPath = ($zip->path ?? '');

        if ($zipPath === '' || !$fs->exists($zipPath) || !$fs->isFile($zipPath)) {
            throw new RuntimeException('ZIP_NOT_FOUND');
        }

        $metaData  = is_array($zip->meta ?? null) ? $zip->meta : [];
        $manifest  = is_array($metaData['manifest'] ?? null) ? $metaData['manifest'] : [];
        $pluginMan = is_array($manifest['plugin'] ?? null) ? $manifest['plugin'] : [];

        $placeholderId = $zip->placeholder_id;

        $placeholderSlug = $this->sanitizePlaceholderName(
            (string)(
                $zip->placeholder->slug
                ?? $pluginMan['slug']
                ?? $manifest['slug']
                ?? $manifest['name']
                ?? ('placeholder-' . $placeholderId)
            )
        );

        $placeholderName = (string)(
            $zip->placeholder->name
            ?? $pluginMan['name']
            ?? $manifest['name']
            ?? $placeholderSlug
        );

        $versionTag = (string)(
            $pluginMan['version']
            ?? $manifest['version']
            ?? '0.0.0'
        );

        $validatorConfig = is_array($metaData['validator_config'] ?? null)
            ? $metaData['validator_config']
            : [];

        $runId = $runId ?: (string) Str::uuid();
        $actor = $actor ?: 'system';

        if ($this->shouldDispatchSync($dispatchMode)) {
            InstallPluginZipJob::dispatchSync(
                zipId: $zip->id,
                zipPath: $zipPath,
                placeholderId: $placeholderId,
                zipMeta: $metaData,
                placeholderSlug: $placeholderSlug,
                placeholderName: $placeholderName,
                versionTag: $versionTag,
                validatorConfig: $validatorConfig,
                runId: $runId,
                actor: $actor,
                installerToken: $installerToken,
            );

            return [
                'ok' => true,
                'queued' => false,
                'run_id' => $runId,
                'zip_id' => $zip->id,
                'placeholder_name' => $placeholderName,
                'placeholder_slug' => $placeholderSlug,
                'version_tag' => $versionTag,
            ];
        }

        InstallPluginZipJob::dispatch(
            zipId: $zip->id,
            zipPath: $zipPath,
            placeholderId: $placeholderId,
            zipMeta: $metaData,
            placeholderSlug: $placeholderSlug,
            placeholderName: $placeholderName,
            versionTag: $versionTag,
            validatorConfig: $validatorConfig,
            runId: $runId,
            actor: $actor,
            installerToken: $installerToken,
        );

        return [
            'ok' => true,
            'queued' => true,
            'run_id' => $runId,
            'zip_id' => $zip->id,
            'placeholder_name' => $placeholderName,
            'placeholder_slug' => $placeholderSlug,
            'version_tag' => $versionTag,
        ];

    }

    public function delete(int $zipId): array
    {
        $zip = $this->getZip($zipId);

        $fs = $this->afs->fs();

        $fileDeleted = false;
        $zipPath = ($zip->path ?? '');

        if ($zipPath !== '' && $fs->exists($zipPath) && $fs->isFile($zipPath)) {
            $fs->delete($zipPath);
            $fileDeleted = true;
        }

        // staging dirs are created like: storage/app/fortiplugin/staging/{zipId}-{runId}
        // we’ll best-effort delete any matching directories
        $stagingDeleted = 0;
        foreach ((array) glob(storage_path("app/fortiplugin/staging/{$zipId}-*")) as $candidate) {
            if ($candidate && $fs->exists($candidate) && $fs->isDirectory($candidate)) {
                $fs->delete($candidate); // recursive by contract
                $stagingDeleted++;
            }
        }

        $zip->delete();

        return [
            'ok' => true,
            'zip_id' => $zipId,
            'file_deleted' => $fileDeleted,
            'staging_deleted' => $stagingDeleted,
        ];
    }

    public function getZip(int $zipId): PluginZip
    {
        $zip = PluginZip::query()->find($zipId);

        if (!$zip) {
            throw new RuntimeException("PluginZip #{$zipId} not found");
        }

        return $zip;
    }

    private function shouldDispatchSync(string $dispatchMode): bool
    {
        $mode = strtolower(trim($dispatchMode));

        if ($mode === 'sync') return true;
        if ($mode === 'queue') return false;

        // auto: sync in CLI/scripts, queue in normal HTTP lifecycle
        return app()->runningInConsole();
    }

    private function sanitizePlaceholderName(string $name): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,100}$/i', $name)) {
            throw new RuntimeException('INVALID_PLACEHOLDER_NAME');
        }

        return $name;
    }
}
