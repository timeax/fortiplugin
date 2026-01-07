<?php /** @noinspection PhpRedundantCatchClauseInspection */

/** @noinspection GrazieInspection */

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use Opis\JsonSchema\Validator;

class ConfigValidator
{
    /**
     * Validates:
     * 1) fortiplugin.json against the main schema
     * 2) hostConfig (if present):
     *    - if string: loads the referenced file and validates it against the host-config schema
     *    - if object: validates the inline object against the host-config schema
     *
     */
    public function validate(string $pluginRoot, string $schemaPath): array
    {
        $pluginRoot = rtrim($pluginRoot, '/\\');
        $configFile = $pluginRoot . DIRECTORY_SEPARATOR . 'fortiplugin.json';

        if (!is_file($configFile)) {
            return ['error' => 'fortiplugin.json not found'];
        }

        if (!is_file($schemaPath)) {
            return ['error' => 'Schema file not found: ' . $schemaPath];
        }

        // Helper to decode JSON files consistently
        $decodeFile = static function (string $path) {
            $json = @file_get_contents($path);
            if ($json === false) {
                throw new JsonException("Failed to read file: $path");
            }
            return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        };

        // Load plugin config
        try {
            $data = $decodeFile($configFile);
        } catch (JsonException $e) {
            return ['error' => 'Invalid JSON in fortiplugin.json: ' . $e->getMessage()];
        }

        // Load main schema (so we can assign a local $id if missing)
        try {
            $mainSchema = $decodeFile($schemaPath);
        } catch (JsonException $e) {
            return ['error' => 'Invalid JSON in schema: ' . $e->getMessage()];
        }

        $schemaDir = dirname($schemaPath);

        // Convention: host schema sits beside the main schema
        $hostSchemaPath = $schemaDir . DIRECTORY_SEPARATOR . 'fortiplugin.host-config.schema.json';
        $hostSchema = null;

        if (is_file($hostSchemaPath)) {
            try {
                $hostSchema = $decodeFile($hostSchemaPath);
            } catch (JsonException $e) {
                return ['error' => 'Invalid JSON in host config schema: ' . $e->getMessage()];
            }
        }

        $validator = new Validator();
        $resolver = $validator->resolver();

        /**
         * Optional: allow $ref like:
         *   fortiplugin://schemas/fortiplugin.host-config.schema.json
         * by mapping it to the local $schemaDir.
         */
        $resolver?->registerProtocolDir('fortiplugin', 'schemas', $schemaDir);

        // Give schemas stable local IDs (no web URL required)
        $mainSchemaId = isset($mainSchema->{'$id'}) && is_string($mainSchema->{'$id'})
            ? $mainSchema->{'$id'}
            : 'urn:fortiplugin:config';

        // Register main schema
        // registerRaw lets us force an id even if the schema file has none. 1
        $resolver?->registerRaw($mainSchema, $mainSchemaId);

        // Register host schema (if available)
        $hostSchemaId = null;
        if ($hostSchema !== null) {
            $hostSchemaId = isset($hostSchema->{'$id'}) && is_string($hostSchema->{'$id'})
                ? $hostSchema->{'$id'}
                : 'urn:fortiplugin:host-config';

            $resolver?->registerRaw($hostSchema, $hostSchemaId);

            // Also register common aliases so your schema can $ref either form
            $resolver?->registerFile('fortiplugin://schemas/fortiplugin.host-config.schema.json', $hostSchemaPath);
            $resolver?->registerFile('urn:fortiplugin:host-config', $hostSchemaPath);
        }

        // Validate fortiplugin.json against main schema
        $result = $validator->validate($data, $mainSchemaId); // validate by schema id/uri 2

        if (!$result->isValid()) {
            $error = $result->error();
            return [
                'error' => 'Schema validation failed',
                'details' => $error ? $this->extractErrors($error) : ['Schema validation failed (no details available)'],
            ];
        }

        /**
         * If hostConfig exists:
         * - string => treat as relative json path under plugin root
         * - object => treat as inline host config payload
         */
        $hostConfig = $data->hostConfig ?? null;

        if ($hostConfig !== null) {
            if ($hostSchemaId === null) {
                return [
                    'error' => 'Host config schema missing',
                    'details' => [
                        'Expected host schema file beside main schema: ' . $hostSchemaPath,
                    ],
                ];
            }

            // Resolve & validate host config payload
            if (is_string($hostConfig)) {
                // Reject absolute paths + traversal
                if (
                    str_starts_with($hostConfig, '/') ||
                    str_starts_with($hostConfig, '\\') ||
                    preg_match('~^[A-Za-z]:[\\\\/]~', $hostConfig) ||
                    str_contains($hostConfig, '..')
                ) {
                    return ['error' => 'Invalid hostConfig path (must be relative, no "..", no absolute paths)'];
                }

                $hostConfigFile = $pluginRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $hostConfig);

                if (!is_file($hostConfigFile)) {
                    return ['error' => 'hostConfig file not found: ' . $hostConfig];
                }

                try {
                    $hostData = $decodeFile($hostConfigFile);
                } catch (JsonException $e) {
                    return ['error' => 'Invalid JSON in hostConfig file: ' . $e->getMessage()];
                }

                $hostResult = $validator->validate($hostData, $hostSchemaId);

                if (!$hostResult->isValid()) {
                    $err = $hostResult->error();
                    return [
                        'error' => 'HostConfig schema validation failed',
                        'details' => $err ? $this->extractErrors($err) : ['HostConfig validation failed (no details available)'],
                    ];
                }
            } elseif (is_object($hostConfig)) {
                $hostResult = $validator->validate($hostConfig, $hostSchemaId);

                if (!$hostResult->isValid()) {
                    $err = $hostResult->error();
                    return [
                        'error' => 'HostConfig schema validation failed',
                        'details' => $err ? $this->extractErrors($err) : ['HostConfig validation failed (no details available)'],
                    ];
                }
            }
        }

        return []; // Fully valid
    }

    protected function extractErrors($error): array
    {
        if (!$error) {
            return [];
        }

        $pointer = $error->data()->fullPath();


        $message = $error->message();
        $keyword = $error->keyword();
        $args = $error->args();

        $result = [[
            'path' => $pointer,
            'message' => $message,
            'keyword' => $keyword,
            'args' => $args,
        ]];

        return array_reduce(
            $error->subErrors(),
            function ($carry, $sub) {
                return [...$carry, ...$this->extractErrors($sub)];
            },
            $result
        );
    }
}