<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Cache;

use DateTimeInterface;
use JsonException;
use JsonSerializable;

/**
 * Builds stable content hashes (ETags) for capabilities and assignment snapshots.
 *
 * Order-insensitive: arrays are normalized (keys sorted; list values sorted after normalization).
 * Objects/stdClass are cast to arrays first.
 */
final class KeyBuilder
{
    /**
     * ETag from a compiled capability map (implementation-defined shape).
     * @throws JsonException
     */
    public static function fromCapabilities(array $capabilities, string $algo = 'sha256'): string
    {
        $norm = self::normalize($capabilities);
        return self::hash(self::json($norm), $algo);
    }

    /**
     * ETag from assignment snapshot + optional versions.
     *
     * @param int $pluginId
     * @param array $directMorphs list of ['type'=>string,'id'=>int,'active'=>bool, 'window'=>..., 'constraints'=>..., 'audit'=>...]
     * @param array $tagMorphs same shape as $directMorphs
     * @param array $concrete map: type => (id => version|string|timestamp|array-of-fields)
     * @param array $catalogs map of catalog version markers, e.g. ['models'=>rev, 'modules'=>rev, 'notify'=>rev, 'codec'=>rev]
     * @param string $algo
     * @return string
     * @throws JsonException
     */
    public static function fromAssignments(
        int    $pluginId,
        array  $directMorphs,
        array  $tagMorphs,
        array  $concrete = [],
        array  $catalogs = [],
        string $algo = 'sha256'
    ): string
    {
        $payload = [
            'plugin' => $pluginId,
            'direct' => self::normalize($directMorphs),
            'via_tags' => self::normalize($tagMorphs),
            'concrete' => self::normalize($concrete),
            'catalogs' => self::normalize($catalogs),
        ];
        return self::hash(self::json($payload), $algo);
    }

    /**
     * Recursively normalize data for deterministic hashing:
     * - stdClass → array
     * - associative arrays: sort keys; normalize values
     * - list arrays: normalize values, then sort by JSON value
     * - scalars left as-is (but ints/bools normalized to their type)
     * @throws JsonException
     */
    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        if ($value instanceof DateTimeInterface) {
            // ISO 8601 in UTC
            return $value->format('c');
        }
        if (is_object($value)) {
            $value = (array)$value;
        }
        if (!is_array($value)) {
            // Normalize numeric strings if they were numbers? Keep original types to avoid surprises.
            return $value;
        }

        // Determine if list (0..n-1) or associative
        $isList = array_is_list($value);

        if ($isList) {
            $normalized = array_map([self::class, 'normalize'], $value);
            // Sort list items by their JSON representation for order-insensitivity
            usort($normalized, static function ($a, $b) {
                return strcmp(self::json($a), self::json($b));
            });
            return $normalized;
        }

        // Associative: sort by key
        $normalized = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $k) {
            $normalized[$k] = self::normalize($value[$k]);
        }
        return $normalized;
    }

    /**
     * Canonical JSON (no unicode/slashes escaping; no spaces).
     * @throws JsonException
     */
    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Hash helper (hex string).
     */
    public static function hash(string $data, string $algo = 'sha256'): string
    {
        return hash($algo, $data);
    }
}