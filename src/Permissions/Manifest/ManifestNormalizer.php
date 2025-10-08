<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Manifest;

use JsonException;

/**
 * Canonicalizes a *validated* manifest for ingestion.
 * This class assumes the input came from ManifestValidator/CoreValidator.
 */
final readonly class ManifestNormalizer
{
    /**
     * @param array $validated The normalized output of the core validator.
     * @return array Canonical form suitable for ingestors (deterministic ordering, unified type names).
     * @throws JsonException
     */
    public function normalize(array $validated): array
    {
        $out = [
            'required_permissions' => [],
            'optional_permissions' => [],
        ];

        foreach (['required_permissions', 'optional_permissions'] as $bucket) {
            if (!isset($validated[$bucket]) || !is_array($validated[$bucket])) {
                continue;
            }
            foreach ($validated[$bucket] as $rule) {
                $out[$bucket][] = $this->canonicalRule($rule);
            }
        }

        // Deterministic order (stable) — optional
        foreach (['required_permissions', 'optional_permissions'] as $bucket) {
            usort($out[$bucket], static function (array $a, array $b): int {
                // Order by type, then stringified target, then first action
                $ta = (string)($a['type'] ?? '');
                $tb = (string)($b['type'] ?? '');
                if ($ta !== $tb) return $ta <=> $tb;
                $sa = json_encode($a['target'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
                $sb = json_encode($b['target'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
                if ($sa !== $sb) return $sa <=> $sb;
                $aa = (string)($a['actions'][0] ?? '');
                $ab = (string)($b['actions'][0] ?? '');
                return $aa <=> $ab;
            });
        }

        return $out;
    }

    /* -- internals --------------------------------------------------------- */

    private function canonicalRule(array $rule): array
    {
        $type = (string)($rule['type'] ?? '');
        // Unify "notify" to "notification" for the rest of the system
        if ($type === 'notify') {
            $type = 'notification';
        }

        $canon = $rule;
        $canon['type'] = $type;

        // Canonicalize actions (sorted, unique)
        if (isset($canon['actions']) && is_array($canon['actions'])) {
            $canon['actions'] = $this->uniqueSortedStrings($canon['actions']);
            // Uppercase request-ish verbs if they appear (mainly affects network "request" not needed; kept generic)
        }

        // Per-type canonicalization
        return match ($type) {
            'db'           => $this->canonDb($canon),
            'file'         => $this->canonFile($canon),
            'network'      => $this->canonNetwork($canon),
            'notification' => $this->canonNotify($canon),
            'module'       => $this->canonModule($canon),
            'codec'        => $this->canonCodec($canon),
            default        => $canon,
        };
    }

    private function canonDb(array $r): array
    {
        // Ensure columns are unique/sorted (if present)
        if (isset($r['target']['columns']) && is_array($r['target']['columns'])) {
            $r['target']['columns'] = $this->uniqueSortedStrings($r['target']['columns']);
        }
        // Preserve model_alias/map info produced by the core validator.
        return $r;
    }

    private function canonFile(array $r): array
    {
        if (isset($r['target']['paths']) && is_array($r['target']['paths'])) {
            $r['target']['paths'] = $this->uniqueSortedStrings($r['target']['paths']);
        }
        return $r;
    }

    private function canonNetwork(array $r): array
    {
        if (isset($r['target']['methods']) && is_array($r['target']['methods'])) {
            $r['target']['methods'] = $this->uniqueSortedStrings(
                array_map(static fn($m) => strtoupper((string)$m), $r['target']['methods'])
            );
        }
        if (isset($r['target']['hosts']) && is_array($r['target']['hosts'])) {
            $r['target']['hosts'] = $this->uniqueSortedStrings($r['target']['hosts']);
        }
        if (isset($r['target']['schemes'])) {
            $schemes = is_array($r['target']['schemes']) ? $r['target']['schemes'] : [];
            $schemes = $schemes === [] ? ['https'] : $schemes; // default to https if missing/empty
            $r['target']['schemes'] = $this->uniqueSortedStrings($schemes);
        } else {
            $r['target']['schemes'] = ['https'];
        }
        foreach (['paths','headers_allowed','ips_allowed'] as $k) {
            if (isset($r['target'][$k]) && is_array($r['target'][$k])) {
                $r['target'][$k] = $this->uniqueSortedStrings($r['target'][$k]);
            }
        }
        if (isset($r['target']['ports']) && is_array($r['target']['ports'])) {
            $ports = array_values(array_filter($r['target']['ports'], static fn($p) => is_int($p)));
            sort($ports, SORT_NUMERIC);
            $r['target']['ports'] = $ports;
        }
        // auth_via_host_secret is already boolean via core validator; keep as-is.
        return $r;
    }

    private function canonNotify(array $r): array
    {
        if (isset($r['target']['channels']) && is_array($r['target']['channels'])) {
            $r['target']['channels'] = $this->uniqueSortedStrings($r['target']['channels']);
        }
        foreach (['templates','recipients'] as $k) {
            if (isset($r['target'][$k]) && is_array($r['target'][$k])) {
                $r['target'][$k] = $this->uniqueSortedStrings($r['target'][$k]);
            }
        }
        return $r;
    }

    private function canonModule(array $r): array
    {
        if (isset($r['target']['apis']) && is_array($r['target']['apis'])) {
            $r['target']['apis'] = $this->uniqueSortedStrings($r['target']['apis']);
        }
        // Preserve alias/FQCN/docs provided by core validator.
        return $r;
    }

    private function canonCodec(array $r): array
    {
        // If validator produced resolved_methods, canonicalize it (unless wildcard '*')
        if (isset($r['resolved_methods']) && $r['resolved_methods'] !== '*' && is_array($r['resolved_methods'])) {
            $r['resolved_methods'] = $this->uniqueSortedStrings($r['resolved_methods']);
        }
        // Also canonicalize methods/groups if present for display/debugging
        foreach (['methods','groups'] as $k) {
            if (isset($r[$k]) && is_array($r[$k])) {
                $r[$k] = $this->uniqueSortedStrings($r[$k]);
            }
        }
        return $r;
    }

    /** @param string[] $list */
    private function uniqueSortedStrings(array $list): array
    {
        $list = array_values(array_unique(array_map('strval', $list)));
        sort($list, SORT_STRING);
        return $list;
    }
}