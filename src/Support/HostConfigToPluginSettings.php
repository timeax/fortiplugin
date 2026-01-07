<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Support;

use InvalidArgumentException;
use JsonException;

/**
 * Convert hostConfig.settings (with nested "group" nodes) into insertable PluginSetting rows.
 *
 * Supports:
 * - group nodes: { type:"group", label:"UI", children:{...}, is_required?, is_sensitive? }
 * - leaf nodes:  { label:"...", type:"string|...", value:"...", is_required?, is_sensitive? }
 *
 * Inheritance:
 * - is_required defaults to TRUE, is_sensitive defaults to FALSE
 * - group-level flags apply to children unless overridden by a leaf (or nested group)
 */
final class HostConfigToPluginSettings
{
    /** @var array<string,true> */
    private const LEAF_TYPES = [
        'string' => true,
        'number' => true,
        'boolean' => true,
        'json' => true,
        'file' => true,
        'blob' => true,
        'tristate' => true,
        'multiselect' => true,
        'select' => true,
        'checkbox' => true,
        'radio' => true,
        'chips' => true,
    ];

    /**
     * @param int $pluginId
     * @param array|object $hostConfig Decoded JSON (object or assoc array). Must contain "settings".
     * @return array<int,array{
     *   plugin_id:int,
     *   key:string,
     *   label:string,
     *   value:string,
     *   type:string,
     *   group?:string,
     *   is_required:bool,
     *   is_sensitive:bool
     * }>
     * @throws JsonException
     * @throws JsonException
     */
    public static function makeRows(int $pluginId, array|object $hostConfig): array
    {
        $settings = self::get($hostConfig, 'settings');

        if (!is_array($settings) && !is_object($settings)) {
            throw new InvalidArgumentException('hostConfig.settings must be an object/map.');
        }

        /** @var array<int,array{
         *   plugin_id:int,key:string,label:string,value:string,type:string,group?:string,is_required:bool,is_sensitive:bool
         * }> $rows
         */
        $rows = [];

        /** @var array<string,true> $seenKeys */
        $seenKeys = [];

        foreach (self::iterateMap($settings) as $rootKey => $node) {
            self::assertSegment($rootKey, 'settings key');

            self::walkNode(
                pluginId: $pluginId,
                node: $node,
                keyParts: [$rootKey],
                groupLabels: [],
                rows: $rows,
                seenKeys: $seenKeys,
                inheritedRequired: true,
                inheritedSensitive: false,
            );
        }

        return $rows;
    }

    /**
     * @param array<int,array{
     *   plugin_id:int,key:string,label:string,value:string,type:string,group?:string,is_required:bool,is_sensitive:bool
     * }> $rows
     * @param array<string,true> $seenKeys
     * @throws JsonException
     * @throws JsonException
     */
    private static function walkNode(
        int   $pluginId,
        mixed $node,
        array $keyParts,
        array $groupLabels,
        array &$rows,
        array &$seenKeys,
        bool  $inheritedRequired,
        bool  $inheritedSensitive,
    ): void
    {
        if (!is_array($node) && !is_object($node)) {
            throw new InvalidArgumentException("Invalid node at '" . implode('.', $keyParts) . "': expected object.");
        }

        $type = self::getOptional($node, 'type');
        $children = self::getOptional($node, 'children');

        // Allow group defaults (snake or camel)
        $nodeRequired = self::getOptionalBool($node, ['is_required', 'isRequired']);
        $nodeSensitive = self::getOptionalBool($node, ['is_sensitive', 'isSensitive']);

        $effectiveRequired = $nodeRequired ?? $inheritedRequired;
        $effectiveSensitive = $nodeSensitive ?? $inheritedSensitive;

        $isGroup = ($type === 'group') || ($children !== null);

        if ($isGroup) {
            if ($type !== null && $type !== 'group') {
                throw new InvalidArgumentException(
                    "Node '" . implode('.', $keyParts) . "' has children but type='$type'. Expected 'group' or omit type."
                );
            }

            $label = self::get($node, 'label');
            if (!is_string($label) || trim($label) === '') {
                throw new InvalidArgumentException("Group '" . implode('.', $keyParts) . "' must have a non-empty string label.");
            }

            if ($children === null) {
                $children = self::get($node, 'children');
            }
            if (!is_array($children) && !is_object($children)) {
                throw new InvalidArgumentException("Group '" . implode('.', $keyParts) . "'.children must be an object/map.");
            }

            $nextGroupLabels = [...$groupLabels, $label];

            foreach (self::iterateMap($children) as $childKey => $childNode) {
                self::assertSegment($childKey, 'children key');

                self::walkNode(
                    pluginId: $pluginId,
                    node: $childNode,
                    keyParts: [...$keyParts, $childKey],
                    groupLabels: $nextGroupLabels,
                    rows: $rows,
                    seenKeys: $seenKeys,
                    inheritedRequired: $effectiveRequired,
                    inheritedSensitive: $effectiveSensitive,
                );
            }

            return;
        }

        // Leaf node
        if (!is_string($type) || !isset(self::LEAF_TYPES[$type])) {
            throw new InvalidArgumentException("Leaf '" . implode('.', $keyParts) . "' has invalid or missing type.");
        }

        $label = self::get($node, 'label');
        if (!is_string($label) || trim($label) === '') {
            throw new InvalidArgumentException("Leaf '" . implode('.', $keyParts) . "' must have a non-empty string label.");
        }

        // Leaf can override (already applied via $effective*)
        $rawValue = self::get($node, 'value');

        $key = implode('.', $keyParts);
        if (isset($seenKeys[$key])) {
            throw new InvalidArgumentException("Duplicate setting key detected: $key");
        }
        $seenKeys[$key] = true;

        $group = count($groupLabels) ? implode(' / ', $groupLabels) : null;
        $value = self::normalizeValue($type, $rawValue, $key);

        $rows[] = [
            'plugin_id' => $pluginId,
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'type' => $type,
            'group' => $group,
            'is_required' => $effectiveRequired,
            'is_sensitive' => $effectiveSensitive,
        ];
    }

    /**
     * @throws JsonException
     */
    private static function normalizeValue(string $type, mixed $value, string $fullKey): string
    {
        return match ($type) {
            'boolean', 'checkbox' => self::normalizeBool($value, $fullKey),
            'number' => self::normalizeNumber($value, $fullKey),
            'tristate' => self::normalizeTriState($value, $fullKey),
            'json' => self::normalizeJson($value, $fullKey),
            'multiselect', 'chips' => self::normalizeJsonArray($value, $fullKey),
            default => self::normalizeString($value, $fullKey),
        };
    }

    private static function normalizeString(mixed $value, string $fullKey): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        throw new InvalidArgumentException("Setting '$fullKey' expects a string-like value.");
    }

    private static function normalizeBool(mixed $value, string $fullKey): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            $v = strtolower(trim($value));
            if ($v === 'true' || $v === 'false') {
                return $v;
            }
        }

        throw new InvalidArgumentException("Setting '$fullKey' expects boolean ('true'/'false').");
    }

    private static function normalizeTriState(mixed $value, string $fullKey): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['true', 'false', 'null'], true)) {
                return $v;
            }
        }
        throw new InvalidArgumentException("Setting '$fullKey' expects tristate ('true'/'false'/'null').");
    }

    private static function normalizeNumber(mixed $value, string $fullKey): string
    {
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_string($value) && is_numeric(trim($value))) {
            return trim($value);
        }
        throw new InvalidArgumentException("Setting '$fullKey' expects a numeric value.");
    }

    /**
     * @throws JsonException
     */
    private static function normalizeJson(mixed $value, string $fullKey): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new InvalidArgumentException("Setting '$fullKey' JSON encoding failed.");
            }
            return $json;
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new InvalidArgumentException("Setting '$fullKey' JSON encoding failed.");
        }
        return $json;
    }

    /**
     * @throws JsonException
     */
    private static function normalizeJsonArray(mixed $value, string $fullKey): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            $json = json_encode(array_values($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new InvalidArgumentException("Setting '$fullKey' JSON encoding failed.");
            }
            return $json;
        }
        throw new InvalidArgumentException("Setting '$fullKey' expects an array (or JSON array string).");
    }

    private static function assertSegment(string $segment, string $label): void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $segment)) {
            throw new InvalidArgumentException("Invalid $label '$segment'.");
        }
    }

    private static function getOptionalBool(array|object $obj, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (!self::hasKey($obj, $key)) {
                continue;
            }

            $v = self::getOptional($obj, $key);

            if (is_bool($v)) {
                return $v;
            }

            if (is_int($v)) {
                return $v === 1 ? true : ($v === 0 ? false : null);
            }

            if (is_string($v)) {
                $s = strtolower(trim($v));
                if ($s === 'true') return true;
                if ($s === 'false') return false;
            }

            throw new InvalidArgumentException("Field '$key' must be a boolean.");
        }

        return null;
    }

    private static function get(array|object $obj, string $key): mixed
    {
        $value = self::getOptional($obj, $key);
        if ($value === null && !self::hasKey($obj, $key)) {
            throw new InvalidArgumentException("Missing required field '$key'.");
        }
        return $value;
    }

    private static function getOptional(array|object $obj, string $key): mixed
    {
        if (is_array($obj)) {
            return $obj[$key] ?? null;
        }
        return $obj->{$key} ?? null;
    }

    private static function hasKey(array|object $obj, string $key): bool
    {
        if (is_array($obj)) {
            return array_key_exists($key, $obj);
        }
        return property_exists($obj, $key);
    }

    /**
     * @return iterable<string,mixed>
     */
    private static function iterateMap(array|object $map): iterable
    {
        if (is_array($map)) {
            foreach ($map as $k => $v) {
                yield (string)$k => $v;
            }
            return;
        }

        foreach (get_object_vars($map) as $k => $v) {
            yield (string)$k => $v;
        }
    }
}