<?php /** @noinspection PhpRedundantCatchClauseInspection */
/** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Support\FortiPluginConfig;

final class ConfigValidator
{
    private const CONFIG_SCHEMA_FILE = 'fortiplugin.schema.json';
    private const HOST_CONFIG_SCHEMA_FILE = 'fortiplugin.host-config.schema.json';
    private const PERMS_SCHEMA_FILE = 'fortiplugin.permissions.schema.json';

    /**
     * Validates:
     * 1) fortiplugin.json against the main schema
     * 2) hostConfig (if present) against host-config schema
     * 3) permission_manifest (if present) loads OK, and validates if perms schema exists
     *
     * @throws JsonException
     */
    public function validate(string $pluginRoot): array
    {
        $pluginRoot = rtrim($pluginRoot, '/\\');

        if (!is_dir($pluginRoot)) {
            return ['error' => 'Invalid plugin root: ' . $pluginRoot];
        }

        $schemaDir = $this->schemaDir();

        // Ensure main schema exists (host/perms are optional depending on config)
        $configSchemaPath = $schemaDir . DIRECTORY_SEPARATOR . self::CONFIG_SCHEMA_FILE;
        if (!is_file($configSchemaPath)) {
            return ['error' => 'Schema file not found: ' . $configSchemaPath];
        }

        // Build DTO
        try {
            $cfg = FortiPluginConfig::fromPluginRoot($pluginRoot);
        } catch (JsonException $e) {
            return ['error' => 'Invalid JSON in fortiplugin.json: ' . $e->getMessage()];
        } catch (RuntimeException $e) {
            // e.g. fortiplugin.json missing
            return ['error' => $e->getMessage()];
        }

        // 1) Validate main config
        $configReport = $cfg->validateConfig($schemaDir, self::CONFIG_SCHEMA_FILE);
        if (!($configReport['valid'] ?? false)) {
            return [
                'error' => $configReport['error'] ?? 'Schema validation failed',
                'details' => $this->normalizeDetails($configReport['details'] ?? []),
            ];
        }

        // 2) Validate hostConfig (only if present)
        $hostReport = $cfg->validateHostConfig($schemaDir, self::HOST_CONFIG_SCHEMA_FILE);
        if (!($hostReport['valid'] ?? false)) {
            return [
                'error' => $hostReport['error'] ?? 'HostConfig schema validation failed',
                'details' => $this->normalizeDetails($hostReport['details'] ?? []),
            ];
        }

        // 3) Validate permission manifest (only if present; schema optional)
        $permReport = $cfg->validatePermissionManifest($schemaDir, self::PERMS_SCHEMA_FILE);
        if (!($permReport['valid'] ?? false)) {
            return [
                'error' => $permReport['error'] ?? 'permission_manifest validation failed',
                'details' => $this->normalizeDetails($permReport['details'] ?? []),
            ];
        }

        // 4) Validate route manifests (if routes block exists)
        $routesReport = $cfg->validateRouteManifests($schemaDir);
        if (!($routesReport['valid'] ?? false)) {
            return [
                'error' => $routesReport['error'] ?? 'Routes schema validation failed',
                'details' => $this->normalizeDetails($routesReport['details'] ?? []),
            ];
        }

        return []; // Fully valid
    }

    /**
     * Package-local schema dir:
     * <packageRoot>/schema
     */
    private function schemaDir(): string
    {
        // This file lives at: <packageRoot>/src/Core/Security/ConfigValidator.php
        $packageRoot = dirname(__DIR__, 3);
        return $packageRoot . DIRECTORY_SEPARATOR . 'schema';
    }

    /**
     * Convert FortiPluginConfig error arrays into the legacy list:
     * [
     *   ['path' => '...', 'message' => '...', 'keyword' => '...', 'args' => ...],
     *   ...
     * ]
     */
    private function normalizeDetails(mixed $details): array
    {
        // Already flat list?
        if (is_array($details) && $details !== [] && isset($details[0]) && is_array($details[0])) {
            return array_map(static function (array $e): array {
                return [
                    'path' => $e['path'] ?? ($e['dataPointer'] ?? ''),
                    'message' => $e['message'] ?? 'Validation failed',
                    'keyword' => $e['keyword'] ?? null,
                    'args' => $e['args'] ?? null,
                ];
            }, $details);
        }

        // Otherwise flatten Opis-style tree (FortiPluginConfig::describeOpisError)
        $flat = [];
        $this->flattenOpisErrorNode($details, $flat);

        return $flat !== []
            ? $flat
            : [[
                'path' => '',
                'message' => is_string($details) ? $details : 'Schema validation failed (no details available)',
                'keyword' => null,
                'args' => null,
            ]];
    }

    private function flattenOpisErrorNode(mixed $node, array &$out): void
    {
        if (!is_array($node)) {
            return;
        }

        if (isset($node['message']) || isset($node['dataPointer']) || isset($node['keyword'])) {
            $out[] = [
                'path' => (string)($node['dataPointer'] ?? ''),
                'message' => (string)($node['message'] ?? 'Validation failed'),
                'keyword' => $node['keyword'] ?? null,
                'args' => $node['args'] ?? null,
            ];
        }

        if (isset($node['subErrors']) && is_array($node['subErrors'])) {
            foreach ($node['subErrors'] as $sub) {
                $this->flattenOpisErrorNode($sub, $out);
            }
        }
    }
}