<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use DateTimeImmutable;
use Throwable;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\PathSecurity;

class InstallFilesSection
{
    /**
     * Phase 6: Copy files from staging into install versions dir and promote a pointer.
     * - Honors vendor policy recorded in installation.json.vendor_policy.mode.
     * - Writes a simple pointer file at .internal\\current containing the version id.
     * - Updates installation.json.install with paths and timestamps.
     *
     * Returns array with keys: status ('installed'|'failed'), paths{...}, installed_at, version_id.
     */
    public function run(
        InstallationLogStore $logStore,
        string $stagingRoot,
        string $installRoot,
        ?callable $emit = null
    ): array {
        $stagingRoot = rtrim($stagingRoot, "\\/ ");
        $installRoot = rtrim($installRoot, "\\/ ");
        $nowIso = (new DateTimeImmutable('now'))->format(DATE_ATOM);

        // Read current state to derive vendor policy and fingerprint if available
        $state = $logStore->getCurrent($installRoot);
        $vendorMode = (string)($state['vendor_policy']['mode'] ?? 'strip_bundled_vendor');
        $fingerprint = (string)($state['meta']['fingerprint'] ?? '');
        $versionId = $fingerprint !== '' ? $fingerprint : date('YmdHis');

        $versionsDir = $installRoot . DIRECTORY_SEPARATOR . 'versions';
        $targetDir = $versionsDir . DIRECTORY_SEPARATOR . $versionId;
        $tmpDir = $targetDir . '.tmp';
        $internalDir = $installRoot . DIRECTORY_SEPARATOR . '.internal';
        $pointerFile = $internalDir . DIRECTORY_SEPARATOR . 'current';

        $fs = new AtomicFilesystem();
        $sec = new PathSecurity();
        $filter = function (string $rel) use ($vendorMode): bool {
            if ($vendorMode === 'strip_bundled_vendor') {
                if ($rel === 'vendor' || str_starts_with($rel, 'vendor' . DIRECTORY_SEPARATOR)) {
                    return false; // skip
                }
            }
            return true;
        };

        $status = 'installed';
        $error = null;
        $paths = [
            'staging_root' => $stagingRoot,
            'install_root' => $installRoot,
            'version_dir' => $targetDir,
            'current_pointer' => $pointerFile,
        ];

        try {
            $fs->ensureDir($versionsDir);
            $fs->ensureDir($internalDir);

            // Prepare tmp dir, ensure clean
            if (is_dir($tmpDir)) {
                $fs->removeDir($tmpDir);
            }
            $fs->ensureDir($tmpDir);

            // Copy into tmp then rename to final version dir
            $fs->copyTree($stagingRoot, $tmpDir, $filter, $sec);

            if (is_dir($targetDir)) {
                // If same version already exists, consider idempotent success: reuse existing
                $fs->removeDir($tmpDir);
            } else {
                try {
                    $fs->safeRename($tmpDir, $targetDir);
                } catch (\Throwable $_e) {
                    throw new \RuntimeException('INSTALL_PROMOTION_FAILED: unable to promote version directory');
                }
            }

            // Write/update pointer file atomically
            try {
                $fs->atomicWrite($pointerFile, $versionId);
            } catch (\Throwable $_e) {
                throw new \RuntimeException('INSTALL_PROMOTION_FAILED: unable to promote pointer');
            }

            // Emit success
            if ($emit) {
                try {
                    $emit([
                        'title' => 'Installer: Files Copied',
                        'description' => 'Files copied to version ' . $versionId . ' and pointer promoted',
                        'error' => null,
                        'stats' => ['filePath' => null, 'size' => null],
                        'meta' => ['version_id' => $versionId, 'paths' => $paths],
                    ]);
                } catch (Throwable $_) {}
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
            if ($emit) {
                try {
                    $emit([
                        'title' => 'Installer: Files Copied',
                        'description' => 'Install failed',
                        'error' => ['detail' => $error, 'code' => str_contains($error, 'PROMOTION') ? 'INSTALL_PROMOTION_FAILED' : 'INSTALL_COPY_FAILED'],
                        'stats' => ['filePath' => null, 'size' => null],
                        'meta' => ['version_id' => $versionId, 'paths' => $paths],
                    ]);
                } catch (Throwable $_) {}
            }
        }

        $installBlock = [
            'status' => $status,
            'paths' => $paths,
            'installed_at' => $nowIso,
            'version_id' => $versionId,
            'error' => $error,
        ];

        // Persist to installation.json
        $logStore->setInstall($installRoot, $installBlock);

        // Append to installer emits for completeness
        try {
            $logStore->appendInstallerEmit($installRoot, [
                'title' => 'Installer: Files Copied',
                'description' => $status === 'installed' ? 'Install succeeded' : 'Install failed',
                'error' => $status === 'installed' ? null : ['detail' => $error],
                'stats' => ['filePath' => null, 'size' => null],
                'meta' => $installBlock,
            ]);
        } catch (Throwable $_) {}

        return $installBlock;
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if ($items === false) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
