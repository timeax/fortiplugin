<?php

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use Opis\JsonSchema\Validator;

class ConfigValidator
{
    /**
     * @throws JsonException
     */
    public function validate(string $pluginRoot, string $schemaPath): array
    {
        $configFile = rtrim($pluginRoot, '/\\') . '/plugin.config.json';
        if (!file_exists($configFile)) {
            return ['error' => 'plugin.config.json not found'];
        }

        $json = file_get_contents($configFile);
        $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON in plugin.config.json: ' . json_last_error_msg()];
        }

        $schema = json_decode(file_get_contents($schemaPath), false, 512, JSON_THROW_ON_ERROR); // <-- just the decoded schema object!
        $validator = new Validator();
        $error = $validator->schemaValidation($data, $schema);

        if ($error !== null) {
            $details = $this->extractErrors($error);
            return [
                'error' => 'Schema validation failed',
                'details' => $details,
            ];
        }

        return []; // Valid!
    }

    protected function extractErrors($error, $parentPointer = ''): array
    {
        if (!$error) {
            return [];
        }

        $pointer = $parentPointer . $error->data()->pointer();
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
            function ($carry, $sub) use ($pointer) {
                return [...$carry, ...$this->extractErrors($sub, $pointer)];
            },
            $result
        );
    }
}