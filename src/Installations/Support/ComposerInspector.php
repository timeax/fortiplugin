<?php

namespace Timeax\FortiPlugin\Installations\Support;

/**
 * ComposerInspector reads host composer.json/lock and provides simple
 * satisfaction checks for planning purposes only. This minimal version
 * returns empty data if files are not present. It never executes Composer.
 */
class ComposerInspector
{
    /** @return array<string,string> package=>version from host lock */
    public function readHostLockedPackages(string $projectRoot = null): array
    {
        $projectRoot = $projectRoot ?? base_path() ?? getcwd();
        $lockPath = rtrim((string)$projectRoot, "\\/ ") . DIRECTORY_SEPARATOR . 'composer.lock';
        if (!is_file($lockPath)) return [];
        $json = json_decode((string)@file_get_contents($lockPath), true);
        if (!is_array($json)) return [];
        $pkgs = [];
        foreach ((array)($json['packages'] ?? []) as $p) {
            $name = (string)($p['name'] ?? '');
            $version = (string)($p['version'] ?? '');
            if ($name !== '') $pkgs[$name] = $version;
        }
        foreach ((array)($json['packages-dev'] ?? []) as $p) {
            $name = (string)($p['name'] ?? '');
            $version = (string)($p['version'] ?? '');
            if ($name !== '' && !isset($pkgs[$name])) $pkgs[$name] = $version;
        }
        return $pkgs;
    }

    /** @return array<string,string> plugin requires (name=>constraint) */
    public function readPluginRequires(string $stagingRoot): array
    {
        // Try fortiplugin.json first
        $fp = rtrim($stagingRoot, "\\/ ") . DIRECTORY_SEPARATOR . 'fortiplugin.json';
        if (is_file($fp)) {
            $data = json_decode((string)@file_get_contents($fp), true);
            if (is_array($data) && isset($data['requires']) && is_array($data['requires'])) {
                return $data['requires'];
            }
        }
        // Fallback to composer.json
        $cj = rtrim($stagingRoot, "\\/ ") . DIRECTORY_SEPARATOR . 'composer.json';
        if (is_file($cj)) {
            $data = json_decode((string)@file_get_contents($cj), true);
            if (is_array($data) && isset($data['require']) && is_array($data['require'])) {
                // filter php/ext-* out for package list but keep for conflicts detection
                return $data['require'];
            }
        }
        return [];
    }

    /** Crude check for core packages */
    public function isCorePackage(string $name): bool
    {
        if ($name === 'php') return true;
        if (str_starts_with($name, 'ext-')) return true;
        if ($name === 'laravel/framework') return true;
        return false;
    }
}
