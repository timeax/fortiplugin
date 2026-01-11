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
 * - leaf nodes:  { label:"...", type:"text|toggle|...|json", value?, defaultValue?, meta?, is_required?, is_sensitive? }
 *
 * Inheritance:
 * - is_required defaults to TRUE, is_sensitive defaults to FALSE
 * - group-level flags apply to children unless overridden by a leaf (or nested group)
 *
 * Strict validation:
 * - validates leaf type against InputFieldType enum
 * - validates meta shape and selection constraints
 * - selection inputs require meta.options (select/multiselect/radio and chips if enforced)
 * - selection inputs and chips/checkbox cannot be sensitive
 * - if a value is provided (or defaultValue), it is validated against meta.options and basic format checks
 * - if neither value nor defaultValue is provided, an "empty" placeholder is stored per type
 */
final class HostConfigToPluginSettings
{
    /** @var array<string,true> */
    private const INPUT_TYPES = [
        'text' => true,
        'toggle' => true,
        'tristate' => true,
        'password' => true,
        'email' => true,
        'number' => true,
        'tel' => true,
        'url' => true,
        'search' => true,
        'chips' => true,
        'checkbox' => true,
        'radio' => true,
        'color' => true,
        'range' => true,
        'select' => true,
        'multiselect' => true,
        'date' => true,
        'time' => true,
        'datetime-local' => true,
        'month' => true,
        'week' => true,
        'file' => true,
        'json' => true,
    ];

    /** Types that MUST have meta.options (strict selection). */
    private const TYPES_REQUIRE_OPTIONS = [
        'select' => true,
        'multiselect' => true,
        'radio' => true,
    ];

    /** Types that are NEVER allowed to be sensitive. */
    private const TYPES_DISALLOW_SENSITIVE = [
        'select' => true,
        'multiselect' => true,
        'radio' => true,
        'chips' => true,
        'checkbox' => true,
    ];

    /**
     * @param array|object $hostConfig Must contain "settings"
     * @return array<int,array{
     *   plugin_id:int,
     *   key:string,
     *   label:string,
     *   value:string,
     *   type:string,
     *   group?:string|null,
     *   is_required:bool,
     *   is_sensitive:bool,
     *   meta?:array|null
     * }>
     * @throws JsonException
     */
    public static function makeRows(int $pluginId, array|object $hostConfig): array
    {
        $settings = self::get($hostConfig, 'settings');

        if (!is_array($settings) && !is_object($settings)) {
            throw new InvalidArgumentException('hostConfig.settings must be an object/map.');
        }

        $rows = [];
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

        // Leaf
        if (!is_string($type) || !isset(self::INPUT_TYPES[$type])) {
            throw new InvalidArgumentException("Leaf '" . implode('.', $keyParts) . "' has invalid or missing type.");
        }

        $label = self::get($node, 'label');
        if (!is_string($label) || trim($label) === '') {
            throw new InvalidArgumentException("Leaf '" . implode('.', $keyParts) . "' must have a non-empty string label.");
        }

        $key = implode('.', $keyParts);
        if (isset($seenKeys[$key])) {
            throw new InvalidArgumentException("Duplicate setting key detected: $key");
        }
        $seenKeys[$key] = true;

        $group = count($groupLabels) ? implode(' / ', $groupLabels) : null;

        $hasValue = self::hasKey($node, 'value');
        $hasDefault = self::hasKey($node, 'defaultValue');

        $rawValue = $hasValue ? self::getOptional($node, 'value') : ($hasDefault ? self::getOptional($node, 'defaultValue') : null);
        $valueProvided = $hasValue || $hasDefault;

        $metaRaw = self::getOptional($node, 'meta');

        [$value, $meta] = self::validateAndNormalizeLeaf(
            type: $type,
            rawValue: $rawValue,
            valueProvided: $valueProvided,
            metaRaw: $metaRaw,
            effectiveRequired: $effectiveRequired,
            effectiveSensitive: $effectiveSensitive,
            fullKey: $key
        );

        $rows[] = [
            'plugin_id' => $pluginId,
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'type' => $type,
            'group' => $group,
            'is_required' => $effectiveRequired,
            'is_sensitive' => $effectiveSensitive,
            'meta' => $meta,
        ];
    }

    /**
     * @return array{0:string,1:array|null}
     */
    private static function validateAndNormalizeLeaf(
        string $type,
        mixed  $rawValue,
        bool   $valueProvided,
        mixed  $metaRaw,
        bool   $effectiveRequired,
        bool   $effectiveSensitive,
        string $fullKey
    ): array
    {
        $meta = self::normalizeMeta($metaRaw, $fullKey);

        // Disallow sensitive types
        if (isset(self::TYPES_DISALLOW_SENSITIVE[$type]) && $effectiveSensitive === true) {
            throw new InvalidArgumentException("Setting '$fullKey' type '$type' cannot be sensitive.");
        }

        $hasOptions = is_array($meta) && array_key_exists('options', $meta);

        // Require options for strict selection types
        if (isset(self::TYPES_REQUIRE_OPTIONS[$type]) && !$hasOptions) {
            throw new InvalidArgumentException("Setting '$fullKey' type '$type' requires meta.options.");
        }

        // If options exist, ensure type supports it
        if ($hasOptions && !in_array($type, ['select', 'multiselect', 'radio', 'checkbox', 'chips'], true)) {
            throw new InvalidArgumentException("Setting '$fullKey' has meta.options but type '$type' does not support options.");
        }

        self::validateMetaForType($type, $meta, $fullKey);

        // If no value/defaultValue provided, store an empty placeholder (schema allows it)
        if (!$valueProvided) {
            return [self::emptyValueForType($type), $meta];
        }

        // If an explicit "empty" is provided, keep it as empty placeholder (skip strict value checks)
        if (self::isEmptyProvidedValue($type, $rawValue)) {
            return [self::emptyValueForType($type), $meta];
        }

        // Selection validation (only if value is non-empty)
        if ($hasOptions) {
            $options = self::parseOptions(
                meta: $meta,
                fullKey: $fullKey,
                forceStringValues: ($type === 'chips')
            );

            return match ($type) {
                'select', 'radio' => [
                    self::normalizeString(
                        self::coerceScalarToAllowed($rawValue, $options['allowed'], $fullKey),
                        $fullKey
                    ),
                    $meta,
                ],

                'multiselect', 'checkbox' => [
                    self::encodeJsonArray(
                        self::coerceArrayToAllowed(
                            values: self::coerceToArray($rawValue, $fullKey),
                            allowed: $options['allowed'],
                            fullKey: $fullKey,
                            forceString: false
                        ),
                        $fullKey
                    ),
                    $meta,
                ],

                'chips' => [
                    self::encodeJsonArray(
                        self::coerceArrayToAllowed(
                            values: self::coerceToArray($rawValue, $fullKey),
                            allowed: $options['allowed'],
                            fullKey: $fullKey,
                            forceString: true
                        ),
                        $fullKey
                    ),
                    $meta,
                ],

                default => throw new InvalidArgumentException("Setting '$fullKey' has options but type '$type' is not supported."),
            };
        }

        // Non-selection value validation
        $value = match ($type) {
            'toggle', 'checkbox' => self::normalizeBool($rawValue, $fullKey),

            'tristate' => self::normalizeTriState($rawValue, $fullKey),

            'number', 'range' => self::normalizeNumberOrEmpty($rawValue, $fullKey),

            'json' => self::normalizeJsonOrEmpty($rawValue, $fullKey),

            'email' => self::normalizeEmailOrEmpty($rawValue, $fullKey),
            'url' => self::normalizeUrlOrEmpty($rawValue, $fullKey),
            'color' => self::normalizeColorOrEmpty($rawValue, $fullKey),

            'date' => self::normalizeDateOrEmpty($rawValue, $fullKey),
            'time' => self::normalizeTimeOrEmpty($rawValue, $fullKey),
            'datetime-local' => self::normalizeDatetimeLocalOrEmpty($rawValue, $fullKey),
            'month' => self::normalizeMonthOrEmpty($rawValue, $fullKey),
            'week' => self::normalizeWeekOrEmpty($rawValue, $fullKey),

            default => self::normalizeString($rawValue, $fullKey),
        };

        // If required and still empty placeholder, you can choose to throw here.
        // I’m NOT throwing because your schema explicitly allows missing value.
        // If you want "required => must ship a default", uncomment:
        //
        // if ($effectiveRequired && $value === self::emptyValueForType($type)) {
        //     throw new InvalidArgumentException("Setting '$fullKey' is required and must include a value/defaultValue.");
        // }

        return [$value, $meta];
    }

    private static function emptyValueForType(string $type): string
    {
        return match ($type) {
            'multiselect', 'chips' => '[]',
            'tristate' => 'null',
            'toggle', 'checkbox' => 'false',
            default => '',
        };
    }

    private static function isEmptyProvidedValue(string $type, mixed $v): bool
    {
        // For arrays: allow empty array / empty JSON array / empty string
        if (in_array($type, ['multiselect', 'chips'], true)) {
            if ($v === null) return true;
            if ($v === '') return true;
            if (is_array($v) && count($v) === 0) return true;
            if (is_string($v)) {
                $s = trim($v);
                return $s === '' || $s === '[]';
            }
            return false;
        }

        // For tristate: null is a meaningful empty placeholder
        if ($type === 'tristate') {
            return $v === null || (is_string($v) && strtolower(trim($v)) === 'null');
        }

        // For numbers: empty string / null allowed as "unset"
        return $v === null || (is_string($v) && trim($v) === '');

        // For everything else: null or "" is "unset"
    }

    private static function normalizeMeta(mixed $metaRaw, string $fullKey): ?array
    {
        if ($metaRaw === null) return null;
        if (is_array($metaRaw)) return $metaRaw;
        if (is_object($metaRaw)) return get_object_vars($metaRaw);
        throw new InvalidArgumentException("Setting '$fullKey'.meta must be an object.");
    }

    private static function validateMetaForType(string $type, ?array $meta, string $fullKey): void
    {
        if ($meta === null) return;

        // numeric meta
        foreach (['min', 'max', 'step'] as $k) {
            if (!array_key_exists($k, $meta)) continue;
            if (!is_int($meta[$k]) && !is_float($meta[$k])) {
                throw new InvalidArgumentException("Setting '$fullKey'.meta.$k must be a number.");
            }
        }

        // length meta
        foreach (['minLength', 'maxLength'] as $k) {
            if (!array_key_exists($k, $meta)) continue;
            if (!is_int($meta[$k]) || $meta[$k] < 0) {
                throw new InvalidArgumentException("Setting '$fullKey'.meta.$k must be an integer >= 0.");
            }
        }

        if (array_key_exists('multiple', $meta) && !is_bool($meta['multiple'])) {
            throw new InvalidArgumentException("Setting '$fullKey'.meta.multiple must be a boolean.");
        }

        if (array_key_exists('accept', $meta) && !is_string($meta['accept'])) {
            throw new InvalidArgumentException("Setting '$fullKey'.meta.accept must be a string.");
        }

        if (array_key_exists('readOnly', $meta) && !is_bool($meta['readOnly'])) {
            throw new InvalidArgumentException("Setting '$fullKey'.meta.readOnly must be a boolean.");
        }

        if (array_key_exists('disabled', $meta) && !is_bool($meta['disabled'])) {
            throw new InvalidArgumentException("Setting '$fullKey'.meta.disabled must be a boolean.");
        }

        // options are validated in parseOptions()
    }

    /**
     * @return array{allowed: array<int, string|int|float|bool>}
     */
    private static function parseOptions(array $meta, string $fullKey, bool $forceStringValues): array
    {
        $opts = $meta['options'] ?? null;

        if (!is_array($opts) || count($opts) < 1) {
            throw new InvalidArgumentException("Setting '$fullKey'.meta.options must be a non-empty array.");
        }

        $allowed = [];

        foreach ($opts as $i => $optRaw) {
            if (is_object($optRaw)) $optRaw = get_object_vars($optRaw);
            if (!is_array($optRaw)) {
                throw new InvalidArgumentException("Setting '$fullKey'.meta.options[$i] must be an object.");
            }

            $label = $optRaw['label'] ?? null;
            if (!is_string($label) || trim($label) === '') {
                throw new InvalidArgumentException("Setting '$fullKey'.meta.options[$i].label must be a non-empty string.");
            }

            if (!array_key_exists('value', $optRaw)) {
                throw new InvalidArgumentException("Setting '$fullKey'.meta.options[$i].value is required.");
            }

            $value = $optRaw['value'];

            if (!(is_string($value) || is_int($value) || is_float($value) || is_bool($value))) {
                throw new InvalidArgumentException("Setting '$fullKey'.meta.options[$i].value must be string|number|boolean.");
            }

            if ($forceStringValues && !is_string($value)) {
                throw new InvalidArgumentException("Setting '$fullKey' expects string option values (chips).");
            }

            if (array_key_exists('disabled', $optRaw) && !is_bool($optRaw['disabled'])) {
                throw new InvalidArgumentException("Setting '$fullKey'.meta.options[$i].disabled must be a boolean.");
            }

            $allowed[] = $value;
        }

        return ['allowed' => $allowed];
    }

    private static function coerceScalarToAllowed(mixed $value, array $allowed, string $fullKey): string|int|float|bool
    {
        foreach ($allowed as $a) {
            if ($value === $a) return $a;
        }

        if (is_string($value)) {
            $s = trim($value);
            $ls = strtolower($s);

            // bool coercion
            if ($ls === 'true' || $ls === 'false') {
                $b = ($ls === 'true');
                if (in_array($b, $allowed, true)) return $b;
            }

            // numeric coercion
            if (is_numeric($s)) {
                $n = (str_contains($s, '.') || str_contains($ls, 'e')) ? (float)$s : (int)$s;
                if (in_array($n, $allowed, true)) return $n;
                if (in_array((float)$n, $allowed, true)) return (float)$n;
            }

            if (in_array($s, $allowed, true)) return $s;
        }

        throw new InvalidArgumentException("Setting '$fullKey' value must be one of meta.options values.");
    }

    private static function coerceToArray(mixed $value, string $fullKey): array
    {
        if (is_array($value)) return array_values($value);

        if (is_string($value)) {
            $s = trim($value);
            if ($s === '') return [];

            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidArgumentException("Setting '$fullKey' expects an array or JSON array string.", 0, $e);
            }

            if (!is_array($decoded)) {
                throw new InvalidArgumentException("Setting '$fullKey' expects a JSON array.");
            }

            return array_values($decoded);
        }

        throw new InvalidArgumentException("Setting '$fullKey' expects an array (or JSON array string).");
    }

    private static function coerceArrayToAllowed(array $values, array $allowed, string $fullKey, bool $forceString): array
    {
        $out = [];
        foreach ($values as $i => $v) {
            $c = self::coerceScalarToAllowed($v, $allowed, $fullKey . "[$i]");
            if ($forceString && !is_string($c)) {
                throw new InvalidArgumentException("Setting '$fullKey' expects string values only.");
            }
            $out[] = $c;
        }
        return $out;
    }

    private static function encodeJsonArray(array $arr, string $fullKey): string
    {
        try {
            return json_encode(array_values($arr), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Setting '$fullKey' JSON encoding failed.", 0, $e);
        }
    }

    private static function normalizeString(mixed $value, string $fullKey): string
    {
        if (is_string($value)) return $value;
        if (is_int($value) || is_float($value)) return (string)$value;
        if (is_bool($value)) return $value ? 'true' : 'false';
        if ($value === null) return '';
        throw new InvalidArgumentException("Setting '$fullKey' expects a string-like value.");
    }

    private static function normalizeBool(mixed $value, string $fullKey): string
    {
        if (is_bool($value)) return $value ? 'true' : 'false';

        if (($value === 0 || $value === 1)) {
            return $value === 1 ? 'true' : 'false';
        }

        if (is_string($value)) {
            $v = strtolower(trim($value));
            if ($v === 'true' || $v === 'false') return $v;
        }

        throw new InvalidArgumentException("Setting '$fullKey' expects boolean ('true'/'false').");
    }

    private static function normalizeTriState(mixed $value, string $fullKey): string
    {
        if ($value === null) return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['true', 'false', 'null'], true)) return $v;
        }
        throw new InvalidArgumentException("Setting '$fullKey' expects tristate ('true'/'false'/'null').");
    }

    private static function normalizeNumberOrEmpty(mixed $value, string $fullKey): string
    {
        if ($value === null) return '';
        if (is_string($value) && trim($value) === '') return '';

        if (is_int($value) || is_float($value)) return (string)$value;
        if (is_string($value) && is_numeric(trim($value))) return trim($value);

        throw new InvalidArgumentException("Setting '$fullKey' expects a numeric value.");
    }

    private static function normalizeJsonOrEmpty(mixed $value, string $fullKey): string
    {
        if ($value === null) return '';
        if (is_string($value)) return $value; // allow raw JSON string (or empty handled earlier)

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Setting '$fullKey' JSON encoding failed.", 0, $e);
        }
    }

    private static function normalizeEmailOrEmpty(mixed $value, string $fullKey): string
    {
        $s = self::normalizeString($value, $fullKey);
        if ($s === '') return '';
        if (filter_var($s, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Setting '$fullKey' expects a valid email.");
        }
        return $s;
    }

    private static function normalizeUrlOrEmpty(mixed $value, string $fullKey): string
    {
        $s = self::normalizeString($value, $fullKey);
        if ($s === '') return '';
        if (filter_var($s, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Setting '$fullKey' expects a valid URL.");
        }
        return $s;
    }

    private static function normalizeColorOrEmpty(mixed $value, string $fullKey): string
    {
        $s = self::normalizeString($value, $fullKey);
        if ($s === '') return '';
        if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $s)) {
            throw new InvalidArgumentException("Setting '$fullKey' expects a hex color like #RGB or #RRGGBB.");
        }
        return $s;
    }

    private static function normalizeDateOrEmpty(mixed $value, string $fullKey): string
    {
        $s = self::normalizeString($value, $fullKey);
        if ($s === '') return '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            throw new InvalidArgumentException("Setting '$fullKey' expects date format YYYY-MM-DD.");
        }
        return $s;
    }

    private static function normalizeTimeOrEmpty(mixed $value, string $fullKey): string
    {
        $s = self::normalizeString($value, $fullKey);
        if ($s === '') return '';
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $s)) {
            throw new InvalidArgumentException("Setting '$fullKey' expects time format HH:MM or HH:MM:SS.");
        }
        return $s;
    }

    private static function normalizeDatetimeLocalOrEmpty(mixed $value, string $fullKey): string
    {
        $s = self::normalizeString($value, $fullKey);
        if ($s === '') return '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $s)) {
            throw new InvalidArgumentException("Setting '$fullKey' expects datetime-local format YYYY-MM-DDTHH:MM(:SS).");
        }
        return $s;
    }

    private static function normalizeMonthOrEmpty(mixed $value, string $fullKey): string
    {
        $s = self::normalizeString($value, $fullKey);
        if ($s === '') return '';
        if (!preg_match('/^\d{4}-\d{2}$/', $s)) {
            throw new InvalidArgumentException("Setting '$fullKey' expects month format YYYY-MM.");
        }
        return $s;
    }

    private static function normalizeWeekOrEmpty(mixed $value, string $fullKey): string
    {
        $s = self::normalizeString($value, $fullKey);
        if ($s === '') return '';
        if (!preg_match('/^\d{4}-W\d{2}$/', $s)) {
            throw new InvalidArgumentException("Setting '$fullKey' expects week format YYYY-Www.");
        }
        return $s;
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
            if (!self::hasKey($obj, $key)) continue;

            $v = self::getOptional($obj, $key);

            if (is_bool($v)) return $v;

            if (($v === 0 || $v === 1)) {
                return $v === 1;
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
        if (is_array($obj)) return $obj[$key] ?? null;
        return $obj->{$key} ?? null;
    }

    private static function hasKey(array|object $obj, string $key): bool
    {
        if (is_array($obj)) return array_key_exists($key, $obj);
        return property_exists($obj, $key);
    }

    /**
     * @return iterable<string,mixed>
     */
    private static function iterateMap(array|object $map): iterable
    {
        if (is_array($map)) {
            foreach ($map as $k => $v) yield (string)$k => $v;
            return;
        }

        foreach (get_object_vars($map) as $k => $v) {
            yield (string)$k => $v;
        }
    }
}