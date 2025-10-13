<?php

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;

class Psr4Checker
{
    /**
     * Validate that host composer.json has autoload.psr-4 mapping:
     *   Key: <psr4_root>\\<PlaceholderName>\\
     *   Value: <psr4_root>/<PlaceholderName>/
     * Returns ['ok'=>bool, 'errors'=>string[], 'details'=>array].
     * @throws JsonException
     */
    public function check(string $projectRoot, string $psr4Root, string $placeholderName): array
    {
        $errors = [];
        $details = [];
        $projectRoot = rtrim($projectRoot, "\\/ ");
        $composerPath = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($composerPath)) {
            return ['ok' => false, 'errors' => ['COMPOSER_JSON_MISSING'], 'details' => ['path' => $composerPath]];
        }
        $json = json_decode((string)@file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($json)) {
            return ['ok' => false, 'errors' => ['JSON_READ_ERROR'], 'details' => ['path' => $composerPath]];
        }
        $psr4 = (array)($json['autoload']['psr-4'] ?? []);
        $key = rtrim($psr4Root, "\\") . "\\" . $this->studly($placeholderName) . "\\"; // e.g., Plugins\\MyPlugin\\
        $expectedVal = rtrim($psr4Root, "\\/") . '/' . $this->studly($placeholderName) . '/'; // e.g., Plugins/MyPlugin/

        $details['expected_key'] = $key;
        $details['expected_value'] = $expectedVal;

        if (!array_key_exists($key, $psr4)) {
            $errors[] = 'COMPOSER_PSR4_MISMATCH';
            return ['ok' => false, 'errors' => $errors, 'details' => $details + ['found' => array_keys($psr4)]];
        }
        $val = (string)$psr4[$key];
        // Normalize to forward slashes and ensure trailing slash
        $norm = rtrim(str_replace('\\\
', '/', str_replace('\\\\', '/', $val)), '/') . '/';
        if ($norm !== $expectedVal) {
            $errors[] = 'COMPOSER_PSR4_MISMATCH';
            $details['found_value'] = $val;
            $details['normalized_value'] = $norm;
            return ['ok' => false, 'errors' => $errors, 'details' => $details];
        }
        return ['ok' => true, 'errors' => [], 'details' => $details];
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords(strtolower($value));
        return str_replace(' ', '', $value);
    }
}
