<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Audit;

/**
 * Masks sensitive fields in nested arrays/objects.
 *
 * Supports:
 *  - Explicit dot-paths in $explicit (e.g. ['password','headers.Authorization','headers.*'])
 *  - Heuristics for common secret-looking keys: password, secret, token, authorization, cookie,
 *    api_key, client_secret, private_key, passphrase (case-insensitive).
 *  - Special case: 'Authorization: Bearer <token>' -> 'Authorization: Bearer ***'
 */
final class Redactor
{
    /** @var string[] lowercase key fragments considered sensitive */
    private array $heuristicKeys = [
        'password', 'passwd', 'secret', 'token', 'authorization', 'cookie',
        'api_key', 'apikey', 'client_secret', 'private_key', 'passphrase',
        'access_token', 'refresh_token',
    ];

    /** @var string Mask replacement */
    private string $mask;

    /** @param string[]|null $heuristicKeys override default key fragments */
    public function __construct(?array $heuristicKeys = null, string $mask = '***')
    {
        if ($heuristicKeys !== null) {
            $this->heuristicKeys = array_values(array_unique(array_map('strtolower', $heuristicKeys)));
        }
        $this->mask = $mask;
    }

    /**
     * Redact sensitive fields.
     *
     * @param array|object $data
     * @param string[]     $explicit Dot-paths (case-insensitive). '*' wildcard suffix masks a subtree.
     * @return array Redacted associative structure
     */
    public function redact(array|object $data, array $explicit = []): array
    {
        $arr = is_object($data) ? (array)$data : $data;

        // Normalize explicit paths to lowercase, trim spaces, and split once for fast checks.
        $explicit = array_values(array_filter(array_map(
            static fn(string $p) => strtolower(trim($p)),
            $explicit
        )));
        $explicitExact = [];
        $explicitWild  = []; // prefix (without trailing dot)
        foreach ($explicit as $p) {
            if ($p === '') continue;
            if (str_ends_with($p, '.*')) {
                $explicitWild[] = rtrim(substr($p, 0, -2), '.');
            } else {
                $explicitExact[] = $p;
            }
        }

        return $this->walk($arr, [], $explicitExact, $explicitWild);
    }

    /**
     * Recursive walker that returns a new redacted array.
     *
     * @param mixed       $node
     * @param string[]    $pathParts (lowercased)
     * @param string[]    $exact
     * @param string[]    $wildPrefixes
     * @return mixed
     * @noinspection GrazieInspection
     */
    private function walk(mixed $node, array $pathParts, array $exact, array $wildPrefixes): mixed
    {
        if (is_object($node)) {
            $node = (array)$node;
        }
        if (!is_array($node)) {
            // leaf value; nothing to redact unless parent key requires it
            return $node;
        }

        // Distinguish list vs assoc. We always return arrays (not objects).
        $isList = array_is_list($node);
        if ($isList) {
            return array_map(function ($val) use ($exact, $pathParts, $wildPrefixes) {
                return $this->walk($val, $pathParts, $exact, $wildPrefixes);
            }, $node);
        }

        $out = [];
        foreach ($node as $key => $val) {
            $keyStr  = (string)$key;
            $keyL    = strtolower($keyStr);
            $curPath = [...$pathParts, $keyL];
            $dot     = implode('.', $curPath);

            if ($this->shouldRedact($keyL, $dot, $exact, $wildPrefixes)) {
                $out[$keyStr] = $this->maskValue($keyL, $val);
                continue;
            }

            // Recurse into nested values
            $out[$keyStr] = $this->walk($val, $curPath, $exact, $wildPrefixes);
        }

        return $out;
    }

    private function shouldRedact(string $keyLower, string $dotPathLower, array $exact, array $wildPrefixes): bool
    {
        // 1) explicit exact path
        if (in_array($dotPathLower, $exact, true)) {
            return true;
        }
        // 2) explicit wildcard subtree
        foreach ($wildPrefixes as $pre) {
            if ($pre === '' || $pre === $dotPathLower || str_starts_with($dotPathLower, $pre . '.')) {
                return true;
            }
        }
        // 3) heuristic key fragments
        foreach ($this->heuristicKeys as $frag) {
            if (str_contains($keyLower, $frag)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mask a value conservatively. Keeps "Bearer " scheme if present.
     */
    private function maskValue(string $keyLower, mixed $value): string|array
    {
        if (is_array($value) || is_object($value)) {
            // Mask all leaves within this subtree
            $arr = is_object($value) ? (array)$value : $value;
            $masked = [];
            foreach ($arr as $k => $v) {
                $masked[$k] = $this->maskValue(is_string($k) ? strtolower($k) : (string)$k, $v);
            }
            return $masked;
        }

        if (is_string($value)) {
            // Preserve 'Bearer ' prefix if found
            if ($keyLower === 'authorization' || preg_match('/^\s*bearer\s+/i', $value)) {
                return preg_replace('/^(\s*bearer\s+).+$/i', '$1' . $this->mask, $value) ?? ('Bearer ' . $this->mask);
            }
        }

        // Scalars and everything else get a straight mask
        return $this->mask;
    }
}