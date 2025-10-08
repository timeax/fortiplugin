<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Support;

/**
 * Single source of truth for host-config normalization.
 *
 * All methods are pure (input → canonicalized output) and
 * safe to use from both catalogs and validators.
 */
final class HostConfigNormalizer
{
    /**
     * Normalize models map.
     *
     * Input shape (host config):
     *   [ alias => [
     *       'map' => FQCN,
     *       'relations' => [ relationName => relatedAlias, ... ] (optional),
     *       'columns' => [
     *         'all' => string[],       // optional
     *         'writable' => string[]   // optional, enforced ⊆ all when both present
     *       ] (optional)
     *   ], ...]
     *
     * Output (canonical):
     *   [ alias => [
     *       'map'       => FQCN,
     *       'relations' => [ relationName => relatedAlias, ... ],
     *       'columns'   => ['all' => ?string[], 'writable' => ?string[]]
     *   ], ...]
     *
     * - Drops invalid entries.
     * - Dedupes/sorts string lists.
     * - Ensures writable ⊆ all (when both present).
     */
    public static function models(array $raw): array
    {
        $out = [];
        foreach ($raw as $alias => $def) {
            if (!is_string($alias) || $alias === '' || !is_array($def)) {
                continue;
            }
            $fqcn = $def['map'] ?? null;
            if (!is_string($fqcn) || $fqcn === '') {
                continue;
            }

            // relations
            $rels = [];
            if (isset($def['relations']) && is_array($def['relations'])) {
                foreach ($def['relations'] as $rel => $relAlias) {
                    if (is_string($rel) && $rel !== '' && is_string($relAlias) && $relAlias !== '') {
                        $rels[$rel] = $relAlias;
                    }
                }
                ksort($rels, SORT_STRING);
            }

            // columns
            $all = null;
            $writable = null;
            if (isset($def['columns']) && is_array($def['columns'])) {
                if (isset($def['columns']['all']) && is_array($def['columns']['all'])) {
                    $all = self::uniqueSortedStrings($def['columns']['all']);
                }
                if (isset($def['columns']['writable']) && is_array($def['columns']['writable'])) {
                    $writable = self::uniqueSortedStrings($def['columns']['writable']);
                }
                // enforce writable ⊆ all when both present
                if ($all !== null && $writable !== null) {
                    $writable = array_values(array_intersect($writable, $all));
                }
            }

            $out[$alias] = [
                'map' => $fqcn,
                'relations' => $rels,
                'columns' => ['all' => $all, 'writable' => $writable],
            ];
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Normalize modules map.
     *
     * Input:  [ alias => ['map' => FQCN, 'docs' => ?string], ...]
     * Output: [ alias => ['map' => FQCN, 'docs' => ?string], ...]
     * - Drops invalid entries, dedupes/sorts.
     */
    public static function modules(array $raw): array
    {
        $out = [];
        foreach ($raw as $alias => $def) {
            if (!is_string($alias) || $alias === '' || !is_array($def)) {
                continue;
            }
            $fqcn = $def['map'] ?? null;
            if (!is_string($fqcn) || $fqcn === '') {
                continue;
            }
            $docs = null;
            if (isset($def['docs']) && is_string($def['docs']) && $def['docs'] !== '') {
                $docs = $def['docs'];
            }
            $out[$alias] = ['map' => $fqcn, 'docs' => $docs];
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Normalize notification channels.
     *
     * Accepts associative or list:
     *   ['email'=>true,'sms'=>true] OR ['email','sms']
     * Returns sorted unique list: ['email','sms']
     */
    public static function notificationChannels(array $raw): array
    {
        // If associative, use keys; else take values.
        $keys = array_keys($raw);
        $isAssoc = array_keys($keys) !== $keys;
        $list = $isAssoc ? array_keys($raw) : array_values($raw);

        return self::uniqueSortedStrings($list);
    }

    /**
     * Normalize codec groups from an Obfuscator-like map.
     *
     * Input (from Obfuscator::availableGroups()):
     *   [ group => [ phpFunctionName => wrapperName, ... ], ... ]
     * Output:
     *   [ group => [ phpFunctionName, ... ], ... ] // methods sorted/unique; groups sorted
     */
    public static function codecGroupsFromObfuscatorMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $group => $map) {
            if (!is_string($group) || $group === '' || !is_array($map)) {
                continue;
            }
            $methods = array_keys($map);
            $methods = self::uniqueSortedStrings($methods);
            $out[$group] = $methods;
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /* ------------------------ helpers ------------------------ */

    /** @return string[] */
    private static function uniqueSortedStrings(array $list): array
    {
        $list = array_values(array_filter($list, static fn($v) => is_string($v) && $v !== ''));
        $list = array_values(array_unique(array_map('strval', $list)));
        sort($list, SORT_STRING);
        return $list;
    }
}