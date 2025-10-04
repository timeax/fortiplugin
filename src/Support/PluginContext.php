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
        $pluginBase = base_path(config('secured-plugin.directory', 'Plugins'));
        $pluginBase = rtrim($pluginBase, '/\\') . DIRECTORY_SEPARATOR;

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::getStackDepth());

        foreach ($trace as $frame) {
            if (!isset($frame['file'])) continue;
            $file = $frame['file'];
            if (str_starts_with($file, $pluginBase)) {
                // File is inside the plugin base directory
                $relPath = substr($file, strlen($pluginBase));
                $parts = explode(DIRECTORY_SEPARATOR, $relPath);
                if (!empty($parts[0])) {
                    // Return the plugin's root directory (e.g., .../Plugins/MyPlugin)
                    return $pluginBase . $parts[0];
                }
            }
        }
        return null;
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
        if (!$pluginDir) return null;

        $pluginName = basename($pluginDir); // Studly class
        $class = "Plugins\\$pluginName\\Internal\\Config";
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