<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use RuntimeException;

/**
 * Path normalization and base containment checks without following symlinks.
 */
final class PathSecurity
{
    public function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') { array_pop($parts); continue; }
            $parts[] = $seg;
        }
        $norm = ($path[0] ?? '') === '/' ? '/' : '';
        $norm .= implode('/', $parts);
        if ($path !== '/' && str_ends_with($path, '/')) $norm .= '/';
        return $norm;
    }

    public function assertInside(string $base, string $target): void
    {
        if (!$this->isInside($base, $target)) {
            throw new RuntimeException("Path escapes base: target=$target base=$base");
        }
    }

    public function isInside(string $base, string $target): bool
    {
        $b = rtrim($this->normalize($base), '/').'/';
        $t = $this->normalize($target);
        // If target is relative, treat as base + target
        if (!str_starts_with($t, '/')) $t = $b . $t;
        $t = $this->normalize($t).'/';
        return str_starts_with($t, $b);
    }
}