<?php

declare(strict_types=1);

namespace Tests\Support;

use Timeax\FortiPlugin\Models\PluginZip;
use ZipArchive;

final class FortiPluginZipFactory
{
    public static function make(array $overrides = []): PluginZip
    {
        $zipPath = self::makeTempZip([
            'composer.json' => json_encode(['name' => 'demo/plugin', 'type' => 'library'], JSON_PRETTY_PRINT),
            'fortiplugin.json' => json_encode(['providers' => []], JSON_PRETTY_PRINT),
        ]);

        $zip = new PluginZip();

        $zip->id = (int) ($overrides['id'] ?? 999);
        $zip->placeholder_id = (int) ($overrides['placeholder_id'] ?? 77);
        $zip->path = (string) ($overrides['path'] ?? $zipPath);
        $zip->meta = $overrides['meta'] ?? [
            'manifest' => [
                'plugin' => [
                    'slug' => 'demo-plugin',
                    'version' => '1.2.3',
                ],
            ],
            'validator_config' => [],
        ];

        return $zip;
    }

    public static function cleanup(?PluginZip $zip): void
    {
        if (!$zip) return;
        $path = $zip->path ?? null;
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param array<string,string> $files
     */
    private static function makeTempZip(array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fortiplugin_');
        if ($tmp === false) {
            throw new \RuntimeException('FAILED_TO_CREATE_TEMP_FILE');
        }

        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $za = new ZipArchive();
        if ($za->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('FAILED_TO_CREATE_ZIP');
        }

        foreach ($files as $name => $contents) {
            $za->addFromString($name, $contents);
        }

        $za->close();

        return $zipPath;
    }
}
