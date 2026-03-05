<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Random\RandomException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Enums\ProcessStatus;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Installer;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Models\PluginProcess;
use Timeax\FortiPlugin\Services\ValidatorService;
use ZipArchive;

final class InstallPluginZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    /**
     * @param array<string,mixed> $zipMeta
     * @param array<string,mixed> $validatorConfig
     */
    public function __construct(
        public readonly int     $zipId,
        public readonly string  $zipPath,
        public readonly int     $placeholderId,
        public readonly array   $zipMeta,
        public readonly string  $placeholderSlug,
        public readonly string  $placeholderName,
        public readonly string  $versionTag,
        public readonly array   $validatorConfig,
        public readonly string  $runId,
        public readonly string  $actor,
        public readonly ?string $installerToken = null,
    )
    {
    }


    /**
     * @throws Throwable
     * @throws RandomException
     * @throws JsonException
     */
    public function handle(
        Installer        $installer,
        InstallerPolicy  $policy,
        ValidatorService $validator,
    ): void
    {
        if (!is_file($this->zipPath)) {
            throw new RuntimeException('ZIP_NOT_FOUND');
        }


        $stagingBase = storage_path("app/fortiplugin/staging/$this->zipId-$this->runId");
        $this->ensureDir($stagingBase);

        $this->safeExtractZip($this->zipPath, $stagingBase);

        $pluginDir = $this->resolvePluginRoot($stagingBase);
        if ($pluginDir === null) {
            throw new RuntimeException('PLUGIN_ROOT_NOT_FOUND');
        }

        $logsDir = rtrim($pluginDir, "\\/") . DIRECTORY_SEPARATOR . $policy->getLogsDirName();
        $this->ensureDir($logsDir);

        $installRoot = (string)config('fortiplugin.install_directory', 'apps');
        $installDir = base_path($installRoot . DIRECTORY_SEPARATOR . $this->placeholderName);

        $validatorConfigHash = $this->stableSha256($this->validatorConfig);

        $meta = new InstallMeta(
            psr4_root: $policy->getPsr4Root(),
            placeholder_name: $this->placeholderName,
            placeholder_slug: $this->placeholderSlug,
            plugin_placeholder_id: $this->placeholderId,
            zip_id: $this->zipId,
            actor: $this->actor,
            paths: [
                'staging' => $pluginDir,
                'install' => $installDir,
                'logs' => $logsDir,
            ],
            started_at: now()->toIso8601String(),
            updated_at: now()->toIso8601String(),
            fingerprint: hash_file('sha256', $this->zipPath) ?: '',
            validator_config_hash: $validatorConfigHash,
        );


        $result = $installer->run(
            meta: $meta,
            zipId: $this->zipId,
            validator: $validator,
            validatorConfig: $this->validatorConfig,
            validatorConfigHash: $validatorConfigHash,
            versionTag: $this->versionTag,
            actor: $this->actor,
            runId: $this->runId,
            installerToken: $this->installerToken,
        );

        $process = PluginProcess::where('run_id', $this->runId)->firstOrFail();

        if ($result->passed()) {
            $process->status = ProcessStatus::success;
        } else if ($result->failed()) {
            $process->status = ProcessStatus::failed;
        }

        if ($process->isDirty()) $process->save();

        $safeData = json_decode(json_encode($result->toArray(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        cache()->put("fortiplugin:install:$this->runId", $safeData, now()->addDay());
    }

    private function sanitizePlaceholderName(string $name): string
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

        $dest = rtrim($dest, DIRECTORY_SEPARATOR);

        if (!is_dir($dest) && !@mkdir($dest, 0755, true) && !is_dir($dest)) {
            $zip->close();
            throw new RuntimeException('FAILED_TO_CREATE_DEST_DIR');
        }

        $destReal = realpath($dest) ?: $dest;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || $name === '') {
                continue;
            }

            // Normalize Windows separators to POSIX (critical on Linux)
            $normalized = str_replace('\\', '/', $name);
            $normalized = ltrim($normalized, '/');

            // Zip-slip / unsafe paths (stronger than ".." substring check)
            if (
                str_contains($normalized, "\0") ||
                str_starts_with($normalized, '../') ||
                str_contains($normalized, '/../') ||
                str_contains($normalized, ':')
            ) {
                $zip->close();
                throw new RuntimeException('ZIP_SLIP_DETECTED');
            }

            // Directory entry
            if (str_ends_with($normalized, '/')) {
                $dirPath = $dest . DIRECTORY_SEPARATOR . rtrim(str_replace('/', DIRECTORY_SEPARATOR, $normalized), DIRECTORY_SEPARATOR);
                if (!mkdir($dirPath, 0755, true) && !is_dir($dirPath)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $dirPath));
                }
                continue;
            }

            $target = $dest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
            $targetDir = dirname($target);

            if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                $zip->close();
                throw new RuntimeException('FAILED_TO_CREATE_EXTRACT_DIR');
            }

            // Ensure final resolved directory remains inside $dest (extra safety)
            $targetDirReal = realpath($targetDir) ?: $targetDir;
            if (!str_starts_with($targetDirReal, $destReal)) {
                $zip->close();
                throw new RuntimeException('ZIP_SLIP_DETECTED');
            }

            // Read using ORIGINAL name (ZipArchive indexes by original entry name)
            $in = $zip->getStream($name);
            if (!$in) {
                $zip->close();
                throw new RuntimeException('FAILED_TO_READ_ZIP_ENTRY');
            }

            $out = fopen($target, 'wb');
            if (!$out) {
                fclose($in);
                $zip->close();
                throw new RuntimeException('FAILED_TO_WRITE_EXTRACTED_FILE');
            }

            stream_copy_to_stream($in, $out);
            fclose($out);
            fclose($in);
        }

        $zip->close();
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("FAILED_TO_CREATE_DIR: $dir");
        }
    }

    private function resolvePluginRoot(string $base): ?string
    {
        $base = rtrim($base, "\\/");

        if ($this->looksLikePluginRoot($base)) return $base;

        foreach (scandir($base) ?: [] as $c) {
            if ($c === '.' || $c === '..') continue;
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

    /** @throws JsonException */
    private function stableSha256(array $value): string
    {
        $json = json_encode($this->normalizeForStableJson($value), JSON_THROW_ON_ERROR);
        return hash('sha256', $json);
    }

    private function normalizeForStableJson(mixed $v): mixed
    {
        if (!is_array($v)) return $v;
        ksort($v);
        foreach ($v as $k => $vv) {
            $v[$k] = $this->normalizeForStableJson($vv);
        }
        return $v;
    }

    private function normalizeResult(mixed $result): array
    {
        if (is_array($result)) return $result;
        if (is_object($result) && method_exists($result, 'toArray')) {
            return (array)$result->toArray();
        }
        return is_object($result) ? get_object_vars($result) : ['value' => $result];
    }
}
