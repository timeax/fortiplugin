<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use DateTimeInterface;
use JsonException;
use JsonSerializable;

/**
 * Builds stable, order-insensitive natural keys for concrete permission rows.
 * These keys let the repository upsert/dedupe safely.
 */
final class NaturalKeyBuilder
{
    /**
     * @throws JsonException
     */
    public static function db(array $target, array $actions): string
    {
        $payload = [
            'type'    => 'db',
            'model'   => $target['model'] ?? null,
            'table'   => $target['table'] ?? null,
            'columns' => self::sortStrings($target['columns'] ?? null),
            'actions' => self::sortStrings($actions),
        ];
        return self::hash($payload);
    }

    /**
     * @throws JsonException
     */
    public static function file(array $target, array $actions): string
    {
        $payload = [
            'type'      => 'file',
            'base_dir'  => (string)($target['base_dir'] ?? ''),
            'paths'     => self::sortStrings($target['paths'] ?? []),
            'follow'    => (bool)($target['follow_symlinks'] ?? false),
            'actions'   => self::sortStrings($actions),
        ];
        return self::hash($payload);
    }

    /**
     * @throws JsonException
     */
    public static function notification(array $target, array $actions): string
    {
        $payload = [
            'type'       => 'notification',
            'channels'   => self::sortStrings($target['channels'] ?? []),
            'templates'  => self::sortStrings($target['templates'] ?? []),
            'recipients' => self::sortStrings($target['recipients'] ?? []),
            'actions'    => self::sortStrings($actions),
        ];
        return self::hash($payload);
    }

    /**
     * @throws JsonException
     */
    public static function module(array $target, array $actions): string
    {
        $payload = [
            'type'    => 'module',
            'plugin'  => (string)($target['plugin_fqcn'] ?? $target['plugin'] ?? ''),
            'apis'    => self::sortStrings($target['apis'] ?? []),
            'actions' => self::sortStrings($actions),
        ];
        return self::hash($payload);
    }

    /**
     * @throws JsonException
     */
    public static function network(array $target, array $actions): string
    {
        // This is your "rule_key"
        $payload = [
            'type'            => 'network',
            'hosts'           => self::sortStrings($target['hosts'] ?? []),
            'methods'         => self::sortStrings(array_map('strtoupper', (array)($target['methods'] ?? []))),
            'schemes'         => self::sortStrings($target['schemes'] ?? ['https']),
            'ports'           => self::sortInts($target['ports'] ?? []),
            'paths'           => self::sortStrings($target['paths'] ?? []),
            'headers_allowed' => self::sortStrings($target['headers_allowed'] ?? []),
            'ips_allowed'     => self::sortStrings($target['ips_allowed'] ?? []),
            'auth_via_host'   => (bool)($target['auth_via_host_secret'] ?? true),
            'actions'         => self::sortStrings($actions),
        ];
        return self::hash($payload);
    }

    /**
     * @throws JsonException
     */
    public static function codec(array $rule): string
    {
        // Use resolved methods if present, else wildcard or given methods
        $methods = $rule['resolved_methods'] ?? ($rule['methods'] ?? '*');
        $payload = [
            'type'     => 'codec',
            'methods'  => $methods === '*' ? '*' : self::sortStrings((array)$methods),
            'groups'   => self::sortStrings($rule['groups'] ?? []),
            // options affects semantics (e.g., allow_unserialize_classes) → include in key
            'options'  => isset($rule['options']) ? self::normalize($rule['options']) : null,
        ];
        return self::hash($payload);
    }

    /* ------------------- normalization + hashing ------------------- */

    private static function sortStrings(?array $list): ?array
    {
        if ($list === null) return null;
        $out = [];
        foreach ($list as $v) {
            if (is_string($v) && $v !== '') $out[] = $v;
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_STRING);
        return $out;
    }

    private static function sortInts(?array $list): ?array
    {
        if ($list === null) return null;
        $out = [];
        foreach ($list as $v) {
            if (is_int($v)) $out[] = $v;
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_NUMERIC);
        return $out;
    }

    /**
     * @param mixed $v
     * @return mixed
     * @throws JsonException
     */
    private static function normalize(mixed $v): mixed
    {
        if ($v instanceof JsonSerializable) $v = $v->jsonSerialize();
        if ($v instanceof DateTimeInterface) return $v->format('c');
        if (is_object($v)) $v = (array)$v;
        if (!is_array($v)) return $v;

        $isList = array_is_list($v);
        $norm = [];
        if ($isList) {
            foreach ($v as $item) $norm[] = self::normalize($item);
            usort($norm, static fn($a, $b) => strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR)));
            return $norm;
        }
        $keys = array_keys($v); sort($keys, SORT_STRING);
        foreach ($keys as $k) $norm[$k] = self::normalize($v[$k]);
        return $norm;
    }

    /**
     * @param array $payload
     * @param string $algo
     * @return string
     * @throws JsonException
     * @noinspection PhpSameParameterValueInspection
     */
    private static function hash(array $payload, string $algo = 'sha256'): string
    {
        return hash($algo, json_encode(self::normalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}