<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Matchers;

final class CodecGuard
{
    /**
     * Returns true if the method is considered dangerous and needs an allow-list.
     */
    public function requiresAllowList(string $method): bool
    {
        return strtolower($method) === 'unserialize';
    }

    /**
     * Ensure the concrete permission has an allow-list for unserialize.
     *
     * @param array|null $allowed  The concrete "allowed" blob from CodecPermission (normalized at ingestion):
     *                             ['methods'=>..., 'groups'=>..., 'options'=>['allow_unserialize_classes'=>string[]]]
     * @return array{ok:bool,reason?:string,allowed_classes?:array}
     */
    public function validateConcreteFor(string $method, ?array $allowed): array
    {
        if (!$this->requiresAllowList($method)) {
            return ['ok' => true, 'reason' => null, 'allowed_classes' => null];
        }

        $classes = $allowed['options']['allow_unserialize_classes'] ?? null;
        if (!is_array($classes) || $classes === []) {
            return ['ok' => false, 'reason' => 'unserialize_missing_allowlist', 'allowed_classes' => null];
        }

        // normalize
        $classes = array_values(array_unique(array_filter(array_map(
            static fn($c) => is_string($c) ? trim($c) : '',
            $classes
        ))));

        if ($classes === []) {
            return ['ok' => false, 'reason' => 'unserialize_empty_allowlist', 'allowed_classes' => null];
        }

        return ['ok' => true, 'reason' => null, 'allowed_classes' => $classes];
    }

    /**
     * Optional helper: check an individual class name against the allow-list.
     *
     * @param string $fqcn
     * @param string[] $allowedClasses
     * @return bool
     */
    public function classAllowed(string $fqcn, array $allowedClasses): bool
    {
        $fqcn = ltrim($fqcn, '\\');
        foreach ($allowedClasses as $c) {
            if (ltrim($c, '\\') === $fqcn) {
                return true;
            }
        }
        return false;
    }
}