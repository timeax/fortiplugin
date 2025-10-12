<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Security;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionFunction;
use SplFileInfo;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;

/**
 * Class FileScanner
 *
 * Recursively scans a directory tree and invokes a callback for files that are
 * likely to contain PHP code — either by trusted extensions (php/phtml/…)
 * OR because the file CONTENT indicates a PHP payload (e.g. "<?php" in a .jpg).
 *
 * Security goals:
 *  - Detect PHP hidden in "unrelated" files (images, text, vendor assets).
 *  - Detect double-extension tricks and Unicode filename spoofing.
 *  - Prevent symlink escapes.
 *  - Respect host ignore rules, but (by default) DO NOT ignore files that
 *    actually contain PHP payloads.
 *
 * Runtime behavior:
 *  - Web requests: enforce size limits (policy-configurable) to guard memory.
 *  - CLI and background jobs/queue workers: no size limits (full scan).
 *
 * Policy config keys (all optional):
 *  - ignore: string[]
 *      Glob-style patterns; matched against both absolute and root-relative paths.
 *      Supports negation with a leading '!'. (See shouldIgnore()).
 *
 *  - php_extensions: string[]
 *      List of extensions considered PHP-like (default: ['php','phtml','phpt']).
 *
 *  - scan_size: array{string:int}
 *      Per-extension maximum file bytes when web context (e.g., ['php' => 50000]).
 *
 *  - max_web_file_bytes: int
 *      Hard cap (bytes) for any single file read/sniff in web context. If exceeded,
 *      file is skipped without reading content (default: 256 * 1024).
 *
 *  - strict_ignore_blocks_payload: bool
 *      If true, an ignore rule will still exclude a file even when a PHP payload
 *      is detected via content sniffing. Default: false (payloads bypass ignore).
 *
 *  - php_short_open_tag_enabled: bool
 *      If set, overrides auto-detection for short tags ('<?'). Default: autodetect via ini.
 *
 *  - scanner_emit_pre_flags: bool
 *      If true (default), the scanner will emit pre-flag "issue rows" for filename/content
 *      suspicions in addition to calling your callback.
 *
 * Usage:
 *  $results = (new FileScanner($policy))->scan($dir, function (string $path, array $meta = []) {
 *      // $meta['flags'] holds filename/content suspicion flags (if any)
 *      return MyAnalyzer::analyze($path);
 *  });
 *
 * @template T
 */
class FileScanner
{
    protected PluginPolicy $policy;

    /**
     * Absolute realpath of the scan root (set during scan()).
     * @var string|null
     */
    protected ?string $root = null;

    public function __construct(PluginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Recursively scans $directory and invokes $callback for each eligible file.
     *
     * A file is eligible if:
     *  - It is a regular file (not a dir), AND
     *  - Not a symlink, AND
     *  - (has a PHP-like extension) OR (its CONTENT sniff indicates PHP payload),
     *  - Not ignored by policy 'ignore' rules (unless payload detected and
     *    strict_ignore_blocks_payload=false), AND
     *  - (Web context only) does not exceed configured size limits.
     *
     * The callback may accept either (string $path) or (string $path, array $meta).
     * $meta will include ['flags' => array<array{type:string,hint:string}>].
     *
     * @template TResult
     * @param string $directory Directory to scan (absolute or relative).
     * @param Closure(string):TResult $callback
     * @return array<int,TResult|array<int,array<string,mixed>>>      Collected non-falsy callback results (and optional pre-flag issues).
     */
    public function scan(string $directory, Closure $callback, ?Closure $emit): array
    {
        $realRoot = realpath($directory);
        $this->root = $realRoot !== false ? $realRoot : $directory;

        $config = $this->policy->getConfig();
        $allowedExts = $this->resolvePhpExtensions($config);
        $scanLimits = (array)($config['scan_size'] ?? []);
        $webHardCap = (int)($config['max_web_file_bytes'] ?? (256 * 1024));
        $strictIgnore = (bool)($config['strict_ignore_blocks_payload'] ?? false);
        $shortOpenTags = $this->shortOpenTagEnabled($config);
        $emitPreFlags = (bool)($config['scanner_emit_pre_flags'] ?? true);
        $ignore_non_php = (bool)($config['ignore_non_php'] ?? false);

        $collected = [];

        $rdiFlags = FilesystemIterator::SKIP_DOTS; // intentionally do not follow symlinks
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, $rdiFlags)
        );

        if ($emit) {
            $emit([
                'count' => iterator_count($iter),
                'title' => 'Scanning files',
                'message' => 'Scanning files in ' . $directory
            ]);
        }

        /** @var SplFileInfo $info */
        foreach ($iter as $info) {
            // Must be a regular file; reject symlinks to avoid escapes.
            if (!$info->isFile() || $info->isLink()) {
                continue;
            }

            $absPath = $this->normalizeSeparators($info->getPathname());
            $basename = $info->getBasename();

            // Collect suspicion flags for this file
            $preFlags = [];

            // Filename-level Unicode spoofing (bidi controls / isolates)
            if ($this->hasSuspiciousUnicodeName($basename)) {
                $preFlags[] = [
                    'type' => 'suspicious_filename_unicode',
                    'hint' => 'Filename contains bidi control characters (possible extension spoofing)',
                ];
            }

            // Enforce size caps only in web runtime
            if ($this->isWebContext()) {
                if ($this->exceedsMaxSizeByExt($info, $scanLimits)) {
                    $emit && $emit([
                        'title' => 'File ignored',
                        'message' => 'File ignored due to policy rules',
                        'path' => $absPath,
                        'flags' => $preFlags,
                        'issue' => 'max_web_file_bytes'
                    ]);
                    continue;
                }
                // Apply global sniff cap to avoid reading giant binaries in web
                if ($webHardCap > 0 && ($info->getSize() ?: 0) > $webHardCap) {
                    $emit && $emit([
                        'title' => 'File ignored',
                        'message' => 'File ignored due to policy rules',
                        'path' => $absPath,
                        'flags' => $preFlags,
                        'issue' => 'max_web_file_bytes'
                    ]);
                    continue;
                }
            }

            // Decide eligibility:
            // 1) Extension says PHP-like OR filename double-extension trick suggests PHP
            $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
            $extLooksPhp = in_array($ext, $allowedExts, true);
            $doubleExtSusp = $this->isDoubleExtensionSuspicious($basename, $allowedExts);
            if ($doubleExtSusp) {
                $preFlags[] = [
                    'type' => 'suspicious_double_extension',
                    'hint' => 'Double-extension pattern detected (e.g., *.jpg.php or *.php.txt)',
                ];
            }

            // 2) Content sniff says there's PHP payload (<?php, <?=, <? if enabled, or shebang)
            $payload = $this->containsPhpPayload($absPath, $shortOpenTags);
            if ($payload && !$extLooksPhp) {
                $preFlags[] = [
                    'type' => 'php_payload_in_non_php',
                    'hint' => 'PHP payload found in a non-PHP file',
                ];
            }

            if (!($extLooksPhp || $doubleExtSusp || $payload) && !$ignore_non_php) {
                // Not interesting
                $emit && $emit(['title' => 'File ignored', 'message' => 'File ignored due to policy rules', 'path' => $absPath, 'flags' => $preFlags]);
                continue;
            }

            // Ignore rules:
            $ignored = $this->shouldIgnore($absPath);
            if ($ignored) {
                // If payload is detected, we default to BYPASS ignore (safer)
                if (!$payload || $strictIgnore) {
                    $emit && $emit(['title' => 'File ignored', 'message' => 'File ignored due to policy rules', 'path' => $absPath, 'flags' => $preFlags]);
                    continue;
                }
            }

            // Invoke the callback; pass meta if it accepts a second parameter
            $meta = ['flags' => $preFlags];
            $result = $this->invokeCallback($callback, $absPath, $meta);
            if ($result) {
                $collected[] = $result;
            }

            // Optionally emit pre-flag issues directly from the scanner
            if ($emitPreFlags && $preFlags) {
                $issues = $this->makeFlagIssues($absPath, $basename, $preFlags);
                if ($issues) {
                    $collected[] = $issues; // keep chunked; caller may flatten
                }
            }
        }

        return $collected;
    }

    // ───────────────────────────── Helpers ─────────────────────────────

    /**
     * Policy-driven extension list for PHP-like files.
     * Ensures 'php' is present; defaults to ['php','phtml','phpt'].
     *
     * @param array<string,mixed> $config
     * @return array<int,string>
     */
    protected function resolvePhpExtensions(array $config): array
    {
        $exts = $config['php_extensions'] ?? ['php', 'phtml', 'phpt'];
        $exts = array_values(array_unique(array_map(
            static fn($e) => strtolower((string)$e),
            (array)$exts
        )));
        if (!in_array('php', $exts, true)) {
            array_unshift($exts, 'php');
        }
        return $exts;
    }

    /**
     * Check if a file should be ignored by policy ('ignore' patterns).
     * Supports '!' negation to re-include paths.
     *
     * @param string $absolutePath Normalized absolute path.
     * @return bool   True if ignored.
     */
    protected function shouldIgnore(string $absolutePath): bool
    {
        $patterns = $this->policy->getConfig()['ignore'] ?? [];
        if (!$patterns) {
            return false;
        }

        $normalized = $this->normalizeSeparators($absolutePath);
        $rel = $this->root
            ? ltrim($this->normalizeSeparators(str_replace($this->root, '', $normalized)), DIRECTORY_SEPARATOR)
            : $normalized;

        $ignored = false;

        foreach ($patterns as $pattern) {
            $negated = false;
            $p = $pattern;

            if (is_string($p) && $p !== '' && $p[0] === '!') {
                $negated = true;
                $p = substr($p, 1);
            }

            if (!is_string($p) || $p === '') {
                continue;
            }

            $pNorm = $this->normalizeSeparators($p);
            $match = fnmatch($pNorm, $rel) || fnmatch($pNorm, $normalized);

            if ($match) {
                $ignored = !$negated;
            }
        }

        return $ignored;
    }

    /**
     * Returns true when short open tags are enabled.
     * Can be forced via policy 'php_short_open_tag_enabled'.
     *
     * @param array<string,mixed> $config
     * @return bool
     */
    protected function shortOpenTagEnabled(array $config): bool
    {
        if (array_key_exists('php_short_open_tag_enabled', $config)) {
            return (bool)$config['php_short_open_tag_enabled'];
        }
        // Safe default: respect runtime setting
        return (bool)ini_get('short_open_tag');
    }

    /**
     * Lightweight content sniff to detect PHP payload in ANY file.
     * Reads the first ~64KB (CLI/background) or up to policy 'max_web_file_bytes' (web).
     * Looks for:
     *  - "<?php"
     *  - "<?=" (short echo)
     *  - "<?" (if short_open_tag enabled)
     *  - Shebang "#!/usr/bin/php" at start
     *
     * @param string $absPath
     * @param bool $shortTags
     * @return bool
     */
    protected function containsPhpPayload(string $absPath, bool $shortTags): bool
    {
        // Read cap: larger in CLI/background, smaller in web
        $config = $this->policy->getConfig();
        $webSniffCap = (int)($config['max_web_file_bytes'] ?? (256 * 1024));
        $cap = $this->isWebContext() ? max(4096, $webSniffCap) : (64 * 1024);

        $h = @fopen($absPath, 'rb');
        if ($h === false) {
            return false;
        }
        $data = @fread($h, $cap);
        @fclose($h);

        if ($data === false || $data === '') {
            return false;
        }

        if (str_contains($data, '<?php') || str_contains($data, '<?=')) {
            return true;
        }
        // Avoid counting XML headers as PHP payload
        if ($shortTags && str_contains($data, '<?') && !str_starts_with(ltrim($data), '<?xml')) {
            return true;
        }
        // Shebang
        return str_starts_with($data, '#!/usr/bin/php') || str_starts_with($data, "#!/usr/bin/env php");
    }

    /**
     * Detect basic double-extension tricks like "image.jpg.php" or "file.php.txt".
     *
     * @param string $basename
     * @param array<int,string> $phpExts
     * @return bool
     */
    protected function isDoubleExtensionSuspicious(string $basename, array $phpExts): bool
    {
        $lower = strtolower($basename);
        $parts = explode('.', $lower);

        if (count($parts) < 2) {
            return false;
        }

        // Example suspicious forms:
        //  - *.php.*
        //  - *.*.php
        $last = array_pop($parts);
        if (in_array($last, $phpExts, true)) {
            // e.g., name.jpg.php
            return true;
        }
        if (in_array($parts[count($parts) - 1] ?? '', $phpExts, true)) {
            // e.g., name.php.txt
            return true;
        }

        return false;
    }

    /**
     * Detect presence of Unicode bidi override or other RTL control chars in filename
     * that could visually spoof extensions in some UIs.
     *
     * @param string $basename
     * @return bool
     */
    protected function hasSuspiciousUnicodeName(string $basename): bool
    {
        // Common suspects: U+202E (RTL override), U+202A..U+202C (embedding/POP),
        // U+2066..U+2069 (isolates)
        /** @noinspection RegExpSingleCharAlternation */
        return (bool)preg_match('/\x{202E}|\x{202A}|\x{202B}|\x{202C}|\x{2066}|\x{2067}|\x{2068}|\x{2069}/u', $basename);
    }

    /**
     * Web-only: check per-extension max size via policy scan_size.
     *
     * @param SplFileInfo $info
     * @param array<string,int> $limits
     * @return bool
     */
    protected function exceedsMaxSizeByExt(SplFileInfo $info, array $limits): bool
    {
        if (!$this->isWebContext()) {
            return false;
        }
        $ext = strtolower(pathinfo($info->getPathname(), PATHINFO_EXTENSION));
        $limit = ($limits[$ext] ?? 0);
        if ($limit <= 0) {
            return false;
        }
        $size = $info->getSize();
        return $size !== false && $size > $limit;
    }

    /**
     * Normalize path separators to the current OS.
     *
     * @param string $path
     * @return string
     */
    protected function normalizeSeparators(string $path): string
    {
        return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * True if running in a web (non-console) context.
     * Laravel's queue workers and CLI commands runInConsole() === true.
     *
     * @return bool
     */
    protected function isWebContext(): bool
    {
        // Prefer Laravel helper if available; fallback to PHP_SAPI check.
        if (function_exists('app')) {
            try {
                return !app()->runningInConsole();
            } catch (Throwable) {
                // ignore
            }
        }
        return PHP_SAPI !== 'cli';
    }

    /**
     * Invoke analyzer callback. If it accepts (path, meta), pass both.
     *
     * @template TResult
     * @param Closure $callback Closure(string $path [, array $meta]): TResult
     * @param string $path
     * @param array<string,mixed> $meta
     * @return mixed                  TResult|false|null
     */
    protected function invokeCallback(Closure $callback, string $path, array $meta): mixed
    {
        $arity = $this->callbackArity($callback);
        if ($arity >= 2) {
            return $callback($path, $meta);
        }
        return $callback($path);
    }

    /**
     * Determine number of parameters accepted by the callback.
     *
     * @param Closure $callback
     * @return int
     */
    protected function callbackArity(Closure $callback): int
    {
        try {
            return (new ReflectionFunction($callback))->getNumberOfParameters();
        } catch (Throwable) {
            return 1; // safe fallback: assume single-arg
        }
    }

    /**
     * Convert collected pre-flags into canonical issue rows.
     *
     * Each flag item should be ['type'=>string,'hint'=>string].
     * The filename (basename) is reported as the "token" for quick context.
     *
     * @param string $file
     * @param string $basename
     * @param array<int,array<string,mixed>> $flags
     * @return array<int,array<string,mixed>>
     */
    protected function makeFlagIssues(string $file, string $basename, array $flags): array
    {
        $rows = [];
        foreach ($flags as $f) {
            $rows[] = [
                'type' => (string)($f['type'] ?? 'suspicious'),
                'token' => $basename,
                'file' => $file,
                'line' => 0,      // filename-level issue (not line-based)
                'snippet' => '',
                'issue' => (string)($f['hint'] ?? 'Suspicious file indicator'),
            ];
        }
        return $rows;
    }
}