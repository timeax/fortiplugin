<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Support;

use FilesystemIterator;
use JsonException;
use Opis\JsonSchema\Validator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * FortiPluginConfig
 *
 * DTO-ish wrapper around decoded fortiplugin.json that:
 * - resolves relative paths inside plugin root safely
 * - loads hostConfig (inline object OR relative json path)
 * - loads permission_manifest (relative json path)
 * - validates config / hostConfig / permission manifest using Opis\JsonSchema
 *
 * NOTE (raw $ref):
 * If your schemas use raw GitHub URLs in $id/$ref, this class maps that URL prefix to a local schema folder
 * so validation works offline (and on hosts where allow_url_fopen is disabled).
 */
//const RAW_SCHEMA_BASE = '';
final class FortiPluginConfig
{
    public const DEFAULT_CONFIG_FILE = 'fortiplugin.json';

    // Your raw schema base (as you shared)
    public const RAW_SCHEMA_BASE = 'https://raw.githubusercontent.com/timeax/fortiplugin/refs/heads/main/schema/';

    // The schema IDs we validate against (match your raw URLs)
    public const SCHEMA_ID_CONFIG = self::RAW_SCHEMA_BASE . 'fortiplugin.schema.json';
    public const SCHEMA_ID_HOSTCONFIG = self::RAW_SCHEMA_BASE . 'fortiplugin.host-config.schema.json';
    public const SCHEMA_ID_PERMS = self::RAW_SCHEMA_BASE . 'manifest.schema.json';
    public const SCHEMA_ID_ROUTES = self::RAW_SCHEMA_BASE . 'fortiplugin.routes.schema.json';

    public function __construct(
        public readonly object  $raw,
        public readonly string  $pluginRoot,
        public readonly ?string $sourceFile = null,
    )
    {
        $root = rtrim($pluginRoot, "/\\");
        if ($root === '' || !is_dir($root)) {
            throw new RuntimeException("Invalid pluginRoot: $pluginRoot");
        }
    }

    /**
     * Load fortiplugin.json from plugin root.
     *
     * @throws JsonException
     */
    public static function fromPluginRoot(string $pluginRoot, string $configFile = self::DEFAULT_CONFIG_FILE): self
    {
        $pluginRoot = rtrim($pluginRoot, "/\\");
        $path = $pluginRoot . DIRECTORY_SEPARATOR . $configFile;

        if (!is_file($path)) {
            throw new RuntimeException("Config file not found: $path");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Unable to read config file: $path");
        }

        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        if (!is_object($decoded)) {
            throw new RuntimeException("Config JSON must decode to an object: $path");
        }

        return new self($decoded, $pluginRoot, $path);
    }

    /* ------------------------------------------------------------
     | Basic getters
     * ------------------------------------------------------------ */

    public function name(): ?string
    {
        return $this->str('name');
    }

    public function alias(): ?string
    {
        return $this->str('alias');
    }

    public function description(): ?string
    {
        return $this->str('description');
    }

    public function version(): ?string
    {
        return $this->str('version');
    }

    /** @return list<string> */
    public function providers(): array
    {
        $p = $this->raw->providers ?? null;
        if (!is_array($p)) return [];
        $out = [];
        foreach ($p as $v) {
            if (is_string($v) && $v !== '') $out[] = $v;
        }
        return array_values($out);
    }

    /** @return array<string,mixed> */
    public function dependencies(): array
    {
        $d = $this->raw->dependencies ?? null;
        return is_object($d) ? get_object_vars($d) : (is_array($d) ? $d : []);
    }

    public function permissionManifestRelativePath(): ?string
    {
        $p = $this->raw->permission_manifest ?? null;
        return is_string($p) && trim($p) !== '' ? trim($p) : null;
    }

    public function hostConfigRelativePath(): ?string
    {
        $c = $this->raw->hostConfig ?? null;
        return is_string($c) && trim($c) !== '' ? trim($c) : null;
    }

    /** @return array|object|null */
    public function hostConfigInline(): array|object|null
    {
        $c = $this->raw->hostConfig ?? null;
        return (is_array($c) || is_object($c)) ? $c : null;
    }

    /* ------------------------------------------------------------
     | Path resolution (inside plugin root)
     * ------------------------------------------------------------ */

    /**
     * Resolve a relative file path inside the plugin safely.
     * - rejects absolute paths and traversal
     * - if target exists, ensures it stays inside plugin root
     */
    public function resolvePluginPath(string $relativePath): string
    {
        $rel = trim($relativePath);

        if ($rel === '') {
            throw new RuntimeException('Path cannot be empty.');
        }

        // absolute (unix/windows) or traversal
        if (
            str_starts_with($rel, '/') ||
            str_starts_with($rel, '\\') ||
            preg_match('~^[A-Za-z]:[\\\\/]~', $rel) ||
            str_contains($rel, '..')
        ) {
            throw new RuntimeException("Invalid relative path: $relativePath");
        }

        $full = rtrim($this->pluginRoot, "/\\")
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);

        // if it exists: enforce that it is within root
        $rootReal = realpath($this->pluginRoot) ?: $this->pluginRoot;
        $fullReal = realpath($full);

        if ($fullReal !== false) {
            $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');
            $fullReal = rtrim(str_replace('\\', '/', $fullReal), '/');

            if (!str_starts_with($fullReal . '/', $rootReal . '/')) {
                throw new RuntimeException("Resolved path escapes plugin root: $relativePath");
            }
        }

        return $full;
    }

    /* ------------------------------------------------------------
     | hostConfig loading
     * ------------------------------------------------------------ */

    public function hasHostConfig(): bool
    {
        return property_exists($this->raw, 'hostConfig') && $this->raw->hostConfig !== null;
    }

    /**
     * Get hostConfig payload:
     * - inline object/array => returned as-is
     * - string => loads referenced JSON file inside plugin root
     *
     * @return array|object|null
     * @throws JsonException
     */
    public function getHostConfig(): array|object|null
    {
        $inline = $this->hostConfigInline();
        if ($inline !== null) {
            return $inline;
        }

        $rel = $this->hostConfigRelativePath();
        if ($rel === null) {
            return null;
        }

        $path = $this->resolvePluginPath($rel);

        if (!is_file($path)) {
            throw new RuntimeException("hostConfig file not found: $rel");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Unable to read hostConfig file: $rel");
        }

        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        if (!is_object($decoded) && !is_array($decoded)) {
            throw new RuntimeException("hostConfig must decode to object/array: $rel");
        }

        return $decoded;
    }

    public function hostConfigFullPath(): ?string
    {
        $rel = $this->hostConfigRelativePath();
        return $rel ? $this->resolvePluginPath($rel) : null;
    }

    /* ------------------------------------------------------------
     | permission manifest loading
     * ------------------------------------------------------------ */

    /**
     * Load permission_manifest JSON (decoded object).
     *
     * @throws JsonException
     */
    public function getPermissionManifest(): ?object
    {
        $rel = $this->permissionManifestRelativePath();
        if ($rel === null) {
            return null;
        }

        $path = $this->resolvePluginPath($rel);

        if (!is_file($path)) {
            throw new RuntimeException("permission_manifest file not found: $rel");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Unable to read permission_manifest file: $rel");
        }

        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        if (!is_object($decoded)) {
            throw new RuntimeException("permission_manifest must decode to an object: $rel");
        }

        return $decoded;
    }

    public function permissionManifestFullPath(): ?string
    {
        $rel = $this->permissionManifestRelativePath();
        return $rel ? $this->resolvePluginPath($rel) : null;
    }

    /* ------------------------------------------------------------
     | Validation (Opis\JsonSchema)
     * ------------------------------------------------------------ */

    /**
     * Validate the config against fortiplugin.schema.json.
     *
     * @return array{valid:bool,error?:string,details?:array}
     * @throws JsonException
     */
    public function validateConfig(string $schemaDir, string $configSchemaFile = 'fortiplugin.schema.json'): array
    {
        $schemaDir = rtrim($schemaDir, "/\\");
        $schemaPath = $schemaDir . DIRECTORY_SEPARATOR . $configSchemaFile;

        if (!is_file($schemaPath)) {
            return ['valid' => false, 'error' => "Config schema not found: $schemaPath"];
        }

        $validator = self::makeValidator($schemaDir, $schemaPath);

        // validate by schema id (raw URL) - resolver maps it to local file
        $result = $validator->validate($this->raw, self::SCHEMA_ID_CONFIG);

        if ($result->isValid()) {
            return ['valid' => true];
        }

        $err = $result->error();
        return [
            'valid' => false,
            'error' => 'Schema validation failed',
            'details' => $err ? self::describeOpisError($err) : [],
        ];
    }

    /**
     * Validate resolved hostConfig (if present) against fortiplugin.host-config.schema.json.
     *
     * @return array{valid:bool,error?:string,details?:array,skipped?:bool}
     * @throws JsonException
     */
    public function validateHostConfig(string $schemaDir, string $hostSchemaFile = 'fortiplugin.host-config.schema.json'): array
    {
        if (!$this->hasHostConfig()) {
            return ['valid' => true, 'skipped' => true];
        }

        $schemaDir = rtrim($schemaDir, "/\\");
        $schemaPath = $schemaDir . DIRECTORY_SEPARATOR . $hostSchemaFile;

        if (!is_file($schemaPath)) {
            return ['valid' => false, 'error' => "HostConfig schema not found: $schemaPath"];
        }

        try {
            $hostConfig = $this->getHostConfig();
        } catch (Throwable $e) {
            return ['valid' => false, 'error' => 'Failed to load hostConfig', 'details' => ['message' => $e->getMessage()]];
        }

        if ($hostConfig === null) {
            return ['valid' => true, 'skipped' => true];
        }

        $validator = self::makeValidator($schemaDir, null, $schemaPath);

        $result = $validator->validate($hostConfig, self::SCHEMA_ID_HOSTCONFIG);

        if ($result->isValid()) {
            return ['valid' => true];
        }

        $err = $result->error();
        return [
            'valid' => false,
            'error' => 'HostConfig validation failed',
            'details' => $err ? self::describeOpisError($err) : [],
        ];
    }

    /**
     * Validate permission_manifest:
     * - loads JSON
     * - validates against permissions schema IF you have one locally
     *
     * @return array{valid:bool,error?:string,details?:array,skipped?:bool}
     * @throws JsonException
     */
    public function validatePermissionManifest(
        string $schemaDir,
        string $permissionSchemaFile = 'fortiplugin.permissions.schema.json'
    ): array
    {
        $rel = $this->permissionManifestRelativePath();
        if ($rel === null) {
            return ['valid' => true, 'skipped' => true];
        }

        $schemaDir = rtrim($schemaDir, "/\\");
        $schemaPath = $schemaDir . DIRECTORY_SEPARATOR . $permissionSchemaFile;

        try {
            $manifest = $this->getPermissionManifest();
        } catch (Throwable $e) {
            return ['valid' => false, 'error' => 'Failed to load permission_manifest', 'details' => ['message' => $e->getMessage()]];
        }

        if ($manifest === null) {
            return ['valid' => true, 'skipped' => true];
        }

        // If you haven't created a permissions schema yet, treat as "loaded ok".
        if (!is_file($schemaPath)) {
            return ['valid' => true, 'skipped' => true];
        }

        $validator = self::makeValidator($schemaDir, null, null, $schemaPath);

        $result = $validator->validate($manifest, self::SCHEMA_ID_PERMS);

        if ($result->isValid()) {
            return ['valid' => true];
        }

        $err = $result->error();
        return [
            'valid' => false,
            'error' => 'permission_manifest validation failed',
            'details' => $err ? self::describeOpisError($err) : [],
        ];
    }

    /**
     * Validate config + hostConfig + permission_manifest (if present).
     *
     * @return array{
     *   valid:bool,
     *   config:array,
     *   hostConfig:array,
     *   permission_manifest:array
     * }
     * @throws JsonException
     * @throws JsonException
     * @throws JsonException
     */
    public function validateAll(string $schemaDir): array
    {
        $config = $this->validateConfig($schemaDir);
        $host = $this->validateHostConfig($schemaDir);
        $perms = $this->validatePermissionManifest($schemaDir);

        $valid = ($config['valid'] ?? false) && ($host['valid'] ?? false) && ($perms['valid'] ?? false);

        return [
            'valid' => $valid,
            'config' => $config,
            'hostConfig' => $host,
            'permission_manifest' => $perms,
        ];
    }

    /**
     * Validate discovered route manifest files against fortiplugin.routes.schema.json.
     *
     * @return array{
     *   valid:bool,
     *   skipped?:bool,
     *   error?:string,
     *   details?:array,
     *   files?:int
     * }
     * @throws JsonException
     * @throws JsonException
     */
    public function validateRouteManifests(
        string $schemaDir,
        string $routesSchemaFile = 'fortiplugin.routes.schema.json'
    ): array
    {
        $routesCfg = $this->routesConfig();
        if ($routesCfg === null) {
            return ['valid' => true, 'skipped' => true];
        }

        $schemaDir = rtrim($schemaDir, "/\\");
        $schemaPath = $schemaDir . DIRECTORY_SEPARATOR . $routesSchemaFile;

        if (!is_file($schemaPath)) {
            return [
                'valid' => false,
                'error' => 'Routes schema missing',
                'details' => [
                    ['file' => $routesSchemaFile, 'message' => 'Expected routes schema file in schema dir', 'dataPointer' => ''],
                ],
            ];
        }

        $files = $this->listRouteManifestFiles();

        if ($files === []) {
            return [
                'valid' => false,
                'error' => 'No route manifest files found',
                'details' => [
                    [
                        'file' => $this->routesDirRelative() ?? '',
                        'message' => "No files matched routes.glob='{$this->routesGlob()}'",
                        'dataPointer' => '',
                    ],
                ],
            ];
        }

        $validator = self::makeValidator(
            $schemaDir,
            null,
            null,
            null,
            $schemaPath
        );

        $allDetails = [];

        foreach ($files as $file) {
            try {
                $json = @file_get_contents($file);
                if ($json === false) {
                    $allDetails[] = ['file' => $file, 'message' => 'Failed to read file', 'dataPointer' => ''];
                    continue;
                }

                $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
                if (!is_object($decoded)) {
                    $allDetails[] = ['file' => $file, 'message' => 'Route manifest must decode to an object', 'dataPointer' => ''];
                    continue;
                }
            } catch (Throwable $e) {
                $allDetails[] = ['file' => $file, 'message' => 'Invalid JSON: ' . $e->getMessage(), 'dataPointer' => ''];
                continue;
            }

            $result = $validator->validate($decoded, self::SCHEMA_ID_ROUTES);

            if (!$result->isValid()) {
                $err = $result->error();
                if ($err) {
                    $tree = self::describeOpisError($err);
                    $allDetails = array_merge($allDetails, self::flattenOpisTreeWithFile($tree, $file));
                } else {
                    $allDetails[] = ['file' => $file, 'message' => 'Routes schema validation failed (no details)', 'dataPointer' => ''];
                }
            }
        }

        if ($allDetails !== []) {
            return [
                'valid' => false,
                'error' => 'Routes schema validation failed',
                'details' => $allDetails,
                'files' => count($files),
            ];
        }

        return ['valid' => true, 'files' => count($files)];
    }

    /* ------------------------------------------------------------
 | routes: list manifest files (supports .routes.json)
* ------------------------------------------------------------ */
    /* ------------------------------------------------------------
     | routes (dir + glob)
     * ------------------------------------------------------------ */

    public function hasRoutes(): bool
    {
        return isset($this->raw->routes) && is_object($this->raw->routes);
    }

    /**
     * Relative routes dir (e.g. "routes" or "Resources/routes")
     */
    public function routesDirRelative(): ?string
    {
        $r = $this->raw->routes ?? null;
        if (!is_object($r)) {
            return null;
        }

        $dir = $r->dir ?? null;
        return is_string($dir) && trim($dir) !== '' ? trim($dir) : null;
    }

    /**
     * Glob pattern inside routes dir. Defaults to
     */
    public function routesGlob(): string
    {
        $r = $this->raw->routes ?? null;
        if (!is_object($r)) {
            return '**/*.routes.json';
        }

        $glob = $r->glob ?? null;
        return is_string($glob) && trim($glob) !== '' ? trim($glob) : '**/*.routes.json';
    }

    /**
     * Absolute routes dir path inside plugin root.
     */
    public function routesDirFullPath(): ?string
    {
        $dir = $this->routesDirRelative();
        return $dir ? $this->resolvePluginPath($dir) : null;
    }

    /**
     * Convenience accessor.
     *
     * @return array{dir:string,glob:string}|null
     */
    public function routesConfig(): ?array
    {
        $dir = $this->routesDirRelative();
        if ($dir === null) {
            return null;
        }

        return [
            'dir' => $dir,
            'glob' => $this->routesGlob(),
        ];
    }

    /**
     * List route manifest files under routes.dir that match routes.glob.
     *
     * - Supports ** (recursive), * (single segment), ? (single char).
     * - Returns absolute paths by default.
     *
     * @return list<string>
     */
    public function listRouteManifestFiles(bool $absolute = true): array
    {
        $dir = $this->routesDirFullPath();
        if ($dir === null || !is_dir($dir)) {
            return [];
        }

        $glob = $this->routesGlob();
        $regex = self::globToRegex($glob);

        $dirNorm = rtrim(str_replace('\\', '/', $dir), '/');
        $files = [];

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $info) {
            if (!$info instanceof SplFileInfo || !$info->isFile()) {
                continue;
            }

            $abs = $info->getPathname();
            $absNorm = str_replace('\\', '/', $abs);

            // Build path relative to routes dir
            if (!str_starts_with($absNorm, $dirNorm . '/')) {
                continue;
            }

            $rel = substr($absNorm, strlen($dirNorm) + 1);

            if (preg_match($regex, $rel) === 1) {
                $files[] = $absolute ? $abs : $rel;
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * Convert a glob into a regex.
     * Supports:
     * - **  => any chars (including '/')
     * - *   => any chars except '/'
     * - ?   => one char except '/'
     */
    private static function globToRegex(string $glob): string
    {
        $glob = str_replace('\\', '/', trim($glob));
        $re = '';
        $len = strlen($glob);

        for ($i = 0; $i < $len; $i++) {
            $c = $glob[$i];

            // ** => .*
            if ($c === '*' && ($i + 1 < $len) && $glob[$i + 1] === '*') {
                $re .= '.*';
                $i++; // skip second '*'
                continue;
            }

            // * => [^/]*
            if ($c === '*') {
                $re .= '[^/]*';
                continue;
            }

            // ? => [^/]
            if ($c === '?') {
                $re .= '[^/]';
                continue;
            }

            // Escape regex special chars
            if (str_contains('.^$+()[]{}|\\', $c)) {
                $re .= '\\' . $c;
                continue;
            }

            $re .= $c;
        }

        return '#^' . $re . '$#';
    }

    /**
     * Flatten FortiPluginConfig::describeOpisError() output into list entries with "file".
     *
     * @return list<array{file:string,message:string,dataPointer:string,keyword?:mixed,args?:mixed}>
     */
    private static function flattenOpisTreeWithFile(array $node, string $file): array
    {
        $out = [];

        $out[] = [
            'file' => $file,
            'message' => (string)($node['message'] ?? 'Validation failed'),
            'dataPointer' => (string)($node['dataPointer'] ?? ''),
            'keyword' => $node['keyword'] ?? null,
            'args' => $node['args'] ?? null,
        ];

        if (isset($node['subErrors']) && is_array($node['subErrors'])) {
            foreach ($node['subErrors'] as $sub) {
                if (is_array($sub)) {
                    $out = array_merge($out, self::flattenOpisTreeWithFile($sub, $file));
                }
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------
     | Internals
     * ------------------------------------------------------------ */

    private function str(string $key): ?string
    {
        $v = $this->raw->{$key} ?? null;
        return is_string($v) ? $v : null;
    }

    /**
     * Create Validator with offline mapping for your raw GitHub URLs.
     *
     * @param string $schemaDir Local directory that contains your schema files
     * @param string|null $configSchemaPath Local path to config schema file (if you want to pin it)
     * @param string|null $hostSchemaPath Local path to host schema file (if you want to pin it)
     * @param string|null $permissionSchemaPath Local path to permission schema file (if you want to pin it)
     */
    private static function makeValidator(
        string  $schemaDir,
        ?string $configSchemaPath = null,
        ?string $hostSchemaPath = null,
        ?string $permissionSchemaPath = null,
        ?string $routesSchemaPath = null
    ): Validator
    {
        $v = new Validator();
        $r = $v->resolver();

        if (!$r) return $v;

        // Prefer prefix mapping (raw URL -> local directory) to avoid HTTP fetches.
        if (method_exists($r, 'registerPrefix')) {
            $r->registerPrefix(self::RAW_SCHEMA_BASE, $schemaDir);
        }

        // Pin exact schema IDs to local files (stronger than prefix mapping).
        $pin = static function (string $id, string $fallbackFile) use ($r, $schemaDir): void {
            $local = $schemaDir . DIRECTORY_SEPARATOR . $fallbackFile;
            if (is_file($local) && method_exists($r, 'registerFile')) {
                $r->registerFile($id, $local);
            }
        };

        if ($configSchemaPath !== null && method_exists($r, 'registerFile')) {
            $r->registerFile(self::SCHEMA_ID_CONFIG, $configSchemaPath);
            self::registerSchemaOwnIdIfPresent($r, $configSchemaPath);
        } else {
            $pin(self::SCHEMA_ID_CONFIG, 'fortiplugin.schema.json');
        }

        if ($hostSchemaPath !== null && method_exists($r, 'registerFile')) {
            $r->registerFile(self::SCHEMA_ID_HOSTCONFIG, $hostSchemaPath);
            self::registerSchemaOwnIdIfPresent($r, $hostSchemaPath);
        } else {
            $pin(self::SCHEMA_ID_HOSTCONFIG, 'fortiplugin.host-config.schema.json');
        }

        if ($permissionSchemaPath !== null && method_exists($r, 'registerFile')) {
            $r->registerFile(self::SCHEMA_ID_PERMS, $permissionSchemaPath);
            self::registerSchemaOwnIdIfPresent($r, $permissionSchemaPath);
        } else {
            $pin(self::SCHEMA_ID_PERMS, 'manifest.schema.json');
        }

        if ($routesSchemaPath !== null && method_exists($r, 'registerFile')) {
            $r->registerFile(self::SCHEMA_ID_ROUTES, $routesSchemaPath);
            self::registerSchemaOwnIdIfPresent($r, $routesSchemaPath);
        } else {
            $pin(self::SCHEMA_ID_ROUTES, 'fortiplugin.routes.schema.json');
        }

        return $v;
    }

    /**
     * If a schema file contains a "$id", register it too (helps if your schemas reference their own $id).
     */
    private static function registerSchemaOwnIdIfPresent(object $resolver, string $schemaPath): void
    {
        $json = @file_get_contents($schemaPath);
        if ($json === false) return;

        try {
            $decoded = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        if (is_object($decoded) && isset($decoded->{'$id'}) && is_string($decoded->{'$id'}) && $decoded->{'$id'} !== '' && method_exists($resolver, 'registerFile')) {
            // Map schema's $id -> local file
            $resolver->registerFile($decoded->{'$id'}, $schemaPath);
        }
    }

    /**
     * Convert Opis error object into an array (best-effort, version tolerant).
     */
    private static function describeOpisError(object $error): array
    {
        $out = [
            'message' => method_exists($error, 'message') ? $error->message() : 'Validation failed',
            'keyword' => method_exists($error, 'keyword') ? $error->keyword() : null,
            'dataPointer' => method_exists($error, 'dataPointer') ? $error->dataPointer() : null,
            'schemaPointer' => method_exists($error, 'schemaPointer') ? $error->schemaPointer() : null,
        ];

        if (method_exists($error, 'subErrors')) {
            $subs = $error->subErrors();
            if (is_array($subs) && $subs !== []) {
                $out['subErrors'] = array_map(
                    static fn($e) => is_object($e) ? self::describeOpisError($e) : ['message' => (string)$e],
                    $subs
                );
            }
        }

        return $out;
    }
}