<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Installer;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\DTO\InstallerResult;
use ZipArchive;

final readonly class PluginInstallationService
{
    public function __construct(
        private Installer $installer,
        private InstallerPolicy $policy,
        private ValidatorService $validator,
    ) {}

    /**
     * @param string $zipPath
     * @param array{
     *     zipId?: int|string,
     *     placeholderId?: int|string,
     *     placeholderName?: string,
     *     versionTag?: string,
     *     validatorConfig?: array,
     *     runId?: string,
     *     actor?: string,
     *     installerToken?: string|null
     * } $options
     * @return InstallerResult
     * @throws JsonException
     */
    public function installFromZip(string $zipPath, array $options = []): InstallerResult
    {
        if (!is_file($zipPath)) {
            throw new RuntimeException('ZIP_NOT_FOUND');
        }

        $runId = $options['runId'] ?? (string) Str::uuid();
        $zipId = $options['zipId'] ?? $zipPath;
        $actor = $options['actor'] ?? 'system';
        $placeholderId = $options['placeholderId'] ?? 0;
        $validatorConfig = $options['validatorConfig'] ?? [];
        $installerToken = $options['installerToken'] ?? null;

        $safeZipId = is_numeric($zipId) ? (string) $zipId : Str::slug((string) $zipId);
        $stagingBase = storage_path("app/fortiplugin/staging/{$safeZipId}-{$runId}");
        $this->ensureDir($stagingBase);

        $this->safeExtractZip($zipPath, $stagingBase);

        $pluginDir = $this->resolvePluginRoot($stagingBase);
        if ($pluginDir === null) {
            throw new RuntimeException('PLUGIN_ROOT_NOT_FOUND');
        }

        // Try to auto-resolve metadata if not provided
        $placeholderName = $options['placeholderName'] ?? null;
        $versionTag = $options['versionTag'] ?? null;

        if ($placeholderName === null || $versionTag === null) {
            $manifest = $this->loadManifest($pluginDir);
            $pluginMan = $manifest['plugin'] ?? [];

            if ($placeholderName === null) {
                $placeholderName = (string) (
                    $pluginMan['slug']
                    ?? $manifest['slug']
                    ?? $manifest['name']
                    ?? ('placeholder-' . $placeholderId)
                );
            }

            if ($versionTag === null) {
                $versionTag = (string) (
                    $pluginMan['version']
                    ?? $manifest['version']
                    ?? '0.0.0'
                );
            }
        }

        $safeName = $this->sanitizePlaceholderName($placeholderName);

        $logsDir = rtrim($pluginDir, "\\/") . DIRECTORY_SEPARATOR . $this->policy->getLogsDirName();
        $this->ensureDir($logsDir);

        $installRoot = (string) config('fortiplugin.directory', 'apps');
        $installDir  = base_path($installRoot . DIRECTORY_SEPARATOR . $safeName);

        $validatorConfigHash = $this->stableSha256($validatorConfig);

        $meta = new InstallMeta(
            psr4_root: $this->policy->getPsr4Root(),
            placeholder_name: $safeName,
            plugin_placeholder_id: $placeholderId,
            zip_id: $zipId,
            actor: $actor,
            paths: [
                'staging' => $pluginDir,
                'install' => $installDir,
                'logs'    => $logsDir,
            ],
            started_at: now()->toIso8601String(),
            updated_at: now()->toIso8601String(),
            fingerprint: hash_file('sha256', $zipPath) ?: '',
            validator_config_hash: $validatorConfigHash,
        );

        return $this->installer->run(
            meta: $meta,
            zipId: $zipId,
            validator: $this->validator,
            validatorConfig: $validatorConfig,
            validatorConfigHash: $validatorConfigHash,
            versionTag: $versionTag,
            actor: $actor,
            runId: $runId,
            emit: null,
            onFinish: null,
            installerToken: $installerToken,
        );
    }

    public function sanitizePlaceholderName(string $name): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,100}$/i', $name)) {
            throw new RuntimeException('INVALID_PLACEHOLDER_NAME');
        }
        return $name;
    }

    private function safeExtractZip(string $zipPath, string $dest): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('FAILED_TO_OPEN_ZIP');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (
                str_contains($name, '..') ||
                str_starts_with($name, '/') ||
                str_contains($name, ':')
            ) {
                $zip->close();
                throw new RuntimeException('ZIP_SLIP_DETECTED');
            }
        }

        if (!$zip->extractTo($dest)) {
            $zip->close();
            throw new RuntimeException('FAILED_TO_EXTRACT_ZIP');
        }

        $zip->close();
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("FAILED_TO_CREATE_DIR: {$dir}");
        }
    }

    private function resolvePluginRoot(string $base): ?string
    {
        $base = rtrim($base, "\\/");

        if ($this->looksLikePluginRoot($base)) {
            return $base;
        }

        foreach (scandir($base) ?: [] as $c) {
            if ($c === '.' || $c === '..') {
                continue;
            }
            $p = $base . DIRECTORY_SEPARATOR . $c;
            if (is_dir($p) && $this->looksLikePluginRoot($p)) {
                return $p;
            }
        }

        return null;
    }

    private function looksLikePluginRoot(string $dir): bool
    {
        return is_file($dir . '/fortiplugin.json')
            || is_file($dir . '/composer.json');
    }

    private function loadManifest(string $pluginDir): array
    {
        $manifestPath = is_file($pluginDir . '/fortiplugin.json')
            ? $pluginDir . '/fortiplugin.json'
            : $pluginDir . '/composer.json';

        if (!is_file($manifestPath)) {
            return [];
        }

        try {
            return json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
    }

    /** @throws JsonException */
    private function stableSha256(array $value): string
    {
        $json = json_encode($this->normalizeForStableJson($value), JSON_THROW_ON_ERROR);
        return hash('sha256', $json);
    }

    private function normalizeForStableJson(mixed $v): mixed
    {
        if (!is_array($v)) {
            return $v;
        }
        ksort($v);
        foreach ($v as $k => $vv) {
            $v[$k] = $this->normalizeForStableJson($vv);
        }
        return $v;
    }
}
