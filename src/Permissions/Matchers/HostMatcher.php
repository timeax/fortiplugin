<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Matchers;

final class HostMatcher
{
    /**
     * @param string $method e.g. "GET"
     * @param string $scheme "https"|"http"
     * @param string $host lowercased hostname
     * @param int $port explicit or defaulted (80/443)
     * @param string $path request path ("/v1/...")
     * @param string[] $methods allowlist (empty => allow all)
     * @param string[]|null $schemes allowlist (null|[] => allow "https" by convention or all? We'll treat null|[] as allow any)
     * @param int[]|null $ports allowlist (null|[] => require default port match; aligns with your checker)
     * @param string[]|null $paths allowed path prefixes or regex via "re:/.../"
     * @param string[]|null $hosts allowed hosts (exact or "*.example.com")
     */
    public function match(
        string $method,
        string $scheme,
        string $host,
        int    $port,
        string $path,
        array  $methods,
        ?array $schemes,
        ?array $ports,
        ?array $paths,
        ?array $hosts
    ): bool
    {
        if (!$this->methodMatches($method, $methods)) return false;
        if (!$this->schemeMatches($scheme, $schemes)) return false;
        if (!$this->hostMatches($host, $hosts ?? [])) return false;
        if (!$this->portMatches($port, $ports, $scheme)) return false;
        if (!$this->pathMatches($path, $paths ?? [])) return false;
        return true;
    }

    public function methodMatches(string $method, array $allowed): bool
    {
        if ($allowed === []) return true;
        $m = strtoupper($method);
        foreach ($allowed as $a) {
            if (strtoupper((string)$a) === $m) return true;
        }
        return false;
    }

    public function schemeMatches(string $scheme, ?array $schemes): bool
    {
        if ($schemes === null || $schemes === []) return true;
        $s = strtolower($scheme);
        foreach ($schemes as $x) {
            if (strtolower((string)$x) === $s) return true;
        }
        return false;
    }

    public function hostMatches(string $host, array $patterns): bool
    {
        if ($patterns === []) return true;
        $h = strtolower($host);
        foreach ($patterns as $p) {
            $p = strtolower((string)$p);
            if ($p === $h) return true;
            if (str_starts_with($p, '*.')) {
                $suffix = substr($p, 1); // ".example.com"
                // Ensure at least one label before suffix
                if ($suffix !== '' && str_ends_with($h, $suffix) && substr_count($h, '.') >= substr_count($suffix, '.') + 1) {
                    return true;
                }
            }
        }
        return false;
    }

    public function portMatches(int $port, ?array $ports, string $scheme): bool
    {
        if ($ports === null || $ports === []) {
            $default = $scheme === 'https' ? 443 : 80;
            return $port === $default;
        }
        foreach ($ports as $p) {
            if ((int)$p === $port) return true;
        }
        return false;
    }

    public function pathMatches(string $path, array $prefixes): bool
    {
        if ($prefixes === []) return true;
        foreach ($prefixes as $pre) {
            $pre = (string)$pre;
            if (str_starts_with($pre, 're:')) {
                $spec = substr($pre, 3);
                $delim = substr($spec, 0, 1);
                $last = strrpos($spec, $delim);
                if ($delim && $last !== false) {
                    $regex = substr($spec, 0, $last + 1);
                    $flags = substr($spec, $last + 1);
                    if (@preg_match($regex . $flags, $path)) return true;
                }
            } else {
                if ($pre === '' || $pre === '/') return true;
                if (str_starts_with($path, $pre)) return true;
            }
        }
        return false;
    }
}