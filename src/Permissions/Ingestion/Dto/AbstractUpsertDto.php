<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion\Dto;

use JsonException;
use Timeax\FortiPlugin\Permissions\Cache\KeyBuilder;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;

/**
 * Common helpers for canonicalization & natural-key hashing.
 */
abstract class AbstractUpsertDto implements UpsertDtoInterface
{
    /** Build a stable natural key over the provided identity map.
     * @throws JsonException
     */
    protected function keyFromIdentity(array $identity): string
    {
        $norm = $this->normalize($identity);
        return hash('sha256', json_encode($norm, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Deep, deterministic normalization for hashing/compare.
     * @throws JsonException
     */
    protected function normalize(mixed $v): mixed
    {
        return KeyBuilder::normalize($v);
    }

    /** Unique+sorted list of strings (optionally force case). */
    protected function canonList(array $list, ?string $case = null): array
    {
        $list = array_values(array_unique(array_map('strval', $list)));
        if ($case === 'upper') $list = array_map('strtoupper', $list);
        if ($case === 'lower') $list = array_map('strtolower', $list);
        sort($list, SORT_STRING);
        return $list;
    }

    /** Canon an optional list (null OK). */
    protected function canonListOrNull(?array $list, ?string $case = null): ?array
    {
        return $list === null ? null : $this->canonList($list, $case);
    }

    /** Canon map<string,bool> (permissions-like) to strict booleans in key order. */
    protected function canonBoolMap(array $map, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = (bool)($map[$k] ?? false);
        }
        return $out;
    }
}