<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Enums\PluginSettingValueType;
use Timeax\FortiPlugin\Models\PluginSetting;

final readonly class PluginSettingsWriter
{
    /**
     * Decode a stored setting value into native PHP.
     */
    public static function decodeValue(
        ?PluginSettingValueType $type,
        ?string $raw,
        mixed $default = null
    ): mixed {
        if ($type === null || $raw === null) {
            return $default;
        }

        return match ($type) {
            PluginSettingValueType::string,
            PluginSettingValueType::file,
            PluginSettingValueType::blob,
            PluginSettingValueType::select => $raw,

            // arrays (stored as JSON array string)
            PluginSettingValueType::radio,
            PluginSettingValueType::multiselect,
            PluginSettingValueType::chips => self::tryDecodeJsonArray($raw, $default),

            PluginSettingValueType::number => is_numeric($raw)
                ? ((str_contains($raw, '.') || str_contains($raw, 'e') || str_contains($raw, 'E'))
                    ? (float) $raw
                    : (int) $raw)
                : $default,

            PluginSettingValueType::boolean,
            PluginSettingValueType::checkbox =>
                filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default,

            PluginSettingValueType::tristate => match (strtolower(trim($raw))) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => $default,
            },

            // any JSON (object/array/scalar) stored as JSON string
            PluginSettingValueType::json => self::tryDecodeJson($raw, $default),
        };
    }

    /**
     * Encode an input value into (raw-string, type).
     *
     * @return array{0:string,1:PluginSettingValueType}
     */
    public static function encodeValue(
        mixed $value,
        ?PluginSettingValueType $type = null
    ): array {
        if ($type === null) {
            // Infer
            if (is_bool($value)) {
                return [$value ? 'true' : 'false', PluginSettingValueType::boolean];
            }

            if (is_int($value) || is_float($value)) {
                return [(string) $value, PluginSettingValueType::number];
            }

            if (is_array($value) || is_object($value)) {
                return [self::encodeJson($value), PluginSettingValueType::json];
            }

            return [(string) $value, PluginSettingValueType::string];
        }

        // Explicit type passed
        return match ($type) {
            PluginSettingValueType::boolean,
            PluginSettingValueType::checkbox => [
                is_bool($value)
                    ? ($value ? 'true' : 'false')
                    : self::normalizeBoolString((string) $value),
                $type
            ],

            PluginSettingValueType::tristate => [
                $value === null
                    ? 'null'
                    : (is_bool($value)
                    ? ($value ? 'true' : 'false')
                    : self::normalizeTriStateString((string) $value)),
                $type
            ],

            PluginSettingValueType::number, PluginSettingValueType::select, PluginSettingValueType::file, PluginSettingValueType::blob, PluginSettingValueType::string => [(string) $value, $type],

            PluginSettingValueType::json => [
                (is_array($value) || is_object($value)) ? self::encodeJson($value) : (string) $value,
                $type
            ],

            // arrays stored as JSON array string
            PluginSettingValueType::radio,
            PluginSettingValueType::multiselect,
            PluginSettingValueType::chips => [
                is_array($value)
                    ? self::encodeJson(array_values($value))
                    : self::encodeJson([(string) $value]),
                $type
            ],
        };
    }

    /**
     * Upsert a plugin setting.
     * If value is null, the setting is deleted.
     */
    public function set(int $pluginId, string $key, mixed $value, ?PluginSettingValueType $type = null): void
    {
        if ($value === null) {
            $this->delete($pluginId, $key);
            return;
        }

        [$raw, $finalType] = self::encodeValue($value, $type);

        PluginSetting::query()->updateOrCreate(
            ['plugin_id' => $pluginId, 'key' => $key],
            [
                'value' => $raw,
                'type'  => $finalType,
            ]
        );
    }

    /**
     * Delete a plugin setting.
     */
    public function delete(int $pluginId, string $key): void
    {
        PluginSetting::query()
            ->where('plugin_id', $pluginId)
            ->where('key', $key)
            ->delete();
    }

    /**
     * Upsert multiple plugin settings.
     *
     * @param array<string,mixed> $kv
     * @param array<string,PluginSettingValueType|string>|null $types
     */
    public function setMany(int $pluginId, array $kv, ?array $types = null): void
    {
        foreach ($kv as $key => $value) {
            $t = $types[$key] ?? null;

            if (is_string($t)) {
                $t = PluginSettingValueType::tryFrom($t);
            }

            $this->set($pluginId, $key, $value, $t instanceof PluginSettingValueType ? $t : null);
        }
    }

    private static function encodeJson(array|object $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to JSON-encode plugin setting value: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function tryDecodeJson(string $raw, mixed $default): mixed
    {
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $default;
        }
    }

    private static function tryDecodeJsonArray(string $raw, mixed $default): mixed
    {
        $decoded = self::tryDecodeJson($raw, $default);
        return is_array($decoded) ? $decoded : $default;
    }

    private static function normalizeBoolString(string $raw): string
    {
        $v = strtolower(trim($raw));
        return ($v === 'true' || $v === 'false') ? $v : $raw;
    }

    private static function normalizeTriStateString(string $raw): string
    {
        $v = strtolower(trim($raw));
        return in_array($v, ['true', 'false', 'null'], true) ? $v : $raw;
    }
}