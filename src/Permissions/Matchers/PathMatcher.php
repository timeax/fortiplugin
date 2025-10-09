<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Matchers;

final class PathMatcher
{
    /**
     * Check a path against a sandbox and allowlist patterns.
     *
     * @param string $baseDir Sandbox root (absolute or relative to process CWD).
     * @param string $path Requested path (user/plugin input).
     * @param string[] $patterns Allowlist patterns (glob: "*","?","**"; or regex via "re:/.../i").
     * @param bool $followSymlinks If true, we accept a realpath() escape (host explicitly allows following).
     * @return array{ok:bool,reason?:string,normalized?:string,matched?:string}
     */
    public function match(string $baseDir, string $path, array $patterns, bool $followSymlinks = false): array
    {
        // 1) Normalize base & request lexically (no FS calls required)
        $root = $this->normalizePath($baseDir);
        if ($root === '' || $root === '/') {
            // strongly discourage using the filesystem root as sandbox
            return ['ok' => false, 'reason' => 'invalid_sandbox_root', 'normalized' => null, 'matched' => null];
        }

        $rel = ltrim(str_replace('\\', '/', $path), '/');
        if ($rel === '' || str_contains($rel, "\0")) {
            return ['ok' => false, 'reason' => 'invalid_path', 'normalized' => null, 'matched' => null];
        }

        // Build lexical normalized absolute inside the sandbox (no realpath)
        $joined = rtrim($root, '/') . '/' . $rel;
        $norm = $this->collapseDotSegments($joined);

        // 2) Symlink policy: when NOT following, enforce lexical prefix; when following, optionally realpath().
        if (!$followSymlinks) {
            if (!$this->isUnderPrefix($norm, $root)) {
                return ['ok' => false, 'reason' => 'sandbox_escape', 'normalized' => $norm, 'matched' => null];
            }
        } else {
            // Optional: try to realpath both root & candidate; if realpath fails, keep lexical.
            $rootReal = @realpath($root) ?: $root;
            $normReal = @realpath($norm) ?: $norm;
            if (!$this->isUnderPrefix($normReal, $rootReal)) {
                return ['ok' => false, 'reason' => 'sandbox_escape', 'normalized' => $normReal, 'matched' => null];
            }
            $norm = $normReal;
        }

        // 3) Pattern match (use path relative to sandbox for glob convenience)
        $relative = ltrim(substr($norm, strlen(rtrim($root, '/'))), '/');
        if ($relative === '') {
            $relative = '.'; // the root itself
        }

        foreach ($patterns as $p) {
            if ($this->patternMatch($relative, $p)) {
                return ['ok' => true, 'reason' => null, 'normalized' => $norm, 'matched' => $p];
            }
        }

        return ['ok' => false, 'reason' => 'no_pattern_match', 'normalized' => $norm, 'matched' => null];
    }

    private function isUnderPrefix(string $candidate, string $root): bool
    {
        $c = rtrim(str_replace('\\', '/', $candidate), '/');
        $r = rtrim(str_replace('\\', '/', $root), '/');
        return $c === $r || str_starts_with($c . '/', $r . '/');
    }

    private function normalizePath(string $p): string
    {
        $p = str_replace('\\', '/', $p);
        $p = rtrim($p, '/');
        if ($p === '') $p = '.';
        return $this->collapseDotSegments($p);
    }

    /**
     * RFC 3986-ish lexical collapse of "." and "..", without following symlinks.
     */
    private function collapseDotSegments(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $stack = [];
        foreach ($parts as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($stack);
                continue;
            }
            $stack[] = $seg;
        }
        $prefix = str_starts_with($path, '/') ? '/' : '';
        return $prefix . implode('/', $stack);
    }

    /**
     * Glob/regex matcher. Supports:
     * - Glob: "*", "?", "**" (cross-dir)
     * - Regex: "re:/pattern/flags"
     * @noinspection SubStrUsedAsArrayAccessInspection
     */
    private function patternMatch(string $relative, string $pattern): bool
    {
        if (str_starts_with($pattern, 're:')) {
            $spec = substr($pattern, 3);
            // Expect form /.../flags
            $delim = substr($spec, 0, 1);
            $last = strrpos($spec, $delim);
            if ($delim && $last !== false && $last > 0) {
                $regex = substr($spec, 0, $last + 1);
                $flags = substr($spec, $last + 1);
                return (bool)@preg_match($regex . $flags, $relative);
            }
            return false;
        }

        // Convert glob to regex
        $rx = $this->globToRegex($pattern);
        return (bool)preg_match($rx, $relative);
    }

    private function globToRegex(string $glob): string
    {
        $g = str_replace('\\', '/', $glob);

        // Escape regex specials, then reintroduce glob tokens
        $escaped = '';
        $len = strlen($g);
        for ($i = 0; $i < $len; $i++) {
            $ch = $g[$i];
            if ($ch === '*') {
                // '**' => .*
                if ($i + 1 < $len && $g[$i + 1] === '*') {
                    $escaped .= '.*';
                    $i++;
                } else {
                    $escaped .= '[^/]*';
                }
            } elseif ($ch === '?') {
                $escaped .= '[^/]';
            } else {
                $escaped .= preg_quote($ch, '/');
            }
        }

        return '/^' . $escaped . '$/i';
    }
}