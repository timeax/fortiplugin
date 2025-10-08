<?php

namespace Timeax\FortiPlugin\Support;

final class MiddlewareNormalizer
{
    /** @var string[] */
    private static array $spatie = ['role', 'permission', 'role_or_permission'];

    /**
     * Merge guard from group/route, inject auth:guard, and append guard to Spatie middleware.
     * Route-level guard overrides group guard if provided.
     *
     * @param string|null $groupGuard
     * @param string|null $routeGuard
     * @param string[] $middleware
     * @return string[]
     */
    public static function normalize(?string $groupGuard, ?string $routeGuard, array $middleware): array
    {
        $guard = $routeGuard ?? $groupGuard ?? null;
        $middleware = array_values(array_filter(array_map('strval', $middleware)));

        // Inject guard into Spatie middleware if missing ",guard"
        $middleware = array_map(static fn($m) => self::withSpatieGuard($m, $guard), $middleware);

        // Add auth:guard if a guard exists and no auth middleware is present
        if ($guard && !self::hasAuth($middleware)) {
            array_unshift($middleware, "auth:{$guard}");
        }

        // Deduplicate while preserving order
        $seen = [];
        $out = [];
        foreach ($middleware as $m) {
            if (!isset($seen[$m])) {
                $seen[$m] = true;
                $out[] = $m;
            }
        }
        return $out;
    }

    private static function hasAuth(array $mw): bool
    {
        foreach ($mw as $m) {
            if ($m === 'auth' || str_starts_with($m, 'auth:')) return true;
        }
        return false;
    }

    private static function withSpatieGuard(string $item, ?string $guard): string
    {
        if (!$guard) return $item;

        foreach (self::$spatie as $prefix) {
            $needle = $prefix . ':';
            if (str_starts_with($item, $needle)) {
                $rest = substr($item, strlen($needle));   // e.g. "edit posts|publish posts"
                // If a comma already exists, assume guard explicitly provided.
                if (str_contains($rest, ',')) return $item;
                return $prefix . ':' . $rest . ',' . $guard;  // e.g. "permission:edit...,web"
            }
        }
        return $item;
    }
}