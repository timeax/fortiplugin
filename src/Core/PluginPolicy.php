<?php /** @noinspection SpellCheckingInspection */
/** @noinspection PhpUnused */
/** @noinspection ClassConstantCanBeUsedInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Core;

/**
 * PluginPolicy
 *
 * Effective scanning policy for FortiPlugin:
 *   1) Start from Forti defaults (this class).
 *   2) Add host overlays from config (validator.*).
 *   3) Apply host "overrides" to ALLOW specific items otherwise blocked.
 *
 * Notes
 * - "Forbidden" => hard block (must not be used).
 * - "Unsupported" => flagged/risky (can be treated as warnings by scanner).
 * - Overrides are *surgical* ALLOWs. Prefer granting permissions via review.
 */
class PluginPolicy
{
    /* ---------------------------------------------------------------------
     |  Forti defaults (base deny lists)
     |---------------------------------------------------------------------*/

    /** @var array<int,string> */
    protected array $fileIoMethods = [
        // File & Directory Read
        'fopen', 'fread', 'file_get_contents', 'file', 'fgets', 'fgetc', 'fgetcsv',
        'readfile', 'stream_get_contents', 'stream_get_line', 'file_exists',
        'is_readable', 'stat', 'lstat', 'scandir', 'opendir', 'readdir',
        'parse_ini_file', 'parse_ini_string', 'glob', 'realpath',

        // File & Directory Write
        'fwrite', 'file_put_contents', 'fputcsv', 'fflush', 'ftruncate', 'flock',
        'rename', 'touch', 'chmod', 'chown', 'chgrp', 'move_uploaded_file',
        'stream_set_write_buffer', 'tempnam', 'tmpfile', 'mkdir', 'rmdir',

        // Copy/Move/Delete
        'copy', 'unlink', 'symlink', 'link',
    ];

    /** @var array<int,string> */
    protected array $streamFunctions = [
        'stream_context_create', 'stream_context_set_option', 'stream_context_get_options',
        'stream_context_set_params', 'stream_copy_to_stream', 'stream_filter_append',
        'stream_filter_prepend', 'stream_filter_remove', 'stream_get_contents',
        'stream_get_line', 'stream_get_meta_data', 'stream_get_transports',
        'stream_get_wrappers', 'stream_is_local', 'stream_register_wrapper',
        'stream_resolve_include_path', 'stream_select', 'stream_set_blocking',
        'stream_set_chunk_size', 'stream_set_read_buffer', 'stream_set_timeout',
        'stream_socket_accept', 'stream_socket_client', 'stream_socket_enable_crypto',
        'stream_socket_get_name', 'stream_socket_pair', 'stream_socket_recvfrom',
        'stream_socket_sendto', 'stream_socket_server', 'stream_wrapper_register',
        'stream_wrapper_restore', 'stream_wrapper_unregister',
    ];

    /** @var array<int,string> */
    protected array $curlMethods = [
        'curl_close', 'curl_copy_handle', 'curl_errno', 'curl_error', 'curl_escape', 'curl_exec', 'curl_getinfo', 'curl_init',
        'curl_multi_add_handle', 'curl_multi_close', 'curl_multi_errno', 'curl_multi_exec', 'curl_multi_getcontent',
        'curl_multi_info_read', 'curl_multi_init', 'curl_multi_remove_handle', 'curl_multi_select', 'curl_multi_setopt',
        'curl_multi_strerror', 'curl_pause', 'curl_reset', 'curl_setopt', 'curl_setopt_array', 'curl_share_close',
        'curl_share_errno', 'curl_share_init', 'curl_share_init_persistent', 'curl_share_setopt', 'curl_share_strerror',
        'curl_unescape', 'curl_upkeep', 'curl_version',
    ];

    /** @var array<int,string> */
    protected array $forbiddenNamespaceList = [
        'Illuminate\\Routing\\',           // Route
        'Illuminate\\Filesystem\\',        // File
        'Illuminate\\Support\\Facades\\File',
        'Illuminate\\Support\\Facades\\Storage',
        'Illuminate\\Contracts\\Filesystem\\',
        'Illuminate\\Http\\UploadedFile',
        'Symfony\\Component\\HttpFoundation\\File\\', // incl. FileBag etc.
        'Illuminate\\Support\\Facades\\Route',
        'Illuminate\\Support\\Facades\\Artisan',      // Command execution
        'Illuminate\\Support\\Facades\\Schema',       // Schema mutations
        'Illuminate\\Support\\Facades\\DB',           // DB facade directly
        'Illuminate\\Database\\',                     // Direct DB access
    ];

    /** @var array{
     *    functions:array<int,string>,
     *    reflectionPrefix:string,
     *    magicMethods:array<int,string>,
     *    wrappers:array<int,string>
     * }
     */
    protected array $alwaysForbidden = [
        'functions' => [
            'eval', 'assert', 'exec', 'shell_exec', 'passthru', 'system',
            'proc_open', 'popen', 'dl', 'create_function', 'unserialize',
            'register_shutdown_function', 'set_error_handler', 'set_exception_handler', 'register_tick_function',
            'putenv', 'ini_set', 'ini_restore',
        ],
        'reflectionPrefix' => 'Reflection',
        'magicMethods' => ['__call', '__callStatic', '__invoke', '__autoload'],
        'wrappers' => ['php://', 'data://', 'glob://', 'zip://', 'phar://'],
    ];

    /** @var array<int,string> */
    protected array $callbackFunctions = [
        'array_map', 'array_filter', 'array_walk', 'array_walk_recursive', 'usort', 'uasort', 'uksort', 'array_reduce',
        'register_shutdown_function', 'set_error_handler', 'set_exception_handler', 'register_tick_function',
    ];

    /** @var array<int,string> */
    protected array $envManipulationFunctions = [
        // Environment
        'putenv', 'getenv', 'apache_setenv', 'apache_getenv',
        // INI
        'ini_set', 'ini_alter', 'ini_restore', 'ini_get', 'ini_get_all', 'ini_parse_quantity',
        // Process / system
        'proc_open', 'proc_close', 'proc_terminate', 'proc_get_status', 'proc_nice',
        // CLI/Server process manipulation
        'pcntl_exec', 'pcntl_fork', 'pcntl_wait', 'pcntl_waitpid', 'pcntl_signal', 'pcntl_alarm',
        'pcntl_wexitstatus', 'pcntl_wifexited', 'pcntl_wifsignaled', 'pcntl_wifstopped',
        'pcntl_signal_dispatch', 'pcntl_get_last_error', 'pcntl_errno',
        // Limits / shutdown
        'set_time_limit', 'ignore_user_abort', 'fastcgi_finish_request',
    ];

    /** @var array<int,string> */
    protected array $diContainerMethods = [
        // Laravel/Illuminate
        'bind', 'singleton', 'instance', 'scoped', 'share', 'extend', 'when', 'tag', 'alias',
        'resolving', 'afterResolving', 'make',
        // Symfony/PSR
        'register', 'set', 'addArgument', 'addMethodCall', 'setShared', 'addTag',
        // Pimple / Interop
        'offsetSet', 'offsetGet', 'addService', 'addProvider', 'delegate', 'factory',
        // Zend / others
        'configure', 'define', 'protect',
        // CakePHP
        'load', 'unload',
        // Custom markers
        'service', 'handler', 'controller',
    ];

    /** @var array<int,string> */
    protected array $obfuscators = [
        // Encoders/decoders
        'base64_decode', 'base64_encode', 'gzinflate', 'gzdeflate', 'gzencode', 'gzdecode', 'gzcompress', 'gzuncompress',
        'str_rot13', 'rot13', 'bin2hex', 'hex2bin', 'chr', 'ord', 'pack', 'unpack',
        'urlencode', 'urldecode', 'rawurlencode', 'rawurldecode', 'convert_uuencode', 'convert_uudecode',
        'json_encode', 'json_decode', 'serialize', 'unserialize',
        // Misc
        'strrev', 'md5', 'sha1', 'sha256', 'hash', 'hash_hmac', 'openssl_encrypt', 'openssl_decrypt',
        'mcrypt_encrypt', 'mcrypt_decrypt', // legacy
        // Compression/encoding helpers
        'bzcompress', 'bzdecompress', 'zlib_encode', 'zlib_decode', 'deflate_add', 'inflate_add', 'inflate_init', 'deflate_init',
        // Transformations often chained
        'addslashes', 'stripslashes', 'quotemeta', 'strip_tags',
    ];

    /* ---------------------------------------------------------------------
     |  Host overlay & overrides (config-driven)
     |---------------------------------------------------------------------*/

    /** Raw host config (as passed in) */
    protected array $config = [];

    /** Additive risk sets from host (stricter) */
    protected array $unsupportedFunctions = []; // tokens + dangerous + env + obfuscators
    protected array $forbiddenNamespaces = []; // base + host
    protected array $forbiddenPackages = []; // host

    /**
     * Class method allowlist:
     * If a class is present, ONLY the listed methods are allowed; all others are blocked.
     * Merged with host 'blocklist' and then expanded via overrides['classes'].
     */
    protected mixed $blocklist;

    /** Overrides that ALLOW specific items otherwise blocked */
    protected array $overrides = [
        'functions' => [],
        'tokens' => [],
        'dangerous' => [],
        'namespaces' => [],
        'packages' => [],
        'wrappers' => [],
        'magic_methods' => [],
        'classes' => [], // ['ClassName' => ['method1','method2']]
    ];

    // Fast lookup sets for overrides
    protected array $allowFunctionSet = [];
    protected array $allowTokenSet = [];
    protected array $allowDangerSet = [];

    /* ---------------------------------------------------------------------
     |  Construction / normalization
     |---------------------------------------------------------------------*/

    public function __construct(array $config = [])
    {
        // Include stream functions as part of file I/O for stricter default posture
        $this->fileIoMethods = array_values(array_unique(array_merge($this->fileIoMethods, $this->streamFunctions)));

        // Store config reference
        $this->config = $config;

        // Compute "unsupported" = tokens (host) + dangerous (host) + env + obfuscators
        $this->unsupportedFunctions = array_values(array_unique(array_merge(
            $config['dangerous_functions'] ?? [],
            $config['tokens'] ?? [],
            $this->envManipulationFunctions,
            $this->obfuscators
        )));

        // Forbidden namespaces/packages (stricter by host)
        $this->forbiddenNamespaces = array_values(array_unique(array_merge(
            $config['forbidden_namespaces'] ?? [],
            $this->forbiddenNamespaceList
        )));
        $this->forbiddenPackages = array_values(array_unique($config['forbidden_packages'] ?? []));

        // Method allowlist per class (host can define)
        $this->blocklist = $config['allowed_class_methods'] ?? [];

        // Overrides (ALLOWS)
        $this->overrides = array_replace_recursive($this->overrides, $config['overrides'] ?? []);

        // Create lowercase lookup sets for function-name comparisons
        $fn = array_map('strtolower', $this->overrides['functions'] ?? []);
        $tokens = array_map('strtolower', $this->overrides['tokens'] ?? []);
        $danger = array_map('strtolower', $this->overrides['dangerous'] ?? []);

        $this->allowFunctionSet = array_fill_keys($fn, true);
        $this->allowTokenSet = array_fill_keys($tokens, true);
        $this->allowDangerSet = array_fill_keys($danger, true);

        // Subtract overrides from forbidden namespaces/packages
        if (!empty($this->overrides['namespaces'])) {
            $this->forbiddenNamespaces = array_values(array_diff(
                $this->forbiddenNamespaces,
                $this->overrides['namespaces']
            ));
        }
        if (!empty($this->overrides['packages'])) {
            $lowerForbidden = array_map('strtolower', $this->forbiddenPackages);
            $lowerAllowed = array_map('strtolower', $this->overrides['packages']);
            $this->forbiddenPackages = array_values(array_diff($lowerForbidden, $lowerAllowed));
        }

        // Expand class method allowlist using overrides['classes'] (adds allowed methods)
        foreach (($this->overrides['classes'] ?? []) as $class => $methods) {
            $methods = array_values(array_unique($methods));
            if (!isset($this->blocklist[$class])) {
                $this->blocklist[$class] = [];
            }
            $this->blocklist[$class] = array_values(array_unique(array_merge($this->blocklist[$class], $methods)));
        }
    }

    /* ---------------------------------------------------------------------
     |  Checks — Forbidden
     |---------------------------------------------------------------------*/

    public function isForbiddenNamespace(string $namespace): bool
    {
        foreach ($this->forbiddenNamespaces as $forbidden) {
            if (stripos($namespace, $forbidden) === 0) {
                return true;
            }
        }
        return false;
    }

    public function isForbiddenPackage(string $package): bool
    {
        $needle = strtolower($package);
        return in_array($needle, $this->forbiddenPackages, true);
    }

    /**
     * Forbidden functions: Forti defaults + curl + fileIO + alwaysForbidden,
     * then subtract *allowed* overrides.
     */
    public function isForbiddenFunction($name): bool
    {
        $n = strtolower((string)$name);

        // If specifically allowed, it's NOT forbidden
        if (isset($this->allowFunctionSet[$n]) || isset($this->allowTokenSet[$n]) || isset($this->allowDangerSet[$n])) {
            return false;
        }

        return in_array($n, $this->getForbiddenFunctions(), true);
    }

    /**
     * Methods blocked by class method-allowlist semantics.
     * If a class is present in blocklist, any method NOT explicitly listed is blocked.
     */
    public function isBlockedMethod($class, $method): bool
    {
        $class = $this->resolveClass((string)$class);
        if (!isset($this->blocklist[$class])) {
            // No allowlist for this class → not blocked by allowlist semantics
            return false;
        }
        return !in_array((string)$method, $this->blocklist[$class], true);
    }

    public function isForbiddenReflection($class): bool
    {
        // Null / non-string? we can't determine — treat as not-forbidden here.
        if ($class === null) {
            return false;
        }

        // Allow Stringable objects
        if (is_object($class) && method_exists($class, '__toString')) {
            $class = (string)$class;
        }

        if (!is_string($class) || $class === '') {
            return false;
        }

        // Normalize leading backslash
        $class = ltrim($class, '\\');

        // If namespace overrides explicitly ALLOW something, unblock it
        $namespaces = is_array($this->overrides['namespaces'] ?? null) ? $this->overrides['namespaces'] : [];
        foreach ($namespaces as $ns) {
            if (is_string($ns) && $ns !== '' && stripos($class, ltrim($ns, '\\')) === 0) {
                return false;
            }
        }

        // Default rule: anything starting with "Reflection"
        $prefix = $this->alwaysForbidden['reflectionPrefix'] ?? 'Reflection';
        return stripos($class, $prefix) === 0;
    }

    public function getForbiddenWrappers(): array
    {
        // Subtract overrides
        return array_values(array_diff($this->alwaysForbidden['wrappers'], $this->overrides['wrappers'] ?? []));
    }

    public function getForbiddenMagicMethods(): array
    {
        // Subtract overrides
        return array_values(array_diff($this->alwaysForbidden['magicMethods'], $this->overrides['magic_methods'] ?? []));
    }

    /**
     * Effective forbidden functions list (after subtracting allowed overrides).
     */
    public function getForbiddenFunctions(): array
    {
        $forbidden = array_map('strtolower', array_values(array_unique(array_merge(
            $this->alwaysForbidden['functions'],
            $this->fileIoMethods,
            $this->curlMethods
        ))));

        // Subtract overrides (functions/tokens/dangerous)
        $allow = array_keys($this->allowFunctionSet + $this->allowTokenSet + $this->allowDangerSet);
        if (!empty($allow)) {
            $forbidden = array_values(array_diff($forbidden, $allow));
        }

        return $forbidden;
    }

    public function getReflectionPrefix()
    {
        return $this->alwaysForbidden['reflectionPrefix'];
    }

    /* ---------------------------------------------------------------------
     |  Checks — Unsupported (warnings)
     |---------------------------------------------------------------------*/

    /**
     * Return effective unsupported set (after subtracting allowed overrides).
     */
    public function getUnsupportedFunctions(): array
    {
        $list = array_map('strtolower', $this->unsupportedFunctions);
        $allow = array_keys($this->allowFunctionSet + $this->allowTokenSet + $this->allowDangerSet);
        if (!empty($allow)) {
            $list = array_values(array_diff($list, $allow));
        }
        return $list;
    }

    public function isUnsupportedFunction($name): bool
    {
        $n = strtolower((string)$name);
        if (isset($this->allowFunctionSet[$n]) || isset($this->allowTokenSet[$n]) || isset($this->allowDangerSet[$n])) {
            return false;
        }
        return in_array($n, $this->getUnsupportedFunctions(), true);
    }

    /* ---------------------------------------------------------------------
     |  Accessors / Utilities
     |---------------------------------------------------------------------*/

    public function getFileFunctions(): array
    {
        return $this->fileIoMethods;
    }

    public function getObfuscators(): array
    {
        return $this->obfuscators;
    }

    public function getEnvMethods(): array
    {
        return $this->envManipulationFunctions;
    }

    /** Return the current (merged) class method allowlist map. */
    public function getBlocklist()
    {
        return $this->blocklist;
    }

    /** Namespaces currently considered forbidden (after overrides). */
    public function getForbiddenNamespaces(): array
    {
        return $this->forbiddenNamespaces;
    }

    /** Composer packages currently considered forbidden (after overrides). */
    public function getForbiddenPackages(): array
    {
        return $this->forbiddenPackages;
    }

    public function getCallbackFunctions(): array
    {
        return $this->callbackFunctions;
    }

    public function getStreamFunctions(): array
    {
        return $this->streamFunctions;
    }

    public function getDiContainerMethods(): array
    {
        return $this->diContainerMethods;
    }

    /** Raw config as provided to the policy */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function resolveClass($class): string
    {
        // Hook for alias resolution if you track aliases; identity for now.
        return (string)$class;
    }
}