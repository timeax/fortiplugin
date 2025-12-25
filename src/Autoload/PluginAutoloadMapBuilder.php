<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Autoload;

use RuntimeException;

final class PluginAutoloadMapBuilder
{
    /**
     * Returns: [ 'NamespacePrefix\\' => [ '/abs/path1', '/abs/path2' ] ]
     *
     * Security/consistency rule:
     * - We ONLY accept the expected prefix: "{$psr4Root}\\{$pluginName}\\"
     * - We ignore any other PSR-4 mappings a plugin may declare.
     */
    public function build(string $pluginRoot, string $pluginName, string $psr4Root = 'Plugins'): array
    {
        $composerPath = rtrim($pluginRoot, "/\\") . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($composerPath)) {
            throw new RuntimeException("Plugin composer.json not found: {$composerPath}");
        }

        $raw = @file_get_contents($composerPath);
        if ($raw === false) {
            throw new RuntimeException("Unable to read plugin composer.json: {$composerPath}");
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException("Invalid JSON in plugin composer.json: {$composerPath}");
        }

        $psr4 = $json['autoload']['psr-4'] ?? null;
        if (!is_array($psr4)) {
            throw new RuntimeException("Missing autoload.psr-4 in plugin composer.json: {$composerPath}");
        }

        $expectedPrefix = $this->normalizePrefix($psr4Root . '\\' . $pluginName . '\\');

        // Find the exact matching prefix (normalized)
        $matchedKey = null;
        foreach ($psr4 as $prefix => $_paths) {
            if (!is_string($prefix)) {
                continue;
            }
            if ($this->normalizePrefix($prefix) === $expectedPrefix) {
                $matchedKey = $prefix;
                break;
            }
        }

        if ($matchedKey === null) {
            throw new RuntimeException("Plugin PSR-4 prefix not found. Expected: {$expectedPrefix} in {$composerPath}");
        }

        $paths = $psr4[$matchedKey];
        $pathsArr = is_array($paths) ? $paths : [$paths];

        $absPaths = [];
        foreach ($pathsArr as $rel) {
            if (!is_string($rel)) {
                continue;
            }

            $rel = trim($rel);
            if ($rel === '') {
                continue;
            }

            $abs = $this->resolvePath($pluginRoot, $rel);
            $absPaths[] = $abs;
        }

        $absPaths = array_values(array_unique(array_filter($absPaths, static fn ($p) => is_string($p) && trim($p) !== '')));

        if ($absPaths === []) {
            throw new RuntimeException("Plugin PSR-4 paths empty/invalid for prefix {$expectedPrefix} in {$composerPath}");
        }

        return [
            $expectedPrefix => $absPaths,
        ];
    }

    private function resolvePath(string $pluginRoot, string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return rtrim($pluginRoot, "/\\") . DIRECTORY_SEPARATOR . ltrim($path, "/\\");
    }

    private function isAbsolutePath(string $path): bool
    {
        // Linux/macOS absolute
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return true;
        }

        // Windows absolute: C:\... or C:/...
        return (bool) preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
    }

    private function normalizePrefix(string $prefix): string
    {
        $p = str_replace('/', '\\', $prefix);
        $p = trim($p);
        return rtrim($p, '\\') . '\\';
    }
}
