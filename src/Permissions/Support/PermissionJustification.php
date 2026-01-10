<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Support;

final class PermissionJustification
{
    /**
     * Generate a deterministic default justification when none is provided.
     *
     * @param array<string,mixed> $rule
     */
    public static function ensureJustification(array $rule, string $bucket): array
    {
        if (!isset($rule['justification']) || trim((string)$rule['justification']) === '') {
            $rule['justification'] = self::make($rule, $bucket);
        }

        return $rule;
    }

    /**
     * Generate a code-based justification string from bucket + type + actions (+ target hint).
     *
     * Buckets: "$required_permissions" | "$optional_permissions" (or any string)
     *
     * @param array<string,mixed> $rule
     */
    public static function make(array $rule, string $bucket): string
    {
        $bucketLabel = match ($bucket) {
            '$required_permissions', 'required_permissions' => 'Required permission',
            '$optional_permissions', 'optional_permissions' => 'Optional permission',
            default => 'Permission',
        };

        $type = (string)($rule['type'] ?? 'unknown');

        $actions = self::stringifyActions($rule['actions'] ?? null);
        $actionsPart = $actions !== '' ? "Actions: $actions." : '';

        $targetHint = self::targetHint($type, $rule['target'] ?? null, $rule);
        $targetPart = $targetHint !== '' ? "Target: $targetHint." : '';

        $why = match ($bucket) {
            '$required_permissions', 'required_permissions'
            => "This capability must be granted for the plugin to install/boot without degraded behaviour.",
            '$optional_permissions', 'optional_permissions'
            => "This capability can be granted later to unlock additional features.",
            default
            => "Capability declaration for host review.",
        };

        $typeLabel = strtoupper($type);

        // Deterministic + human readable. No environment assumptions.
        return trim("$bucketLabel ($typeLabel). $actionsPart $targetPart $why");
    }

    private static function stringifyActions(mixed $actions): string
    {
        if (!is_array($actions)) {
            return '';
        }

        $out = [];
        foreach ($actions as $a) {
            if (is_string($a) && $a !== '') {
                $out[] = $a;
            }
        }

        if ($out === []) {
            return '';
        }

        sort($out); // deterministic
        return implode(', ', $out);
    }

    private static function targetHint(string $type, mixed $target, array $rule): string
    {
        return match ($type) {
            'db'      => is_array($target) ? self::dbTargetHint($target) : '',
            'file'    => is_array($target) ? self::fileTargetHint($target) : '',
            'network' => is_array($target) ? self::networkTargetHint($target) : '',
            'notify'  => is_array($target) ? self::notifyTargetHint($target) : '',
            'module'  => is_array($target) ? self::moduleTargetHint($target) : '',
            'codec'   => self::codecRuleHint($rule),
            default   => '',
        };
    }


    /** @param array<string,mixed> $t */
    private static function dbTargetHint(array $t): string
    {
        $modelOrTable = is_string($t['model'] ?? null) ? $t['model'] : (is_string($t['table'] ?? null) ? $t['table'] : null);
        if (!$modelOrTable) {
            return '';
        }

        $cols = '';
        if (isset($t['columns']) && is_array($t['columns']) && $t['columns'] !== []) {
            $cols = ' (columns: ' . implode(', ', array_values(array_filter($t['columns'], 'is_string'))) . ')';
        }

        return "$modelOrTable$cols";
    }

    /** @param array<string,mixed> $t */
    private static function fileTargetHint(array $t): string
    {
        $base = is_string($t['base_dir'] ?? null) ? $t['base_dir'] : null;
        $paths = (isset($t['paths']) && is_array($t['paths'])) ? array_values(array_filter($t['paths'], 'is_string')) : [];

        if (!$base && $paths === []) {
            return '';
        }

        $pathsPart = $paths !== [] ? ' paths: ' . implode(', ', $paths) : '';
        return trim(($base ?: '') . $pathsPart);
    }

    /** @param array<string,mixed> $t */
    private static function networkTargetHint(array $t): string
    {
        $hosts = (isset($t['hosts']) && is_array($t['hosts'])) ? array_values(array_filter($t['hosts'], 'is_string')) : [];
        $methods = (isset($t['methods']) && is_array($t['methods'])) ? array_values(array_filter($t['methods'], 'is_string')) : [];

        if ($hosts === [] && $methods === []) {
            return '';
        }

        $h = $hosts !== [] ? implode(', ', $hosts) : '';
        $m = $methods !== [] ? implode(', ', $methods) : '';

        if ($h && $m) {
            return "$h (methods: $m)";
        }

        return $h ?: "methods: $m";
    }

    /** @param array<string,mixed> $t */
    private static function notifyTargetHint(array $t): string
    {
        $channels = (isset($t['channels']) && is_array($t['channels'])) ? array_values(array_filter($t['channels'], 'is_string')) : [];
        if ($channels === []) {
            return '';
        }

        sort($channels);
        return 'channels: ' . implode(', ', $channels);
    }

    /** @param array<string,mixed> $t */
    private static function moduleTargetHint(array $t): string
    {
        $plugin = is_string($t['plugin'] ?? null) ? $t['plugin'] : null;
        $apis = (isset($t['apis']) && is_array($t['apis'])) ? array_values(array_filter($t['apis'], 'is_string')) : [];

        if (!$plugin && $apis === []) {
            return '';
        }

        $apisPart = $apis !== [] ? ' apis: ' . implode(', ', $apis) : '';
        return trim(($plugin ?: '') . $apisPart);
    }


    /** @param array<string,mixed> $rule */
    private static function codecRuleHint(array $rule): string
    {
        // target is literally "codec"
        $parts = ['codec'];

        if (isset($rule['methods'])) {
            if ($rule['methods'] === '*') {
                $parts[] = 'methods: *';
            } elseif (is_array($rule['methods'])) {
                $m = array_values(array_filter($rule['methods'], 'is_string'));
                if ($m !== []) {
                    sort($m);
                    $parts[] = 'methods: ' . implode(', ', $m);
                }
            }
        }

        if (isset($rule['groups']) && is_array($rule['groups'])) {
            $g = array_values(array_filter($rule['groups'], 'is_string'));
            if ($g !== []) {
                sort($g);
                $parts[] = 'groups: ' . implode(', ', $g);
            }
        }

        return implode(' | ', $parts);
    }
}