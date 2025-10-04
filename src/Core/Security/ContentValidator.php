<?php /** @noinspection RegExpUnexpectedAnchor */

namespace Timeax\FortiPlugin\Core\Security;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Timeax\FortiPlugin\Core\PluginPolicy;

class ContentValidator
{
    protected PluginPolicy $policy;
    protected ?string $root = null;

    public function __construct(PluginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Scan a directory (all PHP files), returning all violations found.
     */
    public function scan(string $directory): array
    {
        $this->root = realpath($directory) ?: $directory;

        $all = [];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iter as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }
            if ($this->shouldIgnore($path)) {
                continue;
            }

            foreach ($this->scanFile($path) as $v) {
                $all[] = $v;
            }
        }

        return $all;
    }

    /**
     * Scan one PHP file and return violations.
     */
    public function scanFile(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return [[
                'type' => 'read_error',
                'file' => $filePath,
                'line' => 0,
                'snippet' => '',
                'issue' => 'Unable to read file',
            ]];
        }

        return $this->scanSource($content, $filePath);
    }

    /**
     * Scan a raw PHP source string and return violations.
     */
    public function scanSource(string $content, string $filePath = '[source]'): array
    {
        $violations = [];
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        foreach ($lines as $i => $line) {
            $ln = $i + 1;

            $this->append($violations, $this->invalidTokens($line, $ln, $filePath));
            $this->append($violations, $this->containsBlocklistTokens($line, $ln, $filePath));
            $this->append($violations, $this->containsForbiddenNamespaces($line, $ln, $filePath));
            $this->append($violations, $this->containsForbiddenFunctions($line, $ln, $filePath));
            $this->append($violations, $this->containsUnsupportedFunctions($line, $ln, $filePath));
        }

        return $violations;
    }

    /**
     * Append items to the target array without creating extra copies.
     */
    protected function append(array &$target, array $items): void
    {
        if (!$items) return;
        foreach ($items as $v) {
            $target[] = $v;
        }
    }

    /**
     * Checks if a file path matches any ignore patterns (relative or absolute).
     */
    protected function shouldIgnore(string $absolutePath): bool
    {
        $patterns = $this->policy->getConfig()['ignore'] ?? [];
        if (!$patterns) return false;

        $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $absolutePath);
        $rel = $this->root
            ? ltrim(str_replace($this->root, '', $normalized), DIRECTORY_SEPARATOR)
            : $normalized;

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $rel) || fnmatch($pattern, $normalized)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Finds use/assignment/function-arg of "invalid tokens"
     * (host additive list; separate from forbidden/unsupported).
     */
    protected function invalidTokens(string $line, int $lineNumber, string $filePath): array
    {
        $tokens = $this->policy->getConfig()['tokens'] ?? [];
        if (!$tokens) return [];

        $alts = array_map(static fn($t) => preg_quote((string)$t, '/'), $tokens);
        $part = '(?<![A-Za-z0-9_])(' . implode('|', $alts) . ')(?![A-Za-z0-9_])';

        $out = [];

        // Direct usage
        if (preg_match("/$part/i", $line, $m)) {
            $out[] = [
                'type' => 'invalid_token_usage',
                'token' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Direct usage of invalid token',
            ];
        }

        // Assignment
        if (preg_match("/(?:\$\w+|\$\w+\[.*?]|\w+::\$\w+|\$\w+->\w+)\s*=\s*$part\s*;/i", $line, $m)) {
            $out[] = [
                'type' => 'invalid_token_assignment',
                'token' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Assigned to variable/array/object/class property',
            ];
        }

        // Function argument
        if (preg_match("/\b\w+\s*\(\s*$part\s*\)/i", $line, $m)) {
            $out[] = [
                'type' => 'invalid_token_function_argument',
                'token' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Used as a function argument',
            ];
        }

        return $out;
    }

    /**
     * Detect use of blocklisted classes/facades and their methods.
     */
    protected function containsBlocklistTokens(string $line, int $lineNumber, string $filePath): array
    {
        $violations = [];
        $map = $this->policy->getBlocklist(); // effective allowlist after overrides

        foreach ($map as $class => $allowed) {
            $q = preg_quote($class, '/');

            if (preg_match("/new\s+$q\s*\(/", $line)) {
                $violations[] = [
                    'type' => 'blocklist_instantiation',
                    'token' => $class,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => "Instantiation: new $class",
                ];
            }

            if (preg_match("/$q\s*::\s*__construct\s*\(/", $line)) {
                $violations[] = [
                    'type' => 'blocklist_constructor',
                    'token' => $class,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => "Constructor: $class::__construct",
                ];
            }

            if (preg_match("/\b$q\s*::\s*class\b/", $line)) {
                $violations[] = [
                    'type' => 'blocklist_class_reference',
                    'token' => $class,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => "Class reference: $class::class",
                ];
            }

            if (str_contains($line, "$class::") && !in_array('*', $allowed, true)) {
                preg_match_all("/\\b$q::([A-Za-z_][A-Za-z0-9_]*)/", $line, $m);
                foreach ($m[1] as $method) {
                    if (!in_array($method, $allowed, true)) {
                        $violations[] = [
                            'type' => 'blocklist_method',
                            'token' => $class,
                            'method' => $method,
                            'file' => $filePath,
                            'line' => $lineNumber,
                            'snippet' => trim($line),
                            'issue' => "Method: $class::$method",
                        ];
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * Forbidden namespaces.
     */
    protected function containsForbiddenNamespaces(string $line, int $lineNumber, string $filePath): array
    {
        $violations = [];
        $namespaces = $this->policy->getForbiddenNamespaces();

        // use statements
        if (preg_match('/^use\s+([^;]+);/i', $line, $m)) {
            $ns = trim($m[1]);
            foreach ($namespaces as $forbidden) {
                if (stripos($ns, $forbidden) === 0) {
                    $violations[] = [
                        'type' => 'forbidden_namespace_import',
                        'namespace' => $forbidden,
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'snippet' => trim($line),
                        'issue' => 'Import of forbidden namespace or child',
                    ];
                }
            }
        }

        foreach ($namespaces as $forbidden) {
            $q = preg_quote($forbidden, '/');

            // new/extends/implements/static/instanceof
            if (preg_match('/\b' . $q . '\\\\/', $line)) {
                $violations[] = [
                    'type' => 'forbidden_namespace_reference',
                    'namespace' => $forbidden,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => 'Reference to forbidden namespace',
                ];
            }

            // string references
            if (preg_match('/[\'"]' . $q . '\\\\[^\'"]+[\'"]/', $line)) {
                $violations[] = [
                    'type' => 'forbidden_namespace_string',
                    'namespace' => $forbidden,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => 'Forbidden namespace/class referenced as a string',
                ];
            }
        }

        return $violations;
    }

    /**
     * Hard-blocked functions (Forti defaults + curl + file I/O, minus overrides).
     */
    protected function containsForbiddenFunctions(string $line, int $lineNumber, string $filePath): array
    {
        $funcs = $this->policy->getForbiddenFunctions();
        if (!$funcs) return [];

        $alts = array_map(static fn($f) => preg_quote((string)$f, '/'), $funcs);
        $part = '(?<![A-Za-z0-9_])(' . implode('|', $alts) . ')(?![A-Za-z0-9_])';

        $out = [];

        if (preg_match("/$part\s*\(/i", $line, $m)) {
            $out[] = [
                'type' => 'forbidden_function',
                'function' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Call to forbidden function',
            ];
        }

        if (preg_match("/(?:\$\w+|\$\w+\[.*?]|\w+::\$\w+|\$\w+->\w+)\s*=\s*$part\s*;/i", $line, $m)) {
            $out[] = [
                'type' => 'forbidden_function_assignment',
                'function' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Assigned to variable/array/object/class property',
            ];
        }

        return $out;
    }

    /**
     * Unsupported/risky functions (warnings) after subtracting overrides.
     */
    protected function containsUnsupportedFunctions(string $line, int $lineNumber, string $filePath): array
    {
        $funcs = $this->policy->getUnsupportedFunctions();
        if (!$funcs) return [];

        $alts = array_map(static fn($f) => preg_quote((string)$f, '/'), $funcs);
        $part = '(?<![A-Za-z0-9_])(' . implode('|', $alts) . ')(?![A-Za-z0-9_])';

        $out = [];

        if (preg_match("/$part\s*\(/i", $line, $m)) {
            $out[] = [
                'type' => 'unsupported_function',
                'function' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Call to unsupported/risky function',
            ];
        }

        return $out;
    }
}