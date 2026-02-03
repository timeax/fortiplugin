<?php

namespace Timeax\FortiPlugin\Support;

use Timeax\FortiPlugin\Contracts\ConfigInterface;

/**
 * PluginContext
 *
 * Utility class to detect the calling plugin's base directory, config, and name,
 * by scanning the call stack for the first file inside the configured Plugins directory.
 *
 * - Respects 'secured-plugin.directory' config (default: 'Plugins')
 * - Stack frame scan depth defaults to 10 (configurable, but never less than 10)
 * - No caching for accuracy in multi-plugin requests
 *
 * Usage:
 *   $pluginDir = PluginContext::getCurrentPluginDir();
 *   $configPath = PluginContext::getCurrentConfigPath();
 *   $pluginName = PluginContext::getCurrentPluginName();
 */
class PluginContext
{
    /**
     * Returns the maximum number of call stack frames to scan,
     * always at least 10.
     *
     * @return int
     */
    protected static function getStackDepth(): int
    {
        $extra = (int)config('secured-plugin.stack_depth', 1); // default to 1 if not set
        return (max($extra, 1)) + 9; // always at least 10
    }

    /**
     * Returns the base directory path of the calling plugin, or null if not found.
     *
     * @return string|null
     */
    public static function getCurrentPluginDir(): ?string
    {
        $pluginBase = base_path(config('fortiplugin.install_directory', 'Plugins'));

        // Normalize base path to an absolute real path when possible
        $baseReal = realpath($pluginBase) ?: $pluginBase;
        $baseNorm = self::normalizePath($baseReal);

        // Ensure trailing slash so prefix matching doesn't falsely match "PluginsX"
        $baseNorm = rtrim($baseNorm, '/') . '/';

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::getStackDepth());

        foreach ($trace as $frame) {
            if (!isset($frame['file']) || !is_string($frame['file'])) {
                continue;
            }

            $file = $frame['file'];
            $fileReal = realpath($file) ?: $file;
            $fileNorm = self::normalizePath($fileReal);

            if (!self::pathStartsWith($fileNorm, $baseNorm)) {
                continue;
            }

            // file is inside plugin base directory
            $relPath = substr($fileNorm, strlen($baseNorm));
            $relPath = ltrim($relPath, '/');

            if ($relPath === '') {
                continue;
            }

            $parts = explode('/', $relPath);
            if (!empty($parts[0])) {
                // Return the plugin's root directory in normalized absolute form
                return rtrim($baseNorm, '/') . '/' . $parts[0];
            }
        }

        return null;
    }

    private static function normalizePath(string $path): string
    {
        // Convert backslashes to slashes and collapse duplicate slashes
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('~/+~', '/', $path) ?: $path;

        // Optional: trim trailing slash (caller decides on trailing slash needs)
        return rtrim($path, '/');
    }

    private static function pathStartsWith(string $path, string $prefix): bool
    {
        // $prefix is expected to already have trailing slash
        if (PHP_OS_FAMILY === 'Windows') {
            return str_starts_with(strtolower($path), strtolower($prefix));
        }

        return str_starts_with($path, $prefix);
    }

    /**
     * Returns the full path to the Config.php of the current plugin, or null if not found.
     *
     * @return string|null
     */
    public static function getCurrentConfigPath(): ?string
    {
        $pluginDir = self::getCurrentPluginDir();
        if ($pluginDir) {
            $configPath = $pluginDir . DIRECTORY_SEPARATOR . '.internal/Config.php';
            return file_exists($configPath) ? $configPath : null;
        }
        return null;
    }

    /**
     * Returns the name (folder) of the current plugin, or null if not found.
     *
     * @return string|null
     */
    public static function getCurrentPluginName(): ?string
    {
        $pluginDir = self::getCurrentPluginDir();
        return $pluginDir ? basename($pluginDir) : null;
    }

    /**
     * Returns the config class FQCN for the current plugin,
     * or null if not found. Use static methods on the returned class name.
     *
     * @return class-string<ConfigInterface>|null
     */
    public static function getCurrentConfigClass(): ?string
    {
        $pluginDir = self::getCurrentPluginDir();
        debug($pluginDir);
        if (!$pluginDir) return null;

        $pluginName = basename($pluginDir); // Studly class
        $psr4 = config('fortiplugin.psr4_root');
        $class = "$psr4\\$pluginName\\Config";
        return class_exists($class) ? $class : null;
    }

    /**
     * @return object{name:string, directory:string, config: class-string<ConfigInterface>|null, config_path: string}|null
     */
    public static function getCurrentContext(): ?object
    {
        $pluginDir = self::getCurrentPluginDir();
        $pluginName = $pluginDir ? basename($pluginDir) : null;
        $configPath = self::getCurrentConfigPath();
        $config = self::getCurrentConfigClass();

        if (!$pluginDir && !$config && !$pluginName) {
            return null;
        }

        return (object)[
            'name' => $pluginName,
            'directory' => $pluginDir,
            'config' => $config,
            'config_path' => $configPath,
        ];
    }
}
