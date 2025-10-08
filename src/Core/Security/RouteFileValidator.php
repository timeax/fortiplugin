<?php

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Exceptions\DuplicateRouteIdException;

final class RouteFileValidator
{
    /**
     * Validate a single route JSON file:
     * - Decode JSON
     * - (Optionally) validate with JSON Schema externally
     * - Enforce unique "id" per route node within the file
     * - Register IDs globally in $registry to ensure cross-file uniqueness
     *
     * @throws JsonException
     * @throws DuplicateRouteIdException
     */
    public static function validateFile(string $filePath, RouteIdRegistry $registry): void
    {
        $json = file_get_contents($filePath);
        if ($json === false) {
            throw new RuntimeException("Cannot read route file: $filePath");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['routes']) || !is_array($data['routes'])) {
            throw new RuntimeException("Invalid route file (missing 'routes' array): $filePath");
        }

        // Enforce uniqueness within this file.
        $local = [];
        $walk = static function (array $node, string $path) use (&$walk, &$local, $filePath, $registry): void {
            // all route nodes must carry id/desc by schema; be defensive:
            $id = $node['id'] ?? null;
            $desc = $node['desc'] ?? null;
            if (!is_string($id) || $id === '' || !is_string($desc) || $desc === '') {
                throw new RuntimeException("Route at $filePath {$path} missing required 'id'/'desc'.");
            }

            // Check file-scope uniqueness
            if (isset($local[$id])) {
                $first = $local[$id];
                throw new RuntimeException(
                    "Duplicate route id '{$id}' within the same file.\n" .
                    " - First at: {$filePath} {$first}\n" .
                    " - Again at: {$filePath} {$path}"
                );
            }
            $local[$id] = $path;

            // Check plugin-scope uniqueness (across files)
            $registry->register($id, $filePath, $path);

            // Recurse into groups
            if (($node['type'] ?? null) === 'group') {
                $children = $node['routes'] ?? [];
                foreach ($children as $i => $child) {
                    $walk($child, $path . "/routes[{$i}]");
                }
            }
        };

        foreach ($data['routes'] as $i => $node) {
            $walk($node, "/routes[{$i}]");
        }
    }
}