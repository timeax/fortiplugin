<?php

namespace Timeax\FortiPlugin\Lib\Utils;

use Illuminate\Support\Facades\Storage;
use Timeax\FortiPlugin\Support\PluginContext;
use Timeax\FortiPlugin\Core\ChecksModulePermission;

class FileIoUtility
{
    use ChecksModulePermission;

    protected string $type = 'module';
    protected string $target = 'file.io';

    /**
     * Only allow access to files within allowed plugin folders.
     * Stricter, with explicit permission hook for anything else.
     */
    protected function isPathAllowed(string $path, string $disk = 'local'): bool
    {
        $pluginName = PluginContext::getCurrentPluginName() ?? 'unknown_plugin';

        // Resolve absolute path on the chosen disk
        $diskPath = Storage::disk($disk)->path($path);
        $absPath = realpath($diskPath);
        if ($absPath === false) {
            return false; // path doesn't exist / broken symlink
        }

        // Block any ".internal" segment anywhere in the resolved path
        if (preg_match('~(^|/)\.internal(/|$)~', $absPath)) {
            return false;
        }

        // Canonical roots (with trailing slash for safe prefix checks)
        $storageRoot = $this->canon(storage_path());
        $pluginBase = $this->canon(storage_path('plugins/' . $pluginName));

        // Must live somewhere under storage/
        if (!$this->startsWithPath($absPath, $storageRoot)) {
            return false;
        }

        // Always allow inside this plugin's own storage area
        if ($this->startsWithPath($absPath, $pluginBase)) {
            return true;
        }

        // Optionally allow extra safe roots (configured, not user-controlled)
        foreach ($this->allowedExtraRoots($pluginName) as $extraRoot) {
            $canon = $this->canon($extraRoot);
            if ($canon && $this->startsWithPath($absPath, $canon)) {
                return true;
            }
        }

        // Anything else requires an explicit permission decision.
        // Wire this to your gate/service (FileAccessGateService, policy, etc.)
        return $this->hasExplicitFilePermission($absPath, $disk);
    }

    /** Ensure trailing slash; resolve realpath if possible. */
    protected function canon(string $path): ?string
    {
        $rp = realpath($path);
        if ($rp === false) {
            return null;
        }
        return rtrim($rp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** Robust "path starts with root" using canonicalized forms. */
    protected function startsWithPath(string $absPath, string $rootWithSlash): bool
    {
        // Normalize the target path with trailing slash removed
        $abs = rtrim($absPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return strncmp($abs, $rootWithSlash, strlen($rootWithSlash)) === 0;
    }

    /**
     * Extra roots you explicitly trust (e.g., cache area for this plugin).
     * Configure via: config('secure-plugin.allowed_paths')
     * Values can include "{plugin}" placeholder.
     */
    protected function allowedExtraRoots(string $pluginName): array
    {
        $roots = (array)config('secure-plugin.allowed_paths', []);
        return array_map(
            static fn($p) => str_replace(['{plugin}', ':plugin'], $pluginName, $p),
            $roots
        );
    }

    /**
     * Hook for your permission layer.
     * Replace it with your own Gate/Policy or service call.
     * @noinspection PhpUnusedParameterInspection
     */
    protected function hasExplicitFilePermission(string $absPath, string $disk): array
    {
        // Example (pseudocode):
        // return app(FileAccessGateService::class)->allows($absPath, $disk);
        $config = $this->getPluginConfigClass();
        return $config::getPermission('file', $absPath, ['read']);
    }
}